"""Synthetic-only tests. Never connect to an IONOS host or open SSH credentials."""
import base64
from contextlib import redirect_stdout, redirect_stderr
import hashlib
import io
import os
import struct
import subprocess
import unittest
from unittest.mock import patch

import orgavela_pgp05_hostkey_candidate as m


HOST = "access-1234567890.webspace-host.com"
BLOB = struct.pack(">I", 11) + b"ssh-ed25519" + struct.pack(">I", 32) + bytes(range(32))
B64 = base64.b64encode(BLOB).decode("ascii")
FPR = "SHA256:" + base64.b64encode(hashlib.sha256(BLOB).digest()).decode("ascii").rstrip("=")
LINE = (HOST + " ssh-ed25519 " + B64 + "\n").encode("ascii")


class CandidateTests(unittest.TestCase):
    def test_01_exact_synthetic_public_key(self):
        self.assertEqual(m.read_candidate(LINE, HOST), FPR)

    def test_02_allow_identical_duplicate_and_comment(self):
        self.assertEqual(m.read_candidate(b"# synthetic\n" + LINE * 2, HOST), FPR)

    def test_03_wrong_provider_hostname_refused(self):
        for value in ("orgavela.com", "dreamloud.de", "access-bad.webspace-host.com",
                      "access-1234567890.webspace-host.com.evil", "-oProxyCommand",
                      "access-1234567890.webspace-host.com\nbad"):
            with self.subTest(value=value), self.assertRaises(m.Refused):
                m.validate_host(value)

    def test_04_key_line_other_host_refused(self):
        with self.assertRaises(m.Refused):
            m.read_candidate(LINE, "access-9876543210.webspace-host.com")

    def test_05_other_algorithms_refused(self):
        with self.assertRaises(m.Refused):
            m.read_candidate(LINE.replace(b"ssh-ed25519 ", b"ssh-rsa "), HOST)

    def test_06_malformed_and_oversized_refused(self):
        for data in (b"", b"garbage", b"\xff", b" " * (m.MAX_SCAN_BYTES + 1),
                     LINE + b"WARNING: unexpected", LINE.replace(B64.encode(), b"!")):
            with self.subTest(data=data[:12]), self.assertRaises(m.Refused):
                m.read_candidate(data, HOST)

    def test_07_incorrect_key_blob_structure_refused(self):
        bad = base64.b64encode(BLOB[:-1] + b"x" + b"extra").decode()
        with self.assertRaises(m.Refused):
            m.public_key_blob(bad)

    def test_08_scan_refuses_unpinned_public_key(self):
        result = subprocess.CompletedProcess(args=[], returncode=0, stdout=LINE, stderr=b"")
        with patch.object(m.subprocess, "run", return_value=result):
            with self.assertRaises(m.Refused):
                m.check_remote_candidate(HOST)

    def test_09_scan_synthetic_approved_key_and_exact_argv(self):
        result = subprocess.CompletedProcess(args=[], returncode=0, stdout=LINE, stderr=b"")
        with patch.object(m.subprocess, "run", return_value=result) as run:
            with patch.object(m, "EXPECTED_FPR", FPR):
                m.check_remote_candidate(HOST)
        args, kwargs = run.call_args
        self.assertEqual(args[0], ["ssh-keyscan", "-p", "22", "-T", "5", "-t", "ed25519", HOST])
        self.assertFalse(kwargs["check"])
        self.assertLessEqual(kwargs["timeout"], 9)

    def test_10_keyscan_failure_or_timeout_refused(self):
        fail = subprocess.CompletedProcess(args=[], returncode=1, stdout=LINE, stderr=b"hostname")
        with patch.object(m.subprocess, "run", return_value=fail), self.assertRaises(m.Refused):
            m.check_remote_candidate(HOST)
        with patch.object(m.subprocess, "run", side_effect=subprocess.TimeoutExpired(["ssh-keyscan"], 2)), self.assertRaises(m.Refused):
            m.check_remote_candidate(HOST)

    def test_11_cli_needs_explicit_flag(self):
        with patch.object(m.subprocess, "run", side_effect=AssertionError("network must never start")):
            with redirect_stderr(io.StringIO()):
                self.assertEqual(m.main(["--host", HOST]), 78)

    def test_12_cli_success_is_unauthenticated_only(self):
        result = subprocess.CompletedProcess(args=[], returncode=0, stdout=LINE, stderr=b"")
        output = io.StringIO()
        with patch.object(m.subprocess, "run", return_value=result):
            with patch.object(m, "EXPECTED_FPR", FPR):
                with redirect_stdout(output):
                    self.assertEqual(m.main(["--hostkey-candidate-only", "--host", HOST]), 0)
        self.assertEqual(output.getvalue().strip(), "PGP05_SSH_HOSTKEY_CANDIDATE=MATCHED_NOT_AUTHENTICATED")
        self.assertNotIn(HOST, output.getvalue())

    def test_13_cli_failure_sanitized(self):
        out, err = io.StringIO(), io.StringIO()
        with patch.object(m.subprocess, "run", side_effect=OSError("secret from OS")):
            with redirect_stdout(out), redirect_stderr(err):
                self.assertEqual(m.main(["--hostkey-candidate-only", "--host", HOST]), 78)
        self.assertEqual(out.getvalue(), "")
        self.assertEqual(err.getvalue().strip(), "PGP05_SSH_HOSTKEY_CANDIDATE=REFUSED")

    def test_14_no_secret_transport_in_source(self):
        import ast
        from pathlib import Path
        tree=ast.parse(Path(m.__file__).read_text())
        calls={n.func.id for n in ast.walk(tree) if isinstance(n,ast.Call) and isinstance(n.func,ast.Name)}
        self.assertFalse(calls & {"eval","exec","open"})
        self.assertNotIn("sshpass",Path(m.__file__).read_text())
        self.assertNotIn("wp eval",Path(m.__file__).read_text())


if __name__=="__main__":
    unittest.main()
