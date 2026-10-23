"""Synthetic-only transport tests. No IONOS connection or real credentials."""
import contextlib
import hashlib
import io
import os
from pathlib import Path
import struct
import base64
import subprocess
import sys
import tempfile
import time
import unittest
from unittest.mock import patch

import orgavela_pgp05_circleci_ssh_probe as m

HOST = 'access-1234567890.webspace-host.com'
BLOB = struct.pack('>I', 11) + b'ssh-ed25519' + struct.pack('>I', 32) + bytes(range(32))
LINE = (HOST + ' ssh-ed25519 ' + base64.b64encode(BLOB).decode() + '\n').encode()
FPR = 'SHA256:' + base64.b64encode(hashlib.sha256(BLOB).digest()).decode().rstrip('=')
SHA = 'a' * 40


def environment(metadata=False):
    env = {'CIRCLE_PROJECT_USERNAME': 'janjrre', 'CIRCLE_PROJECT_REPONAME': 'Projekt-Wordpress-Plugin',
           'CIRCLE_BRANCH': m.BRANCH, 'CIRCLE_SHA1': SHA,
           'PGP05_APPROVED_SHA': SHA, 'PGP05_SSH_HOST': HOST,
           'PGP05_ZERO_COST_CONFIRMED': 'yes'}
    if metadata:
        env.update({'PGP05_SSH_USER': 'fixture_user', 'PGP05_SSH_PASSWORD': 'synthetic password $() only',
                    'PGP05_WP_ROOT': '/synthetic/root', 'PGP05_REMOTE_SHA256': m.REMOTE_SHA256,
                    'PGP05_STARTUP_REVIEWED': 'yes',
                    'PGP05_METADATA_REVIEW_REF': 'https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/27#issuecomment-1'})
    return env


class SettingsTests(unittest.TestCase):
    def test_exact_modes(self):
        self.assertEqual(m.settings(environment(), 'hostkey'), HOST)
        self.assertEqual(m.settings(environment(True), 'metadata'), HOST)

    def test_wrong_repository_branch_tag_sha_cost_refused(self):
        for key, value in [('CIRCLE_PROJECT_USERNAME', 'other'), ('CIRCLE_PROJECT_REPONAME', 'other'),
                           ('CIRCLE_BRANCH', 'main'), ('CIRCLE_TAG', 'tag'), ('CIRCLE_SHA1', 'b'*40),
                           ('PGP05_APPROVED_SHA', 'bad'), ('PGP05_ZERO_COST_CONFIRMED', 'no')]:
            with self.subTest(key=key), self.assertRaises(m.Refused):
                m.settings({**environment(), key: value}, 'hostkey')

    def test_foreign_secret_injection_and_unknown_probe_env_refused(self):
        for key in ['IONOS_SSH_PASSWORD', 'BACKUP_TOKEN', 'ORGAVELA_BACKUP_PRIVATE_KEY', 'GH_TOKEN',
                    'GITHUB_TOKEN', 'SSHPASS', 'AGE_SECRET_KEY', 'PGP05_EXTRA']:
            with self.subTest(key=key), self.assertRaises(m.Refused):
                m.settings({**environment(), key: 'synthetic'}, 'hostkey')

    def test_hostkey_mode_rejects_login_secrets(self):
        with self.assertRaises(m.Refused):
            m.settings(environment(True), 'hostkey')

    def test_metadata_requires_every_private_field(self):
        for key in m.METADATA_FIELDS:
            env = environment(True)
            del env[key]
            with self.subTest(key=key), self.assertRaises(m.Refused):
                m.settings(env, 'metadata')

    def test_root_user_password_injection_refused(self):
        for key, values in {
            'PGP05_WP_ROOT': ['/', '/public', '/a/../b', '/a//b', '/a/b/', '/a/b;id', '/a/b c', '-bad'],
            'PGP05_SSH_USER': ['-bad', 'user;id', 'a\nb'],
            'PGP05_SSH_PASSWORD': ['', 'a\nb', 'a\rb', 'a\x00b', 'x'*1025],
            'PGP05_METADATA_REVIEW_REF': ['yes', 'https://evil.invalid/#issuecomment-1'],
            'PGP05_STARTUP_REVIEWED': ['no'], 'PGP05_REMOTE_SHA256': ['0'*64],
        }.items():
            for value in values:
                with self.subTest(key=key, value=value), self.assertRaises(m.Refused):
                    m.settings({**environment(True), key: value}, 'metadata')


