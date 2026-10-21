"""Adversarial checks of the synthetic-only receipt boundary; no real source."""
from contextlib import redirect_stdout, redirect_stderr
import io
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch
import orgavela_pgp05_static_identity_v2 as scanner
import orgavela_pgp05_static_receipt_contract as validator
import test_orgavela_pgp05_static_identity_v2 as fixturemod

class ReceiptContractTests(unittest.TestCase):
    def setUp(self):
        tmp=tempfile.TemporaryDirectory(prefix='pgp05-contract-')
        self.addCleanup(tmp.cleanup)
        self.base=Path(tmp.name)
        self.root=fixturemod.fixture(self.base)
        self.valid=scanner.assess(self.root,synthetic=True)

    def test_exact_scanner_receipt_passes_as_synthetic_only(self):
        assert validator.load(json.dumps(self.valid).encode())['production_allowed'] is False

    def test_real_scope_never_accepted(self):
        self.valid['scope']='metadata-only'
        with self.assertRaises(validator.Refused): validator.validate(self.valid)

    def test_duplicate_keys_refused(self):
        data=json.dumps(self.valid)
        with self.assertRaises(validator.Refused): validator.load((data[:-1]+',"mu_count":1}').encode())

    def test_all_fields_required(self):
        for name in list(self.valid):
            with self.subTest(name=name):
                altered=dict(self.valid); altered.pop(name)
                with self.assertRaises(validator.Refused): validator.validate(altered)

    def test_no_added_fields_even_unsafe_names(self):
        for name in ('wp_config','db_password','ssh_host','db_name'):
            with self.subTest(name=name):
                altered=dict(self.valid); altered[name]='leak'
                with self.assertRaises(validator.Refused): validator.validate(altered)

    def test_positive_marker_strict_types(self):
        for key in validator.FLAGS_TRUE:
            for v in (False,1,'true',None,[],{}):
                altered=dict(self.valid); altered[key]=v
                with self.subTest(key=key,v=str(v)),self.assertRaises(validator.Refused):
                    validator.validate(altered)

    def test_negative_markers_strict_types(self):
        for key in validator.FLAGS_FALSE:
            for v in (True,0,'false',None,[],{}):
                altered=dict(self.valid); altered[key]=v
                with self.subTest(key=key,v=str(v)),self.assertRaises(validator.Refused):
                    validator.validate(altered)

    def test_mu_exact_count(self):
        for v in (0,2,True,False,'1',[],{},None):
            altered=dict(self.valid); altered['mu_count']=v
            with self.subTest(v=str(v)),self.assertRaises(validator.Refused):
                validator.validate(altered)

    def test_schema_and_scope_constraints(self):
        for key in ('schema','scope'):
            for v in (None,[],{},'other',1):
                altered=dict(self.valid); altered[key]=v
                with self.subTest(key=key,v=str(v)),self.assertRaises(validator.Refused):
                    validator.validate(altered)

    def test_large_payload_rejected(self):
        with self.assertRaises(validator.Refused):validator.load(b' '*4097)

    def test_empty_and_non_json(self):
        for b in (b'',b'[]',b'null',b'false',b'\xff',b'not json'):
            with self.subTest(b=b),self.assertRaises(validator.Refused): validator.load(b)

    def test_nonfinite(self):
        d=json.dumps(self.valid).replace('"mu_count": 1','"mu_count": NaN')
        with self.assertRaises(validator.Refused):validator.load(d.encode())

    def test_json_scalar_types(self):
        for val in (None,True,False,42,'x',[],()):
            with self.subTest(val=str(val)),self.assertRaises(validator.Refused):
                validator.validate(val)

    def test_cli_denies_missing_optin(self):
        p=self.base/'x.json'; p.write_text(json.dumps(self.valid))
        with redirect_stdout(io.StringIO()),redirect_stderr(io.StringIO()):
            self.assertEqual(validator.main(['--receipt-file',str(p)]),1)

    def test_cli_accepts_synthetic_only(self):
        p=self.base/'x.json'; p.write_text(json.dumps(self.valid))
        out=io.StringIO()
        with redirect_stdout(out),redirect_stderr(io.StringIO()):
            self.assertEqual(validator.main(['--synthetic-only','--receipt-file',str(p)]),0)
        self.assertEqual(out.getvalue().strip(),'PGP05_STATIC_RECEIPT_SYNTHETIC_ONLY=PASS')

    def test_cli_refuses_parent_symlink(self):
        p=self.base/'x.json';p.write_text(json.dumps(self.valid))
        link=self.base/'link'; link.symlink_to(self.base, target_is_directory=True)
        err=io.StringIO()
        with redirect_stdout(io.StringIO()),redirect_stderr(err):
            self.assertEqual(validator.main(['--synthetic-only','--receipt-file',str(link/'x.json')]),1)
        self.assertEqual(err.getvalue().strip(),'PGP05_STATIC_RECEIPT=REFUSED')

    def test_cli_refuses_file_symlink(self):
        p=self.base/'x.json';p.write_text(json.dumps(self.valid))
        link=self.base/'link.json';link.symlink_to(p)
        with redirect_stdout(io.StringIO()),redirect_stderr(io.StringIO()):
            self.assertEqual(validator.main(['--synthetic-only','--receipt-file',str(link)]),1)

    def test_cli_refuses_nonsynthetic_layout(self):
        self.valid['production_capture_allowed']=True
        p=self.base/'x.json';p.write_text(json.dumps(self.valid))
        with redirect_stdout(io.StringIO()),redirect_stderr(io.StringIO()):
            self.assertEqual(validator.main(['--synthetic-only','--receipt-file',str(p)]),1)

    def test_absent_live_cli_switch(self):
        with self.assertRaises(SystemExit):
            validator.main(['--live','--receipt-file',str(self.base/'x.json')])

    def test_never_run_php_or_network(self):
        import ast
        tree=ast.parse(Path(validator.__file__).read_text())
        imported=set()
        for node in ast.walk(tree):
            if isinstance(node,ast.Import):imported.update(a.name.split('.')[0] for a in node.names)
        self.assertFalse({'socket','subprocess','requests','urllib','pymysql'} & imported)

    def test_no_claim_of_ionos_identity(self):
        result=validator.validate(self.valid)
        self.assertIs(result['source_identity_proven'],False)
        self.assertIs(result['real_db_mapping_proven'],False)

    def test_cli_refuses_fifo_without_hanging(self):
        import os
        fifo=self.base/'pending.pipe';os.mkfifo(fifo)
        with redirect_stdout(io.StringIO()),redirect_stderr(io.StringIO()):
            self.assertEqual(validator.main(['--synthetic-only','--receipt-file',str(fifo)]),1)

    def test_cli_refuses_world_or_group_writable_receipt(self):
        p=self.base/'x.json';p.write_text(json.dumps(self.valid))
        for mode in (0o666, 0o620, 0o602):
            p.chmod(mode)
            with self.subTest(mode=oct(mode)):
                with redirect_stdout(io.StringIO()),redirect_stderr(io.StringIO()):
                    self.assertEqual(validator.main(['--synthetic-only','--receipt-file',str(p)]),1)

    def test_cli_refuses_hardlinked_receipt(self):
        import os
        p=self.base/'x.json';p.write_text(json.dumps(self.valid))
        os.link(p,self.base/'h.json')
        with redirect_stdout(io.StringIO()),redirect_stderr(io.StringIO()):
            self.assertEqual(validator.main(['--synthetic-only','--receipt-file',str(p)]),1)

    def test_cli_rejects_race_modifying_receipt_during_read(self):
        p=self.base/'x.json';p.write_text(json.dumps(self.valid))
        original=validator.os.read
        def tamper(fd,n):
            data=original(fd,n)
            p.write_text(json.dumps(self.valid) + ' ')
            return data
        with patch.object(validator.os,'read',side_effect=tamper):
            with redirect_stdout(io.StringIO()),redirect_stderr(io.StringIO()):
                self.assertEqual(validator.main(['--synthetic-only','--receipt-file',str(p)]),1)

if __name__=='__main__':unittest.main()
