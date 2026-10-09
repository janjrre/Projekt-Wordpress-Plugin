<?php
namespace UOP\Tests\Integration;

use PHPUnit\Framework\TestCase;
use UOP\Application\Privacy\WordPressPrivacyAdapter;
use UOP\Core\{PublicId, TransactionManager};
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\{AuditWriter, Installer, OutboxRepository, PersonRepository, PrivacyAccountGateway, SchemaManifest, WpdbConnection};

final class M5WordPressPrivacyTest extends TestCase {
	private WpdbConnection $db;
	private OrgScope $scope;
	private string $prefix;
	private PersonRepository $people;
	private PrivacyAccountGateway $privacy;
	private WordPressPrivacyAdapter $adapter;

	protected function setUp(): void {
		global $wpdb;
		$this->db = new WpdbConnection($wpdb);
		$this->prefix = $wpdb->prefix.'uop_';
		$manifest = new SchemaManifest(dirname(__DIR__,2).'/schema/manifest.json');
		foreach (array_keys($manifest->tables()) as $table) {
			$this->db->execute('DROP TABLE IF EXISTS %i',[$this->prefix.$table]);
		}
		foreach (['uop_db_version','uop_data_version','uop_migration_progress_1','uop_migration_status','uop_default_organization_id'] as $option) delete_option($option);
		Installer::runner()->run();
		$this->scope = new OrgScope((int)get_option('uop_default_organization_id'));
		$this->people = new PersonRepository($this->db,$this->prefix);
		$this->privacy = new PrivacyAccountGateway($this->db,$this->prefix);
		$tx = new TransactionManager($this->db,static function(int $attempt): void {},static function(\Throwable $error): void {});
		$this->adapter = new WordPressPrivacyAdapter($this->privacy,$tx,new AuditWriter($this->db,$this->prefix),new OutboxRepository($this->db,$this->prefix));
		$this->adapter->register_hooks();
	}

	private function linked_user(): array {
		$email = 'privacy_'.bin2hex(random_bytes(5)).'@example.invalid';
		$id = wp_create_user('privacy_'.bin2hex(random_bytes(5)),wp_generate_password(24),$email);
		self::assertIsInt($id);
		(new \WP_User($id))->set_role('administrator');
		wp_set_current_user($id);
		$person = PublicId::generate();
		$this->people->create($this->scope,$person,'The Account Owner',$email,'2030-01-01 09:00:00');
		self::assertTrue($this->people->link($this->scope,$person,$id,'2030-01-01 09:00:00'));
		$row = $this->people->find($this->scope,$person);
		self::assertNotNull($row);
		return [$id,$email,$person,(int)$row['id']];
	}

	private function add_value(int $person_id,int $n=1): void {
		$field=PublicId::generate();
		$this->db->execute('INSERT INTO %i (public_id,organization_id,field_key,data_type,label,sensitivity,status,created_at,updated_at) VALUES (%s,%d,%s,%s,%s,%s,%s,%s,%s)',[
			$this->prefix.'profile_fields',$field->to_binary(),$this->scope->id,'details_'.bin2hex(random_bytes(3)),'text','Private note','personal','active','2030-01-01 00:00:00','2030-01-01 00:00:00'
		]);
		$field_row=$this->db->rows('SELECT id FROM %i WHERE public_id=%s',[$this->prefix.'profile_fields',$field->to_binary()])[0];
		for($i=0;$i<$n;$i++) {
			$this->db->execute('INSERT INTO %i (person_id,field_id,ordinal,value_string,updated_at) VALUES (%d,%d,%d,%s,%s)',[
				$this->prefix.'profile_values',$person_id,(int)$field_row['id'],$i,'personal private value '.$i,'2030-01-01 00:00:00'
			]);
		}
	}

	private function add_snapshot(int $person_id,string $email): void {
		$r=PublicId::generate();
		$key=PublicId::generate();
		$this->db->execute('INSERT INTO %i (public_id,submission_key,organization_id,person_id,event_post_id,form_version_id,status,source,contact_email,submitted_at,created_at,updated_at) VALUES (%s,%s,%d,%d,%d,%d,%s,%s,%s,%s,%s,%s)',[
			$this->prefix.'registrations',$r->to_binary(),$key->to_binary(),$this->scope->id,$person_id,99,1,'submitted','portal',$email,'2030-01-01 00:00:00','2030-01-01 00:00:00','2030-01-01 00:00:00'
		]);
		$row=$this->db->rows('SELECT id FROM %i WHERE public_id=%s',[$this->prefix.'registrations',$r->to_binary()])[0];
		$body=wp_json_encode(['fields'=>['notes'=>'Historical sensitive data']],JSON_THROW_ON_ERROR);
		$this->db->execute('INSERT INTO %i (public_id,registration_id,revision,form_version_id,payload_json,payload_hash,created_at) VALUES (%s,%d,%d,%d,%s,%s,%s)',[
			$this->prefix.'registration_snapshots',PublicId::generate()->to_binary(),(int)$row['id'],1,1,$body,hash('sha256',$body,true),'2030-01-01 00:00:00'
		]);
	}

