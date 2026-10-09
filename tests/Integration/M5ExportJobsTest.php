<?php
namespace UOP\Tests\Integration;

use PHPUnit\Framework\TestCase;
use InvalidArgumentException;
use RuntimeException;
use UOP\Application\Export\{ExportJobService,PersonExportGenerator};
use UOP\Application\Policy\{Actor,PolicyService};
use UOP\REST\ExportController;
use UOP\Core\{CorrelationId,PublicId,TransactionManager};
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\{AssignmentRepository,AuditWriter,DelegationRepository,ExportJobRepository,Installer,OutboxRepository,PersonRepository,SchemaManifest,WpdbConnection};
use UOP\Infrastructure\Export\ExportStorageInterface;
use UOP\Infrastructure\Export\LocalExportStorage;

final class M5ExportJobsTest extends TestCase {
    private WpdbConnection $db;
    private string $prefix;
    private OrgScope $scope;
    private Actor $actor;
    private PersonRepository $people;
    private PolicyService $policy;
    private ExportJobRepository $jobs;
    private ExportJobService $exports;
    private ExportStorageInterface $storage;
    private bool $authorized = true;

    private const NOW='2030-01-02 10:00:00';
    private const LATER='2030-01-03 11:00:00';

    protected function setUp(): void {
        global $wpdb;
        $this->db=new WpdbConnection($wpdb);
        $this->prefix=$wpdb->prefix.'uop_';
        $manifest=new SchemaManifest(dirname(__DIR__,2).'/schema/manifest.json');
        foreach(array_keys($manifest->tables()) as $table) $this->db->execute('DROP TABLE IF EXISTS %i',[$this->prefix.$table]);
        foreach(['uop_db_version','uop_data_version','uop_migration_progress_1','uop_migration_status','uop_default_organization_id'] as $key) delete_option($key);
        Installer::runner()->run();
        $this->scope=new OrgScope((int)get_option('uop_default_organization_id'));
        $user=wp_create_user('export_'.bin2hex(random_bytes(5)),wp_generate_password(24),'export_'.bin2hex(random_bytes(5)).'@example.invalid');
        self::assertIsInt($user);
        (new \WP_User($user))->set_role('administrator');
        wp_set_current_user($user);
        $this->actor=new Actor($user);
        $this->people=new PersonRepository($this->db,$this->prefix);
        $this->jobs=new ExportJobRepository($this->db,$this->prefix);
        $policy=new PolicyService($this->people,new DelegationRepository($this->db,$this->prefix),new AssignmentRepository($this->db,$this->prefix),fn(int $id,string $cap): bool => $id===$user && $this->authorized);
        $this->policy=$policy;
        $tx=new TransactionManager($this->db,static function(int $n): void {},static function(\Throwable $e): void {});
        $this->storage=new class implements ExportStorageInterface {
            public array $files=[];
            public function write(string $contents): string {
                $key=bin2hex(random_bytes(16)).'.csv';
                $this->files[$key]=$contents;
                return $key;
            }
            public function read(string $key): string {
                if(!isset($this->files[$key])) throw new RuntimeException('Not found');
                return $this->files[$key];
            }
            public function delete(string $key): void { unset($this->files[$key]); }
        };
        $this->exports=new ExportJobService($this->jobs,new PersonExportGenerator($this->people,$policy),$this->storage,$policy,$tx,new AuditWriter($this->db,$this->prefix),new OutboxRepository($this->db,$this->prefix));
    }

    private function make_person(string $name): PublicId {
        $uuid=PublicId::generate();
        $this->people->create($this->scope,$uuid,$name,null,'2029-01-01 00:00:00');
        return $uuid;
    }

    private function queue(?PublicId $cmd=null,array $columns=['public_id','display_name','status']): PublicId {
        return $this->exports->request($this->actor,$this->scope,$cmd??PublicId::generate(),$columns,'active',self::NOW,CorrelationId::generate());
    }

    public function test_queue_replay_processing_and_private_checksum_download(): void {
        $p1=$this->make_person('=SUM(1,2)');
        $p2=$this->make_person('Second person');
        $cmd=PublicId::generate();
        $job=$this->queue($cmd);
        self::assertSame($job->to_string(),$this->queue($cmd)->to_string());
        self::assertSame('queued',$this->exports->status($this->actor,$this->scope,$job,self::NOW)['status']);
        $this->exports->process($this->scope,$job,self::NOW);
        $this->exports->process($this->scope,$job,self::NOW);
        $state=$this->exports->status($this->actor,$this->scope,$job,self::NOW);
        self::assertSame('ready',$state['status']);
        self::assertSame(2,$state['row_count']);
        $csv=$this->exports->download($this->actor,$this->scope,$job,self::NOW);
        self::assertStringContainsString($p1->to_string(),$csv);
        self::assertStringContainsString($p2->to_string(),$csv);
        self::assertStringContainsString("'=SUM(1,2)",$csv);
        self::assertCount(1,$this->storage->files);
        self::assertSame('ready',$this->exports->status($this->actor,$this->scope,$job,self::NOW)['status']);
        self::assertSame(1,(int)$this->db->rows("SELECT COUNT(id) AS total FROM %i WHERE status='ready'",[$this->prefix.'export_jobs'])[0]['total']);
    }

