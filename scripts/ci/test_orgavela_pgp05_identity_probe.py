"""Local-only tests for PGP05 read-only WordPress identity metadata probe.

No WordPress/IONOS access. Fake wpdb and fake directory structure only.
"""
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

SCRIPT = Path(__file__).with_name('orgavela_pgp05_identity_probe.php')


class ProbeTest(unittest.TestCase):
    def setUp(self):
        self.t = tempfile.TemporaryDirectory()
        self.addCleanup(self.t.cleanup)
        self.root = Path(self.t.name) / 'synthetic-wp'
        for p in ('wp-admin', 'wp-content/plugins/uop-core', 'wp-includes'):
            (self.root / p).mkdir(parents=True)
        (self.root / 'wp-config.php').write_text('<?php // synthetic configuration marker only')
        (self.root / 'wp-content/plugins/uop-core/uop-core.php').write_text('<?php // fake plugin marker')

    def invoke(self, **settings):
        variables = dict(
            site_home='https://orgavela.com', site_url='https://orgavela.com',
            expected_root=str(self.root), actual_root=str(self.root),
            configured_db='synthetic_wp', selected_db='synthetic_wp',
            prefix='synthetic_', total='52', innodb='52', core_count='10',
            wp_cli='1',
        )
        variables.update({k: str(v) for k, v in settings.items()})
        php = """<?php
        if (getenv('F_WP_CLI') === '1') define('WP_CLI', true);
        define('ABSPATH', getenv('F_ACTUAL_ROOT'));
        function get_option($name) {return getenv($name === 'home' ? 'F_SITE_HOME' : 'F_SITE_URL');}
        class FakeDB {
            public $dbname; public $prefix;
            function __construct() {$this->dbname = getenv('F_CONFIGURED_DB'); $this->prefix = getenv('F_PREFIX');}
            function get_var($sql) {
                if ($sql === 'SELECT DATABASE()') return getenv('F_SELECTED_DB');
                if (strpos($sql, 'TABLE_NAME IN') !== false) return getenv('F_CORE_COUNT');
                return null;
            }
            function get_row($sql) {
                if (strpos($sql, 'information_schema.TABLES') === false) return null;
                return (object)['total'=>getenv('F_TOTAL'), 'innodb'=>getenv('F_INNODB')];
            }
            function prepare($sql, ...$args) {
                if (count($args) !== 10) throw new Exception('bad synthetic fixture');
                return str_replace('%s', "'FAKE_SYNTHETIC_ONLY'", $sql);
            }
        }
        $wpdb = new FakeDB();
        require getenv('F_SCRIPT');
        """
        fixture = Path(self.t.name) / 'fake_wp_bootstrap.php'
        fixture.write_text(php)
        env = os.environ.copy()
        for k, v in variables.items():
            env['F_' + k.upper()] = v
        env['F_SCRIPT'] = str(SCRIPT)
        env['ORGAVELA_PGP05_EXPECTED_WP_ROOT'] = variables['expected_root']
        result = subprocess.run(['php', '-d', 'display_errors=0', str(fixture)],
                                capture_output=True, text=True, env=env, timeout=6)
        return result

    def refused(self, **opts):
        r = self.invoke(**opts)
        self.assertEqual(r.returncode, 2, r.stdout)
        self.assertNotIn('synthetic_wp', r.stdout + r.stderr)
        self.assertNotIn(str(self.root), r.stdout + r.stderr)
        self.assertIn('ORGAVELA_PGP05_IDENTITY=REFUSED', r.stderr)

    def test_accepts_purely_synthetic_expected_metadata(self):
        r = self.invoke()
        self.assertEqual(r.returncode, 0, r.stderr)
        self.assertIn('METADATA_ONLY_PASS', r.stdout)
        self.assertIn('52_of_52_innodb', r.stdout)
        self.assertIn('not_independently_compared', r.stdout)
        self.assertIn('BACKUP_CAPTURE=blocked', r.stdout)
        self.assertNotIn('synthetic_wp', r.stdout)
        self.assertNotIn(str(self.root), r.stdout)

    def test_refuse_missing_wpcli(self): self.refused(wp_cli='0')
    def test_refuse_wrong_home(self): self.refused(site_home='https://dreamloud.de')
    def test_refuse_insecure_home(self): self.refused(site_home='http://orgavela.com')
    def test_refuse_wrong_siteurl(self): self.refused(site_url='https://other.invalid')
    def test_refuse_root_mismatch(self): self.refused(expected_root=self.t.name)
    def test_refuse_root_with_parent(self): self.refused(expected_root=str(self.root)+'/../synthetic-wp')
    def test_refuse_dreamloud_path(self): self.refused(expected_root='/home/dreamloud/public')
    def test_refuse_runtime_db_mismatch(self): self.refused(selected_db='fake_2')
    def test_refuse_different_table_count(self): self.refused(total='51')
    def test_refuse_non_innodb(self): self.refused(innodb='51')
    def test_refuse_missing_core_tables(self): self.refused(core_count='9')
    def test_refuse_more_core_tables(self): self.refused(core_count='11')
    def test_refuse_injection_prefix(self): self.refused(prefix='fake;DROP')
    def test_refuse_non_numeric_engine(self): self.refused(innodb='yes')
    def test_refuse_non_numeric_total(self): self.refused(total='52.1')

    def test_refuse_missing_config(self):
        (self.root / 'wp-config.php').unlink()
        self.refused()

    def test_refuse_missing_plugin(self):
        (self.root / 'wp-content/plugins/uop-core/uop-core.php').unlink()
        self.refused()

    def test_refuse_missing_admin_directory(self):
        (self.root / 'wp-admin').rmdir()
        self.refused()

    def test_refuse_symlinked_root_escape(self):
        link = Path(self.t.name)/'external-link'
        link.symlink_to(self.root)
        # The root and expected root are resolved but a symlink to outside
        # an expected path still fails the exact-root identity comparison.
        self.refused(actual_root=str(link),expected_root=self.t.name)


if __name__ == '__main__':
    unittest.main()