	public function test_account_linked_export_is_registered_and_paginated(): void {
		[$user,$email,$uuid,$id]=$this->linked_user();
		$this->add_value($id,27);
		$this->add_snapshot($id,$email);
		$exporters=apply_filters('wp_privacy_personal_data_exporters',[]);
		$erasers=apply_filters('wp_privacy_personal_data_erasers',[]);
		self::assertArrayHasKey('uop-core',$exporters);
		self::assertArrayHasKey('uop-core',$erasers);
		$page1=$this->adapter->export($email,1);
		self::assertIsArray($page1);
		self::assertFalse($page1['done']);
		self::assertCount(27,$page1['data']);
		$groups=array_column($page1['data'],'group_id');
		self::assertContains('uop-person',$groups);
		self::assertContains('uop-profile',$groups);
		self::assertContains('uop-registration',$groups);
		$page2=$this->adapter->export($email,2);
		self::assertTrue($page2['done']);
		self::assertCount(2,$page2['data']);
		self::assertSame('resolved',$this->privacy->resolve($email)['status']);
	}

	public function test_shared_family_email_blocks_export_and_erasure_without_touching_either_person(): void {
		[$user,$email,$uuid,$id]=$this->linked_user();
		$this->add_value($id);
		$child=PublicId::generate();
		$this->people->create($this->scope,$child,'Unlinked family member',$email,'2030-01-01 00:00:00');
		$child_record=$this->people->find($this->scope,$child);
		$this->add_value((int)$child_record['id']);
		self::assertSame('manual',$this->privacy->resolve($email)['status']);
		$export=$this->adapter->export($email);
		self::assertCount(1,$export['data']);
		self::assertSame('uop-identity',$export['data'][0]['group_id']);
		$erase=$this->adapter->erase($email);
		self::assertFalse($erase['items_removed']);
		self::assertTrue($erase['items_retained']);
		self::assertCount(2,$this->db->rows('SELECT id FROM %i',[$this->prefix.'profile_values']));
		self::assertSame('The Account Owner',$this->people->find($this->scope,$uuid)['display_name']);
	}

	public function test_registration_contact_email_on_other_person_forces_manual_resolution(): void {
		[$user,$email]=$this->linked_user();
		$guest=PublicId::generate();
		$this->people->create($this->scope,$guest,'Unlinked guest',null,'2030-01-01 00:00:00');
		$this->add_snapshot((int)$this->people->find($this->scope,$guest)['id'],$email);
		self::assertSame('manual',$this->privacy->resolve($email)['status']);
		self::assertSame('uop-identity',$this->adapter->export($email)['data'][0]['group_id']);
	}

	public function test_erasure_redacts_current_profile_but_explicitly_retains_history(): void {
		[$user,$email,$uuid,$id]=$this->linked_user();
		$this->add_value($id);
		$this->add_snapshot($id,$email);
		$erase=$this->adapter->erase($email);
		self::assertTrue($erase['items_removed']);
		self::assertTrue($erase['items_retained']);
		self::assertSame([],$this->db->rows('SELECT id FROM %i WHERE person_id=%d',[$this->prefix.'profile_values',$id]));
		self::assertSame('Erased person',$this->people->find($this->scope,$uuid)['display_name']);
		self::assertNull($this->people->find($this->scope,$uuid)['primary_email']);
		self::assertCount(1,$this->db->rows('SELECT id FROM %i',[$this->prefix.'registration_snapshots']));
		self::assertCount(1,$this->db->rows("SELECT id FROM %i WHERE action='person.privacy_profile_redacted'",[$this->prefix.'audit_log']));
		self::assertCount(1,$this->db->rows("SELECT id FROM %i WHERE event_name='person.privacy_profile_redacted'",[$this->prefix.'domain_events']));
		self::assertFalse($this->adapter->erase($email)['items_removed']);
	}

	public function test_future_legal_hold_prevents_erasure(): void {
		[$user,$email,$uuid,$id]=$this->linked_user();
		$this->add_value($id);
		$this->db->execute('UPDATE %i SET retention_hold_until=%s WHERE id=%d',[$this->prefix.'persons','2099-01-01 00:00:00',$id]);
		$result=$this->adapter->erase($email);
		self::assertFalse($result['items_removed']);
		self::assertTrue($result['items_retained']);
		self::assertCount(1,$this->db->rows('SELECT id FROM %i WHERE person_id=%d',[$this->prefix.'profile_values',$id]));
	}

	public function test_outbox_failure_rolls_back_profile_erasure(): void {
		[$user,$email,$uuid,$id]=$this->linked_user();
		$this->add_value($id);
		$this->db->execute('DROP TABLE %i',[$this->prefix.'domain_events']);
		$result=$this->adapter->erase($email);
		self::assertFalse($result['items_removed']);
		self::assertTrue($result['items_retained']);
		self::assertCount(1,$this->db->rows('SELECT id FROM %i WHERE person_id=%d',[$this->prefix.'profile_values',$id]));
		self::assertSame('The Account Owner',$this->people->find($this->scope,$uuid)['display_name']);
	}

	public function test_no_account_link_is_never_resolved_by_email_alone(): void {
		$owner=$this->linked_user();
		$other=PublicId::generate();
		$this->people->create($this->scope,$other,'Unrelated subject','someone_'.bin2hex(random_bytes(3)).'@example.invalid','2030-01-01 00:00:00');
		self::assertSame('manual',$this->privacy->resolve($this->people->find($this->scope,$other)['primary_email'])['status']);
	}
}
