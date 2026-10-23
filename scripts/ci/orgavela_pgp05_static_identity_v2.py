#!/usr/bin/env python3
"""OrgaVela PGP-05: no-bootstrap *metadata-only* WordPress source inventory.

OFFLINE REVIEW CANDIDATE, NOT DEPLOYED, NOT AUTHORIZED TO RUN ON IONOS.
No WordPress/PHP execute/import, no SQL/SSH/network, no file contents read
(except an exact synthetic fixture marker in synthetic-only mode).
No live backup, release writer, cleanup, scheduler, or production enable flag.

This utility verifies filesystem *shape*, never claims effective DB mapping,
DocumentRoot or production restoreability. Caller must independently pin
OrgaVela contract/SSH host key/account/DocumentRoot before a future live run.
"""
from __future__ import annotations

import argparse
from contextlib import ExitStack
import json
import os
from pathlib import Path
import stat
import sys

SCHEMA = 'orgavela-pgp05-static-shape/v2'
MARKER = '.pgp05-synthetic-fixture-marker'
MARKER_VALUE = b'ORGAVELA PGP05 SYNTHETIC ONLY - NOT A REAL WORDPRESS ROOT\n'
MU_FILENAME = 'automation-by-installatron.php'
DIRS = ('wp-admin', 'wp-includes', 'wp-content', 'wp-content/plugins',
        'wp-content/plugins/uop-core', 'wp-content/mu-plugins')
FILES = ('index.php', 'wp-config.php', 'wp-content/plugins/uop-core/uop-core.php',
         'wp-content/mu-plugins/automation-by-installatron.php')
DIR_FLAGS = os.O_RDONLY | getattr(os, 'O_DIRECTORY', 0) | getattr(os, 'O_NOFOLLOW', 0) | getattr(os, 'O_CLOEXEC', 0)
# On systems without O_PATH, do not open real PHP files at all.
STAT_FLAGS = getattr(os, 'O_PATH', 0) | getattr(os, 'O_NOFOLLOW', 0) | getattr(os, 'O_CLOEXEC', 0)

class Refused(ValueError):
    """Safe, content- and path-free rejection."""


def guard(condition: bool) -> None:
    if not condition:
        raise Refused('metadata-only contract refused')


def validate_path(root: Path) -> tuple[str, ...]:
    raw = str(root)
    guard(raw.startswith('/') and raw != '/' and '\x00' not in raw)
    guard('//' not in raw and not raw.endswith('/'))
    tokens = raw.split('/')[1:]
    guard(bool(tokens) and all(t not in ('', '.', '..') for t in tokens))
    return tuple(tokens)


def fstat_dir(fd: int, *, forbid_writable: bool = False) -> os.stat_result:
    s = os.fstat(fd)
    guard(stat.S_ISDIR(s.st_mode))
    if forbid_writable:
        guard(not (s.st_mode & 0o022))
    return s


def open_root(root: Path, stack: ExitStack) -> int:
    """Anchor every path component to an open dirfd; reject symlinks."""
    tokens = validate_path(root)
    fd = os.open('/', DIR_FLAGS)
    stack.callback(os.close, fd)
    for name in tokens:
        nfd = os.open(name, DIR_FLAGS, dir_fd=fd)
        stack.callback(os.close, nfd)
        fstat_dir(nfd)
        fd = nfd
    return fd


def subdirectory(rootfd: int, rel: str, stack: ExitStack) -> int:
    fd = rootfd
    if not rel:
        return fd
    for part in rel.split('/'):
        guard(part not in ('', '.', '..'))
        nfd = os.open(part, DIR_FLAGS, dir_fd=fd)
        stack.callback(os.close, nfd)
        fstat_dir(nfd, forbid_writable=True)
        fd = nfd
    return fd


