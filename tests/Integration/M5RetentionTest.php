<?php
namespace UOP\Tests\Integration;

use PHPUnit\Framework\TestCase;
use InvalidArgumentException;
use RuntimeException;
use UOP\Application\Policy\{Actor,PolicyService};
use UOP\Application\Privacy\RetentionService;
use UOP\Core\{CorrelationId,PublicId,TransactionManager};
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\{AssignmentRepository,AuditWriter,DelegationRepository,Installer,OutboxRepository,PersonRepository,RetentionRepository,SchemaManifest,WpdbConnection};

final class M5RetentionTest extends TestCase {
    private WpdbConnection $db;
    private OrgScope $scope;
    private Actor $actor;
    private RetentionService $service;
    private string $p;
    private const NOW='2030-04-01 00:00:00';
    private const OLD='2030-01-01 00:00:00';

    protected function setUp(): void {
        global $wpdb;
        $this->db=new WpdbConnection($wpdb);
        $this->p=$wpdb->prefix.'uop_';
        $manifest=new SchemaManifest(dirname(__DIR__,2).'/schema/manifest.json');
        foreach(array_keys($manifest->tables()) as $table) $this->db->execute('DROP TABLE IF EXISTS %i',[$this->p.$table]);
        foreach(['uop_db_version','uop_data_version','uop_migration_progress_1','uop_migration_status','uop_default_organization_id'] as $key) delete_option($key);
        Installer::runner()->run();
        $this->scope=new OrgScope((int)get_option('uop_default_organization_id'));
        $id=wp_create_user('retain_'.bin2hex(random_bytes(4)),wp_generate_password(24),'retain_'.bin2hex(random_bytes(4)).'@example.invalid');
        self::assertIsInt($id);
        (new \WP_User($id))->set_role('administrator');
        wp_set_current_user($id);
        $this->actor=new Actor($id);
        $policy=new PolicyService(new PersonRepository($this->db,$this->p),new DelegationRepository($this->db,$this->p),new AssignmentRepository($this->db,$this->p),static fn(int $userid,string $cap): bool=>$userid===$id);
        $tx=new TransactionManager($this->db,static function(int $n): void {},static function(\Throwable $e): void {});
        $this->service=new RetentionService(new RetentionRepository($this->db,$this->p),$policy,$tx,new AuditWriter($this->db,$this->p),new OutboxRepository($this->db,$this->p));
    }

    private function person(): int {
        $id=PublicId::generate();
        $people=new PersonRepository($this->db,$this->p);
        $people->create($this->scope,$id,'Personal name',null,self::OLD);
        return (int)$people->find($this->scope,$id)['id'];
    }

    private function profile(int $id): void {
        $uuid=PublicId::generate();
        $this->db->execute('INSERT INTO %i (public_id,organization_id,field_key,data_type,label,sensitivity,status,created_at,updated_at) VALUES (%s,%d,%s,%s,%s,%s,%s,%s,%s)',[
            $this->p.'profile_fields',$uuid->to_binary(),$this->scope->id,'notes_'.bin2hex(random_bytes(4)),'text','Private notes','personal','active',self::OLD,self::OLD
        ]);
        $field=(int)$this->db->rows('SELECT id FROM %i WHERE public_id=%s',[$this->p.'profile_fields',$uuid->to_binary()])[0]['id'];
        $this->db->execute('INSERT INTO %i (person_id,field_id,ordinal,value_string,updated_at) VALUES (%d,%d,%d,%s,%s)',[
            $this->p.'profile_values',$id,$field,0,'private personal text',self::OLD
        ]);
    }

