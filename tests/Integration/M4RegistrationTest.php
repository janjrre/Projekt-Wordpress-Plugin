<?php
namespace UOP\Tests\Integration;

use PHPUnit\Framework\TestCase;
use InvalidArgumentException;
use RuntimeException;
use UOP\Application\Policy\{Actor, PolicyService};
use UOP\Application\Event\EventService;
use UOP\Application\Form\FormService;
use UOP\Application\Registration\{RegistrationService, RegistrationConfigurationService, RegistrationTransitionService, CapacityAllocationService, CapacityLifecycleService, EventCancellationService, EmailVerificationService};
use UOP\Domain\Registrations\RegistrationStateMachine;
use UOP\Core\{CorrelationId, PublicId, TransactionManager};
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\{AssignmentRepository, AuditWriter, CapacityRepository, DelegationRepository, EventRepository, FormRepository, Installer, OccurrenceRepository, OutboxRepository, PersonRepository, RegistrationRepository, WaitlistRepository, EventCancellationRepository, SchemaManifest, WpdbConnection};

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
			'verification'=>new EmailVerificationService(new RegistrationRepository($this->db,$this->prefix),$policy,$tx,$audit,$outbox),
			'lifecycle'=>new CapacityLifecycleService(new WaitlistRepository($this->db,$this->prefix),new CapacityRepository($this->db,$this->prefix),new RegistrationStateMachine(),$policy,$tx,$audit,$outbox),
			'cancel_event'=>new EventCancellationService(new EventCancellationRepository($this->db,$this->prefix),new RegistrationStateMachine(),$policy,$tx,$audit,$outbox),
			'capacity'=>new CapacityAllocationService(new CapacityRepository($this->db,$this->prefix),new RegistrationRepository($this->db,$this->prefix),new RegistrationStateMachine(),$policy,$tx,$audit,$outbox),
			'transition'=>new RegistrationTransitionService(new RegistrationRepository($this->db,$this->prefix),new RegistrationStateMachine(),$policy,$tx,$audit,$outbox),
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
		try {
			$s['submit']->submit($s['actor'],$this->scope,$person,$event,null,$key,['name'=>'Changed','extras'=>['music','food'],'adult'=>false],$now,CorrelationId::generate());
			self::fail('Reusing the key with a different body must be rejected');
		} catch (RuntimeException) {
			self::assertTrue(true);
		}
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
	public function test_review_and_rejection_are_idempotent_without_capacity_mutations(): void {
		$draft=['schema_version'=>1,'fields'=>[['key'=>'name','type'=>'text','label'=>'Name','required'=>true]]];
		[$s,$person,$event,$form,$now]=$this->setup_registration($draft);
		$uuid=$s['submit']->submit($s['actor'],$this->scope,$person,$event,null,PublicId::generate(),['name'=>'Member'],$now,CorrelationId::generate());
		$review=PublicId::generate();
		$s['transition']->transition($s['actor'],$this->scope,$uuid,'review',$review,$now,CorrelationId::generate());
		$s['transition']->transition($s['actor'],$this->scope,$uuid,'review',$review,$now,CorrelationId::generate());
		$reject=PublicId::generate();
		$s['transition']->transition($s['actor'],$this->scope,$uuid,'rejected',$reject,$now,CorrelationId::generate());
		$rows=$this->db->rows('SELECT id,status,version FROM %i WHERE organization_id = %d AND public_id = %s',[$this->prefix.'registrations',$this->scope->id,$uuid->to_binary()]);
		self::assertSame('rejected',$rows[0]['status']);
		self::assertSame(3,(int)$rows[0]['version']);
		$history=$this->db->rows('SELECT from_status,to_status FROM %i WHERE registration_id = %d ORDER BY id',[$this->prefix.'registration_history',(int)$rows[0]['id']]);
		self::assertSame(['submitted','review','rejected'],array_column($history,'to_status'));
		self::assertSame([], $this->db->rows('SELECT id FROM %i',[$this->prefix.'capacity_claims']));
		try {
			$s['transition']->transition($s['actor'],$this->scope,$uuid,'accepted',PublicId::generate(),$now,CorrelationId::generate());
			self::fail('Capacity-dependent target accepted');
		} catch (InvalidArgumentException) { self::assertTrue(true); }
		try {
			$s['transition']->transition($s['actor'],$this->scope,$uuid,'cancelled',PublicId::generate(),$now,CorrelationId::generate());
			self::fail('Terminal registration cancelled');
		} catch (RuntimeException) { self::assertTrue(true); }
	}

	public function test_last_available_seat_creates_one_claim_and_fifo_waitlist_entry(): void {
		$draft=['schema_version'=>1,'fields'=>[['key'=>'name','type'=>'text','label'=>'Name','required'=>true]]];
		[$s,$person,$event,$form,$now]=$this->setup_registration($draft);
		$other=PublicId::generate();
		$s['people']->create($this->scope,$other,'Second Person',null,$now);
		$first=$s['submit']->submit($s['actor'],$this->scope,$person,$event,null,PublicId::generate(),['name'=>'One'],$now,CorrelationId::generate());
		$second=$s['submit']->submit($s['actor'],$this->scope,$other,$event,null,PublicId::generate(),['name'=>'Two'],$now,CorrelationId::generate());
		$bucket=$s['capacity']->create_general_bucket($s['actor'],$this->scope,$event,1,$now,CorrelationId::generate());
		$command=PublicId::generate();
		self::assertSame('accepted',$s['capacity']->decide($s['actor'],$this->scope,$first,$bucket,$command,$now,CorrelationId::generate()));
		self::assertSame('accepted',$s['capacity']->decide($s['actor'],$this->scope,$first,$bucket,$command,$now,CorrelationId::generate()));
		self::assertSame('waitlisted',$s['capacity']->decide($s['actor'],$this->scope,$second,$bucket,PublicId::generate(),$now,CorrelationId::generate()));
		$actual_occupancy=(new CapacityRepository($this->db,$this->prefix))->occupied($this->scope,(int)$this->db->rows('SELECT id FROM %i WHERE public_id = %s',[$this->prefix.'capacity_buckets',$bucket->to_binary()])[0]['id']);
		self::assertSame(1,$actual_occupancy,'Occupied count must be scoped to the actual bucket, not the organization id');
		$claims=$this->db->rows('SELECT status,registration_id FROM %i',[$this->prefix.'capacity_claims']);
		self::assertCount(1,$claims);
		self::assertSame('confirmed',$claims[0]['status']);
		$queue=$this->db->rows('SELECT priority,status,joined_at FROM %i',[$this->prefix.'waitlist_entries']);
		self::assertCount(1,$queue);
		self::assertSame('waiting',$queue[0]['status']);
		$states=$this->db->rows('SELECT status FROM %i ORDER BY id',[$this->prefix.'registrations']);
		self::assertSame(['accepted','waitlisted'],array_column($states,'status'));
		$evidence=$this->db->rows('SELECT command_id FROM %i WHERE to_status IN (%s,%s)',[$this->prefix.'registration_history','accepted','waitlisted']);
		self::assertCount(2,$evidence);
		try {
			$s['capacity']->decide($s['actor'],$this->scope,$second,$bucket,PublicId::generate(),$now,CorrelationId::generate());
			self::fail('Waitlisted registration bypassed queue');
		} catch (RuntimeException) { self::assertTrue(true); }
	}

	public function test_capacity_cancel_and_private_waitlist_offer_acceptance(): void {
		$draft=['schema_version'=>1,'fields'=>[['key'=>'name','type'=>'text','label'=>'Name','required'=>true]]];
		[$s,$first_person,$event,$form,$now]=$this->setup_registration($draft);
		$second_person=PublicId::generate();
		$s['people']->create($this->scope,$second_person,'Waiting Guest',null,$now);
		$first=$s['submit']->submit($s['actor'],$this->scope,$first_person,$event,null,PublicId::generate(),['name'=>'First'],$now,CorrelationId::generate());
		$second=$s['submit']->submit($s['actor'],$this->scope,$second_person,$event,null,PublicId::generate(),['name'=>'Second'],$now,CorrelationId::generate());
		$bucket=$s['capacity']->create_general_bucket($s['actor'],$this->scope,$event,1,$now,CorrelationId::generate());
		self::assertSame('accepted',$s['capacity']->decide($s['actor'],$this->scope,$first,$bucket,PublicId::generate(),$now,CorrelationId::generate()));
		self::assertSame('waitlisted',$s['capacity']->decide($s['actor'],$this->scope,$second,$bucket,PublicId::generate(),$now,CorrelationId::generate()));
		$s['lifecycle']->cancel($s['actor'],$this->scope,$first,PublicId::generate(),$now,CorrelationId::generate());
		$offer=$s['lifecycle']->offer_next($s['actor'],$this->scope,$bucket,PublicId::generate(),$now,CorrelationId::generate());
		self::assertNotNull($offer);
		self::assertSame(64,strlen($offer['token']));
		$claim=$this->db->rows('SELECT status, expires_at FROM %i WHERE registration_id = %d',[$this->prefix.'capacity_claims',(int)$this->db->rows('SELECT id FROM %i WHERE public_id = %s',[$this->prefix.'registrations',$second->to_binary()])[0]['id']]);
		self::assertSame('held',$claim[0]['status']);
		try {
			$s['lifecycle']->accept_offer($s['actor'],$this->scope,PublicId::from_string($offer['public_id']),str_repeat('a',64),PublicId::generate(),$now,CorrelationId::generate());
			self::fail('Wrong bearer token accepted');
		} catch (RuntimeException) {
			self::assertTrue(true);
		}
		$s['lifecycle']->accept_offer($s['actor'],$this->scope,PublicId::from_string($offer['public_id']),$offer['token'],PublicId::generate(),$now,CorrelationId::generate());
		self::assertSame('confirmed',$this->db->rows('SELECT status FROM %i WHERE registration_id = %d',[$this->prefix.'capacity_claims',(int)$this->db->rows('SELECT id FROM %i WHERE public_id = %s',[$this->prefix.'registrations',$second->to_binary()])[0]['id']])[0]['status']);
		$states=$this->db->rows('SELECT status FROM %i ORDER BY id',[$this->prefix.'registrations']);
		self::assertSame(['cancelled','accepted'],array_column($states,'status'));
		$offer_entry=$this->db->rows('SELECT status,token_hash FROM %i WHERE public_id = %s',[$this->prefix.'waitlist_offers',PublicId::from_string($offer['public_id'])->to_binary()])[0];
		self::assertSame('accepted',$offer_entry['status']);
		self::assertNotSame($offer['token'],$offer_entry['token_hash']);
		$meta=$this->db->rows('SELECT payload_json FROM %i WHERE event_name = %s',[$this->prefix.'domain_events','registration.offered']);
		self::assertCount(1,$meta);
		self::assertStringNotContainsString($offer['token'],$meta[0]['payload_json']);
	}

	public function test_expired_offer_releases_and_reuses_exactly_one_claim(): void {
		$draft=['schema_version'=>1,'fields'=>[['key'=>'name','type'=>'text','label'=>'Name','required'=>true]]];
		[$s,$first_person,$event,$form,$now]=$this->setup_registration($draft);
		$second_person=PublicId::generate();
		$s['people']->create($this->scope,$second_person,'Waiting Guest',null,$now);
		$first=$s['submit']->submit($s['actor'],$this->scope,$first_person,$event,null,PublicId::generate(),['name'=>'First'],$now,CorrelationId::generate());
		$second=$s['submit']->submit($s['actor'],$this->scope,$second_person,$event,null,PublicId::generate(),['name'=>'Second'],$now,CorrelationId::generate());
		$bucket=$s['capacity']->create_general_bucket($s['actor'],$this->scope,$event,1,$now,CorrelationId::generate());
		$s['capacity']->decide($s['actor'],$this->scope,$first,$bucket,PublicId::generate(),$now,CorrelationId::generate());
		$s['capacity']->decide($s['actor'],$this->scope,$second,$bucket,PublicId::generate(),$now,CorrelationId::generate());
		$s['lifecycle']->cancel($s['actor'],$this->scope,$first,PublicId::generate(),$now,CorrelationId::generate());
		$old=$s['lifecycle']->offer_next($s['actor'],$this->scope,$bucket,PublicId::generate(),$now,CorrelationId::generate());
		self::assertNotNull($old);
		self::assertFalse($s['lifecycle']->expire_offer($s['actor'],$this->scope,PublicId::from_string($old['public_id']),PublicId::generate(),$now,CorrelationId::generate()));
		$later='2030-01-05 10:00:00';
		self::assertTrue($s['lifecycle']->expire_offer($s['actor'],$this->scope,PublicId::from_string($old['public_id']),PublicId::generate(),$later,CorrelationId::generate()));
		self::assertFalse($s['lifecycle']->expire_offer($s['actor'],$this->scope,PublicId::from_string($old['public_id']),PublicId::generate(),$later,CorrelationId::generate()));
		$new=$s['lifecycle']->offer_next($s['actor'],$this->scope,$bucket,PublicId::generate(),$later,CorrelationId::generate());
		self::assertNotNull($new);
		self::assertNotSame($old['public_id'],$new['public_id']);
		$claims=$this->db->rows('SELECT status FROM %i WHERE status IN (%s,%s)',[$this->prefix.'capacity_claims','held','confirmed']);
		self::assertCount(1,$claims);
		$offers=$this->db->rows('SELECT status FROM %i ORDER BY id',[$this->prefix.'waitlist_offers']);
		self::assertSame(['expired','offered'],array_column($offers,'status'));
		$s['lifecycle']->accept_offer($s['actor'],$this->scope,PublicId::from_string($new['public_id']),$new['token'],PublicId::generate(),$later,CorrelationId::generate());
		self::assertSame(1,(int)$this->db->rows('SELECT COUNT(*) AS n FROM %i WHERE status = %s',[$this->prefix.'capacity_claims','confirmed'])[0]['n']);
	}

	public function test_single_use_contact_verification_gates_capacity_without_linking_a_user(): void {
		$draft=['schema_version'=>1,'fields'=>[
			['key'=>'contact','type'=>'email','label'=>'Contact email','required'=>true],
			['key'=>'name','type'=>'text','label'=>'Name','required'=>true],
		]];
		[$s,$person,$event,$form,$now]=$this->setup_registration($draft);
		$this->db->execute(
			'UPDATE %i SET require_email_verification = 1 WHERE organization_id = %d AND public_id = %s',
			[$this->prefix.'event_settings',$this->scope->id,$event->to_binary()]
		);
		try {
			$s['submit']->submit($s['actor'],$this->scope,$person,$event,null,PublicId::generate(),['name'=>'No email'],$now,CorrelationId::generate());
			self::fail('Mandatory verification without email was accepted');
		} catch (InvalidArgumentException) { self::assertTrue(true); }
		$registration=$s['submit']->submit($s['actor'],$this->scope,$person,$event,null,PublicId::generate(),[
			'contact'=>'recipient@example.invalid','name'=>'Attendee'
		],$now,CorrelationId::generate());
		$bucket=$s['capacity']->create_general_bucket($s['actor'],$this->scope,$event,1,$now,CorrelationId::generate());
		try {
			$s['capacity']->decide($s['actor'],$this->scope,$registration,$bucket,PublicId::generate(),$now,CorrelationId::generate());
			self::fail('Capacity acceptance without verified contact was permitted');
		} catch (RuntimeException) { self::assertTrue(true); }
		$one=$s['verification']->issue($s['actor'],$this->scope,$registration,$now,CorrelationId::generate());
		self::assertSame('recipient@example.invalid',$one['email']);
		self::assertSame(64,strlen($one['token']));
		$two=$s['verification']->issue($s['actor'],$this->scope,$registration,$now,CorrelationId::generate());
		self::assertNotSame($one['token'],$two['token']);
		self::assertFalse($s['verification']->verify($this->scope,$registration,$one['token'],$now,CorrelationId::generate()));
		self::assertTrue($s['verification']->verify($this->scope,$registration,$two['token'],$now,CorrelationId::generate()));
		self::assertFalse($s['verification']->verify($this->scope,$registration,$two['token'],$now,CorrelationId::generate()));
		self::assertSame('accepted',$s['capacity']->decide($s['actor'],$this->scope,$registration,$bucket,PublicId::generate(),$now,CorrelationId::generate()));
		$row=$this->db->rows('SELECT contact_email, email_verification_token_hash, email_verified_at FROM %i WHERE public_id = %s',[$this->prefix.'registrations',$registration->to_binary()])[0];
		self::assertSame('recipient@example.invalid',$row['contact_email']);
		self::assertNull($row['email_verification_token_hash']);
		self::assertSame($now,$row['email_verified_at']);
		$owner=$this->db->rows('SELECT wp_user_id FROM %i WHERE public_id = %s',[$this->prefix.'persons',$person->to_binary()])[0];
		self::assertNull($owner['wp_user_id'],'A verified email may not silently link a WP user');
	}

	public function test_manager_cancellation_and_next_offer_share_one_atomic_capacity_boundary(): void {
		$draft=['schema_version'=>1,'fields'=>[['key'=>'name','type'=>'text','label'=>'Name','required'=>true]]];
		[$s,$first_person,$event,$form,$now]=$this->setup_registration($draft);
		$people=[$first_person];
		for ($i=1;$i<3;++$i) {
			$person=PublicId::generate();
			$s['people']->create($this->scope,$person,'FIFO Guest '.$i,null,$now);
			$people[]=$person;
		}
		$registrations=[];
		foreach ($people as $i=>$person) {
			$registrations[]=$s['submit']->submit($s['actor'],$this->scope,$person,$event,null,PublicId::generate(),['name'=>'Guest '.$i],$now,CorrelationId::generate());
		}
		$bucket=$s['capacity']->create_general_bucket($s['actor'],$this->scope,$event,1,$now,CorrelationId::generate());
		foreach ($registrations as $i=>$id) {
			self::assertSame(0===$i?'accepted':'waitlisted',$s['capacity']->decide($s['actor'],$this->scope,$id,$bucket,PublicId::generate(),$now,CorrelationId::generate()));
		}
		$command=PublicId::generate();
		$offer=$s['lifecycle']->cancel_and_offer_next($s['actor'],$this->scope,$registrations[0],$command,$now,CorrelationId::generate());
		self::assertNotNull($offer);
		self::assertSame(64,strlen($offer['token']));
		self::assertNull($s['lifecycle']->cancel_and_offer_next($s['actor'],$this->scope,$registrations[0],$command,$now,CorrelationId::generate()),'Replay must not grant another held seat');
		$states=$this->db->rows('SELECT status FROM %i ORDER BY id',[$this->prefix.'registrations']);
		self::assertSame(['cancelled','offered','waitlisted'],array_column($states,'status'));
		$claims=$this->db->rows("SELECT status FROM %i WHERE status IN ('confirmed','held')",[$this->prefix.'capacity_claims']);
		self::assertCount(1,$claims);
		self::assertSame('held',$claims[0]['status']);
		$offers=$this->db->rows('SELECT registration_id,token_hash FROM %i',[$this->prefix.'waitlist_offers']);
		self::assertCount(1,$offers);
		$second_id=$this->db->rows('SELECT id FROM %i WHERE public_id = %s',[$this->prefix.'registrations',$registrations[1]->to_binary()]);
		self::assertSame((int)$second_id[0]['id'],(int)$offers[0]['registration_id'],'Oldest waiting applicant must be selected');
		self::assertNotSame($offer['token'],$offers[0]['token_hash']);
		$history=$this->db->rows("SELECT to_status FROM %i WHERE to_status IN ('cancelled','offered')",[$this->prefix.'registration_history']);
		self::assertSame(['cancelled','offered'],array_column($history,'to_status'));
		$events=$this->db->rows('SELECT event_name,payload_json FROM %i WHERE event_name IN (%s,%s) ORDER BY id',[$this->prefix.'domain_events','registration.cancelled','registration.offered']);
		self::assertSame(['registration.cancelled','registration.offered'],array_column($events,'event_name'));
		foreach ($events as $row) {
			self::assertStringNotContainsString($offer['token'],$row['payload_json']);
		}
		$s['lifecycle']->accept_offer($s['actor'],$this->scope,PublicId::from_string($offer['public_id']),$offer['token'],PublicId::generate(),$now,CorrelationId::generate());
		$occupied=$this->db->rows("SELECT COUNT(*) AS n FROM %i WHERE status IN ('held','confirmed')",[$this->prefix.'capacity_claims']);
		self::assertSame(1,(int)$occupied[0]['n']);
	}

	public function test_atomic_manager_cancellation_rolls_back_when_next_waiter_cannot_be_verified(): void {
		$draft=['schema_version'=>1,'fields'=>[['key'=>'name','type'=>'text','label'=>'Name','required'=>true]]];
		[$s,$first_person,$event,$form,$now]=$this->setup_registration($draft);
		$second_person=PublicId::generate();
		$s['people']->create($this->scope,$second_person,'Unverified Applicant',null,$now);
		$first=$s['submit']->submit($s['actor'],$this->scope,$first_person,$event,null,PublicId::generate(),['name'=>'First'],$now,CorrelationId::generate());
		$second=$s['submit']->submit($s['actor'],$this->scope,$second_person,$event,null,PublicId::generate(),['name'=>'Second'],$now,CorrelationId::generate());
		$bucket=$s['capacity']->create_general_bucket($s['actor'],$this->scope,$event,1,$now,CorrelationId::generate());
		$s['capacity']->decide($s['actor'],$this->scope,$first,$bucket,PublicId::generate(),$now,CorrelationId::generate());
		$s['capacity']->decide($s['actor'],$this->scope,$second,$bucket,PublicId::generate(),$now,CorrelationId::generate());
		$this->db->execute('UPDATE %i SET require_email_verification = 1 WHERE public_id = %s',[$this->prefix.'event_settings',$event->to_binary()]);
		try {
			$s['lifecycle']->cancel_and_offer_next($s['actor'],$this->scope,$first,PublicId::generate(),$now,CorrelationId::generate());
			self::fail('Unverifiable FIFO offer was allowed');
		} catch (RuntimeException) {
			self::assertTrue(true);
		}
		$states=$this->db->rows('SELECT status FROM %i ORDER BY id',[$this->prefix.'registrations']);
		self::assertSame(['accepted','waitlisted'],array_column($states,'status'),'Cancellation must roll back with failed offer');
		self::assertSame(1,(int)$this->db->rows("SELECT COUNT(*) AS n FROM %i WHERE status = 'confirmed'",[$this->prefix.'capacity_claims'])[0]['n']);
		self::assertSame([],$this->db->rows('SELECT id FROM %i',[$this->prefix.'waitlist_offers']));
		self::assertSame([],$this->db->rows("SELECT id FROM %i WHERE event_name = 'registration.cancelled'",[$this->prefix.'domain_events']));
	}


	public function test_offered_seat_cannot_be_accepted_when_event_is_closed_or_unpublished(): void {
		$draft=['schema_version'=>1,'fields'=>[['key'=>'name','type'=>'text','label'=>'Name','required'=>true]]];
		[$s,$person,$event,$form,$now]=$this->setup_registration($draft);
		$waiting_person=PublicId::generate();
		$s['people']->create($this->scope,$waiting_person,'Waitlisted guest',null,$now);
		$first=$s['submit']->submit($s['actor'],$this->scope,$person,$event,null,PublicId::generate(),['name'=>'First'],$now,CorrelationId::generate());
		$second=$s['submit']->submit($s['actor'],$this->scope,$waiting_person,$event,null,PublicId::generate(),['name'=>'Second'],$now,CorrelationId::generate());
		$bucket=$s['capacity']->create_general_bucket($s['actor'],$this->scope,$event,1,$now,CorrelationId::generate());
		$s['capacity']->decide($s['actor'],$this->scope,$first,$bucket,PublicId::generate(),$now,CorrelationId::generate());
		$s['capacity']->decide($s['actor'],$this->scope,$second,$bucket,PublicId::generate(),$now,CorrelationId::generate());
		$s['lifecycle']->cancel($s['actor'],$this->scope,$first,PublicId::generate(),$now,CorrelationId::generate());
		$offer=$s['lifecycle']->offer_next($s['actor'],$this->scope,$bucket,PublicId::generate(),$now,CorrelationId::generate());
		self::assertNotNull($offer);
		$offer_id=PublicId::from_string($offer['public_id']);
		$this->db->execute("UPDATE %i SET status = 'cancelled' WHERE organization_id = %d AND public_id = %s",[$this->prefix.'event_settings',$this->scope->id,$event->to_binary()]);
		try {
			$s['lifecycle']->accept_offer($s['actor'],$this->scope,$offer_id,$offer['token'],PublicId::generate(),$now,CorrelationId::generate());
			self::fail('Closed event accepted an outstanding waitlist offer');
		} catch (RuntimeException) {
			self::assertTrue(true);
		}
		$this->db->execute("UPDATE %i SET status = 'active' WHERE organization_id = %d AND public_id = %s",[$this->prefix.'event_settings',$this->scope->id,$event->to_binary()]);
		$event_post=(int)$this->db->rows('SELECT event_post_id FROM %i WHERE public_id = %s',[$this->prefix.'event_settings',$event->to_binary()])[0]['event_post_id'];
		wp_update_post(['ID'=>$event_post,'post_status'=>'draft']);
		try {
			$s['lifecycle']->accept_offer($s['actor'],$this->scope,$offer_id,$offer['token'],PublicId::generate(),$now,CorrelationId::generate());
			self::fail('Unpublished event accepted an outstanding waitlist offer');
		} catch (RuntimeException) {
			self::assertTrue(true);
		}
		wp_update_post(['ID'=>$event_post,'post_status'=>'publish']);
		$this->db->execute('UPDATE %i SET require_email_verification = 1 WHERE organization_id = %d AND public_id = %s',[$this->prefix.'event_settings',$this->scope->id,$event->to_binary()]);
		try {
			$s['lifecycle']->accept_offer($s['actor'],$this->scope,$offer_id,$offer['token'],PublicId::generate(),$now,CorrelationId::generate());
			self::fail('Newly required email verification was bypassed');
		} catch (RuntimeException) {
			self::assertTrue(true);
		}
		$this->db->execute('UPDATE %i SET require_email_verification = 0 WHERE organization_id = %d AND public_id = %s',[$this->prefix.'event_settings',$this->scope->id,$event->to_binary()]);
		$still_waiting=$this->db->rows('SELECT status FROM %i WHERE public_id = %s',[$this->prefix.'registrations',$second->to_binary()])[0];
		self::assertSame('offered',$still_waiting['status'],'Closed-event rejections must not mutate registration state');
		self::assertSame('held',$this->db->rows('SELECT status FROM %i WHERE registration_id = (SELECT id FROM %i WHERE public_id = %s)',[$this->prefix.'capacity_claims',$this->prefix.'registrations',$second->to_binary()])[0]['status']);
		$s['lifecycle']->accept_offer($s['actor'],$this->scope,$offer_id,$offer['token'],PublicId::generate(),$now,CorrelationId::generate());
		self::assertSame('accepted',$this->db->rows('SELECT status FROM %i WHERE public_id = %s',[$this->prefix.'registrations',$second->to_binary()])[0]['status']);
	}

	/**
	 * Cancellation closes every state and real held/confirmed claim while keeping
	 * all original snapshots and unrelated registrations intact.
	 */
	public function test_event_cancellation_releases_offers_claims_and_emits_per_recipient_events(): void {
		$draft=['schema_version'=>1,'fields'=>[['key'=>'name','type'=>'text','label'=>'Name','required'=>true]]];
		[$s,$person,$event,$form,$now]=$this->setup_registration($draft);
		$people=[$person];
		for ($i=1;$i<7;++$i) {
			$id=PublicId::generate();
			$s['people']->create($this->scope,$id,'Participant '.$i,null,$now);
			$people[]=$id;
		}
		$registrations=[];
		foreach ($people as $i=>$subject) {
			$registrations[]=$s['submit']->submit($s['actor'],$this->scope,$subject,$event,null,PublicId::generate(),['name'=>'Member '.$i],$now,CorrelationId::generate());
		}
		$bucket=$s['capacity']->create_general_bucket($s['actor'],$this->scope,$event,2,$now,CorrelationId::generate());
		for ($i=0;$i<4;++$i) {
			self::assertSame($i<2?'accepted':'waitlisted',$s['capacity']->decide($s['actor'],$this->scope,$registrations[$i],$bucket,PublicId::generate(),$now,CorrelationId::generate()));
		}
		$s['lifecycle']->cancel($s['actor'],$this->scope,$registrations[0],PublicId::generate(),$now,CorrelationId::generate());
		$offer=$s['lifecycle']->offer_next($s['actor'],$this->scope,$bucket,PublicId::generate(),$now,CorrelationId::generate());
		self::assertNotNull($offer);
		$s['transition']->transition($s['actor'],$this->scope,$registrations[4],'review',PublicId::generate(),$now,CorrelationId::generate());
		$s['transition']->transition($s['actor'],$this->scope,$registrations[5],'rejected',PublicId::generate(),$now,CorrelationId::generate());
		$snapshots=$this->db->rows('SELECT registration_id,payload_hash,payload_json FROM %i ORDER BY registration_id',[$this->prefix.'registration_snapshots']);
		$old_events=(int)$this->db->rows('SELECT COUNT(*) AS n FROM %i',[$this->prefix.'domain_events'])[0]['n'];
		self::assertSame(5,$s['cancel_event']->cancel($s['actor'],$this->scope,$event,PublicId::generate(),$now,CorrelationId::generate()));
		$states=$this->db->rows('SELECT status,status_reason_code FROM %i ORDER BY id',[$this->prefix.'registrations']);
		self::assertSame(['cancelled','cancelled','cancelled','cancelled','cancelled','rejected','cancelled'],array_column($states,'status'));
		self::assertSame('event_cancelled',$states[1]['status_reason_code']);
		self::assertNull($states[5]['status_reason_code']);
		self::assertSame('cancelled',$this->db->rows('SELECT status FROM %i WHERE public_id = %s',[$this->prefix.'event_settings',$event->to_binary()])[0]['status']);
		self::assertSame('cancelled',$this->db->rows('SELECT status FROM %i WHERE public_id = %s',[$this->prefix.'capacity_buckets',$bucket->to_binary()])[0]['status']);
		self::assertSame([], $this->db->rows("SELECT id FROM %i WHERE status IN ('held','confirmed')",[$this->prefix.'capacity_claims']));
		self::assertSame('cancelled',$this->db->rows('SELECT status FROM %i WHERE public_id = %s',[$this->prefix.'waitlist_offers',PublicId::from_string($offer['public_id'])->to_binary()])[0]['status']);
		self::assertSame([], $this->db->rows("SELECT id FROM %i WHERE status IN ('waiting','offered')",[$this->prefix.'waitlist_entries']));
		self::assertSame($snapshots,$this->db->rows('SELECT registration_id,payload_hash,payload_json FROM %i ORDER BY registration_id',[$this->prefix.'registration_snapshots']));
		$hist=$this->db->rows("SELECT reason_code FROM %i WHERE reason_code = 'event_cancelled'",[$this->prefix.'registration_history']);
		self::assertCount(5,$hist);
		$notifications=$this->db->rows("SELECT event_name,payload_json FROM %i WHERE event_name IN ('registration.event_cancelled','event.cancelled') ORDER BY id",[$this->prefix.'domain_events']);
		self::assertSame(6,count($notifications));
		self::assertSame(5,count(array_filter($notifications,static fn($row)=>$row['event_name']==='registration.event_cancelled')));
		foreach ($notifications as $row) {
			self::assertStringNotContainsString($offer['token'],$row['payload_json']);
		}
		$event_count=(int)$this->db->rows('SELECT COUNT(*) AS n FROM %i',[$this->prefix.'domain_events'])[0]['n'];
		self::assertSame($old_events+6,$event_count);
		self::assertSame(0,$s['cancel_event']->cancel($s['actor'],$this->scope,$event,PublicId::generate(),$now,CorrelationId::generate()));
		self::assertSame($event_count,(int)$this->db->rows('SELECT COUNT(*) AS n FROM %i',[$this->prefix.'domain_events'])[0]['n']);
		self::assertNull($s['lifecycle']->offer_next($s['actor'],$this->scope,$bucket,PublicId::generate(),$now,CorrelationId::generate()) );
		try {
			$s['lifecycle']->accept_offer($s['actor'],$this->scope,PublicId::from_string($offer['public_id']),$offer['token'],PublicId::generate(),$now,CorrelationId::generate());
			self::fail('Cancelled offer remained usable');
		} catch (RuntimeException) { self::assertTrue(true); }
		try {
			$s['submit']->submit($s['actor'],$this->scope,$person,$event,null,PublicId::generate(),['name'=>'Late arrival'],$now,CorrelationId::generate());
			self::fail('Cancelled event accepted submission');
		} catch (RuntimeException) { self::assertTrue(true); }
	}

	/** Unauthorized or cross-organization attempts cannot close an event. */
	public function test_event_cancellation_respects_live_actor_and_organization_scope(): void {
		$draft=['schema_version'=>1,'fields'=>[['key'=>'name','type'=>'text','label'=>'Name','required'=>true]]];
		[$s,$person,$event,$form,$now]=$this->setup_registration($draft);
		foreach ([
			[new Actor(999999),$this->scope],
			[$s['actor'],new OrgScope($this->scope->id+10)],
		] as [$actor,$scope]) {
			try {
				$s['cancel_event']->cancel($actor,$scope,$event,PublicId::generate(),$now,CorrelationId::generate());
				self::fail('Unauthorized event cancellation succeeded');
			} catch (RuntimeException) { self::assertTrue(true); }
		}
		self::assertSame('active',$this->db->rows('SELECT status FROM %i WHERE public_id = %s',[$this->prefix.'event_settings',$event->to_binary()])[0]['status']);
		self::assertSame([], $this->db->rows("SELECT id FROM %i WHERE event_name = 'event.cancelled'",[$this->prefix.'domain_events']));
	}

	/** An outbox failure aborts the entire event + seat + history transaction. */
	public function test_event_cancellation_rolls_back_all_state_when_outbox_is_unavailable(): void {
		$draft=['schema_version'=>1,'fields'=>[['key'=>'name','type'=>'text','label'=>'Name','required'=>true]]];
		[$s,$person,$event,$form,$now]=$this->setup_registration($draft);
		$registration=$s['submit']->submit($s['actor'],$this->scope,$person,$event,null,PublicId::generate(),['name'=>'Preserved'],$now,CorrelationId::generate());
		$bucket=$s['capacity']->create_general_bucket($s['actor'],$this->scope,$event,1,$now,CorrelationId::generate());
		$s['capacity']->decide($s['actor'],$this->scope,$registration,$bucket,PublicId::generate(),$now,CorrelationId::generate());
		$this->db->execute('DROP TABLE %i',[$this->prefix.'domain_events']);
		try {
			$s['cancel_event']->cancel($s['actor'],$this->scope,$event,PublicId::generate(),$now,CorrelationId::generate());
			self::fail('Cancellation completed without mandatory outbox');
		} catch (\Throwable $error) {
			self::assertNotInstanceOf(\PHPUnit\Framework\AssertionFailedError::class,$error);
		}
		self::assertSame('active',$this->db->rows('SELECT status FROM %i WHERE public_id = %s',[$this->prefix.'event_settings',$event->to_binary()])[0]['status']);
		self::assertSame('active',$this->db->rows('SELECT status FROM %i WHERE public_id = %s',[$this->prefix.'capacity_buckets',$bucket->to_binary()])[0]['status']);
		self::assertSame('accepted',$this->db->rows('SELECT status FROM %i WHERE public_id = %s',[$this->prefix.'registrations',$registration->to_binary()])[0]['status']);
		self::assertSame('confirmed',$this->db->rows('SELECT status FROM %i WHERE registration_id = (SELECT id FROM %i WHERE public_id = %s)',[$this->prefix.'capacity_claims',$this->prefix.'registrations',$registration->to_binary()])[0]['status']);
		self::assertSame([], $this->db->rows("SELECT id FROM %i WHERE reason_code = 'event_cancelled'",[$this->prefix.'registration_history']));
	}
}
