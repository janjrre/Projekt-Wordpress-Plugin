#!/usr/bin/env python3
"""PGP-05: verify only an IONOS Webhosting ED25519 SSH keyscan candidate.

The unauthenticated keyscan alone is NOT an SSH session or a source-identity
attestation. No PHP, WordPress, database, shell login or backup is performed.
When run by a person, the sole nonfixture network request is TCP port 22 via
ssh-keyscan to an explicitly supplied IONOS Webhosting access hostname.
"""
from __future__ import annotations

import argparse
import base64
import binascii
import hashlib
import re
import struct
import subprocess
import sys

EXPECTED_FPR = "SHA256:1gx2w8Rtv3wCgi7Jh8myf/KVd72cRQbow03UP8P095Q"
MAX_SCAN_BYTES = 16384
HOST_RE = re.compile(r"access-[0-9]{6,16}\.webspace-host\.com\Z", re.ASCII)
PUBKEY_TYPE = b"ssh-ed25519"


class Refused(ValueError):
    pass


def validate_host(host: str) -> str:
    if type(host) is not str or not HOST_RE.fullmatch(host):
        raise Refused("target not an explicitly allowlisted IONOS access hostname")
    return host


def public_key_blob(encoded: str) -> bytes:
    if not encoded or len(encoded) > 200:
        raise Refused("invalid key")
    try:
        blob = base64.b64decode(encoded, validate=True)
    except (ValueError, binascii.Error) as error:
        raise Refused("invalid public key encoding") from error
    if len(blob) != 51:
        raise Refused("unexpected key structure")
    name_len = struct.unpack(">I", blob[:4])[0]
    key_len = struct.unpack(">I", blob[15:19])[0]
    if name_len != len(PUBKEY_TYPE) or blob[4:15] != PUBKEY_TYPE or key_len != 32:
        raise Refused("unexpected key type")
    return blob


def read_candidate(data: bytes, host: str) -> str:
    validate_host(host)
    if type(data) is not bytes or not (0 < len(data) <= MAX_SCAN_BYTES):
        raise Refused("empty or oversized scan")
    try:
        lines = data.decode("ascii").splitlines()
    except UnicodeDecodeError as error:
        raise Refused("non-ASCII scan") from error
    fingerprints: set[str] = set()
    for line in lines:
        if not line.strip() or line.startswith("#"):
            continue
        parts = line.split()
        if len(parts) != 3 or parts[0] != host or parts[1] != "ssh-ed25519":
            raise Refused("unexpected host, type or additional scan fields")
        blob = public_key_blob(parts[2])
        fingerprint = "SHA256:" + base64.b64encode(
            hashlib.sha256(blob).digest()
        ).decode("ascii").rstrip("=")
        fingerprints.add(fingerprint)
    if len(fingerprints) != 1:
        raise Refused("missing or conflicting public keys")
    return next(iter(fingerprints))


def check_remote_candidate(host: str) -> None:
    validate_host(host)
    try:
        result = subprocess.run(
            ["ssh-keyscan", "-p", "22", "-T", "5", "-t", "ed25519", host],
            capture_output=True, check=False, timeout=9,
        )
    except (OSError, subprocess.TimeoutExpired) as error:
        raise Refused("SSH keyscan unavailable") from error
    if result.returncode != 0:
        raise Refused("SSH keyscan did not succeed")
    if read_candidate(result.stdout, host) != EXPECTED_FPR:
        raise Refused("host key differs from provider-pinned ED25519 fingerprint")


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--hostkey-candidate-only", action="store_true")
    parser.add_argument("--host", required=True, help="IONOS account host; never logged")
    args = parser.parse_args(argv)
    try:
        if not args.hostkey_candidate_only:
            raise Refused("explicit network opt-in required")
        check_remote_candidate(args.host)
    except (Refused, ValueError, TypeError):
        print("PGP05_SSH_HOSTKEY_CANDIDATE=REFUSED", file=sys.stderr)
        return 78
    print("PGP05_SSH_HOSTKEY_CANDIDATE=MATCHED_NOT_AUTHENTICATED")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
