<?php
namespace UOP\Tests\Integration;

use PHPUnit\Framework\TestCase;
use InvalidArgumentException;
use RuntimeException;
use UOP\Application\Consent\ConsentDefinitionService;
use UOP\Application\Form\FormService;
use UOP\Application\Policy\{Actor, PolicyService};
use UOP\Core\{CorrelationId, PublicId, TransactionManager};
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\{AssignmentRepository, AuditWriter, ConsentRepository, DelegationRepository, FormRepository, Installer, OutboxRepository, PersonRepository, SchemaManifest, WpdbConnection};

final class M5ConsentTest extends TestCase {
	private WpdbConnection $db;
	private OrgScope $scope;
	private string $prefix;

	protected function setUp(): void {
		global $wpdb;
		$this->db=new WpdbConnection($wpdb);
		$this->prefix=$wpdb->prefix.'uop_';
		$manifest=new SchemaManifest(dirname(__DIR__,2).'/schema/manifest.json');
		foreach(array_keys($manifest->tables()) as $table) {
			$this->db->execute('DROP TABLE IF EXISTS %i',[$this->prefix.$table]);
		}
		foreach(['uop_db_version','uop_data_version','uop_migration_progress_1','uop_migration_status','uop_default_organization_id'] as $option) delete_option($option);
		Installer::runner()->run();
		$this->scope=new OrgScope((int)get_option('uop_default_organization_id'));
	}

	private function services(): array {
		$id=wp_create_user('uop_m5_'.bin2hex(random_bytes(4)),wp_generate_password(24),'m5_'.bin2hex(random_bytes(4)).'@example.invalid');
		self::assertIsInt($id);
		$user=new \WP_User($id);
		$user->set_role('administrator');
		wp_set_current_user($id);
		$actor=new Actor($id);
		$policy=new PolicyService(
			new PersonRepository($this->db,$this->prefix),
			new DelegationRepository($this->db,$this->prefix),
			new AssignmentRepository($this->db,$this->prefix),
			static fn(int $uid,string $cap): bool => $uid===$id
		);
		$tx=new TransactionManager($this->db,static function(int $delay): void {},static function(\Throwable $error): void {});
		$audit=new AuditWriter($this->db,$this->prefix);
		$outbox=new OutboxRepository($this->db,$this->prefix);
		return [
			'actor'=>$actor,
			'consents'=>new ConsentDefinitionService(new ConsentRepository($this->db,$this->prefix),$policy,$tx,$audit,$outbox),
			'repository'=>new ConsentRepository($this->db,$this->prefix),
			'forms'=>new FormService(new FormRepository($this->db,$this->prefix),$policy,$tx,$audit,$outbox),
		];
	}

	/** New version documents are immutable and existing form pins do not drift. */
	public function test_versioned_consent_documents_and_form_publication_pins(): void {
		$services=$this->services();
		$now='2030-01-02 10:00:00';
		$definition=$services['consents']->create($services['actor'],$this->scope,'portrait_use','Portrait publication',$now,CorrelationId::generate());
		$content1='I authorize publication of the supplied portrait photograph for this event.';
		$v1=$services['consents']->publish($services['actor'],$this->scope,$definition,$content1,$now,CorrelationId::generate());
		$draft=['schema_version'=>1,'fields'=>[
			['key'=>'portrait','type'=>'consent','label'=>'Image consent','required'=>true,'consent_definition_public_id'=>$definition->to_string()],
		]];
		$form=$services['forms']->create($services['actor'],$this->scope,'portrait_form','Portrait form','event',$draft,$now,CorrelationId::generate());
		$services['forms']->publish($services['actor'],$this->scope,$form,1,$now,CorrelationId::generate());
		$content2='I authorize portrait publication for the event programme and digital announcements.';
		$v2=$services['consents']->publish($services['actor'],$this->scope,$definition,$content2,$now,CorrelationId::generate());
		self::assertNotSame($v1->to_string(),$v2->to_string());
		$services['forms']->save_draft($services['actor'],$this->scope,$form,1,$draft,$now,CorrelationId::generate());
		$services['forms']->publish($services['actor'],$this->scope,$form,2,$now,CorrelationId::generate());
		$version_rows=$this->db->rows('SELECT v.version, v.document_hash, v.content FROM %i v INNER JOIN %i d ON d.id = v.definition_id WHERE d.organization_id = %d AND d.public_id = %s ORDER BY v.version ASC',[$this->prefix.'consent_versions',$this->prefix.'consent_definitions',$this->scope->id,$definition->to_binary()]);
		self::assertSame([1,2],array_map(static fn(array $r): int=>(int)$r['version'],$version_rows));
		self::assertSame([$content1,$content2],array_column($version_rows,'content'));
		foreach($version_rows as $row) self::assertSame(hash('sha256',$row['content'],true),$row['document_hash']);
		$form_rows=$this->db->rows('SELECT v.schema_json FROM %i v INNER JOIN %i f ON f.id = v.form_id WHERE f.organization_id = %d AND f.public_id = %s ORDER BY v.version',[$this->prefix.'form_versions',$this->prefix.'forms',$this->scope->id,$form->to_binary()]);
		self::assertCount(2,$form_rows);
		self::assertSame($v1->to_string(),json_decode($form_rows[0]['schema_json'],true)['fields'][0]['consent_version_public_id']);
		self::assertSame($v2->to_string(),json_decode($form_rows[1]['schema_json'],true)['fields'][0]['consent_version_public_id']);
		self::assertSame($content1,$services['repository']->version($this->scope,$v1)['content']);
		self::assertNull($services['repository']->version(new OrgScope($this->scope->id+9),$v1));
		$rows=$this->db->rows('SELECT current_version_id,status FROM %i WHERE organization_id = %d AND public_id = %s',[$this->prefix.'consent_definitions',$this->scope->id,$definition->to_binary()]);
		self::assertSame('active',$rows[0]['status']);
		self::assertNotNull($rows[0]['current_version_id']);
		$events=$this->db->rows("SELECT event_name FROM %i WHERE event_name IN ('consent.definition_created','consent.version_published') ORDER BY id",[$this->prefix.'domain_events']);
		self::assertSame(['consent.definition_created','consent.version_published','consent.version_published'],array_column($events,'event_name'));
	}

