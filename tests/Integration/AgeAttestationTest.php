<?php
/**
 * End-to-end age review evidence, migration, and authorization with real WP DB.
 *
 * @package UOP
 */

namespace UOP\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use UOP\Application\Event\EventService;
use UOP\Application\Identity\AgeAttestationService;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyService;
use UOP\Application\Registration\RegistrationFactsService;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Core\TransactionManager;
use UOP\Domain\Conditions\ConditionEngine;
use UOP\Domain\Events\OccurrenceWindow;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\AgeAttestationRepository;
use UOP\Infrastructure\Database\AgeAttestationSchema;
use UOP\Infrastructure\Database\AssignmentRepository;
use UOP\Infrastructure\Database\AuditWriter;
use UOP\Infrastructure\Database\DelegationRepository;
use UOP\Infrastructure\Database\EventRepository;
use UOP\Infrastructure\Database\Installer;
use UOP\Infrastructure\Database\OccurrenceRepository;
use UOP\Infrastructure\Database\OutboxRepository;
use UOP\Infrastructure\Database\PersonRepository;
use UOP\Infrastructure\Database\RegistrationFactsRepository;
use UOP\Infrastructure\Database\SchemaManifest;
use UOP\Infrastructure\Database\WpdbConnection;
use UOP\REST\AgeAttestationController;

/** No external customer data or genuine identity document is used. */
final class AgeAttestationTest extends TestCase {
	private WpdbConnection $db;
	private OrgScope $scope;
	private string $prefix;

	/** Fresh, disposable installation including the V2 attestation table. */
	protected function setUp(): void {
		global $wpdb;
		$this->db = new WpdbConnection( $wpdb );
		$this->prefix = $wpdb->prefix . 'uop_';
		$manifest = new SchemaManifest( dirname( __DIR__, 2 ) . '/schema/manifest.json' );
		$this->db->execute( 'DROP TABLE IF EXISTS %i', array( $this->prefix . 'age_attestations' ) );
		foreach ( array_keys( $manifest->tables() ) as $table ) {
			$this->db->execute( 'DROP TABLE IF EXISTS %i', array( $this->prefix . $table ) );
		}
		foreach ( array( 'uop_db_version', 'uop_data_version', 'uop_migration_progress_1', 'uop_migration_progress_2', 'uop_migration_status', 'uop_default_organization_id' ) as $key ) {
			delete_option( $key );
		}
		Installer::runner()->run();
		$this->scope = new OrgScope( (int) get_option( 'uop_default_organization_id' ) );
	}

	/** Clean V2 bootstrap is repeatable and never grants legacy age statements. */
	public function test_additive_schema_is_verified_and_restart_is_idempotent(): void {
		global $wpdb;
		self::assertSame( 2, (int) get_option( 'uop_db_version' ) );
		self::assertSame( 2, (int) get_option( 'uop_data_version' ) );
		self::assertSame( 'good', Installer::health()['status'] );
		AgeAttestationSchema::verify( $this->db, $this->prefix, $wpdb->collate );
		Installer::runner()->run();
		self::assertSame( 'good', Installer::health()['status'] );
		self::assertSame( 0, (int) $this->db->rows( 'SELECT COUNT(*) AS n FROM %i', array( $this->prefix . 'age_attestations' ) )[0]['n'] );
	}

