#!/usr/bin/env python3
"""Explicit opt-in CircleCI SSH probe. No backup, PHP, SQL, or deployment.

Candidate mode authenticates no user. Metadata mode requires separate reviewed
startup/command scope and a revision-restricted context. Never prints raw remote
output, identifiers, credentials, or exception messages. Nothing enables capture.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import selectors
import shlex
import signal
import stat
import subprocess
import sys
import tempfile
import time

from orgavela_pgp05_hostkey_candidate import EXPECTED_FPR, read_candidate, validate_host

BRANCH = 'feat/pgp05-readonly-ssh-metadata-check-20261021'
LIMIT = 16384
KEYSCAN = '/usr/bin/ssh-keyscan'
SSH = '/usr/bin/ssh'
REMOTE_OK = b'PGP05_REMOTE_METADATA=PASS\n'
# Passed on stdin to sh; never uploaded or installed on the server.
# The server login shell/startup hooks execute BEFORE this script. Their separate
# review is mandatory; this script cannot itself prevent their side effects.
REMOTE_SCRIPT = b'''set -eu
PATH=/usr/bin:/bin
export PATH
LC_ALL=C
export LC_ALL
root=$1
expected_user=$2
[ "$(id -un)" = "$expected_user" ] || exit 78
uid=$(id -u)
old_ifs=$IFS
IFS=/
set -- $root
IFS=$old_ifs
part_path=
for component do
    [ -n "$component" ] || continue
    part_path=$part_path/$component
    [ ! -L "$part_path" ] && [ -d "$part_path" ] || exit 78
done
cd -P "$root" || exit 78
[ "$(pwd -P)" = "$root" ] || exit 78
[ "$(stat -c %u .)" = "$uid" ] || exit 78
for directory in wp-admin wp-includes wp-content wp-content/plugins wp-content/plugins/uop-core wp-content/mu-plugins; do
    [ ! -L "$directory" ] && [ -d "$directory" ] || exit 78
done
for file in index.php wp-config.php wp-content/plugins/uop-core/uop-core.php wp-content/mu-plugins/automation-by-installatron.php; do
    [ ! -L "$file" ] && [ -f "$file" ] || exit 78
    [ "$(stat -c %h "$file")" = 1 ] || exit 78
    [ "$(stat -c %u "$file")" = "$uid" ] || exit 78
done
case "$(stat -c %a wp-config.php)" in 400|600) ;; *) exit 78 ;; esac
for entry in wp-content/mu-plugins/* wp-content/mu-plugins/.[!.]* wp-content/mu-plugins/..?*; do
    if [ -e "$entry" ] || [ -L "$entry" ]; then
        [ "$entry" = wp-content/mu-plugins/automation-by-installatron.php ] || exit 78
    fi
done
printf 'PGP05_REMOTE_METADATA=PASS\n'
'''
REMOTE_SHA256 = hashlib.sha256(REMOTE_SCRIPT).hexdigest()
ASKPASS = b'#!/usr/bin/python3\nimport os,sys\nsys.stdout.write(os.environ["PGP05_SSH_PASSWORD"] + "\\n")\n'
COMMON_FIELDS = {'PGP05_SSH_HOST', 'PGP05_APPROVED_SHA', 'PGP05_ZERO_COST_CONFIRMED'}
METADATA_FIELDS = {'PGP05_SSH_USER', 'PGP05_SSH_PASSWORD', 'PGP05_WP_ROOT',
                   'PGP05_REMOTE_SHA256', 'PGP05_STARTUP_REVIEWED', 'PGP05_METADATA_REVIEW_REF'}


class Refused(ValueError):
    """Messages are never included in public output."""


class PrivateParser(argparse.ArgumentParser):
    def error(self, message):
        raise Refused('invalid command line')


def require(ok):
    if not ok:
        raise Refused('probe refused')


def trusted_binary(path):
    require(path in (SSH, KEYSCAN, '/usr/bin/python3'))
    s = os.stat(path)
    require(stat.S_ISREG(s.st_mode) and s.st_uid == 0 and not s.st_mode & 0o022)
    require(os.access(path, os.X_OK))
    return path


def bounded_run(argv, *, env, timeout, input_bytes=b''):
    """Bound BOTH streams while reading; kill process group and reap on failure."""
    require(0 < timeout <= 35 and len(input_bytes) <= LIMIT)
    with tempfile.TemporaryFile() as source:
        source.write(input_bytes)
        source.seek(0)
        process = subprocess.Popen(argv, stdin=source, stdout=subprocess.PIPE,
                                   stderr=subprocess.PIPE, env=env,
                                   start_new_session=True, close_fds=True)
        output, errors = bytearray(), bytearray()
        deadline = time.monotonic() + timeout
        try:
            with selectors.DefaultSelector() as selector:
                selector.register(process.stdout, selectors.EVENT_READ, output)
                selector.register(process.stderr, selectors.EVENT_READ, errors)
                while selector.get_map():
                    remaining = deadline - time.monotonic()
                    require(remaining > 0)
                    for key, _ in selector.select(min(remaining, 0.25)):
                        block = os.read(key.fileobj.fileno(), 4096)
                        if not block:
                            selector.unregister(key.fileobj)
                            continue
                        require(len(key.data) + len(block) <= LIMIT)
                        key.data.extend(block)
            require(deadline > time.monotonic())
            rc = process.wait(timeout=max(0.001, deadline - time.monotonic()))
            require(rc == 0)
            return bytes(output), bytes(errors)
        finally:
            # Also clean up descendants that hold pipes after the client exits.
            try:
                os.killpg(process.pid, signal.SIGKILL)
            except ProcessLookupError:
                pass
            process.wait(timeout=5)
            process.stdout.close()
            process.stderr.close()


def settings(env, mode):
    require(mode in ('hostkey', 'metadata'))
    require(env.get('CIRCLE_PROJECT_USERNAME') == 'janjrre')
    require(env.get('CIRCLE_PROJECT_REPONAME') == 'Projekt-Wordpress-Plugin')
    require(env.get('CIRCLE_BRANCH') == BRANCH and not env.get('CIRCLE_TAG'))
    approved = env.get('PGP05_APPROVED_SHA', '')
    require(bool(re.fullmatch('[0-9a-f]{40}', approved)))
    require(env.get('CIRCLE_SHA1') == approved)
    require(env.get('PGP05_ZERO_COST_CONFIRMED') == 'yes')
    permitted = COMMON_FIELDS | (METADATA_FIELDS if mode == 'metadata' else set())
    require(all(not value or not name.startswith('PGP05_') or name in permitted
                for name, value in env.items()))
    # Refuse accidentally combined deployment/backup/DB contexts and project secrets.
    require(all(not value or not (name.startswith(('IONOS_', 'BACKUP_', 'ORGAVELA_BACKUP_'))
                or name in {'GH_TOKEN', 'GITHUB_TOKEN', 'SSHPASS', 'AGE_SECRET_KEY'})
                for name, value in env.items()))
    host = validate_host(env.get('PGP05_SSH_HOST', ''))
    if mode == 'metadata':
        require(env.get('PGP05_STARTUP_REVIEWED') == 'yes')
        require(env.get('PGP05_REMOTE_SHA256') == REMOTE_SHA256)
        require(bool(re.fullmatch(r'https://github\.com/janjrre/Projekt-Wordpress-Plugin/(?:pull/32|issues/27)#(?:issuecomment|pullrequestreview)-[0-9]+',
                                  env.get('PGP05_METADATA_REVIEW_REF', ''))))
        require(bool(re.fullmatch('[A-Za-z][A-Za-z0-9_-]{0,63}', env.get('PGP05_SSH_USER', ''))))
        root = env.get('PGP05_WP_ROOT', '')
        require(bool(re.fullmatch(r'(?:/[A-Za-z0-9_-]+){2,8}', root)))
        password = env.get('PGP05_SSH_PASSWORD', '')
        require(0 < len(password) <= 1024 and not any(x in password for x in '\r\n\x00'))
    return host


def clean_env(home):
    return {'PATH': '/usr/bin:/bin', 'HOME': str(home), 'LANG': 'C', 'LC_ALL': 'C'}


def candidate_line(data, host):
    require(read_candidate(data, host) == EXPECTED_FPR)
    for line in data.splitlines():
        if line.strip() and not line.startswith(b'#'):
            return line + b'\n'
    raise Refused('missing key')


def ssh_arguments(host, user, pinfile, root):
    opts = ['-F', '/dev/null', '-T', '-p', '22', '-l', user]
    for option in (
        'StrictHostKeyChecking=yes', 'UserKnownHostsFile=' + str(pinfile),
        'GlobalKnownHostsFile=/dev/null', 'HostKeyAlgorithms=ssh-ed25519',
        'UpdateHostKeys=no', 'VerifyHostKeyDNS=no', 'CheckHostIP=no',
        'ForwardAgent=no', 'ForwardX11=no', 'ClearAllForwardings=yes',
        'PermitLocalCommand=no', 'ProxyCommand=none', 'ProxyJump=none',
        'ControlMaster=no', 'ControlPath=none', 'ConnectionAttempts=1',
        'ConnectTimeout=8', 'ServerAliveInterval=5', 'ServerAliveCountMax=1',
        'LogLevel=ERROR', 'NumberOfPasswordPrompts=1', 'PreferredAuthentications=password',
        'PubkeyAuthentication=no', 'KbdInteractiveAuthentication=no',
        'PasswordAuthentication=yes', 'GSSAPIAuthentication=no',
        'HostbasedAuthentication=no', 'BatchMode=no', 'EscapeChar=none',
    ):
        opts += ['-o', option]
    command = 'exec /usr/bin/env -i PATH=/usr/bin:/bin LC_ALL=C /bin/sh -s -- ' + shlex.quote(root) + ' ' + shlex.quote(user)
    return [SSH] + opts + [host, command]


def probe(env, mode, *, runner=bounded_run):
    host = settings(env, mode)
    trusted_binary(KEYSCAN)
    if mode == 'metadata':
        trusted_binary(SSH)
        trusted_binary('/usr/bin/python3')
    with tempfile.TemporaryDirectory(prefix='pgp05-probe-') as name:
        home = Path(name)
        os.chmod(home, 0o700)
        child_env = clean_env(home)
        data, _ = runner([KEYSCAN, '-p', '22', '-T', '5', '-t', 'ed25519', host],
                         env=child_env, timeout=9)
        pin = candidate_line(data, host)
        if mode == 'hostkey':
            return {'schema': 'orgavela-pgp05-ssh-probe/v1', 'mode': mode,
                    'hostkey_candidate_matches_provider': True,
                    'authenticated_ssh': False, 'production_capture_allowed': False}
        pinfile = home / 'known_hosts'
        pinfile.write_bytes(pin)
        pinfile.chmod(0o600)
        helper = home / 'askpass'
        helper.write_bytes(ASKPASS)
        helper.chmod(0o700)
        child_env.update({'SSH_ASKPASS': str(helper), 'SSH_ASKPASS_REQUIRE': 'force',
                          'DISPLAY': 'pgp05:0', 'PGP05_SSH_PASSWORD': env['PGP05_SSH_PASSWORD']})
        data, _ = runner(ssh_arguments(host, env['PGP05_SSH_USER'], pinfile, env['PGP05_WP_ROOT']),
                         env=child_env, timeout=30, input_bytes=REMOTE_SCRIPT)
        require(data == REMOTE_OK)
        return {'schema': 'orgavela-pgp05-ssh-probe/v1', 'mode': mode,
                'hostkey_candidate_matches_provider': True, 'authenticated_ssh': True,
                'expected_user_and_root_shape_matched': True,
                'server_enforced_readonly_account': False,
                'domain_vhost_binding_proven': False, 'database_connected': False,
                'php_requested': False, 'wordpress_bootstrap_requested': False,
                'startup_hooks_independently_observed_by_probe': False,
                'remote_command_sha256': REMOTE_SHA256,
                'production_capture_allowed': False, 'isolated_restore_proven': False}


def main(argv=None):
    # No argparse values/usage errors are emitted, to avoid echoing private inputs.
    try:
        parser = PrivateParser(add_help=False, exit_on_error=False)
        parser.add_argument('--live-opt-in', action='store_true')
        parser.add_argument('--mode', choices=('hostkey', 'metadata'), required=True)
        args, unknown = parser.parse_known_args(argv)
        require(not unknown and args.live_opt_in)
        receipt = probe(dict(os.environ), args.mode)
        receipt['source_revision'] = os.environ['CIRCLE_SHA1']
    except (Exception, SystemExit):
        print('PGP05_CIRCLECI_SSH_PROBE=REFUSED', file=sys.stderr)
        return 78
    print('PGP05_CIRCLECI_SSH_PROBE=PASS')
    print(json.dumps(receipt, sort_keys=True, separators=(',', ':')))
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