	/** Untrusted callers and duplicated texts cannot publish new evidence. */
	public function test_permissions_invalid_documents_and_duplicate_versions_fail_closed(): void {
		$services=$this->services();
		$now='2030-01-02 10:00:00';
		$definition=$services['consents']->create($services['actor'],$this->scope,'terms','Conditions',$now,CorrelationId::generate());
		$original='My voluntary consent for the stated purpose is limited to this published text.';
		$services['consents']->publish($services['actor'],$this->scope,$definition,$original,$now,CorrelationId::generate());
		foreach([
			[new Actor(999999),$this->scope,'A distinct authorized document must be protected.'],
			[$services['actor'],new OrgScope($this->scope->id+7),'A distinct authorized document must be protected.'],
			[$services['actor'],$this->scope,$original],
		] as [$actor,$scope,$content]) {
			try {
				$services['consents']->publish($actor,$scope,$definition,$content,$now,CorrelationId::generate());
				self::fail('Unauthorized or duplicate consent version was accepted');
			} catch(RuntimeException) { self::assertTrue(true); }
		}
		foreach(['short','<script>alert(1)</script>',''] as $invalid) {
			try {
				$services['consents']->publish($services['actor'],$this->scope,$definition,$invalid,$now,CorrelationId::generate());
				self::fail('Malformed consent document was accepted');
			} catch(InvalidArgumentException) { self::assertTrue(true); }
		}
		self::assertCount(1,$this->db->rows('SELECT id FROM %i',[$this->prefix.'consent_versions']));
	}

	/** Atomic outbox failure also rolls back the immutable version and pointer. */
	public function test_document_publication_rolls_back_when_outbox_is_broken(): void {
		$services=$this->services();
		$now='2030-01-02 10:00:00';
		$definition=$services['consents']->create($services['actor'],$this->scope,'publicity','Use in publicity',$now,CorrelationId::generate());
		$this->db->execute('DROP TABLE %i',[$this->prefix.'domain_events']);
		try {
			$services['consents']->publish($services['actor'],$this->scope,$definition,'This is the full consent notice shown to the eligible participant.',$now,CorrelationId::generate());
			self::fail('Published a version without a durable outbox');
		} catch(\Throwable $error) {
			self::assertNotInstanceOf(\PHPUnit\Framework\AssertionFailedError::class,$error);
		}
		self::assertSame([],$this->db->rows('SELECT id FROM %i',[$this->prefix.'consent_versions']));
		$root=$this->db->rows('SELECT status,current_version_id FROM %i WHERE organization_id = %d AND public_id = %s',[$this->prefix.'consent_definitions',$this->scope->id,$definition->to_binary()])[0];
		self::assertSame('draft',$root['status']);
		self::assertNull($root['current_version_id']);
	}
}
