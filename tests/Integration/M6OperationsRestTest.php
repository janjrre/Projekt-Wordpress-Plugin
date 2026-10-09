<?php
/**
 * M6-03 REST regression: immutable consent, event counts and audit isolation.
 *
 * @package UOP
 */

namespace UOP\Tests\Integration;

use PHPUnit\Framework\TestCase;
use UOP\Application\Consent\ConsentDefinitionService;
use UOP\Application\Consent\ConsentRecordService;
use UOP\Application\Event\EventService;
use UOP\Application\Form\FormService;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyService;
use UOP\Application\Query\M6OperationsReadService;
use UOP\Application\Registration\CapacityAllocationService;
use UOP\Application\Registration\RegistrationConfigurationService;
use UOP\Application\Registration\RegistrationEligibilityService;
use UOP\Application\Registration\RegistrationFactsService;
use UOP\Application\Registration\RegistrationService;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Core\TransactionManager;
use UOP\Domain\Organization\OrgScope;
use UOP\Domain\Registrations\RegistrationStateMachine;
use UOP\Infrastructure\Database\AssignmentRepository;
use UOP\Infrastructure\Database\AuditWriter;
use UOP\Infrastructure\Database\CapacityRepository;
use UOP\Infrastructure\Database\ConsentRecordRepository;
use UOP\Infrastructure\Database\ConsentRepository;
use UOP\Infrastructure\Database\DelegationRepository;
use UOP\Infrastructure\Database\EventRepository;
use UOP\Infrastructure\Database\FormRepository;
use UOP\Infrastructure\Database\Installer;
use UOP\Infrastructure\Database\M6OperationsRepository;
use UOP\Infrastructure\Database\OccurrenceRepository;
use UOP\Infrastructure\Database\OutboxRepository;
use UOP\Infrastructure\Database\PersonRepository;
use UOP\Infrastructure\Database\RegistrationFactsRepository;
use UOP\Infrastructure\Database\RegistrationReadRepository;
use UOP\Infrastructure\Database\RegistrationRepository;
use UOP\Infrastructure\Database\SchemaManifest;
use UOP\Infrastructure\Database\WpdbConnection;
use UOP\REST\M6OperationsController;

/** Database-backed tests use genuine policy and audited M4/M5 domain actions. */
final class M6OperationsRestTest extends TestCase {
	private WpdbConnection $db;
	private OrgScope $scope;
	private string $prefix;

	/** Recreate isolated tenant-scoped integration fixtures. */
	protected function setUp(): void {
		global $wpdb;
		$this->db     = new WpdbConnection( $wpdb );
		$this->prefix = $wpdb->prefix . 'uop_';
		$manifest = new SchemaManifest( dirname( __DIR__, 2 ) . '/schema/manifest.json' );
		foreach ( array_keys( $manifest->tables() ) as $table ) {
			$this->db->execute( 'DROP TABLE IF EXISTS %i', array( $this->prefix . $table ) );
		}
		foreach ( array( 'uop_db_version', 'uop_data_version', 'uop_migration_progress_1', 'uop_migration_status', 'uop_default_organization_id' ) as $option ) {
			delete_option( $option );
		}
		Installer::runner()->run();
		$this->scope = new OrgScope( (int) get_option( 'uop_default_organization_id' ) );
		wp_set_current_user( 0 );
	}

