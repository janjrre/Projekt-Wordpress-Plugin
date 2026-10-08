<?php
namespace UOP\Tests\Integration;

use PHPUnit\Framework\TestCase;
use InvalidArgumentException;
use RuntimeException;
use UOP\Application\Policy\{Actor, PolicyService};
use UOP\Application\Event\EventService;
use UOP\Application\Form\FormService;
use UOP\Application\Registration\{RegistrationService, RegistrationConfigurationService};
use UOP\Core\{CorrelationId, PublicId, TransactionManager};
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\{AssignmentRepository, AuditWriter, DelegationRepository, EventRepository, FormRepository, Installer, OccurrenceRepository, OutboxRepository, PersonRepository, RegistrationRepository, SchemaManifest, WpdbConnection};

final class M4RegistrationTest extends TestCase {
	private WpdbConnection $db;
	private OrgScope $scope;
	private string $prefix;

	protected function setUp(): void {
		global $wpdb;
		$this->db = new WpdbConnection($wpdb);
		$this->prefix = $wpdb->prefix . 'uop_';
		$manifest = new SchemaManifest(dirname(__DIR__,2).'/schema/manifest.json');
		foreach (array_keys($manifest->tables()) as $table) {
			$this->db->execute('DROP TABLE IF EXISTS %i',[$this->prefix.$table]);
		}
		foreach (array('uop_db_version','uop_data_version','uop_migration_progress_1','uop_migration_status','uop_default_organization_id') as $key) delete_option($key);
		Installer::runner()->run();
		$this->scope = new OrgScope((int)get_option('uop_default_organization_id'));
	}

	private function fixture(): array {
		if (!post_type_exists('uop_event')) register_post_type('uop_event',array('public'=>true));
		$user_id = wp_create_user('uop_m4_'.bin2hex(random_bytes(4)),wp_generate_password(24),'m4_'.bin2hex(random_bytes(4)).'@example.invalid');
		self::assertIsInt($user_id);
		$user = new \WP_User($user_id);
		$user->set_role('administrator');
		wp_set_current_user($user_id);
		$actor = new Actor($user_id);
		$people = new PersonRepository($this->db,$this->prefix);
		$policy = new PolicyService($people,new DelegationRepository($this->db,$this->prefix),new AssignmentRepository($this->db,$this->prefix),static fn(int $id,string $cap): bool => $id === $user_id);
		$tx = new TransactionManager($this->db,static function(int $n): void {},static function(\Throwable $e): void {});
		$audit = new AuditWriter($this->db,$this->prefix);
		$outbox = new OutboxRepository($this->db,$this->prefix);
		return [
			'actor'=>$actor,
			'people'=>$people,
			'form'=>new FormService(new FormRepository($this->db,$this->prefix),$policy,$tx,$audit,$outbox),
			'event'=>new EventService(new EventRepository($this->db,$this->prefix),new OccurrenceRepository($this->db,$this->prefix),$policy,$tx,$audit,$outbox),
			'config'=>new RegistrationConfigurationService($this->db,$this->prefix,$policy,$tx,$audit,$outbox),
			'submit'=>new RegistrationService(new RegistrationRepository($this->db,$this->prefix),$policy,$tx,$audit,$outbox),
		];
	}

	private function setup_registration(array $draft): array {
		$s = $this->fixture();
		$now = '2030-01-02 10:00:00';
		$post_id = wp_insert_post(array('post_type'=>'uop_event','post_title'=>'Open Event','post_status'=>'publish'),true);
		self::assertIsInt($post_id);
		$event = $s['event']->configure($s['actor'],$this->scope,$post_id,'Europe/Berlin',$now,CorrelationId::generate());
		$form = $s['form']->create($s['actor'],$this->scope,'entry_form','Entry Form','event',$draft,$now,CorrelationId::generate());
		$s['form']->publish($s['actor'],$this->scope,$form,1,$now,CorrelationId::generate());
		$s['config']->bind($s['actor'],$this->scope,$event,$form,$now,CorrelationId::generate());
		$person = PublicId::generate();
		$s['people']->create($this->scope,$person,'Attendee',null,$now);
		return [$s,$person,$event,$form,$now];
	}

