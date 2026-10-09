<?php
namespace UOP\Tests\Integration;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use UOP\Application\Consent\{ConsentDefinitionService, ConsentRecordService};
use UOP\Application\Event\EventService;
use UOP\Application\Form\FormService;
use UOP\Application\Policy\{Actor, PolicyService};
use UOP\Application\Registration\{RegistrationConfigurationService, RegistrationFactsService, RegistrationService};
use UOP\Core\{CorrelationId, PublicId, TransactionManager};
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\{AssignmentRepository, AuditWriter, ConsentRecordRepository, ConsentRepository, DelegationRepository, EventRepository, FormRepository, Installer, OccurrenceRepository, OutboxRepository, PersonRepository, RegistrationFactsRepository, RegistrationRepository, RelationshipRepository, SchemaManifest, WpdbConnection};

final class M5ConsentRecordsTest extends TestCase {
	private WpdbConnection $db;
	private OrgScope $scope;
	private string $prefix;

	protected function setUp(): void {
		global $wpdb;
		$this->db=new WpdbConnection($wpdb);
		$this->prefix=$wpdb->prefix.'uop_';
		$manifest=new SchemaManifest(dirname(__DIR__,2).'/schema/manifest.json');
		foreach(array_keys($manifest->tables()) as $table) $this->db->execute('DROP TABLE IF EXISTS %i',[$this->prefix.$table]);
		foreach(['uop_db_version','uop_data_version','uop_migration_progress_1','uop_migration_status','uop_default_organization_id'] as $key) delete_option($key);
		Installer::runner()->run();
		$this->scope=new OrgScope((int)get_option('uop_default_organization_id'));
	}

	private function fixture(): array {
		if(!post_type_exists('uop_event')) register_post_type('uop_event',['public'=>true]);
		$id=wp_create_user('uop_m5records_'.bin2hex(random_bytes(4)),wp_generate_password(24),'m5record_'.bin2hex(random_bytes(4)).'@example.invalid');
		self::assertIsInt($id);
		(new \WP_User($id))->set_role('administrator');
		wp_set_current_user($id);
		$actor=new Actor($id);
		$people=new PersonRepository($this->db,$this->prefix);
		$delegations=new DelegationRepository($this->db,$this->prefix);
		$relations=new RelationshipRepository($this->db,$this->prefix);
		$policy=new PolicyService($people,$delegations,new AssignmentRepository($this->db,$this->prefix),static fn(int $user,string $cap): bool=>$user===$id);
		$tx=new TransactionManager($this->db,static function(int $delay): void {},static function(\Throwable $error): void {});
		$audit=new AuditWriter($this->db,$this->prefix);
		$outbox=new OutboxRepository($this->db,$this->prefix);
		$records=new ConsentRecordRepository($this->db,$this->prefix);
		$consent=new ConsentRecordService($records,new ConsentRepository($this->db,$this->prefix),$policy,$tx,$audit,$outbox);
		return [
			'actor'=>$actor,'people'=>$people,'delegations'=>$delegations,'relations'=>$relations,'records'=>$records,
			'consent'=>$consent,
			'definitions'=>new ConsentDefinitionService(new ConsentRepository($this->db,$this->prefix),$policy,$tx,$audit,$outbox),
			'event'=>new EventService(new EventRepository($this->db,$this->prefix),new OccurrenceRepository($this->db,$this->prefix),$policy,$tx,$audit,$outbox),
			'form'=>new FormService(new FormRepository($this->db,$this->prefix),$policy,$tx,$audit,$outbox),
			'config'=>new RegistrationConfigurationService($this->db,$this->prefix,$policy,$tx,$audit,$outbox),
			'submit'=>new RegistrationService(new RegistrationRepository($this->db,$this->prefix),$policy,$tx,$audit,$outbox,new RegistrationFactsService(new RegistrationFactsRepository($this->db,$this->prefix),$policy),$people,$consent),
		];
	}

	private function prepared(): array {
		$s=$this->fixture();
		$now='2030-01-02 10:00:00';
		$portrait=$s['definitions']->create($s['actor'],$this->scope,'portrait','Portrait release',$now,CorrelationId::generate());
		$newsletter=$s['definitions']->create($s['actor'],$this->scope,'newsletter','Newsletter permission',$now,CorrelationId::generate());
		$s['definitions']->publish($s['actor'],$this->scope,$portrait,'I allow this portrait to be used in the programme and event media.',$now,CorrelationId::generate());
		$s['definitions']->publish($s['actor'],$this->scope,$newsletter,'I optionally agree to receive updates about future events.',$now,CorrelationId::generate());
		$draft=['schema_version'=>1,'fields'=>[
			['key'=>'name','type'=>'text','label'=>'Name','required'=>true],
			['key'=>'portrait','type'=>'consent','label'=>'Portrait consent','required'=>true,'consent_definition_public_id'=>$portrait->to_string()],
			['key'=>'newsletter','type'=>'consent','label'=>'News consent','required'=>false,'consent_definition_public_id'=>$newsletter->to_string()],
		]];
		$post=wp_insert_post(['post_type'=>'uop_event','post_title'=>'Consents','post_status'=>'publish'],true);
		self::assertIsInt($post);
		$event=$s['event']->configure($s['actor'],$this->scope,$post,'Europe/Berlin',$now,CorrelationId::generate());
		$form=$s['form']->create($s['actor'],$this->scope,'consent_event','Consented event','event',$draft,$now,CorrelationId::generate());
		$s['form']->publish($s['actor'],$this->scope,$form,1,$now,CorrelationId::generate());
		$s['config']->bind($s['actor'],$this->scope,$event,$form,$now,CorrelationId::generate());
		$person=PublicId::generate();
		$s['people']->create($this->scope,$person,'Attendee',null,$now);
		return [$s,$event,$person,$now];
	}

