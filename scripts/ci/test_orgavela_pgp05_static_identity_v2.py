"""Synthetic-only tests for OrgaVela file-descriptor-pinned metadata inspection."""
from contextlib import redirect_stderr, redirect_stdout
import io
import os
from pathlib import Path
import stat
import tempfile
import unittest
from unittest.mock import patch

import orgavela_pgp05_static_identity_v2 as m


def fixture(base: Path):
    r = base / 'fake-wordpress'
    r.mkdir(mode=0o700)
    for rel in m.DIRS:
        (r / rel).mkdir(parents=True, exist_ok=True)
    for rel in m.FILES:
        p = r / rel
        p.write_bytes(b'FAKE PHP FIXTURE CONTENT; NEVER EXECUTE\n')
        p.chmod(0o600)
    (r / m.MARKER).write_bytes(m.MARKER_VALUE)
    (r / m.MARKER).chmod(0o600)
    return r


class StaticTests(unittest.TestCase):
    def setUp(self):
        tmp = tempfile.TemporaryDirectory(prefix='pgp05-static-v2-')
        self.addCleanup(tmp.cleanup)
        self.base = Path(tmp.name)
        self.root = fixture(self.base)

    def refused(self):
        with self.assertRaises((m.Refused, OSError, ValueError)):
            m.assess(self.root, synthetic=True)

    def test_01_valid_receipt_is_never_production(self):
        receipt = m.assess(self.root, synthetic=True)
        self.assertEqual(receipt['mu_count'], 1)
        self.assertFalse(receipt['effective_db_independently_mapped'])
        self.assertFalse(receipt['production_capture_allowed'])

    def test_02_cli_explicit_synthetic_optin(self):
        out, err = io.StringIO(), io.StringIO()
        with redirect_stdout(out), redirect_stderr(err):
            self.assertEqual(m.main(['--synthetic-only', '--fixture-root', str(self.root)]), 0)
        self.assertEqual(err.getvalue(), '')
        self.assertNotIn(str(self.root), out.getvalue())
        self.assertNotIn('FAKE PHP', out.getvalue())

    def test_03_no_implicit_real_cli_mode(self):
        self.assertEqual(m.main(['--fixture-root', str(self.root)]), 1)

    def test_04_valid_directory_type(self):
        with m.ExitStack() as stack:
            fd = m.open_root(self.root, stack)
            self.assertTrue(stat.S_ISDIR(os.fstat(fd).st_mode))

    def test_05_root_missing(self):
        with self.assertRaises(OSError):
            m.assess(self.base / 'missing', synthetic=True)

    def test_06_marker_missing(self):
        (self.root/m.MARKER).unlink()
        self.refused()

    def test_07_marker_altered(self):
        (self.root/m.MARKER).write_text('tampered')
        self.refused()

    def test_08_marker_world_readable(self):
        (self.root/m.MARKER).chmod(0o644)
        self.refused()

    def test_09_marker_symlink(self):
        (self.root/m.MARKER).unlink()
        (self.root/m.MARKER).symlink_to(self.root/'index.php')
        self.refused()

    def test_10_marker_hardlink(self):
        os.link(self.root/m.MARKER, self.root/'extra-marker')
        self.refused()

    def test_11_marker_dir(self):
        (self.root/m.MARKER).unlink()
        (self.root/m.MARKER).mkdir()
        self.refused()

    def test_12_root_symlink(self):
        path = self.base / 'link'
        path.symlink_to(self.root, target_is_directory=True)
        with self.assertRaises(OSError):
            m.assess(path, synthetic=True)

    def test_13_symlink_parent(self):
        path = self.base / 'link-parent'
        path.symlink_to(self.base, target_is_directory=True)
        with self.assertRaises(OSError):
            m.assess(path / self.root.name, synthetic=True)

    def test_14_symlink_wp_content(self):
        import shutil
        (self.root/'wp-content/mu-plugins'/m.MU_FILENAME).unlink()
        (self.root/'wp-content/plugins/uop-core/uop-core.php').unlink()
        shutil.rmtree(self.root/'wp-content')
        (self.root/'wp-content').symlink_to(self.base, target_is_directory=True)
        self.refused()

    def test_15_symlink_mu_file(self):
        x = self.root/'wp-content/mu-plugins'/m.MU_FILENAME
        x.unlink()
        x.symlink_to(self.root/'index.php')
        self.refused()

    def test_16_hardlink_php_file(self):
        os.link(self.root/'index.php', self.root/'copy.php')
        self.refused()

    def test_17_private_config(self):
        (self.root/'wp-config.php').chmod(0o640)
        self.refused()

    def test_18_private_fixture(self):
        self.root.chmod(0o750)
        self.refused()

    def test_19_no_mu_plugin(self):
        (self.root/'wp-content/mu-plugins'/m.MU_FILENAME).unlink()
        self.refused()

    def test_20_extra_mu_plugin(self):
        (self.root/'wp-content/mu-plugins'/'other.php').write_text('x')
        self.refused()

    def test_21_mu_wrong_name(self):
        p = self.root/'wp-content/mu-plugins'/m.MU_FILENAME
        p.rename(p.with_name('renamed.php'))
        self.refused()

    def test_22_missing_uop(self):
        (self.root/'wp-content/plugins/uop-core/uop-core.php').unlink()
        self.refused()

    def test_23_uop_symlink(self):
        p = self.root/'wp-content/plugins/uop-core/uop-core.php'
        p.unlink()
        p.symlink_to(self.root/'index.php')
        self.refused()

    def test_24_relative_path(self):
        with self.assertRaises(m.Refused):
            m.assess(Path('fake-wordpress'), synthetic=True)

    def test_25_dotdot_path(self):
        with self.assertRaises(m.Refused):
            m.assess(self.root / '..' / self.root.name, synthetic=True)

    def test_26_root_forbidden(self):
        with self.assertRaises(m.Refused):
            m.assess(Path('/'), synthetic=True)

    def test_27_never_open_read_php_contents(self):
        orig = m.os.open
        calls = []
        def track(file, flags, *a, **kw):
            # Any regular file apart from the synthetic fixture must be opened O_PATH.
            if isinstance(file, str) and file.endswith('.php'):
                calls.append((file, flags))
            return orig(file, flags, *a, **kw)
        with patch.object(m.os, 'open', track):
            m.assess(self.root, synthetic=True)
        self.assertTrue(calls)
        if hasattr(os, 'O_PATH'):
            self.assertTrue(all(flags & os.O_PATH for _, flags in calls))

    def test_28_absence_of_php_and_network_execution(self):
        import ast
        src = Path(m.__file__).read_text()
        tree = ast.parse(src)
        imported = set()
        for node in ast.walk(tree):
            if isinstance(node, ast.Import):
                imported.update(alias.name.split('.')[0] for alias in node.names)
            if isinstance(node, ast.ImportFrom) and node.module:
                imported.add(node.module.split('.')[0])
        self.assertFalse(imported & {'subprocess', 'socket', 'requests', 'urllib', 'paramiko', 'mysql', 'pymysql'})
        self.assertNotIn('exec(', src)
        self.assertNotIn('eval(', src)

    def test_29_failed_cli_has_no_sensitive_traceback(self):
        (self.root/'wp-config.php').unlink()
        out, err = io.StringIO(), io.StringIO()
        with redirect_stdout(out), redirect_stderr(err):
            self.assertEqual(m.main(['--synthetic-only', '--fixture-root', str(self.root)]), 1)
        self.assertEqual(out.getvalue(), '')
        self.assertEqual(err.getvalue().strip(), 'PGP05_STATIC_METADATA=REFUSED')

    def test_30_scan_live_mode_primitive_is_not_production_approval(self):
        receipt = m.assess(self.root, synthetic=False)
        self.assertEqual(receipt['scope'], 'metadata-only')
        for field in ('effective_db_independently_mapped','verified_ssh_namespace',
                      'verified_domain_document_root','production_capture_allowed'):
            self.assertIs(receipt[field], False)

    def test_31_live_mode_cannot_be_selected_via_cli(self):
        with self.assertRaises(SystemExit):
            m.main(['--live', '--fixture-root', str(self.root)])

    def test_32_reject_marker_as_fifo(self):
        p = self.root/m.MARKER
        p.unlink()
        os.mkfifo(p)
        self.refused()

    def test_34_reject_group_writable_dir(self):
        (self.root/'wp-content').chmod(0o775)
        self.refused()

    def test_35_reject_world_writable_mu_php(self):
        (self.root/'wp-content/mu-plugins'/m.MU_FILENAME).chmod(0o666)
        self.refused()

    def test_36_fail_closed_without_o_path(self):
        with patch.object(m.os, 'O_PATH', 0, create=True):
            # Linux parser mode still pins without content read, but original flag
            # remains present: no accidental fallback to direct PHP file reads.
            self.refused()

    def test_33_detect_root_rename_race_at_reopen(self):
        original = m.open_root
        times = {'n':0}
        def fake_open(root, stack):
            times['n'] += 1
            if times['n'] == 2:
                renamed = self.base/'old-root'
                self.root.rename(renamed)
                self.root.mkdir(mode=0o700)
            return original(root, stack)
        with patch.object(m,'open_root',fake_open):
            self.refused()

if __name__=='__main__':
    unittest.main()