	public function test_idempotent_submission_snapshots_and_immutable_query_values(): void {
		$draft = ['schema_version'=>1,'fields'=>[
			['key'=>'name','type'=>'text','label'=>'Name','required'=>true],
			['key'=>'extras','type'=>'multiselect','label'=>'Extras','required'=>false,'options'=>['food','music']],
			['key'=>'adult','type'=>'checkbox','label'=>'Adult','required'=>false],
		]];
		[$s,$person,$event,$form,$now]=$this->setup_registration($draft);
		$key=PublicId::generate();
		$input=['name'=>'A Guest','extras'=>['music','food'],'adult'=>false];
		$id=$s['submit']->submit($s['actor'],$this->scope,$person,$event,null,$key,$input,$now,CorrelationId::generate());
		$again=$s['submit']->submit($s['actor'],$this->scope,$person,$event,null,$key,$input,$now,CorrelationId::generate());
		self::assertSame($id->to_string(),$again->to_string());
		$rows=$this->db->rows('SELECT id, current_snapshot_id, status, email_verified_at FROM %i WHERE organization_id = %d',[$this->prefix.'registrations',$this->scope->id]);
		self::assertCount(1,$rows);
		self::assertSame('submitted',$rows[0]['status']);
		self::assertNull($rows[0]['email_verified_at']);
		$snapshot=$this->db->rows('SELECT payload_json, payload_hash FROM %i WHERE registration_id = %d',[$this->prefix.'registration_snapshots',(int)$rows[0]['id']]);
		self::assertCount(1,$snapshot);
		self::assertSame(hash('sha256',$snapshot[0]['payload_json'],true),$snapshot[0]['payload_hash']);
		$data=json_decode($snapshot[0]['payload_json'],true);
		self::assertSame($input,$data['fields']);
		$values=$this->db->rows('SELECT field_key, ordinal, value_string, value_boolean FROM %i WHERE registration_id = %d ORDER BY field_key, ordinal',[$this->prefix.'registration_values',(int)$rows[0]['id']]);
		self::assertCount(4,$values);
		self::assertSame(0,(int)array_values(array_filter($values,static fn($r)=>$r['field_key']==='adult'))[0]['value_boolean']);
		self::assertSame([0,1],array_map(static fn($r)=>(int)$r['ordinal'],array_values(array_filter($values,static fn($r)=>$r['field_key']==='extras'))));
		$hist=$this->db->rows('SELECT id FROM %i WHERE registration_id = %d',[$this->prefix.'registration_history',(int)$rows[0]['id']]);
		self::assertCount(1,$hist);
		$out=$this->db->rows('SELECT id FROM %i WHERE aggregate_type = %s AND event_name = %s',[$this->prefix.'domain_events','registration','registration.submitted']);
		self::assertCount(1,$out);
	}

	public function test_rejects_hidden_values_missing_required_and_cross_org_subject(): void {
		$condition=['schema_version'=>1,'all'=>[['source'=>'registration','field'=>'mode','operator'=>'eq','value'=>'extra']]];
		$draft=['schema_version'=>1,'fields'=>[
			['key'=>'mode','type'=>'select','label'=>'Mode','required'=>true,'options'=>['basic','extra']],
			['key'=>'note','type'=>'text','label'=>'Note','required'=>true,'visible_when'=>$condition],
		]];
		[$s,$person,$event,$form,$now]=$this->setup_registration($draft);
		$cases=[
			['mode'=>'basic','note'=>'hidden'],
			['mode'=>'extra'],
			['mode'=>'unknown'],
			['mode'=>'basic','unlisted'=>'x'],
		];
		foreach ($cases as $input) {
			try {
				$s['submit']->submit($s['actor'],$this->scope,$person,$event,null,PublicId::generate(),$input,$now,CorrelationId::generate());
				self::fail('Invalid input accepted');
			} catch (RuntimeException | InvalidArgumentException) {
				self::assertTrue(true);
			}
		}
		try {
			$s['submit']->submit($s['actor'],new OrgScope($this->scope->id+10),$person,$event,null,PublicId::generate(),['mode'=>'basic'],$now,CorrelationId::generate());
			self::fail('Cross-organization submission accepted');
		} catch (RuntimeException) {
			self::assertTrue(true);
		}
		$rows=$this->db->rows('SELECT id FROM %i',[$this->prefix.'registrations']);
		self::assertSame([],$rows);
		$key=PublicId::generate();
		$s['submit']->submit($s['actor'],$this->scope,$person,$event,null,$key,['mode'=>'basic'],$now,CorrelationId::generate());
		try {
			$s['submit']->submit($s['actor'],$this->scope,$person,$event,null,PublicId::generate(),['mode'=>'basic'],$now,CorrelationId::generate());
			self::fail('Duplicate active registration permitted');
		} catch (RuntimeException) {
			self::assertTrue(true);
		}
	}
}
