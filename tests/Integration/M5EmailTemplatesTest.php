<?php
namespace UOP\Tests\Integration;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use UOP\Application\Communication\{EmailTemplateCatalog, EmailTemplateRules, EmailTemplateService};
use UOP\Application\Policy\{Actor, PolicyService};
use UOP\Core\{CorrelationId, TransactionManager};
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\{AssignmentRepository, AuditWriter, DelegationRepository, EmailTemplateRepository, Installer, OutboxRepository, PersonRepository, SchemaManifest, WpdbConnection};

final class M5EmailTemplatesTest extends TestCase {
	private WpdbConnection $db;
	private OrgScope $scope;
	private string $prefix;

	protected function setUp(): void {
		global $wpdb;
		$this->db = new WpdbConnection($wpdb);
		$this->prefix = $wpdb->prefix . 'uop_';
		$manifest = new SchemaManifest(dirname(__DIR__,2).'/schema/manifest.json');
		foreach (array_keys($manifest->tables()) as $table) $this->db->execute('DROP TABLE IF EXISTS %i', [$this->prefix.$table]);
		foreach (['uop_db_version','uop_data_version','uop_migration_progress_1','uop_migration_status','uop_default_organization_id'] as $name) delete_option($name);
		Installer::runner()->run();
		$this->scope = new OrgScope((int)get_option('uop_default_organization_id'));
	}

	private function fixture(): array {
		$id = wp_create_user('uop_m5mail_'.bin2hex(random_bytes(4)),wp_generate_password(24),'m5mail_'.bin2hex(random_bytes(4)).'@example.invalid');
		self::assertIsInt($id);
		(new \WP_User($id))->set_role('administrator');
		wp_set_current_user($id);
		$actor = new Actor($id);
		$people = new PersonRepository($this->db,$this->prefix);
		$policy = new PolicyService($people,new DelegationRepository($this->db,$this->prefix),new AssignmentRepository($this->db,$this->prefix),static fn(int $uid,string $cap): bool => $uid === $id);
		$tx = new TransactionManager($this->db,static function(int $delay): void {},static function(\Throwable $error): void {});
		$catalog = new EmailTemplateCatalog();
		$rules = new EmailTemplateRules($catalog);
		$repo = new EmailTemplateRepository($this->db,$this->prefix);
		$service = new EmailTemplateService($catalog,$rules,$repo,$policy,$tx,new AuditWriter($this->db,$this->prefix),new OutboxRepository($this->db,$this->prefix));
		return ['actor'=>$actor,'catalog'=>$catalog,'rules'=>$rules,'repo'=>$repo,'service'=>$service];
	}

	public function test_builtins_are_available_without_rows_and_render_safely(): void {
		$s=$this->fixture();
		$preview_vars=['participant_name'=>'<Alex & Co>','event_title'=>'A & B','action_url'=>'https://example.org/confirm?key=abc&token=x','expires_at'=>'Tomorrow'];
		foreach (['de_DE','en_US'] as $locale) {
			foreach (['registration_received','email_verification','waitlist_offer','registration_cancelled','event_cancelled','consent_withdrawn'] as $key) {
				$default=$s['service']->get($s['actor'],$this->scope,$key,$locale);
				self::assertSame('builtin',$default['source']);
				self::assertSame(0,$default['revision']);
				self::assertNull($default['public_id']);
				self::assertSame(64,strlen($default['hash']));
				$allowed=array_intersect_key($preview_vars,array_flip($s['catalog']->variables($key)));
				$out=$s['service']->preview($s['actor'],$this->scope,$key,$locale,$allowed);
				self::assertStringNotContainsString('<Alex & Co>',$out['body_html']);
				self::assertStringContainsString('&lt;Alex &amp; Co&gt;',$out['body_html']);
				self::assertStringNotContainsString('<script>',$out['subject']);
				self::assertStringContainsString('A & B',$out['body_text']);
			}
		}
		self::assertSame([],$this->db->rows('SELECT id FROM %i',[$this->prefix.'email_templates']));
	}