	/**
	 * Assemble policy, REST controller and existing transactional services.
	 *
	 * @return array<string,mixed>
	 */
	private function fixture(): array {
		if ( ! post_type_exists( 'uop_event' ) ) {
			register_post_type( 'uop_event', array( 'public' => true ) );
		}
		$admin = wp_create_user( 'uop_m603_admin_' . bin2hex( random_bytes( 4 ) ), wp_generate_password( 24 ), 'admin_' . bin2hex( random_bytes( 4 ) ) . '@example.invalid' );
		$other = wp_create_user( 'uop_m603_other_' . bin2hex( random_bytes( 4 ) ), wp_generate_password( 24 ), 'other_' . bin2hex( random_bytes( 4 ) ) . '@example.invalid' );
		self::assertIsInt( $admin );
		self::assertIsInt( $other );
		( new \WP_User( $admin ) )->set_role( 'administrator' );
		( new \WP_User( $other ) )->set_role( 'subscriber' );
		wp_set_current_user( $admin );
		$actor   = new Actor( $admin );
		$people  = new PersonRepository( $this->db, $this->prefix );
		$policy  = new PolicyService(
			$people,
			new DelegationRepository( $this->db, $this->prefix ),
			new AssignmentRepository( $this->db, $this->prefix ),
			static fn ( int $id, string $cap ): bool => $id === $admin
		);
		$tx      = new TransactionManager( $this->db, static function ( int $delay ): void {}, static function ( \Throwable $error ): void {} );
		$audit   = new AuditWriter( $this->db, $this->prefix );
		$outbox  = new OutboxRepository( $this->db, $this->prefix );
		$docs    = new ConsentRepository( $this->db, $this->prefix );
		$records = new ConsentRecordService( new ConsentRecordRepository( $this->db, $this->prefix ), $docs, $policy, $tx, $audit, $outbox );
		$definitions = new ConsentDefinitionService( $docs, $policy, $tx, $audit, $outbox );
		$views       = new M6OperationsReadService(
			new M6OperationsRepository( $this->db, $this->prefix ),
			new RegistrationReadRepository( $this->db, $this->prefix ),
			new EventRepository( $this->db, $this->prefix ),
			$docs,
			$policy
		);
		( new M6OperationsController( $views, $definitions, $records ) )->register();
		$form       = new FormService( new FormRepository( $this->db, $this->prefix ), $policy, $tx, $audit, $outbox );
		$event      = new EventService( new EventRepository( $this->db, $this->prefix ), new OccurrenceRepository( $this->db, $this->prefix ), $policy, $tx, $audit, $outbox );
		$registrations = new RegistrationRepository( $this->db, $this->prefix );
		$facts         = new RegistrationFactsService( new RegistrationFactsRepository( $this->db, $this->prefix ), $policy );
		$seats         = new CapacityAllocationService( new CapacityRepository( $this->db, $this->prefix ), $registrations, new RegistrationStateMachine(), $policy, $tx, $audit, $outbox, new RegistrationEligibilityService( $registrations, $facts ) );
		$submit        = new RegistrationService( $registrations, $policy, $tx, $audit, $outbox, $facts, $people, $records );
		$config        = new RegistrationConfigurationService( $this->db, $this->prefix, $policy, $tx, $audit, $outbox );
		return compact( 'admin', 'other', 'actor', 'people', 'docs', 'records', 'definitions', 'views', 'form', 'event', 'seats', 'submit', 'config' );
	}