	/** Real REST handler and domain policy share the same live tenant/evidence. */
	public function test_verified_age_is_event_scoped_and_revocation_prevents_reuse(): void {
		if ( ! post_type_exists( 'uop_event' ) ) {
			register_post_type( 'uop_event', array( 'public' => true ) );
		}
		$admin = wp_create_user( 'uop_age_admin_' . bin2hex( random_bytes( 4 ) ), wp_generate_password( 24 ), 'age-admin-' . bin2hex( random_bytes( 4 ) ) . '@example.invalid' );
		self::assertIsInt( $admin );
		( new \WP_User( $admin ) )->set_role( 'administrator' );
		wp_set_current_user( $admin );
		$actor = new Actor( $admin );
		$now = gmdate( 'Y-m-d H:i:s' );
		$people = new PersonRepository( $this->db, $this->prefix );
		$person = PublicId::generate();
		$people->create( $this->scope, $person, 'Synthetic attendee', null, $now );
		$person_id = (int) $people->find( $this->scope, $person )['id'];
		$policy = new PolicyService( $people, new DelegationRepository( $this->db, $this->prefix ), new AssignmentRepository( $this->db, $this->prefix ), static fn ( int $id, string $cap ): bool => $id === $admin );
		$tx = new TransactionManager( $this->db, static function ( int $n ): void {}, static function ( \Throwable $e ): void {} );
		$audit = new AuditWriter( $this->db, $this->prefix );
		$outbox = new OutboxRepository( $this->db, $this->prefix );
		$repo = new AgeAttestationRepository( $this->db, $this->prefix );
		$review = new AgeAttestationService( $repo, $people, $this->db, $this->prefix, $policy, $tx, $audit, $outbox );
		( new AgeAttestationController( $review, $repo, $people, $policy ) )->register();

		$post = wp_insert_post( array( 'post_type' => 'uop_event', 'post_title' => 'Age event', 'post_status' => 'publish' ), true );
		self::assertIsInt( $post );
		$event_service = new EventService( new EventRepository( $this->db, $this->prefix ), new OccurrenceRepository( $this->db, $this->prefix ), $policy, $tx, $audit, $outbox );
		$event_service->configure( $actor, $this->scope, $post, 'UTC', $now, CorrelationId::generate() );
		$start = gmdate( 'Y-m-d\TH:i:s+00:00', time() + 30 * DAY_IN_SECONDS );
		$finish = gmdate( 'Y-m-d\TH:i:s+00:00', time() + 30 * DAY_IN_SECONDS + 3600 );
		$event_service->add_occurrence( $actor, $this->scope, $post, new OccurrenceWindow( $start, $finish, 'UTC' ), $now, CorrelationId::generate() );
		$expiry = gmdate( 'Y-m-d H:i:s', time() + 45 * DAY_IN_SECONDS );
		$age = array( 'source' => 'profile', 'field' => 'date_of_birth', 'operator' => 'age_verified_gte_at', 'value' => 18, 'context' => 'event.start' );
		$condition = array( 'schema_version' => 1, 'not' => $age );
		$facts = new RegistrationFactsService( new RegistrationFactsRepository( $this->db, $this->prefix ), $policy, $repo );
		try {
			$facts->load( $actor, $this->scope, $person_id, $post, 0, array( $condition ) );
			self::fail( 'An unknown age was accepted by NOT.' );
		} catch ( RuntimeException ) {
			self::assertTrue( true );
		}

		$route = '/uop/v1/admin/age-attestations';
		$body = array( 'person_id' => $person->to_string(), 'event_post_id' => $post, 'threshold_age' => 18, 'outcome' => 'gte', 'method' => 'in_person_review', 'valid_until' => $expiry, 'evidence_checked' => true, 'revoke' => false );
		wp_set_current_user( 0 );
		self::assertSame( 401, $this->post( $route, $body )->get_status() );
		wp_set_current_user( $admin );
		self::assertSame( 400, $this->post( $route, array_replace( $body, array( 'evidence_checked' => false ) ) )->get_status() );
		self::assertSame( 400, $this->post( $route, $body + array( 'actor_user_id' => 20 ) )->get_status() );
		self::assertSame( 201, $this->post( $route, $body )->get_status() );
		$context = $facts->load( $actor, $this->scope, $person_id, $post, 0, array( array( 'schema_version' => 1, 'all' => array( $age ) ) ) );
		self::assertSame( 'verified', $context['contexts']['age.verified.gte.18'] );
		self::assertTrue( ( new ConditionEngine() )->evaluate( array( 'schema_version' => 1, 'all' => array( $age ) ), array( 'profile' => array(), 'registration' => array() ), $context['contexts'] ) );
		self::assertFalse( ( new ConditionEngine() )->evaluate( $condition, array( 'profile' => array(), 'registration' => array() ), $context['contexts'] ) );

		$other_post = wp_insert_post( array( 'post_type' => 'uop_event', 'post_title' => 'Other event', 'post_status' => 'publish' ), true );
		self::assertIsInt( $other_post );
		$event_service->configure( $actor, $this->scope, $other_post, 'UTC', $now, CorrelationId::generate() );
		self::assertFalse( $repo->matches( $this->scope, $person_id, $other_post, 18, 'gte', $now, gmdate( 'Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS ) ) );
		self::assertFalse( $repo->matches( new OrgScope( $this->scope->id + 999 ), $person_id, $post, 18, 'gte', $now, gmdate( 'Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS ) ) );

		$revoke = array_replace( $body, array( 'revoke' => true, 'method' => 'revoked', 'valid_until' => $now, 'evidence_checked' => false ) );
		self::assertSame( 201, $this->post( $route, $revoke )->get_status() );
		self::assertFalse( $repo->matches( $this->scope, $person_id, $post, 18, 'gte', $now, gmdate( 'Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS ) ) );
		try {
			$facts->load( $actor, $this->scope, $person_id, $post, 0, array( array( 'schema_version' => 1, 'all' => array( $age ) ) ) );
			self::fail( 'Revoked evidence was reused.' );
		} catch ( RuntimeException ) {
			self::assertTrue( true );
		}
		self::assertSame( 409, $this->post( $route, $revoke )->get_status() );
		self::assertSame( 2, (int) $this->db->rows( 'SELECT COUNT(*) AS n FROM %i WHERE organization_id = %d', array( $this->prefix . 'age_attestations', $this->scope->id ) )[0]['n'] );
	}

	/**
	 * Send a strict JSON request into WordPress, not a mocked controller.
	 *
	 * @param string $route REST route.
	 * @param array<string,mixed> $data JSON body.
	 * @return \WP_REST_Response
	 */
	private function post( string $route, array $data ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'POST', $route );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $data ) );
		return rest_do_request( $request );
	}
}