	/** A reference is the only snapshot/typed registration value for consent. */
	public function test_consent_submission_creates_atomic_versioned_evidence_and_replay(): void {
		[$s,$event,$person,$now]=$this->prepared();
		$key=PublicId::generate();
		$input=['name'=>'Anna','portrait'=>true,'newsletter'=>false];
		$registration=$s['submit']->submit($s['actor'],$this->scope,$person,$event,null,$key,$input,$now,CorrelationId::generate());
		$again=$s['submit']->submit($s['actor'],$this->scope,$person,$event,null,$key,$input,$now,CorrelationId::generate());
		self::assertSame($registration->to_string(),$again->to_string());
		$row=$this->db->rows('SELECT id,person_id FROM %i WHERE organization_id = %d AND public_id = %s',[$this->prefix.'registrations',$this->scope->id,$registration->to_binary()])[0];
		$records=$this->db->rows('SELECT id,public_id,decision,actor_user_id,subject_person_id,registration_id,auth_context FROM %i WHERE organization_id = %d ORDER BY id',[$this->prefix.'consent_records',$this->scope->id]);
		self::assertCount(2,$records);
		self::assertSame(['granted','denied'],array_column($records,'decision'));
		self::assertSame(['manager','manager'],array_column($records,'auth_context'));
		self::assertSame((int)$row['person_id'],(int)$records[0]['subject_person_id']);
		self::assertSame($s['actor']->user_id,(int)$records[0]['actor_user_id']);
		$regrepo=new RegistrationRepository($this->db,$this->prefix);
		$fields=$regrepo->snapshot_fields($this->scope,(int)$row['id']);
		self::assertSame('Anna',$fields['name']);
		self::assertSame('granted',$fields['portrait']['decision']);
		self::assertSame('denied',$fields['newsletter']['decision']);
		self::assertSame('portrait',$fields['portrait']['definition_key']);
		self::assertSame(1,$fields['portrait']['version']);
		self::assertArrayNotHasKey('value_boolean',$fields['portrait']);
		self::assertSame(PublicId::from_binary($records[0]['public_id'])->to_string(),$fields['portrait']['record_public_id']);
		$typed=$this->db->rows('SELECT field_key,data_type,value_boolean,value_reference FROM %i WHERE registration_id = %d ORDER BY id',[$this->prefix.'registration_values',(int)$row['id']]);
		self::assertSame(['name','portrait','newsletter'],array_column($typed,'field_key'));
		self::assertSame('consent',$typed[1]['data_type']);
		self::assertNull($typed[1]['value_boolean']);
		self::assertSame((int)$records[0]['id'],(int)$typed[1]['value_reference']);
		self::assertSame((int)$records[1]['id'],(int)$typed[2]['value_reference']);
	}

	/** Withdrawals supersede without altering registered consent decisions. */
	public function test_withdrawal_is_audited_append_only_and_does_not_rewrite_snapshot(): void {
		[$s,$event,$person,$now]=$this->prepared();
		$registration=$s['submit']->submit($s['actor'],$this->scope,$person,$event,null,PublicId::generate(),['name'=>'Alex','portrait'=>true,'newsletter'=>true],$now,CorrelationId::generate());
		$reg=$this->db->rows('SELECT id FROM %i WHERE public_id = %s',[$this->prefix.'registrations',$registration->to_binary()])[0];
		$historic=(new RegistrationRepository($this->db,$this->prefix))->snapshot_fields($this->scope,(int)$reg['id']);
		$original=PublicId::from_string($historic['portrait']['record_public_id']);
		$withdrawal=PublicId::generate();
		self::assertSame($withdrawal->to_string(),$s['consent']->withdraw($s['actor'],$this->scope,$original,$withdrawal,$now,CorrelationId::generate())->to_string());
		self::assertSame($withdrawal->to_string(),$s['consent']->withdraw($s['actor'],$this->scope,$original,$withdrawal,$now,CorrelationId::generate())->to_string());
		$created=$s['records']->find($this->scope,$withdrawal);
		$base=$s['records']->find($this->scope,$original);
		self::assertSame('granted',$base['decision']);
		self::assertSame('withdrawn',$created['decision']);
		self::assertSame((int)$base['id'],(int)$created['supersedes_record_id']);
		self::assertSame($s['actor']->user_id,(int)$created['actor_user_id']);
		self::assertSame($historic,(new RegistrationRepository($this->db,$this->prefix))->snapshot_fields($this->scope,(int)$reg['id']));
		try {
			$s['consent']->withdraw($s['actor'],$this->scope,$original,PublicId::generate(),$now,CorrelationId::generate());
			self::fail('Conflicting second withdrawal succeeded');
		} catch(RuntimeException) { self::assertTrue(true); }
		try {
			$s['consent']->withdraw(new Actor(999999),$this->scope,$original,PublicId::generate(),$now,CorrelationId::generate());
			self::fail('Unrelated person withdrew consent');
		} catch(RuntimeException) { self::assertTrue(true); }
		self::assertNull($s['records']->find(new OrgScope($this->scope->id+9),$withdrawal));
		self::assertSame(3,(int)$this->db->rows('SELECT COUNT(*) AS n FROM %i',[$this->prefix.'consent_records'])[0]['n']);
		$events=$this->db->rows("SELECT event_name FROM %i WHERE event_name='consent.withdrawn'",[$this->prefix.'domain_events']);
		self::assertCount(1,$events);
	}