	/**
	 * Dispatch exactly one JSON object through the WordPress REST server.
	 *
	 * @param string               $method HTTP method.
	 * @param string               $path   Absolute API route.
	 * @param array<string,mixed>  $body   Strict JSON properties.
	 * @return \WP_REST_Response
	 */
	private function json( string $method, string $path, array $body ): \WP_REST_Response {
		$request = new \WP_REST_Request( $method, $path );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );
		return rest_do_request( $request );
	}

	/** Admin creates immutable versions and retrieves only bounded audit summaries. */
	public function test_privacy_documents_capacity_and_audit_have_distinct_live_rights(): void {
		$s = $this->fixture();
		$create = $this->json( 'POST', '/uop/v1/consent-definitions', array( 'key' => 'media', 'title' => 'Media permission' ) );
		self::assertSame( 201, $create->get_status(), wp_json_encode( $create->get_data() ) );
		$definition = $create->get_data()['public_id'];
		$raw = 'I agree to the documented event-related use of my image.';
		$published = $this->json( 'POST', '/uop/v1/consent-definitions/' . $definition . '/versions', array( 'content' => $raw ) );
		self::assertSame( 201, $published->get_status(), wp_json_encode( $published->get_data() ) );
		$version = $published->get_data()['public_id'];
		$seen = rest_do_request( new \WP_REST_Request( 'GET', '/uop/v1/consent-versions/' . $version ) );
		self::assertSame( 200, $seen->get_status() );
		self::assertSame( $raw, $seen->get_data()['content'] );
		self::assertSame( hash( 'sha256', $raw ), $seen->get_data()['sha256'] );

		$post = wp_insert_post( array( 'post_type' => 'uop_event', 'post_title' => 'Event', 'post_status' => 'publish' ), true );
		self::assertIsInt( $post );
		$now   = gmdate( 'Y-m-d H:i:s' );
		$event = $s['event']->configure( $s['actor'], $this->scope, $post, 'Europe/Berlin', $now, CorrelationId::generate() );
		$bucket = $s['seats']->create_general_bucket( $s['actor'], $this->scope, $event, 12, $now, CorrelationId::generate() );
		$capacity = rest_do_request( new \WP_REST_Request( 'GET', '/uop/v1/events/' . $event->to_string() . '/capacity' ) );
		self::assertSame( 200, $capacity->get_status() );
		self::assertSame( $bucket->to_string(), $capacity->get_data()['buckets'][0]['public_id'] );
		self::assertSame( 12, $capacity->get_data()['buckets'][0]['capacity'] );
		self::assertSame( 0, $capacity->get_data()['buckets'][0]['occupied'] );
		self::assertArrayNotHasKey( 'id', $capacity->get_data()['buckets'][0] );

		$audit = rest_do_request( new \WP_REST_Request( 'GET', '/uop/v1/audit' ) );
		self::assertSame( 200, $audit->get_status() );
		self::assertNotEmpty( $audit->get_data()['items'] );
		self::assertArrayNotHasKey( 'actor_user_id', $audit->get_data()['items'][0] );
		self::assertArrayNotHasKey( 'data_json', $audit->get_data()['items'][0] );
		self::assertArrayNotHasKey( 'object_id', $audit->get_data()['items'][0] );

		wp_set_current_user( $s['other'] );
		self::assertSame( 403, rest_do_request( new \WP_REST_Request( 'GET', '/uop/v1/audit' ) )->get_status() );
		self::assertSame( 403, rest_do_request( new \WP_REST_Request( 'GET', '/uop/v1/consent-versions/' . $version ) )->get_status() );
		self::assertSame( 404, rest_do_request( new \WP_REST_Request( 'GET', '/uop/v1/events/' . $event->to_string() . '/capacity' ) )->get_status() );
		self::assertSame( 403, $this->json( 'POST', '/uop/v1/consent-definitions', array( 'key' => 'other', 'title' => 'No access' ) )->get_status() );
	}

	/** Consent evidence remains immutable across the real API withdrawal command. */
	public function test_registration_consents_are_scoped_and_withdrawal_appends_evidence(): void {
		$s   = $this->fixture();
		$now = gmdate( 'Y-m-d H:i:s' );
		$doc = $s['definitions']->create( $s['actor'], $this->scope, 'portrait', 'Portrait permission', $now, CorrelationId::generate() );
		$s['definitions']->publish( $s['actor'], $this->scope, $doc, 'I allow the use of my portrait in the official programme.', $now, CorrelationId::generate() );
		$post = wp_insert_post( array( 'post_type' => 'uop_event', 'post_title' => 'Consent Event', 'post_status' => 'publish' ), true );
		self::assertIsInt( $post );
		$event = $s['event']->configure( $s['actor'], $this->scope, $post, 'Europe/Berlin', $now, CorrelationId::generate() );
		$form  = $s['form']->create(
			$s['actor'],
			$this->scope,
			'm6_consent_form',
			'Consent Form',
			'event',
			array(
				'schema_version' => 1,
				'fields'         => array(
					array( 'key' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true ),
					array( 'key' => 'portrait', 'type' => 'consent', 'label' => 'Portrait', 'required' => true, 'consent_definition_public_id' => $doc->to_string() ),
				),
			),
			$now,
			CorrelationId::generate()
		);
		$s['form']->publish( $s['actor'], $this->scope, $form, 1, $now, CorrelationId::generate() );
		$s['config']->bind( $s['actor'], $this->scope, $event, $form, $now, CorrelationId::generate() );
		$person = PublicId::generate();
		$s['people']->create( $this->scope, $person, 'Participant', null, $now );
		$registration = $s['submit']->submit( $s['actor'], $this->scope, $person, $event, null, PublicId::generate(), array( 'name' => 'Member', 'portrait' => true ), $now, CorrelationId::generate() );
		$list_url = '/uop/v1/registrations/' . $registration->to_string() . '/consents';
		$list = rest_do_request( new \WP_REST_Request( 'GET', $list_url ) );
		self::assertSame( 200, $list->get_status(), wp_json_encode( $list->get_data() ) );
		self::assertCount( 1, $list->get_data()['items'] );
		$record = $list->get_data()['items'][0]['public_id'];
		self::assertSame( 'granted', $list->get_data()['items'][0]['decision'] );
		self::assertArrayNotHasKey( 'actor_user_id', $list->get_data()['items'][0] );
		self::assertSame( 200, rest_do_request( new \WP_REST_Request( 'GET', '/uop/v1/consents/' . $record ) )->get_status() );

		wp_set_current_user( $s['other'] );
		self::assertSame( 404, rest_do_request( new \WP_REST_Request( 'GET', $list_url ) )->get_status() );
		self::assertSame( 404, rest_do_request( new \WP_REST_Request( 'GET', '/uop/v1/consents/' . $record ) )->get_status() );
		self::assertSame( 404, $this->json( 'POST', '/uop/v1/consents/' . $record . '/withdrawals', array( 'command_id' => PublicId::generate()->to_string() ) )->get_status() );

		wp_set_current_user( $s['admin'] );
		$command = PublicId::generate()->to_string();
		$inject = $this->json( 'POST', '/uop/v1/consents/' . $record . '/withdrawals', array( 'command_id' => $command, 'actor_user_id' => $s['admin'] ) );
		self::assertSame( 400, $inject->get_status() );
		$withdrawal = $this->json( 'POST', '/uop/v1/consents/' . $record . '/withdrawals', array( 'command_id' => $command ) );
		self::assertSame( 201, $withdrawal->get_status(), wp_json_encode( $withdrawal->get_data() ) );
		$again = $this->json( 'POST', '/uop/v1/consents/' . $record . '/withdrawals', array( 'command_id' => $command ) );
		self::assertSame( 201, $again->get_status() );
		self::assertSame( $withdrawal->get_data()['public_id'], $again->get_data()['public_id'] );
		$after = rest_do_request( new \WP_REST_Request( 'GET', $list_url ) );
		self::assertCount( 2, $after->get_data()['items'] );
		self::assertSame( 'granted', $after->get_data()['items'][0]['decision'] );
		self::assertSame( 'withdrawn', $after->get_data()['items'][1]['decision'] );
	}
}
