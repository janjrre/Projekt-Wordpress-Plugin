#!/usr/bin/env python3
"""Synthetic-only PGP-05 no-bootstrap receipt contract; not a live attestation.

Offline only: read a bounded byte-string passed by caller, verify immutable
synthetic contract, never infer IONOS identity, DB mapping or backup readiness.
No network, credentials, filesystem scan or WordPress bootstrap.
"""
from __future__ import annotations
import argparse
import json
import os
import stat
from contextlib import ExitStack
from pathlib import Path
import sys

SCHEMA = 'orgavela-pgp05-static-shape/v2'
MAX_BYTES = 4096
FLAGS_TRUE = frozenset({'expected_orgavela_file_shape', 'wp_config_private_mode',
                        'mu_filename_expected', 'uop_core_files_present'})
FLAGS_FALSE = frozenset({'php_contents_read', 'wp_bootstrap_executed',
                         'network_requested', 'db_rows_read', 'db_connected',
                         'effective_db_independently_mapped',
                         'verified_domain_document_root', 'verified_ssh_namespace',
                         'production_capture_allowed', 'isolated_restore_proven'})
FIELDS = FLAGS_TRUE | FLAGS_FALSE | {'schema', 'scope', 'mu_count'}


class Refused(ValueError):
    pass


def unique_pairs(items):
    output = {}
    for name, value in items:
        if name in output:
            raise Refused('duplicate JSON key')
        output[name] = value
    return output


def validate(value):
    if type(value) is not dict or set(value) != FIELDS:
        raise Refused('invalid receipt shape')
    if value['schema'] != SCHEMA or value['scope'] != 'synthetic':
        raise Refused('not synthetic-only')
    if type(value['mu_count']) is not int or value['mu_count'] != 1:
        raise Refused('invalid MU inventory')
    for key in FLAGS_TRUE:
        if value[key] is not True:
            raise Refused('missing positive synthetic marker')
    for key in FLAGS_FALSE:
        if value[key] is not False:
            raise Refused('unapproved activity/evidence flag')
    return {'synthetic_contract': 'PASS', 'source_identity_proven': False,
            'real_db_mapping_proven': False, 'production_allowed': False}


def load(blob: bytes):
    if type(blob) is not bytes or not 0 < len(blob) <= MAX_BYTES:
        raise Refused('invalid receipt length')
    try:
        value = json.loads(blob.decode('utf-8'), object_pairs_hook=unique_pairs,
                           parse_constant=lambda _: (_ for _ in ()).throw(Refused('nonfinite number')))
        return validate(value)
    except (UnicodeError, ValueError, RecursionError, TypeError, OverflowError) as exc:
        raise Refused('invalid receipt encoding') from exc


def restricted_receipt_bytes(filename: str) -> bytes:
    """Read a synthetic receipt through pinned directories, never a PHP file.

    O_NOFOLLOW on every path component and leaf refuses symlink substitution.
    This is still only synthetically-authorized fixture input, not IONOS data.
    """
    raw = str(filename)
    if not raw.startswith('/') or raw == '/' or '\x00' in raw or '//' in raw:
        raise Refused('unsafe file path')
    names = raw.split('/')[1:]
    if not names or any(x in ('', '.', '..') for x in names):
        raise Refused('unsafe path component')
    dirflags = os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW | getattr(os, 'O_CLOEXEC', 0)
    fileflags = os.O_RDONLY | os.O_NOFOLLOW | os.O_NONBLOCK | getattr(os, 'O_CLOEXEC', 0)
    with ExitStack() as stack:
        parentfd = os.open('/', dirflags)
        stack.callback(os.close, parentfd)
        for name in names[:-1]:
            child = os.open(name, dirflags, dir_fd=parentfd)
            stack.callback(os.close, child)
            if not stat.S_ISDIR(os.fstat(child).st_mode):
                raise Refused('not a directory')
            parentfd = child
        filefd = os.open(names[-1], fileflags, dir_fd=parentfd)
        stack.callback(os.close, filefd)
        before = os.fstat(filefd)
        if (not stat.S_ISREG(before.st_mode) or before.st_nlink != 1 or
                before.st_mode & 0o022 or not 0 < before.st_size <= MAX_BYTES):
            raise Refused('unsafe input metadata')
        data = os.read(filefd, MAX_BYTES + 1)
        after = os.fstat(filefd)
        if (len(data) != before.st_size or
                (before.st_dev, before.st_ino, before.st_size, before.st_mtime_ns, before.st_ctime_ns) !=
                (after.st_dev, after.st_ino, after.st_size, after.st_mtime_ns, after.st_ctime_ns)):
            raise Refused('receipt changed during read')
        return data


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--synthetic-only', action='store_true')
    parser.add_argument('--receipt-file', required=True)
    args = parser.parse_args(argv)
    try:
        if not args.synthetic_only:
            raise Refused('synthetic opt-in required')
        load(restricted_receipt_bytes(args.receipt_file))
    except (OSError, Refused):
        print('PGP05_STATIC_RECEIPT=REFUSED', file=sys.stderr)
        return 1
    print('PGP05_STATIC_RECEIPT_SYNTHETIC_ONLY=PASS')
    return 0

if __name__ == '__main__':
    raise SystemExit(main())