def stat_named_file(rootfd: int, rel: str, stack: ExitStack) -> os.stat_result:
    parts = rel.split('/')
    guard(len(parts) >= 1 and all(x not in ('', '.', '..') for x in parts))
    parent = subdirectory(rootfd, '/'.join(parts[:-1]), stack)
    # O_PATH avoids reading PHP or triggering content access. O_NOFOLLOW and
    # subsequent S_ISREG reject symlinks or other special files.
    fd = os.open(parts[-1], STAT_FLAGS, dir_fd=parent)
    stack.callback(os.close, fd)
    s = os.fstat(fd)
    guard(stat.S_ISREG(s.st_mode) and s.st_nlink == 1 and not (s.st_mode & 0o022))
    return s


def marker_only(rootfd: int, stack: ExitStack) -> None:
    """Strict fixture gate; never read real WordPress/PHP/config files."""
    s = stat_named_file(rootfd, MARKER, stack)
    guard(s.st_size == len(MARKER_VALUE) and not s.st_mode & 0o077)
    fd = os.open(MARKER, os.O_RDONLY | os.O_NONBLOCK | getattr(os, 'O_NOFOLLOW', 0), dir_fd=rootfd)
    stack.callback(os.close, fd)
    before = os.fstat(fd)
    guard(stat.S_ISREG(before.st_mode) and before.st_nlink == 1)
    data = os.read(fd, len(MARKER_VALUE) + 1)
    after = os.fstat(fd)
    guard(data == MARKER_VALUE)
    guard(all(getattr(before, x) == getattr(after, x)
              for x in ('st_dev', 'st_ino', 'st_size', 'st_mtime_ns', 'st_ctime_ns')))


def assess(root: Path, *, synthetic: bool) -> dict[str, object]:
    """Use synthetic=True only for explicit local fictitious test fixture.

    synthetic=False exposes a *review-only* metadata collection primitive used
    in offline tests, not a standalone authorized production capture path.
    """
    guard(bool(getattr(os, 'O_PATH', 0)))  # fail-closed instead of ordinary opens on unsupported OS
    with ExitStack() as stack:
        rootfd = open_root(root, stack)
        b = fstat_dir(rootfd, forbid_writable=True)
        if synthetic:
            guard(not b.st_mode & 0o077)
            marker_only(rootfd, stack)
        for rel in DIRS:
            subdirectory(rootfd, rel, stack)
        file_stats = {rel: stat_named_file(rootfd, rel, stack) for rel in FILES}
        guard(not file_stats['wp-config.php'].st_mode & 0o077)
        with ExitStack() as inventory_stack:
            mu_fd = subdirectory(rootfd, 'wp-content/mu-plugins', inventory_stack)
            names = os.listdir(mu_fd)
            guard(names == [MU_FILENAME])
        # Anchor was held open. Re-resolve through original absolute path after
        # metadata inventory; reject if it no longer resolves to the same inode.
        with ExitStack() as verify_stack:
            nowfd = open_root(root, verify_stack)
            now = os.fstat(nowfd)
            guard((b.st_dev, b.st_ino) == (now.st_dev, now.st_ino))
        return {
            'schema': SCHEMA, 'scope': 'synthetic' if synthetic else 'metadata-only',
            'expected_orgavela_file_shape': True,  # named shape only, not target attestation
            'wp_config_private_mode': True,
            'mu_count': 1, 'mu_filename_expected': True,
            'uop_core_files_present': True,
            'php_contents_read': False, 'wp_bootstrap_executed': False,
            'network_requested': False, 'db_rows_read': False, 'db_connected': False,
            'effective_db_independently_mapped': False,
            'verified_domain_document_root': False,
            'verified_ssh_namespace': False,
            'production_capture_allowed': False,
            'isolated_restore_proven': False,
        }


def main(argv: list[str] | None = None) -> int:
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('--synthetic-only', action='store_true')
    p.add_argument('--fixture-root', required=True)
    args = p.parse_args(argv)
    try:
        guard(args.synthetic_only)
        x = assess(Path(args.fixture_root), synthetic=True)
        guard(x['production_capture_allowed'] is False)
    except (OSError, Refused, ValueError):
        print('PGP05_STATIC_METADATA=REFUSED', file=sys.stderr)
        return 1
    print('PGP05_STATIC_SYNTHETIC_ONLY=PASS')
    print(json.dumps(x, sort_keys=True, separators=(',', ':')))
    return 0

if __name__ == '__main__':
    raise SystemExit(main())