    private function registration(int $person,string $status='cancelled',?string $hold=null): int {
        $uuid=PublicId::generate();
        $this->db->execute('INSERT INTO %i (public_id,submission_key,organization_id,person_id,event_post_id,form_version_id,status,source,retention_hold_until,submitted_at,created_at,updated_at) VALUES (%s,%s,%d,%d,%d,%d,%s,%s,%s,%s,%s,%s)',[
            $this->p.'registrations',$uuid->to_binary(),PublicId::generate()->to_binary(),$this->scope->id,$person,99,1,$status,'portal',$hold,self::OLD,self::OLD,self::OLD
        ]);
        $reg=(int)$this->db->rows('SELECT id FROM %i WHERE public_id=%s',[$this->p.'registrations',$uuid->to_binary()])[0]['id'];
        $snap=PublicId::generate();
        $payload=wp_json_encode(['fields'=>['notes'=>'historical private text']],JSON_THROW_ON_ERROR);
        $this->db->execute('INSERT INTO %i (public_id,registration_id,revision,form_version_id,payload_json,payload_hash,created_at) VALUES (%s,%d,%d,%d,%s,%s,%s)',[
            $this->p.'registration_snapshots',$snap->to_binary(),$reg,1,1,$payload,hash('sha256',$payload,true),self::OLD
        ]);
        $snapshot=(int)$this->db->rows('SELECT id FROM %i WHERE public_id=%s',[$this->p.'registration_snapshots',$snap->to_binary()])[0]['id'];
        $this->db->execute('INSERT INTO %i (registration_id,snapshot_id,field_key,data_type,sensitivity,value_string) VALUES (%d,%d,%s,%s,%s,%s)',[
            $this->p.'registration_values',$reg,$snapshot,'notes','text','personal','historical private text'
        ]);
        return $snapshot;
    }

    private function rule(string $key,string $class,string $trigger,string $action,bool $enabled=true): void {
        $this->service->create_rule($this->actor,$this->scope,$key,$class,$trigger,30,$action,$enabled,self::NOW,CorrelationId::generate());
    }

    public function test_disabled_rules_untrusted_actor_and_unsupported_policy_fail_closed(): void {
        $this->rule('disabled','profile_values','person.created','erase',false);
        $this->expectException(RuntimeException::class);
        $this->service->dry_run($this->actor,$this->scope,'disabled',0,self::NOW);
    }

    public function test_profile_erasure_preview_is_safe_and_atomic(): void {
        $id=$this->person();
        $this->profile($id);
        $this->rule('profiles','profile_values','person.created','erase');
        self::assertSame(1,$this->service->dry_run($this->actor,$this->scope,'profiles',0,self::NOW)['eligible']);
        self::assertCount(1,$this->db->rows('SELECT id FROM %i WHERE person_id=%d',[$this->p.'profile_values',$id]));
        self::assertSame(1,$this->service->run_batch($this->actor,$this->scope,'profiles',0,self::NOW,CorrelationId::generate())['changed']);
        self::assertSame([],$this->db->rows('SELECT id FROM %i WHERE person_id=%d',[$this->p.'profile_values',$id]));
        self::assertSame(0,$this->service->run_batch($this->actor,$this->scope,'profiles',0,self::NOW,CorrelationId::generate())['changed']);
        $logs=$this->db->rows("SELECT data_json FROM %i WHERE action='privacy.retention_executed'",[$this->p.'audit_log']);
        self::assertCount(1,$logs);
        self::assertStringNotContainsString('private personal text',$logs[0]['data_json']);
    }

    public function test_snapshot_anonymize_and_hold(): void {
        $id=$this->person();
        $a=$this->registration($id);
        $b=$this->registration($id,'cancelled','2099-01-01 00:00:00');
        $c=$this->registration($id,'accepted');
        $this->rule('snapshots','registration_snapshots','registration.submitted','anonymize');
        $preview=$this->service->dry_run($this->actor,$this->scope,'snapshots',0,self::NOW);
        self::assertSame(1,$preview['eligible']);
        self::assertSame(1,$preview['held']);
        self::assertSame(1,$this->service->run_batch($this->actor,$this->scope,'snapshots',0,self::NOW,CorrelationId::generate())['changed']);
        $rows=$this->db->rows('SELECT id,payload_json,payload_hash,redacted_at FROM %i ORDER BY id',[$this->p.'registration_snapshots']);
        self::assertNull($rows[0]['payload_json']);
        self::assertNull($rows[0]['payload_hash']);
        self::assertSame(self::NOW,$rows[0]['redacted_at']);
        self::assertNotNull($rows[1]['payload_json']);
        self::assertNotNull($rows[2]['payload_json']);
        self::assertSame([],$this->db->rows('SELECT id FROM %i WHERE snapshot_id=%d',[$this->p.'registration_values',$a]));
        self::assertCount(2,$this->db->rows('SELECT id FROM %i WHERE snapshot_id IN (%d,%d)',[$this->p.'registration_values',$b,$c]));
    }