class TransportTests(unittest.TestCase):
    def test_unpinned_candidate_refused(self):
        with self.assertRaises(m.Refused):
            m.candidate_line(LINE, HOST)

    def test_candidate_only_has_no_authentication_or_private_env(self):
        calls = []
        def runner(argv, **kw):
            calls.append((argv, kw))
            return LINE, b'synthetic host banner'
        with patch.object(m, 'EXPECTED_FPR', FPR), patch.object(m, 'trusted_binary'):
            result = m.probe({**environment(), 'SSH_AUTH_SOCK': 'must not inherit'}, 'hostkey', runner=runner)
        self.assertFalse(result['authenticated_ssh'])
        self.assertEqual(len(calls), 1)
        self.assertEqual(calls[0][0], [m.KEYSCAN, '-p', '22', '-T', '5', '-t', 'ed25519', HOST])
        self.assertEqual(set(calls[0][1]['env']), {'PATH', 'HOME', 'LANG', 'LC_ALL'})

    def test_metadata_strict_pinning_exact_payload_and_cleanup(self):
        calls = []
        def runner(argv, **kw):
            calls.append((argv, kw.copy()))
            if len(calls) == 1:
                return LINE, b''
            options = argv
            self.assertIn('StrictHostKeyChecking=yes', options)
            self.assertIn('HostKeyAlgorithms=ssh-ed25519', options)
            self.assertIn('NumberOfPasswordPrompts=1', options)
            self.assertIn('PubkeyAuthentication=no', options)
            self.assertIn('ClearAllForwardings=yes', options)
            self.assertEqual(kw['input_bytes'], m.REMOTE_SCRIPT)
            self.assertNotIn(environment(True)['PGP05_SSH_PASSWORD'], ' '.join(argv))
            home = Path(kw['env']['HOME'])
            self.assertEqual((home/'known_hosts').read_bytes(), LINE)
            self.assertEqual((home/'known_hosts').stat().st_mode & 0o777, 0o600)
            self.assertEqual((home/'askpass').stat().st_mode & 0o777, 0o700)
            self.assertNotIn(environment(True)['PGP05_SSH_PASSWORD'].encode(), (home/'askpass').read_bytes())
            return m.REMOTE_OK, b''
        with patch.object(m, 'EXPECTED_FPR', FPR), patch.object(m, 'trusted_binary'):
            result = m.probe(environment(True), 'metadata', runner=runner)
        self.assertTrue(result['authenticated_ssh'])
        self.assertFalse(result['domain_vhost_binding_proven'])
        self.assertFalse(result['production_capture_allowed'])
        self.assertEqual(len(calls), 2)
        self.assertFalse(Path(calls[-1][1]['env']['HOME']).exists())

    def test_unexpected_remote_output_refused_and_cleaned(self):
        homes = []
        def runner(argv, **kw):
            homes.append(Path(kw['env']['HOME']))
            return (LINE, b'') if argv[0] == m.KEYSCAN else (m.REMOTE_OK+b'extra', b'')
        with patch.object(m, 'EXPECTED_FPR', FPR), patch.object(m, 'trusted_binary'), self.assertRaises(m.Refused):
            m.probe(environment(True), 'metadata', runner=runner)
        self.assertTrue(all(not path.exists() for path in homes))

    def test_unexpected_remote_stderr_refused_and_cleaned(self):
        for diagnostic in (b'warning\n', b' ', b'synthetic-secret\x00\xff'):
            with self.subTest(diagnostic=diagnostic):
                homes = []
                def runner(argv, **kw):
                    homes.append(Path(kw['env']['HOME']))
                    return (LINE, b'keyscan banner') if argv[0] == m.KEYSCAN else (m.REMOTE_OK, diagnostic)
                with patch.object(m, 'EXPECTED_FPR', FPR), patch.object(m, 'trusted_binary'), self.assertRaises(m.Refused):
                    m.probe(environment(True), 'metadata', runner=runner)
                self.assertEqual(len(homes), 2)
                self.assertTrue(all(not path.exists() for path in homes))

    def test_key_mismatch_never_authenticates(self):
        calls=[]
        def runner(argv, **kw):
            calls.append(argv)
            return LINE, b''
        with patch.object(m, 'trusted_binary'), self.assertRaises(m.Refused):
            m.probe(environment(True), 'metadata', runner=runner)
        self.assertEqual(len(calls), 1)

    def test_secret_helper_emits_only_to_captured_client_pipe(self):
        with tempfile.TemporaryDirectory() as tmp:
            path=Path(tmp)/'askpass';path.write_bytes(m.ASKPASS);path.chmod(0o700)
            result=subprocess.run([str(path)], env={**m.clean_env(tmp),'PGP05_SSH_PASSWORD':'synthetic-secret'},
                                  capture_output=True, timeout=3)
            self.assertEqual((result.returncode,result.stdout,result.stderr),(0,b'synthetic-secret\n',b''))

    def test_openSSH_accepts_options_without_network(self):
        args=m.ssh_arguments(HOST,'fixture_user',Path('/synthetic/pins'),'/synthetic/root')
        result=subprocess.run([args[0],'-G']+args[1:],env=m.clean_env('/tmp'),capture_output=True,timeout=3)
        self.assertEqual(result.returncode,0)
        self.assertIn(b'stricthostkeychecking true\n',result.stdout)
        self.assertIn(b'hostkeyalgorithms ssh-ed25519\n',result.stdout)

    def test_runner_preserves_bounded_streams_and_stdin(self):
        out, err = m.bounded_run([sys.executable, '-c', 'import sys;sys.stdout.buffer.write(sys.stdin.buffer.read());sys.stderr.write("ok")'],
                                 env=m.clean_env('/tmp'), timeout=3, input_bytes=b'fixture')
        self.assertEqual((out, err), (b'fixture', b'ok'))

    def test_runner_rejects_either_output_flood(self):
        for stream in ['stdout', 'stderr']:
            with self.subTest(stream=stream), self.assertRaises(m.Refused):
                m.bounded_run([sys.executable, '-c', 'import sys;sys.'+stream+'.write("x"*100000)'],
                              env=m.clean_env('/tmp'), timeout=3)

    def test_runner_kills_timeout_and_nonzero(self):
        for code in ['import time;time.sleep(10)', 'raise SystemExit(4)']:
            start=time.monotonic()
            with self.subTest(code=code), self.assertRaises(m.Refused):
                m.bounded_run([sys.executable, '-c', code], env=m.clean_env('/tmp'), timeout=0.2)
            self.assertLess(time.monotonic()-start, 3)

    def test_cli_refusal_is_sanitized_and_no_network_without_optin(self):
        for args in [[], ['--mode','hostkey'], ['--live-opt-in','--mode','private-secret'], ['--mode','hostkey','--live-opt-in','private-secret']]:
            out, err=io.StringIO(), io.StringIO()
            with patch.object(m, 'probe', side_effect=AssertionError('must not run')), contextlib.redirect_stdout(out), contextlib.redirect_stderr(err):
                self.assertEqual(m.main(args), 78)
            self.assertEqual(out.getvalue(), '')
            lines = err.getvalue().splitlines()
            self.assertEqual(lines[0], 'PGP05_CIRCLECI_SSH_PROBE=REFUSED')
            report = __import__('json').loads(lines[1])
            self.assertEqual(report['stage'], 'CLI')
            self.assertFalse(report['production_capture_allowed'])
            self.assertNotIn('private-secret', err.getvalue())

    def test_cli_receipt_binds_revision_without_identifiers(self):
        out,err=io.StringIO(),io.StringIO()
        with patch.dict(os.environ,environment(),clear=True), patch.object(m,'probe',return_value={'authenticated_ssh':False}), contextlib.redirect_stdout(out), contextlib.redirect_stderr(err):
            self.assertEqual(m.main(['--mode','hostkey','--live-opt-in']),0)
        self.assertIn(SHA,out.getvalue())
        self.assertNotIn(HOST,out.getvalue())
        self.assertEqual(err.getvalue(),'')


