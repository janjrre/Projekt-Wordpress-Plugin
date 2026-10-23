#!/usr/bin/env python3
"""Parse and validate a redacted OrgaVela WordPress/DB identity receipt.

This file performs no SSH, WordPress, DB, network or secret retrieval.
A passing receipt is a *format/consistency check*, never evidence that an
actual IONOS machine was contacted or a backup may be made.
"""
from __future__ import annotations

import argparse
import json
import os
from pathlib import Path
import stat
import sys

SCHEMA = "orgavela-pgp05-readonly-identity/v1"
KEYS = frozenset((
    "schema", "scope", "target", "source", "wp_root_candidate", "wp_home_match",
    "wp_siteurl_match", "wp_config_present", "wp_core_dirs_present",
    "uop_core_active", "uop_core_version", "wp_version",
    "db_server_family", "db_table_count", "db_innodb_count",
    "db_other_engine_count", "wp_core_tables_present", "uop_tables_present",
    "db_identity_independently_verified", "contains_secret_values", "personal_records_exported",
    "file_contents_exported", "dump_created", "website_writes_requested", "new_ci_runs_requested",
))
ROOTS = frozenset(("public-direct", "home-public", "home-www-public", "home-www"))
SOURCE = frozenset(("synthetic-fixture",))  # runtime-wp-cli retired pending separate approval
MAX_BYTES = 4096


class Refused(ValueError):
    pass


def pairs(items):
    obj = {}
    for k, v in items:
        if k in obj:
            raise Refused("duplicate JSON key")
        obj[k] = v
    return obj


def validate(value):
    if type(value) is not dict or frozenset(value) != KEYS:
        raise Refused("unexpected or missing status fields")
    # JSON can contain arrays/objects as field values. Check types BEFORE hash-based
    # enum membership, otherwise malformed input raises unhandled TypeError.
    if (value["schema"] != SCHEMA or value["scope"] != "metadata-only" or
            value["target"] != "https://orgavela.com" or
            type(value["source"]) is not str or value["source"] not in SOURCE):
        raise Refused("wrong project or audit scope")
    if (type(value["wp_root_candidate"]) is not str or
            value["wp_root_candidate"] not in ROOTS):
        raise Refused("unapproved shell-root class")
    for flag in ("wp_home_match", "wp_siteurl_match", "wp_config_present",
                 "wp_core_dirs_present", "uop_core_active", "wp_core_tables_present",
                 "uop_tables_present"):
        if value[flag] is not True:
            raise Refused("failed site/WordPress identity flag")
    if value["uop_core_version"] != "0.1.0-alpha.2":
        raise Refused("UOP Core baseline drift")
    if value["wp_version"] != "7.1.3":
        raise Refused("WordPress baseline drift")
    if value["db_server_family"] != "MariaDB":
        raise Refused("unexpected database server family")
    for key in ("db_table_count", "db_innodb_count", "db_other_engine_count"):
        if type(value[key]) is not int or not (0 <= value[key] <= 100000):
            raise Refused("invalid DB table count")
    if (value["db_table_count"] != 52 or value["db_innodb_count"] != 52 or
            value["db_other_engine_count"] != 0):
        raise Refused("engine counts disagree with owner-inspected baseline")
    # The phpMyAdmin database name has NOT yet been compared with the effective
    # WordPress DB name: this flag must remain false, even for a valid receipt.
    if value["db_identity_independently_verified"] is not False:
        raise Refused("unsupported independent DB-identity assertion")
    for key in ("contains_secret_values", "personal_records_exported", "file_contents_exported",
                "dump_created", "website_writes_requested", "new_ci_runs_requested"):
        if value[key] is not False:
            raise Refused("unexpected operation or secret disclosure")
    return {"contract": "PASS", "source": value["source"],
            "engine_metadata": "52/52 InnoDB", "production_capture_allowed": False,
            "independent_db_mapping_proven": False, "isolated_restore_proven": False}


def load(raw):
    if type(raw) is not bytes or not 0 < len(raw) <= MAX_BYTES:
        raise Refused("invalid or oversized metadata receipt")
    try:
        payload = json.loads(raw.decode("utf-8"), object_pairs_hook=pairs,
                             parse_constant=lambda _: (_ for _ in ()).throw(Refused("nonfinite JSON")))
    except (ValueError, UnicodeError) as exc:
        raise Refused("invalid metadata JSON") from exc
    return validate(payload)


def restricted_receipt_bytes(filename):
    """Read a local small regular file without following file-level symlinks.

    This is a local parser only: it does not confer trust on any receipt's author.
    The parent-path checks are defense-in-depth, not an atomic directory tree lock.
    """
    p = Path(filename)
    if (not p.is_absolute() or p == Path('/') or '..' in p.parts or
            any(node.is_symlink() for node in (p, *p.parents))):
        raise Refused("unsafe receipt path")
    flags = os.O_RDONLY | getattr(os, "O_NOFOLLOW", 0) | getattr(os, "O_NONBLOCK", 0)
    fd = os.open(p, flags)
    try:
        before = os.fstat(fd)
        if (not stat.S_ISREG(before.st_mode) or before.st_nlink != 1 or
                before.st_mode & 0o022 or not 0 < before.st_size <= MAX_BYTES):
            raise Refused("unsafe receipt file")
        with os.fdopen(fd, "rb", closefd=False) as f:
            raw = f.read(MAX_BYTES + 1)
        after = os.fstat(fd)
        if (len(raw) != before.st_size or
                (before.st_size, before.st_mtime_ns, before.st_ctime_ns) !=
                (after.st_size, after.st_mtime_ns, after.st_ctime_ns)):
            raise Refused("receipt changed while reading")
        return raw
    finally:
        os.close(fd)


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--metadata-receipt", required=True,
                        help="Existing local redacted JSON receipt; no network operations")
    args = parser.parse_args(argv)
    try:
        output = load(restricted_receipt_bytes(args.metadata_receipt))
    except (OSError, Refused):
        print("PGP05_IDENTITY_CONTRACT=REFUSED", file=sys.stderr)
        return 1
    # Never echo filenames, DB names, secrets, hosts, users, paths or original JSON.
    print("PGP05_IDENTITY_CONTRACT=PASS (RECEIPT ONLY; NOT LIVE EVIDENCE)")
    assert output["production_capture_allowed"] is False
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