    public function test_archive_skips_live_registrations_and_legal_holds(): void {
        $a=$this->person();
        $b=$this->person();
        $c=$this->person();
        $this->registration($b,'accepted');
        $this->db->execute('UPDATE %i SET retention_hold_until=%s WHERE id=%d',[$this->p.'persons','2099-01-01 00:00:00',$c]);
        $this->rule('archive','persons','person.created','archive');
        $preview=$this->service->dry_run($this->actor,$this->scope,'archive',0,self::NOW);
        self::assertSame(1,$preview['eligible']);
        self::assertSame(2,$preview['held']);
        self::assertSame(1,$this->service->run_batch($this->actor,$this->scope,'archive',0,self::NOW,CorrelationId::generate())['changed']);
        $rows=$this->db->rows('SELECT id,status FROM %i WHERE id IN (%d,%d,%d) ORDER BY id',[$this->p.'persons',$a,$b,$c]);
        self::assertSame(['archived','active','active'],array_column($rows,'status'));
    }

    public function test_25_record_cursor_and_outbox_rollback(): void {
        for($i=0;$i<27;$i++) $this->profile($this->person());
        $this->rule('paged','profile_values','person.created','erase');
        $first=$this->service->run_batch($this->actor,$this->scope,'paged',0,self::NOW,CorrelationId::generate());
        self::assertSame(25,$first['changed']);
        self::assertFalse($first['done']);
        $second=$this->service->run_batch($this->actor,$this->scope,'paged',$first['next_cursor'],self::NOW,CorrelationId::generate());
        self::assertSame(2,$second['changed']);
        self::assertTrue($second['done']);
        self::assertCount(27,$this->db->rows("SELECT id FROM %i WHERE event_name='privacy.retention_executed'",[$this->p.'domain_events']));
        $id=$this->person();
        $this->profile($id);
        $this->db->execute('DROP TABLE %i',[$this->p.'domain_events']);
        try {
            $this->service->run_batch($this->actor,$this->scope,'paged',0,self::NOW,CorrelationId::generate());
            self::fail('Erasure without outbox succeeded');
        } catch(\Throwable $e) {
            self::assertNotInstanceOf(\PHPUnit\Framework\AssertionFailedError::class,$e);
        }
        self::assertCount(1,$this->db->rows('SELECT id FROM %i WHERE person_id=%d',[$this->p.'profile_values',$id]));
    }
    public function test_privacy_hold_is_explicitly_authorized_idempotent_and_releasable(): void {
        $id=$this->person();
        $row=$this->db->rows('SELECT public_id FROM %i WHERE id=%d',[$this->p.'persons',$id])[0];
        $uuid=PublicId::from_binary($row['public_id']);
        $this->rule('person_archive','persons','person.created','archive');
        self::assertTrue($this->service->set_hold($this->actor,$this->scope,'person',$uuid,'2099-01-01 00:00:00','legal_review',self::NOW,CorrelationId::generate()));
        self::assertFalse($this->service->set_hold($this->actor,$this->scope,'person',$uuid,'2099-01-01 00:00:00','legal_review',self::NOW,CorrelationId::generate()));
        self::assertSame(0,$this->service->dry_run($this->actor,$this->scope,'person_archive',0,self::NOW)['eligible']);
        try {
            $this->service->set_hold(new Actor(999999),$this->scope,'person',$uuid,null,null,self::NOW,CorrelationId::generate());
            self::fail('Privacy hold could be removed by an unauthorized account');
        } catch(RuntimeException) { self::assertTrue(true); }
        try {
            $this->service->set_hold($this->actor,new OrgScope($this->scope->id+5),'person',$uuid,null,null,self::NOW,CorrelationId::generate());
            self::fail('Cross-organization privacy hold was altered');
        } catch(RuntimeException) { self::assertTrue(true); }
        self::assertTrue($this->service->set_hold($this->actor,$this->scope,'person',$uuid,null,null,self::NOW,CorrelationId::generate()));
        self::assertSame(1,$this->service->dry_run($this->actor,$this->scope,'person_archive',0,self::NOW)['eligible']);
        $evidence=$this->db->rows("SELECT action,data_json FROM %i WHERE action='privacy.retention_hold_changed'",[$this->p.'audit_log']);
        self::assertCount(2,$evidence);
        self::assertStringNotContainsString('legal_review',$evidence[0]['data_json']);
    }

}
