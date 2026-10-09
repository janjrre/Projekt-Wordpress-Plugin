<?php
namespace UOP\Tests\Integration;

use PHPUnit\Framework\TestCase;
use InvalidArgumentException;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\{Installer,M5OperationsRepository,SchemaManifest,WpdbConnection};

final class M5OperationsTest extends TestCase {
	private WpdbConnection $db;
	private string $prefix;
	private OrgScope $scope;
	private M5OperationsRepository $operations;

	protected function setUp(): void {
		global $wpdb;
		$this->db=new WpdbConnection($wpdb);
		$this->prefix=$wpdb->prefix.'uop_';
		$manifest=new SchemaManifest(dirname(__DIR__,2).'/schema/manifest.json');
		foreach(array_keys($manifest->tables()) as $table) $this->db->execute('DROP TABLE IF EXISTS %i',[$this->prefix.$table]);
		foreach(['uop_db_version','uop_data_version','uop_migration_progress_1','uop_migration_status','uop_default_organization_id'] as $option) delete_option($option);
		Installer::runner()->run();
		$this->scope=new OrgScope((int)get_option('uop_default_organization_id'));
		$this->operations=new M5OperationsRepository($this->db,$this->prefix);
	}

	public function test_operational_mail_diagnostics_are_tenant_scoped_and_minimal(): void {
		$uuid=PublicId::generate();
		$this->db->execute(
			"INSERT INTO %i (public_id,organization_id,idempotency_key,recipient,template_key,subject,body_text,status,attempts,queued_at,created_at,updated_at,template_revision,template_hash,locale,last_error_code) VALUES (%s,%d,%s,%s,%s,%s,%s,%s,%d,%s,%s,%s,%d,%s,%s,%s)",
			[$this->prefix.'email_messages',$uuid->to_binary(),$this->scope->id,random_bytes(32),'noreply@example.invalid','registration_received','Confidential heading','Confidential content','failed',1,'2030-01-01 00:00:00','2030-01-01 00:00:00','2030-01-01 00:00:00',1,random_bytes(32),'de_DE','wp_mail_failed']
		);
		self::assertSame(['failed'=>1],$this->operations->counts($this->scope,'email'));
		$rows=$this->operations->problem_messages($this->scope);
		self::assertCount(1,$rows);
		self::assertSame($uuid->to_binary(),$rows[0]['public_id']);
		foreach(['recipient','subject','body_text','body_html','idempotency_key'] as $hidden) self::assertArrayNotHasKey($hidden,$rows[0]);
		self::assertSame([],$this->operations->problem_messages(new OrgScope($this->scope->id+1)));
		self::assertSame([],$this->operations->counts(new OrgScope($this->scope->id+1),'email'));
	}

	public function test_export_and_retention_diagnostics_contain_only_allowed_fields(): void {
		$this->db->execute(
			'INSERT INTO %i (organization_id,rule_key,data_class,trigger_type,delay_days,action,enabled,settings_json,created_at,updated_at) VALUES (%d,%s,%s,%s,%d,%s,%d,%s,%s,%s)',
			[$this->prefix.'retention_rules',$this->scope->id,'short_profiles','profile_values','person.created',14,'erase',1,'{"private_note":"never_in_diagnostics"}','2030-01-01 00:00:00','2030-01-01 00:00:00']
		);
		$rows=$this->operations->retention_rules($this->scope);
		self::assertCount(1,$rows);
		self::assertSame('short_profiles',$rows[0]['rule_key']);
		self::assertArrayNotHasKey('settings_json',$rows[0]);
		self::assertSame([],$this->operations->retention_rules(new OrgScope($this->scope->id+1)));
		$this->expectException(InvalidArgumentException::class);
		$this->operations->counts($this->scope,'unknown');
	}
}
