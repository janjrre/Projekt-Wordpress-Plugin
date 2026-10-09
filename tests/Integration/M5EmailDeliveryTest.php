<?php
namespace UOP\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use UOP\Application\Communication\{EmailMessageService, EmailTemplateCatalog, EmailTemplateRules, EmailTemplateService};
use UOP\Application\Policy\{Actor, PolicyService};
use UOP\Core\{CorrelationId, PublicId, TransactionManager};
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\{AssignmentRepository, AuditWriter, DelegationRepository, EmailMessageRepository, EmailTemplateRepository, Installer, OutboxRepository, PersonRepository, SchemaManifest, WpdbConnection};
use UOP\Infrastructure\Queue\EmailDeliveryWorker;

final class M5EmailDeliveryTest extends TestCase {
	private WpdbConnection $db;
	private OrgScope $scope;
	private string $prefix;

	protected function setUp(): void {
		global $wpdb;
		$this->db=new WpdbConnection($wpdb);
		$this->prefix=$wpdb->prefix.'uop_';
		$manifest=new SchemaManifest(dirname(__DIR__,2).'/schema/manifest.json');
		foreach(array_keys($manifest->tables()) as $table) $this->db->execute('DROP TABLE IF EXISTS %i',[$this->prefix.$table]);
		foreach(['uop_db_version','uop_data_version','uop_migration_progress_1','uop_migration_status','uop_default_organization_id'] as $name) delete_option($name);
		Installer::runner()->run();
		$this->scope=new OrgScope((int)get_option('uop_default_organization_id'));
	}

	private function fixture(): array {
		$id=wp_create_user('uop_delivery_'.bin2hex(random_bytes(4)),wp_generate_password(20),'delivery_'.bin2hex(random_bytes(4)).'@example.invalid');
		self::assertIsInt($id);
		(new \WP_User($id))->set_role('administrator');
		wp_set_current_user($id);
		$actor=new Actor($id);
		$policy=new PolicyService(new PersonRepository($this->db,$this->prefix),new DelegationRepository($this->db,$this->prefix),new AssignmentRepository($this->db,$this->prefix),static fn(int $uid,string $cap): bool=>$uid===$id);
		$tx=new TransactionManager($this->db,static function(int $n): void {},static function(\Throwable $e): void {});
		$audit=new AuditWriter($this->db,$this->prefix);
		$outbox=new OutboxRepository($this->db,$this->prefix);
		$catalog=new EmailTemplateCatalog();
		$rules=new EmailTemplateRules($catalog);
		$templates=new EmailTemplateService($catalog,$rules,new EmailTemplateRepository($this->db,$this->prefix),$policy,$tx,$audit,$outbox);
		$messages=new EmailMessageRepository($this->db,$this->prefix);
		return [
			'actor'=>$actor,'templates'=>$templates,'messages'=>$messages,
			'queue'=>new EmailMessageService($templates,$rules,$messages,$policy,$tx,$audit,$outbox),
			'worker'=>new EmailDeliveryWorker($this->db,$this->prefix,$messages,$tx),
		];
	}

	private function snapshot(PublicId $uuid): array {
		return $this->db->rows('SELECT status,attempts,recipient,subject,body_text,body_html,template_hash,template_revision,last_error_code FROM %i WHERE organization_id=%d AND public_id=%s',[$this->prefix.'email_messages',$this->scope->id,$uuid->to_binary()])[0];
	}

	public function test_immutable_rendered_queue_is_command_idempotent_and_token_free_in_outbox(): void {
		$s=$this->fixture();
		$command=PublicId::generate();
		$now='2030-01-03 11:00:00';
		$vars=['participant_name'=>'A <B>','event_title'=>'A & B','action_url'=>'https://example.org/verify?token=TOP_SECRET','expires_at'=>'Tomorrow'];
		$mail=$s['queue']->queue($s['actor'],$this->scope,$command,'student@example.invalid','email_verification','de_DE',$vars,null,$now,CorrelationId::generate());
		$first=$this->snapshot($mail);
		self::assertSame('queued',$first['status']);
		self::assertSame(0,(int)$first['attempts']);
		self::assertStringContainsString('&lt;B&gt;',$first['body_html']);
		self::assertStringContainsString('TOP_SECRET',$first['body_text']);
		self::assertSame(32,strlen($first['template_hash']));
		$retry=$s['queue']->queue($s['actor'],$this->scope,$command,'student@example.invalid','email_verification','de_DE',$vars,null,$now,CorrelationId::generate());
		self::assertSame($mail->to_string(),$retry->to_string());
		self::assertSame($first,$this->snapshot($retry));
		$events=$this->db->rows("SELECT payload_json FROM %i WHERE event_name='mail.queued'",[$this->prefix.'domain_events']);
		self::assertCount(1,$events);
		self::assertStringNotContainsString('TOP_SECRET',$events[0]['payload_json']);
		self::assertSame([['id'=>1]],$this->db->rows('SELECT id FROM %i ORDER BY id',[$this->prefix.'email_messages']));
	}