class DiagnosticTests(unittest.TestCase):
    def test_local_setting_reasons_stop_before_network(self):
        cases = [('PGP05_APPROVED_SHA','bad','REVISION'),
                 ('PGP05_ZERO_COST_CONFIRMED','no','COST'),
                 ('PGP05_SSH_HOST','private.invalid','HOST'),
                 ('PGP05_REMOTE_SHA256','0'*64,'REMOTE_SCRIPT_HASH'),
                 ('PGP05_METADATA_REVIEW_REF','private-secret','REVIEW_REF'),
                 ('PGP05_STARTUP_REVIEWED','no','STARTUP_SCOPE'),
                 ('PGP05_WP_ROOT','/one','ROOT_FORMAT'),
                 ('PGP05_SSH_USER','-private-secret','USER_FORMAT'),
                 ('PGP05_SSH_PASSWORD','private\nsecret','PASSWORD_FORMAT')]
        for key, value, reason in cases:
            with self.subTest(key=key):
                def runner(*args, **kw):
                    self.fail('must not reach network runner')
                with self.assertRaises(m.Refused) as caught:
                    m.probe({**environment(True),key:value},'metadata',runner=runner)
                self.assertEqual((caught.exception.stage,caught.exception.reason),('SETTINGS',reason))

    def test_remote_reported_codes_require_exact_empty_streams(self):
        # Python is a fictitious SSH process here; no network executable runs.
        with patch.object(m,'SSH',sys.executable):
            for code, expected in m.REMOTE_REFUSALS.items():
                with self.subTest(code=code), self.assertRaises(m.Refused) as caught:
                    m.bounded_run([sys.executable,'-c',f'raise SystemExit({code})'],env=m.clean_env('/tmp'),timeout=2)
                self.assertEqual(caught.exception.reason,expected)
            for source in ['raise SystemExit(255)', 'raise SystemExit(91)',
                           'print("private-secret");raise SystemExit(82)',
                           'import sys;sys.stderr.write("private-secret");raise SystemExit(82)']:
                with self.assertRaises(m.Refused) as caught:
                    m.bounded_run([sys.executable,'-c',source],env=m.clean_env('/tmp'),timeout=2)
                self.assertEqual(caught.exception.reason,'PROCESS_EXIT')

    def test_stage_labels_do_not_claim_authentication(self):
        def runner(argv,**kw):
            if argv[0]==m.KEYSCAN:return LINE,b''
            raise m.Refused('private-secret',reason='PROCESS_EXIT')
        with patch.object(m,'EXPECTED_FPR',FPR),patch.object(m,'trusted_binary'),self.assertRaises(m.Refused) as caught:
            m.probe(environment(True),'metadata',runner=runner)
        self.assertEqual((caught.exception.stage,caught.exception.reason),('SSH','PROCESS_EXIT'))

    def test_public_diagnostics_never_echo_exception_or_hostile_code(self):
        errors = [ValueError('private-secret'), m.Refused('private-secret'),
                  m.Refused('private-secret',reason='private-secret',stage='private-secret')]
        changed=m.Refused();changed.reason=['private-secret'];changed.stage={'private-secret':'value'};errors.append(changed)
        for error in errors:
            out,err=io.StringIO(),io.StringIO()
            with patch.object(m,'probe',side_effect=error),contextlib.redirect_stdout(out),contextlib.redirect_stderr(err):
                self.assertEqual(m.main(['--mode','metadata','--live-opt-in']),78)
            self.assertEqual(out.getvalue(),'')
            self.assertNotIn('private-secret',err.getvalue())
            report=__import__('json').loads(err.getvalue().splitlines()[1])
            self.assertEqual(set(report),{'schema','stage','reason','production_capture_allowed'})
            self.assertIn(report['stage'],m.STAGES)
            self.assertIn(report['reason'],m.REASONS)
            self.assertFalse(report['production_capture_allowed'])


