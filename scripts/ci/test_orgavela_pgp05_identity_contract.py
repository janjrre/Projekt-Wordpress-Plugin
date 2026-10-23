import json
import os
from contextlib import redirect_stderr
from io import StringIO
from pathlib import Path
import tempfile
import unittest

import orgavela_pgp05_identity_contract as m


def valid():
    return {
        'schema':m.SCHEMA, 'scope':'metadata-only', 'target':'https://orgavela.com',
        'source':'synthetic-fixture', 'wp_root_candidate':'home-www-public',
        'wp_home_match':True, 'wp_siteurl_match':True,
        'wp_config_present':True, 'wp_core_dirs_present':True,
        'uop_core_active':True, 'uop_core_version':'0.1.0-alpha.2',
        'wp_version':'7.1.3', 'db_server_family':'MariaDB',
        'db_table_count':52, 'db_innodb_count':52, 'db_other_engine_count':0,
        'wp_core_tables_present':True, 'uop_tables_present':True,
        'db_identity_independently_verified':False,
        'contains_secret_values':False, 'personal_records_exported':False,
        'file_contents_exported':False, 'dump_created':False,
        'website_writes_requested':False, 'new_ci_runs_requested':False}


class ContractTests(unittest.TestCase):
    def test_synthetic_fixture_format_pass(self):
        x=m.load(json.dumps(valid()).encode());self.assertEqual(x['contract'],'PASS')
        self.assertFalse(x['production_capture_allowed'])
        self.assertFalse(x['independent_db_mapping_proven'])

    def test_runtime_receipt_is_refused_after_wpcli_retirement(self):
        x=valid(); x['source']='runtime-wp-cli'
        with self.assertRaises(m.Refused):
            m.validate(x)

    def test_all_fields_required(self):
        for key in valid():
            x=valid();del x[key]
            with self.subTest(key=key),self.assertRaises(m.Refused):m.validate(x)

    def test_added_fields_refused(self):
        for key in ('db_name','db_password','root_actual_path','ssh_host','public_user'):
            x=valid();x[key]='NEVER STORE THIS'
            with self.subTest(key=key),self.assertRaises(m.Refused):m.validate(x)

    def test_target_or_scope(self):
        for key,bad in [('target','https://dreamloud.de'),('scope','backup'),('source','ssh-dump'),('schema','v2'),('wp_root_candidate','/etc')]:
            x=valid();x[key]=bad
            with self.subTest(key=key),self.assertRaises(m.Refused):m.validate(x)

    def test_false_required_flags(self):
        for key in ('wp_home_match','wp_siteurl_match','wp_config_present',
                    'wp_core_dirs_present','uop_core_active','wp_core_tables_present','uop_tables_present'):
            for bad in (False,'true',1,None):
                x=valid();x[key]=bad
                with self.subTest(key=key,bad=bad),self.assertRaises(m.Refused):m.validate(x)

    def test_nonzero_activity_or_secret_flags(self):
        for key in ('contains_secret_values','personal_records_exported','file_contents_exported',
                    'dump_created','website_writes_requested','new_ci_runs_requested'):
            for bad in (True,'false',0,None):
                x=valid();x[key]=bad
                with self.subTest(key=key,bad=bad),self.assertRaises(m.Refused):m.validate(x)

    def test_engine_counts(self):
        for key,bad in [('db_table_count',53),('db_innodb_count',51),
                        ('db_other_engine_count',1),('db_table_count',True),
                        ('db_innodb_count','52'),('db_other_engine_count',-1)]:
            x=valid();x[key]=bad
            with self.subTest(key=key,bad=bad),self.assertRaises(m.Refused):m.validate(x)

    def test_baseline_versions(self):
        for key,bad in [('wp_version','7.1.4'),('uop_core_version','0.1.0-alpha.3'),
                        ('db_server_family','MySQL')]:
            x=valid();x[key]=bad
            with self.subTest(key=key),self.assertRaises(m.Refused):m.validate(x)

    def test_cannot_assert_independent_mapping(self):
        x=valid();x['db_identity_independently_verified']=True
        with self.assertRaises(m.Refused):m.validate(x)

    def test_duplicate_json_keys(self):
        data=json.dumps(valid()).encode()
        tampered=data[:-1]+b',"target":"https://orgavela.com"}'
        with self.assertRaises(m.Refused):m.load(tampered)

    def test_oversized(self):
        with self.assertRaises(m.Refused):m.load(b' '*5000)

    def test_invalid_json(self):
        for raw in (b'',b'not json',b'[]',b'null',b'\xff'):
            with self.subTest(raw=raw),self.assertRaises(m.Refused):m.load(raw)

    def test_cli_opt_in_file(self):
        with tempfile.TemporaryDirectory() as td:
            p=Path(td)/'synthetic.json';p.write_text(json.dumps(valid()))
            self.assertEqual(m.main(['--metadata-receipt',str(p)]),0)
            self.assertEqual(m.main(['--metadata-receipt','relative.json']),1)

    def test_synthetic_fixture_still_not_production(self):
        x=m.validate(valid())
        self.assertIs(x['production_capture_allowed'],False)
        self.assertIs(x['isolated_restore_proven'],False)

    def test_unhashable_json_values_fail_closed(self):
        for field in ('source', 'wp_root_candidate'):
            for invalid in ([], {}, [1,2], {'secret': 'do not disclose'}, None, 42, True):
                specimen = valid(); specimen[field] = invalid
                with self.subTest(field=field, invalid=repr(invalid)):
                    with self.assertRaises(m.Refused):
                        m.load(json.dumps(specimen).encode())

    def test_invalid_object_never_prints_traceback(self):
        with tempfile.TemporaryDirectory() as td:
            for key in ('source', 'wp_root_candidate'):
                specimen = valid(); specimen[key] = {'secret': 'SENSITIVE SENTINEL'}
                p = Path(td) / 'receipt.json'
                p.write_text(json.dumps(specimen))
                out = StringIO()
                with redirect_stderr(out):
                    result = m.main(['--metadata-receipt', str(p)])
                self.assertEqual(result, 1)
                self.assertEqual(out.getvalue().strip(), 'PGP05_IDENTITY_CONTRACT=REFUSED')

    def test_receipt_file_type_and_mode_fail_closed(self):
        with tempfile.TemporaryDirectory() as td:
            p = Path(td) / 'receipt.json'
            p.write_text(json.dumps(valid()))
            p.chmod(0o666)
            self.assertEqual(m.main(['--metadata-receipt', str(p)]), 1)
            p.chmod(0o600)
            self.assertEqual(m.main(['--metadata-receipt', str(p)]), 0)
            link = Path(td) / 'link.json'
            link.symlink_to(p)
            self.assertEqual(m.main(['--metadata-receipt', str(link)]), 1)
            parent = Path(td) / 'linkdir'
            parent.symlink_to(Path(td), target_is_directory=True)
            self.assertEqual(m.main(['--metadata-receipt', str(parent/'receipt.json')]), 1)
            fifo = Path(td) / 'pipe'
            os.mkfifo(fifo)
            self.assertEqual(m.main(['--metadata-receipt', str(fifo)]), 1)


if __name__=='__main__':unittest.main()