	public function test_duplicate_jobs_send_only_once_and_preserve_content_after_template_edit(): void {
		$s=$this->fixture();
		$now='2030-01-03 11:00:00';
		$vars=['participant_name'=>'Pat','event_title'=>'First Event'];
		$mail=$s['queue']->queue($s['actor'],$this->scope,PublicId::generate(),'pat@example.invalid','registration_received','de_DE',$vars,null,$now,CorrelationId::generate());
		$original=$this->snapshot($mail);
		$s['templates']->save($s['actor'],$this->scope,'registration_received','de_DE',0,'Changed {{event_title}}','Other content for {{participant_name}}',null,$now,CorrelationId::generate());
		$calls=[];
		$filter=static function($pre,array $atts) use (&$calls) {
			$calls[]=$atts;
			return true;
		};
		add_filter('pre_wp_mail',$filter,10,2);
		try {
			$s['worker']->deliver($this->scope->id,$mail->to_string());
			$s['worker']->deliver($this->scope->id,$mail->to_string());
		} finally { remove_filter('pre_wp_mail',$filter,10); }
		self::assertCount(1,$calls);
		self::assertSame($original['subject'],$calls[0]['subject']);
		self::assertSame($original['body_html'],$calls[0]['message']);
		self::assertSame('accepted',$this->snapshot($mail)['status']);
		self::assertSame(1,(int)$this->snapshot($mail)['attempts']);
		self::assertFalse($s['queue']->retry_failed($s['actor'],$this->scope,$mail,CorrelationId::generate()));
	}

	public function test_known_failure_can_be_retried_explicitly_without_rerendering(): void {
		$s=$this->fixture();
		$now='2030-01-03 11:00:00';
		$mail=$s['queue']->queue($s['actor'],$this->scope,PublicId::generate(),'pat@example.invalid','registration_received','de_DE',['participant_name'=>'Pat','event_title'=>'X'],null,$now,CorrelationId::generate());
		$original=$this->snapshot($mail);
		$calls=0;
		$filter=static function($pre,array $atts) use (&$calls) {
			++$calls;
			return $calls>1;
		};
		add_filter('pre_wp_mail',$filter,10,2);
		try {
			$s['worker']->deliver($this->scope->id,$mail->to_string());
			self::assertSame('failed',$this->snapshot($mail)['status']);
			self::assertSame('wp_mail_failed',$this->snapshot($mail)['last_error_code']);
			$s['worker']->deliver($this->scope->id,$mail->to_string());
			self::assertSame(1,$calls);
			self::assertTrue($s['queue']->retry_failed($s['actor'],$this->scope,$mail,CorrelationId::generate()));
			$s['worker']->deliver($this->scope->id,$mail->to_string());
			self::assertSame(2,$calls);
		} finally { remove_filter('pre_wp_mail',$filter,10); }
		$after=$this->snapshot($mail);
		self::assertSame('accepted',$after['status']);
		self::assertSame(2,(int)$after['attempts']);
		foreach(['recipient','subject','body_html','body_text','template_hash','template_revision'] as $column) self::assertSame($original[$column],$after[$column]);
	}

	public function test_ambiguous_inflight_claim_cross_tenant_and_permission_denial_are_fail_closed(): void {
		$s=$this->fixture();
		$now='2030-01-03 11:00:00';
		$command=PublicId::generate();
		$vars=['participant_name'=>'Pat','event_title'=>'X'];
		try {
			$s['queue']->queue(new Actor(999999),$this->scope,$command,'pat@example.invalid','registration_received','de_DE',$vars,null,$now,CorrelationId::generate());
			self::fail('Unauthorized mail enqueue');
		} catch(RuntimeException) { self::assertTrue(true); }
		$mail=$s['queue']->queue($s['actor'],$this->scope,$command,'pat@example.invalid','registration_received','de_DE',$vars,null,$now,CorrelationId::generate());
		$claimed=$s['messages']->claim($this->scope,$mail);
		self::assertNotNull($claimed);
		self::assertSame('sending',$this->snapshot($mail)['status']);
		$calls=0;
		$filter=static function() use (&$calls) { ++$calls; return true; };
		add_filter('pre_wp_mail',$filter);
		try {
			$s['worker']->deliver($this->scope->id,$mail->to_string());
			$s['worker']->deliver($this->scope->id+123,$mail->to_string());
		} finally { remove_filter('pre_wp_mail',$filter); }
		self::assertSame(0,$calls);
		self::assertFalse($s['queue']->retry_failed($s['actor'],$this->scope,$mail,CorrelationId::generate()));
		self::assertSame('sending',$this->snapshot($mail)['status']);
		self::assertSame([],$s['messages']->pending(new OrgScope($this->scope->id+123)));
		try {
			$s['queue']->queue($s['actor'],$this->scope,$command,'different@example.invalid','registration_received','de_DE',$vars,null,$now,CorrelationId::generate());
			self::fail('Conflicting replay recipient accepted');
		} catch(RuntimeException) { self::assertTrue(true); }
	}

	public function test_queue_creation_rolls_back_when_audit_outbox_unavailable(): void {
		$s=$this->fixture();
		$this->db->execute('DROP TABLE %i',[$this->prefix.'domain_events']);
		try {
			$s['queue']->queue($s['actor'],$this->scope,PublicId::generate(),'pat@example.invalid','registration_received','de_DE',['participant_name'=>'Pat','event_title'=>'X'],null,'2030-01-03 11:00:00',CorrelationId::generate());
			self::fail('Created an undeliverable mail without outbox');
		} catch(\Throwable $error) {
			self::assertNotInstanceOf(\PHPUnit\Framework\AssertionFailedError::class,$error);
		}
		self::assertSame([],$this->db->rows('SELECT id FROM %i',[$this->prefix.'email_messages']));
	}
}