    public function test_command_conflict_and_projection_validation_fail_closed(): void {
        $cmd=PublicId::generate();
        $this->queue($cmd);
        try {
            $this->queue($cmd,['status']);
            self::fail('Accepted conflicting replay');
        } catch(RuntimeException) { self::assertTrue(true); }
        foreach([['private_email'],['display_name','display_name'],[]] as $columns) {
            try {
                $this->queue(null,$columns);
                self::fail('Accepted invalid projection');
            } catch(InvalidArgumentException) { self::assertTrue(true); }
        }
        self::assertCount(1,$this->db->rows('SELECT id FROM %i',[$this->prefix.'export_jobs']));
    }

    public function test_authorization_revoked_before_processing_and_download(): void {
        $this->make_person('Sensitive actor');
        $first=$this->queue();
        $this->authorized=false;
        $this->exports->process($this->scope,$first,self::NOW);
        $row=$this->jobs->find($this->scope,$first);
        self::assertSame('failed',$row['status']);
        self::assertSame('authorization_revoked',$row['error_code']);
        self::assertCount(0,$this->storage->files);
        $this->authorized=true;
        $second=$this->queue();
        $this->exports->process($this->scope,$second,self::NOW);
        $this->authorized=false;
        try {
            $this->exports->download($this->actor,$this->scope,$second,self::NOW);
            self::fail('Revoked user downloaded private export');
        } catch(RuntimeException) { self::assertTrue(true); }
        self::assertCount(1,$this->storage->files);
    }

    public function test_cross_org_and_expired_exports_are_unavailable(): void {
        $job=$this->queue();
        $this->exports->process($this->scope,$job,self::NOW);
        try {
            $this->exports->download($this->actor,new OrgScope($this->scope->id+100),$job,self::NOW);
            self::fail('Cross-tenant export download');
        } catch(RuntimeException) { self::assertTrue(true); }
        try {
            $this->exports->download($this->actor,$this->scope,$job,self::LATER);
            self::fail('Expired export downloaded');
        } catch(RuntimeException) { self::assertTrue(true); }
        self::assertSame(1,$this->exports->cleanup($this->scope,self::LATER));
        self::assertCount(0,$this->storage->files);
        self::assertSame('expired',$this->jobs->find($this->scope,$job)['status']);
        self::assertSame(0,$this->exports->cleanup($this->scope,self::LATER));
    }

    public function test_corrupt_private_file_is_rejected(): void {
        $this->make_person('Integrity check');
        $job=$this->queue();
        $this->exports->process($this->scope,$job,self::NOW);
        $key=(string)$this->jobs->find($this->scope,$job)['storage_key'];
        $this->storage->files[$key].="changed";
        try {
            $this->exports->download($this->actor,$this->scope,$job,self::NOW);
            self::fail('Tampered export downloaded');
        } catch(RuntimeException) { self::assertTrue(true); }
    }

    public function test_rest_export_contract_denies_unknown_inputs_and_serves_csv_without_json(): void {
        $this->make_person('REST member');
        $controller=new ExportController($this->exports,$this->policy);
        add_action('rest_api_init',[$controller,'register']);
        do_action('rest_api_init');
        $request=new \WP_REST_Request('POST','/uop/v1/exports');
        $request->set_header('content-type','application/json');
        $request->set_body(wp_json_encode([
            'command_id'=>PublicId::generate()->to_string(),
            'columns'=>['public_id','display_name'],
            'status'=>'active',
        ],JSON_THROW_ON_ERROR));
        $response=rest_do_request($request);
        self::assertSame(202,$response->get_status());
        $job=PublicId::from_string($response->get_data()['public_id']);
        $status=rest_do_request(new \WP_REST_Request('GET','/uop/v1/exports/'.$job->to_string()));
        self::assertSame(200,$status->get_status());
        self::assertArrayNotHasKey('storage_key',$status->get_data());
        $this->exports->process($this->scope,$job,self::NOW);
        $dl_request=new \WP_REST_Request('GET','/uop/v1/exports/'.$job->to_string().'/download');
        $download=rest_do_request($dl_request);
        self::assertSame(200,$download->get_status());
        self::assertSame('text/csv; charset=utf-8',$download->get_headers()['Content-Type']);
        ob_start();
        $served=$controller->serve_download(false,$download,$dl_request,rest_get_server());
        $file=ob_get_clean();
        self::assertTrue($served);
        self::assertStringContainsString('REST member',$file);
        self::assertFalse(str_contains($file,'uop_private_csv'));
        $this->authorized=false;
        self::assertSame(403,rest_do_request(new \WP_REST_Request('GET','/uop/v1/exports/'.$job->to_string()))->get_status());
    }

    public function test_private_local_storage_uses_opaque_references_and_strict_permissions(): void {
        $storage=new LocalExportStorage();
        $bytes="public_id,display_name\\nabc,Private user\\n";
        $key=$storage->write($bytes);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}\\.csv$/D',$key);
        self::assertSame($bytes,$storage->read($key));
        try {
            $storage->read('../wp-config.php');
            self::fail('Private storage accepted traversal key');
        } catch(RuntimeException) { self::assertTrue(true); }
        $storage->delete($key);
        try {
            $storage->read($key);
            self::fail('Deleted export remained readable');
        } catch(RuntimeException) { self::assertTrue(true); }
    }

    public function test_outbox_failure_rolls_back_job_creation(): void {
        $this->db->execute('DROP TABLE %i',[$this->prefix.'domain_events']);
        try {
            $this->queue();
            self::fail('Queued export without outbox');
        } catch(\Throwable $e) {
            self::assertNotInstanceOf(\PHPUnit\Framework\AssertionFailedError::class,$e);
        }
        self::assertSame([],$this->db->rows('SELECT id FROM %i',[$this->prefix.'export_jobs']));
    }
}