	/** Guardian submits their own decision while the minor remains the subject. */
	public function test_guardian_actor_is_distinct_and_revoke_blocks_new_decisions(): void {
		[$s,$event,$child,$now]=$this->prepared();
		$parent=PublicId::generate();
		$s['people']->create($this->scope,$parent,'Parent',null,$now);
		$guardian=wp_create_user('uop_guardian_'.bin2hex(random_bytes(4)),wp_generate_password(24),'guardian_'.bin2hex(random_bytes(4)).'@example.invalid');
		self::assertIsInt($guardian);
		self::assertTrue($s['people']->link($this->scope,$parent,$guardian,$now));
		$parent_row=$s['people']->find($this->scope,$parent);
		$child_row=$s['people']->find($this->scope,$child);
		$relation=PublicId::generate();
		$s['relations']->create($this->scope,$relation,(int)$parent_row['id'],(int)$child_row['id'],'guardian_of',$now);
		$grant=PublicId::generate();
		$s['delegations']->grant($this->scope,$grant,$guardian,(int)$child_row['id'],'registration_manage','organization',0,(int)$s['relations']->find($this->scope,$relation)['id'],$now);
		$actor=new Actor($guardian);
		$registration=$s['submit']->submit($actor,$this->scope,$child,$event,null,PublicId::generate(),['name'=>'Child','portrait'=>true],$now,CorrelationId::generate());
		$record=$this->db->rows("SELECT public_id,actor_user_id,subject_person_id,auth_context FROM %i WHERE decision='granted' LIMIT 1",[$this->prefix.'consent_records'])[0];
		self::assertSame($guardian,(int)$record['actor_user_id']);
		self::assertSame((int)$child_row['id'],(int)$record['subject_person_id']);
		self::assertSame('delegate',$record['auth_context']);
		$s['delegations']->revoke($this->scope,$grant,$now);
		try {
			$s['consent']->withdraw($actor,$this->scope,PublicId::from_binary($record['public_id']),PublicId::generate(),$now,CorrelationId::generate());
			self::fail('Revoked guardian withdrew evidence');
		} catch(RuntimeException) { self::assertTrue(true); }
		self::assertSame('submitted',$this->db->rows('SELECT status FROM %i WHERE public_id = %s',[$this->prefix.'registrations',$registration->to_binary()])[0]['status']);
		self::assertCount(1,$this->db->rows('SELECT id FROM %i',[$this->prefix.'consent_records']));
	}

	/** Required and malformed decisions cannot create orphan registrations. */
	public function test_invalid_required_decisions_and_outbox_failures_roll_back(): void {
		[$s,$event,$person,$now]=$this->prepared();
		foreach([
			['name'=>'No agreement','portrait'=>false],
			['name'=>'Fake evidence','portrait'=>['decision'=>'granted']],
			['name'=>'Missing agreement'],
		] as $input) {
			try {
				$s['submit']->submit($s['actor'],$this->scope,$person,$event,null,PublicId::generate(),$input,$now,CorrelationId::generate());
				self::fail('Invalid consent accepted');
			} catch(InvalidArgumentException) { self::assertTrue(true); }
		}
		self::assertSame([],$this->db->rows('SELECT id FROM %i',[$this->prefix.'registrations']));
		self::assertSame([],$this->db->rows('SELECT id FROM %i',[$this->prefix.'consent_records']));
		$this->db->execute('DROP TABLE %i',[$this->prefix.'domain_events']);
		try {
			$s['submit']->submit($s['actor'],$this->scope,$person,$event,null,PublicId::generate(),['name'=>'Should rollback','portrait'=>true],$now,CorrelationId::generate());
			self::fail('Registration persisted without durable consent outbox');
		} catch(\Throwable $error) {
			self::assertNotInstanceOf(\PHPUnit\Framework\AssertionFailedError::class,$error);
		}
		self::assertSame([],$this->db->rows('SELECT id FROM %i',[$this->prefix.'registrations']));
		self::assertSame([],$this->db->rows('SELECT id FROM %i',[$this->prefix.'consent_records']));
	}
}