class RemoteScriptTests(unittest.TestCase):
    def run_fixture(self, change=None):
        # Only a private synthetic root and files are created, never a real site.
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp)/'fixture'
            root.mkdir(mode=0o700)
            for rel in ['wp-admin','wp-includes','wp-content/plugins/uop-core','wp-content/mu-plugins']:
                (root/rel).mkdir(parents=True, exist_ok=True)
            for rel in ['index.php','wp-config.php','wp-content/plugins/uop-core/uop-core.php','wp-content/mu-plugins/automation-by-installatron.php']:
                (root/rel).touch(mode=0o600)
            if change:
                change(root)
            user=subprocess.check_output(['id','-un'], text=True).strip()
            return subprocess.run(['/bin/sh','-s','--',str(root),user], input=m.REMOTE_SCRIPT,
                                  capture_output=True, timeout=3, env=m.clean_env(tmp))

    def test_remote_valid_shape_without_php_content_access(self):
        result=self.run_fixture()
        self.assertEqual((result.returncode,result.stdout,result.stderr),(0,m.REMOTE_OK,b''))

    def test_remote_extra_mu_file_refused(self):
        for filename in ['extra.php', '.hidden.php']:
            result=self.run_fixture(lambda root:(root/'wp-content/mu-plugins'/filename).touch())
            self.assertEqual(result.returncode,90)
            self.assertEqual(result.stdout,b'')

    def test_remote_directory_symlink_refused(self):
        def change(root):
            (root/'wp-admin').rmdir();(root/'wp-admin').symlink_to(root/'wp-includes',target_is_directory=True)
        self.assertEqual(self.run_fixture(change).returncode,85)

    def test_php_files_never_executed(self):
        def change(root):
            (root/'index.php').write_text('<?php throw new Exception("synthetic never execute");')
            (root/'wp-config.php').write_text('<?php exit(1);')
        result=self.run_fixture(change)
        self.assertEqual((result.returncode,result.stdout),(0,m.REMOTE_OK))

    def test_remote_symlink_hardlink_config_mode_and_missing_refused(self):
        def symlink(root):
            p=root/'wp-config.php';p.unlink();p.symlink_to(root/'index.php')
        def hardlink(root):
            os.link(root/'wp-config.php',root/'other')
        for change, code in [(symlink,86),(hardlink,87),(lambda r:(r/'wp-config.php').chmod(0o644),89),(lambda r:(r/'index.php').unlink(),86)]:
            result=self.run_fixture(change)
            self.assertEqual(result.returncode,code)
            self.assertEqual(result.stdout,b'')


if __name__=='__main__':
    unittest.main()