	public function test_overrides_are_scoped_revisioned_and_consistently_audited(): void {
		$s=$this->fixture();
		$now='2030-01-03 12:00:00';
		$subject='Anmeldung: {{event_title}}';
		$body='Hallo {{participant_name}} zur Veranstaltung {{event_title}}.';
		$html='<p>Hallo <strong>{{participant_name}}</strong> zu {{event_title}}.</p>';
		$first=$s['service']->save($s['actor'],$this->scope,'registration_received','de_DE',0,$subject,$body,$html,$now,CorrelationId::generate());
		self::assertSame(1,$first['revision']);
		$loaded=$s['service']->get($s['actor'],$this->scope,'registration_received','de_DE');
		self::assertSame('override',$loaded['source']);
		self::assertSame(1,$loaded['revision']);
		self::assertSame($first['public_id'],$loaded['public_id']);
		$other=$s['service']->get($s['actor'],new OrgScope($this->scope->id+4),'registration_received','de_DE');
		self::assertSame('builtin',$other['source']);
		$new=$s['service']->save($s['actor'],$this->scope,'registration_received','de_DE',1,$subject.' bestätigt',$body.' Danke.',$html,$now,CorrelationId::generate());
		self::assertSame(2,$new['revision']);
		self::assertSame($first['public_id'],$new['public_id']);
		self::assertNotSame($loaded['hash'],$s['service']->get($s['actor'],$this->scope,'registration_received','de_DE')['hash']);
		try {
			$s['service']->save($s['actor'],$this->scope,'registration_received','de_DE',1,'Changed',$body,$html,$now,CorrelationId::generate());
			self::fail('Stale edit overwritten');
		} catch(RuntimeException) { self::assertTrue(true); }
		try {
			$s['service']->save($s['actor'],$this->scope,'registration_received','de_DE',2,$subject.' bestätigt',$body.' Danke.',$html,$now,CorrelationId::generate());
			self::fail('No-op update advanced revision');
		} catch(RuntimeException) { self::assertTrue(true); }
		self::assertSame(2,(int)$this->db->rows('SELECT revision FROM %i WHERE organization_id = %d',[$this->prefix.'email_templates',$this->scope->id])[0]['revision']);
		self::assertCount(2,$this->db->rows("SELECT id FROM %i WHERE event_name='mail.template_saved'",[$this->prefix.'domain_events']));
		self::assertCount(2,$this->db->rows("SELECT id FROM %i WHERE action='email_template.saved'",[$this->prefix.'audit_log']));
	}

	/** Plain-text-only overrides persist true SQL NULL, not empty HTML blobs. */
	public function test_plain_text_only_overrides_keep_null_html(): void {
		$s=$this->fixture();
		$now='2030-01-03 12:00:00';
		$a=$s['service']->save($s['actor'],$this->scope,'event_cancelled','de_DE',0,'Event: {{event_title}}','Hello {{participant_name}}',null,$now,CorrelationId::generate());
		self::assertSame(1,$a['revision']);
		$stored=$s['service']->get($s['actor'],$this->scope,'event_cancelled','de_DE');
		self::assertNull($stored['body_html']);
		self::assertSame('override',$stored['source']);
		$row=$this->db->rows('SELECT body_html FROM %i WHERE organization_id = %d AND template_key = %s',[$this->prefix.'email_templates',$this->scope->id,'event_cancelled'])[0];
		self::assertNull($row['body_html']);
		$b=$s['service']->save($s['actor'],$this->scope,'event_cancelled','de_DE',1,'Event: {{event_title}}','Hello {{participant_name}}, update',null,$now,CorrelationId::generate());
		self::assertSame(2,$b['revision']);
		self::assertNull($s['service']->get($s['actor'],$this->scope,'event_cancelled','de_DE')['body_html']);
	}

	public function test_html_variable_header_and_preview_injection_are_blocked(): void {
		$s=$this->fixture();
		$rules=$s['rules'];
		$valid=['registration_received','de_DE','Safe {{event_title}}','Hi {{participant_name}}','<p>Hi {{participant_name}}</p>'];
		foreach ([
			['registration_received','de_DE',"Unsafe\nHeader",'Hello',null],
			['registration_received','de_DE','Missing {{secret}}','Hello',null],
			['email_verification','de_DE','Secret {{action_url}}','Follow {{action_url}}',null],
			['registration_received','de_DE','Safe','Hello','<img src="https://example.org/pixel">'],
			['registration_received','de_DE','Safe','Hello','<p onclick="alert(1)">Hi</p>'],
			['registration_received','de_DE','Safe','Hello','<a href="javascript:alert(1)">unsafe</a>'],
			['registration_received','de_DE','Safe','Hello','<a href="{{event_title}}">unsafe</a>'],
			['email_verification','de_DE','Safe','Click {{action_url}}','<p><a href="http://example.com">Link</a></p>'],
			['registration_received','en_GB','Safe','Hello',null],
			['custom_unknown','de_DE','Safe','Hello',null],
		] as $invalid) {
			try {
				$rules->validate(...$invalid);
				self::fail('Unsafe mail template was allowed');
			} catch(InvalidArgumentException) { self::assertTrue(true); }
		}
		self::assertSame(32,strlen($rules->validate(...$valid)));
		$html=$s['service']->preview($s['actor'],$this->scope,'registration_received','de_DE',['participant_name'=>'<script>alert(1)</script>','event_title'=>'A'],['subject'=>'Welcome','body_text'=>'Hi {{participant_name}}','body_html'=>'<p>Hi {{participant_name}}</p>']);
		self::assertStringNotContainsString('<script>',$html['body_html']);
		self::assertStringContainsString('&lt;script&gt;',$html['body_html']);
		try {
			$s['service']->preview($s['actor'],$this->scope,'email_verification','de_DE',['participant_name'=>'A','event_title'=>'B','expires_at'=>'Now','action_url'=>'javascript:alert(1)']);
			self::fail('Unsafe action URL accepted');
		} catch(InvalidArgumentException) { self::assertTrue(true); }
		try {
			$s['service']->preview($s['actor'],$this->scope,'email_verification','de_DE',['participant_name'=>'A','event_title'=>'B','expires_at'=>'Now']);
			self::fail('Missing action URL accepted');
		} catch(InvalidArgumentException) { self::assertTrue(true); }
		self::assertSame([],$this->db->rows('SELECT id FROM %i',[$this->prefix.'email_templates']));
	}

	public function test_live_permissions_cross_tenant_and_atomic_outbox_rollback(): void {
		$s=$this->fixture();
		$now='2030-01-03 12:00:00';
		try {
			$s['service']->save(new Actor(999999),$this->scope,'registration_received','de_DE',0,'Subject','Plain text',null,$now,CorrelationId::generate());
			self::fail('Unauthorized template save');
		} catch(RuntimeException) { self::assertTrue(true); }
		try {
			$s['service']->preview(new Actor(999999),$this->scope,'registration_received','de_DE',['participant_name'=>'A','event_title'=>'B']);
			self::fail('Unauthorized template preview');
		} catch(RuntimeException) { self::assertTrue(true); }
		$this->db->execute('DROP TABLE %i',[$this->prefix.'domain_events']);
		try {
			$s['service']->save($s['actor'],$this->scope,'registration_received','de_DE',0,'Subject','Plain text',null,$now,CorrelationId::generate());
			self::fail('Template saved without outbox');
		} catch(\Throwable $err) {
			self::assertNotInstanceOf(\PHPUnit\Framework\AssertionFailedError::class,$err);
		}
		self::assertSame([],$this->db->rows('SELECT id FROM %i',[$this->prefix.'email_templates']));
	}
}
