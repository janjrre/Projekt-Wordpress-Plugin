<?php
/**
 * M6 API regression: actor isolation, delegation, versioning and capacity-safe calls.
 *
 * @package UOP
 */

namespace UOP\Tests\Integration;

use PHPUnit\Framework\TestCase;
use UOP\Application\Event\EventService;
use UOP\Application\Event\PostCommitPublisher;
use UOP\Application\Form\FormService;
use UOP\Application\Identity\PersonService;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyService;
use UOP\Application\Query\M6ReadService;
use UOP\Application\Registration\CapacityLifecycleService;
use UOP\Application\Registration\EmailVerificationService;
use UOP\Application\Registration\RegistrationConfigurationService;
use UOP\Application\Registration\RegistrationEligibilityService;
use UOP\Application\Registration\RegistrationFactsService;
use UOP\Application\Registration\RegistrationService;
use UOP\Application\Registration\RegistrationTransitionService;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Core\TransactionManager;
use UOP\Domain\Organization\OrgScope;
use UOP\Domain\Registrations\RegistrationStateMachine;
use UOP\Infrastructure\Database\AssignmentRepository;
use UOP\Infrastructure\Database\AuditWriter;
use UOP\Infrastructure\Database\CapacityRepository;
use UOP\Infrastructure\Database\DelegationRepository;
use UOP\Infrastructure\Database\EventRepository;
use UOP\Infrastructure\Database\FormRepository;
use UOP\Infrastructure\Database\Installer;
use UOP\Infrastructure\Database\OccurrenceRepository;
use UOP\Infrastructure\Database\OutboxRepository;
use UOP\Infrastructure\Database\PersonRepository;
use UOP\Infrastructure\Database\RegistrationFactsRepository;
use UOP\Infrastructure\Database\RegistrationRepository;
use UOP\Infrastructure\Database\SchemaManifest;
use UOP\Infrastructure\Database\WaitlistRepository;
use UOP\Infrastructure\Database\WpdbConnection;
use UOP\REST\PeopleController;
use UOP\REST\RegistrationController;

/** Check that shared family email is not an identity or authorization key. */
final class M6PeopleRegistrationTest extends TestCase {
	private WpdbConnection $db;
	private OrgScope $scope;
	private string $prefix;

	/** Rebuild disposable tables for each authorization scenario. */
	protected function setUp(): void {
		global $wpdb;
		$this->db = new WpdbConnection( $wpdb );
		$this->prefix = $wpdb->prefix . 'uop_';
		$manifest = new SchemaManifest( dirname( __DIR__, 2 ) . '/schema/manifest.json' );
		foreach ( array_keys( $manifest->tables() ) as $table ) {
			$this->db->execute( 'DROP TABLE IF EXISTS %i', array( $this->prefix . $table ) );
		}
		foreach ( array( 'uop_db_version', 'uop_data_version', 'uop_migration_progress_1', 'uop_migration_status', 'uop_default_organization_id' ) as $key ) {
			delete_option( $key );
		}
		Installer::runner()->run();
		$this->scope = new OrgScope( (int) get_option( 'uop_default_organization_id' ) );
		wp_set_current_user( 0 );
	}

	/**
	 * Build genuine M2-M4 services; controllers cannot use fake policies.
	 *
	 * @return array<string, mixed>
	 */
	private function fixture(): array {
		$admin = wp_create_user( 'uop_m6_admin_' . bin2hex( random_bytes( 4 ) ), wp_generate_password( 20 ), 'admin_' . bin2hex( random_bytes( 4 ) ) . '@example.invalid' );
		$parent = wp_create_user( 'uop_m6_parent_' . bin2hex( random_bytes( 4 ) ), wp_generate_password( 20 ), 'parent_' . bin2hex( random_bytes( 4 ) ) . '@example.invalid' );
		self::assertIsInt( $admin );
		self::assertIsInt( $parent );
		( new \WP_User( $admin ) )->set_role( 'administrator' );
		( new \WP_User( $parent ) )->set_role( 'subscriber' );

		$people = new PersonRepository( $this->db, $this->prefix );
		$delegations = new DelegationRepository( $this->db, $this->prefix );
		$policy = new PolicyService(
			$people,
			$delegations,
			new AssignmentRepository( $this->db, $this->prefix ),
			static fn ( int $id, string $cap ): bool => $id === $admin
		);
		$tx = new TransactionManager( $this->db, static function ( int $n ): void {}, static function ( \Throwable $error ): void {} );
		$audit = new AuditWriter( $this->db, $this->prefix );
		$outbox = new OutboxRepository( $this->db, $this->prefix );
		$publisher = new PostCommitPublisher( $tx );
		$person_service = new PersonService( $people, $policy, $tx, $audit, $outbox, $publisher );
		$reads = new M6ReadService( $people, $delegations, $this->db, $this->prefix, $policy );
		$registrations = new RegistrationRepository( $this->db, $this->prefix );
		$facts = new RegistrationFactsService( new RegistrationFactsRepository( $this->db, $this->prefix ), $policy );
		$eligibility = new RegistrationEligibilityService( $registrations, $facts );
		$submit = new RegistrationService( $registrations, $policy, $tx, $audit, $outbox, $facts, $people );
		$transitions = new RegistrationTransitionService( $registrations, new RegistrationStateMachine(), $policy, $tx, $audit, $outbox );
		$capacity = new CapacityLifecycleService(
			new WaitlistRepository( $this->db, $this->prefix ),
			new CapacityRepository( $this->db, $this->prefix ),
			new RegistrationStateMachine(),
			$policy,
			$tx,
			$audit,
			$outbox,
			$eligibility
		);
		$verification = new EmailVerificationService( $registrations, $policy, $tx, $audit, $outbox );
		( new PeopleController( $reads, $person_service ) )->register();
		( new RegistrationController( $reads, $submit, $transitions, $capacity, $verification ) )->register();
		return compact( 'admin', 'parent', 'people', 'delegations', 'policy', 'tx', 'audit', 'outbox', 'person_service', 'reads', 'submit' );
	}

	/**
	 * Build a JSON request against the actual WordPress REST dispatch.
	 *
	 * @param string               $method HTTP method.
	 * @param string               $path   UOP route.
	 * @param array<string, mixed> $body   JSON body.
	 * @return \WP_REST_Response
	 */
	private function json( string $method, string $path, array $body ): \WP_REST_Response {
		$request = new \WP_REST_Request( $method, $path );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );
		return rest_do_request( $request );
	}

	/** Unlinked child with the same email remains inaccessible until delegated. */
	public function test_family_email_never_merges_people_and_profile_view_does_not_grant_edits(): void {
		$s = $this->fixture();
		$now = gmdate( 'Y-m-d H:i:s' );
		$adult = PublicId::generate();
		$child = PublicId::generate();
		$s['people']->create( $this->scope, $adult, 'Parent', 'family@example.invalid', $now );
		$s['people']->create( $this->scope, $child, 'Child', 'family@example.invalid', $now );
		self::assertTrue( $s['people']->link( $this->scope, $adult, $s['parent'], $now ) );
		wp_set_current_user( $s['parent'] );

		$mine = rest_do_request( new \WP_REST_Request( 'GET', '/uop/v1/me/persons' ) );
		self::assertSame( 200, $mine->get_status() );
		self::assertCount( 1, $mine->get_data()['items'] );
		self::assertSame( $adult->to_string(), $mine->get_data()['items'][0]['public_id'] );
		$denied = rest_do_request( new \WP_REST_Request( 'GET', '/uop/v1/people/' . $child->to_string() ) );
		self::assertSame( 404, $denied->get_status() );

		$child_row = $s['people']->find( $this->scope, $child );
		$s['delegations']->grant( $this->scope, PublicId::generate(), $s['parent'], (int) $child_row['id'], 'profile_view', 'organization', 0, null, $now );
		$mine = rest_do_request( new \WP_REST_Request( 'GET', '/uop/v1/me/persons' ) );
		self::assertCount( 2, $mine->get_data()['items'] );
		$visible = rest_do_request( new \WP_REST_Request( 'GET', '/uop/v1/people/' . $child->to_string() ) );
		self::assertSame( 200, $visible->get_status() );
		self::assertArrayNotHasKey( 'primary_email', $visible->get_data() );

		$forbidden = $this->json( 'PATCH', '/uop/v1/people/' . $child->to_string(), array( 'display_name' => 'Not allowed', 'version' => 1 ) );
		self::assertNotSame( 200, $forbidden->get_status() );
		self::assertSame( 'Child', $s['people']->find( $this->scope, $child )['display_name'] );
		$s['delegations']->grant( $this->scope, PublicId::generate(), $s['parent'], (int) $child_row['id'], 'profile_edit', 'organization', 0, null, $now );
		$updated = $this->json( 'PATCH', '/uop/v1/people/' . $child->to_string(), array( 'display_name' => 'Updated Child', 'version' => 1 ) );
		self::assertSame( 200, $updated->get_status(), wp_json_encode( $updated->get_data() ) );
		self::assertSame( 'Updated Child', $updated->get_data()['display_name'] );
		self::assertSame( 2, $updated->get_data()['version'] );
		$stale = $this->json( 'PATCH', '/uop/v1/people/' . $child->to_string(), array( 'display_name' => 'Stale', 'version' => 1 ) );
		self::assertSame( 409, $stale->get_status() );
		$injected = $this->json( 'PATCH', '/uop/v1/people/' . $child->to_string(), array( 'display_name' => 'Injected', 'version' => 2, 'wp_user_id' => $s['parent'] ) );
		self::assertSame( 400, $injected->get_status() );
		self::assertNull( $s['people']->find( $this->scope, $child )['wp_user_id'] );
	}

	/** Submission, object reads, privilege escalation and cancellation golden path. */
	public function test_rest_submission_is_isolated_and_state_commands_use_m4(): void {
		$s = $this->fixture();
		$now = gmdate( 'Y-m-d H:i:s' );
		$adult = PublicId::generate();
		$child = PublicId::generate();
		$s['people']->create( $this->scope, $adult, 'Parent', 'family@example.invalid', $now );
		$s['people']->create( $this->scope, $child, 'Child', 'family@example.invalid', $now );
		$s['people']->link( $this->scope, $adult, $s['parent'], $now );
		if ( ! post_type_exists( 'uop_event' ) ) {
			register_post_type( 'uop_event', array( 'public' => true ) );
		}
		wp_set_current_user( $s['admin'] );
		$post = wp_insert_post( array( 'post_type' => 'uop_event', 'post_title' => 'REST Event', 'post_status' => 'publish' ), true );
		self::assertIsInt( $post );
		$event_service = new EventService( new EventRepository( $this->db, $this->prefix ), new OccurrenceRepository( $this->db, $this->prefix ), $s['policy'], $s['tx'], $s['audit'], $s['outbox'] );
		$form_service = new FormService( new FormRepository( $this->db, $this->prefix ), $s['policy'], $s['tx'], $s['audit'], $s['outbox'] );
		$event = $event_service->configure( new Actor( $s['admin'] ), $this->scope, $post, 'Europe/Berlin', $now, CorrelationId::generate() );
		$form = $form_service->create( new Actor( $s['admin'] ), $this->scope, 'm6_rest', 'M6 REST', 'event', array( 'schema_version' => 1, 'fields' => array( array( 'key' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true ) ) ), $now, CorrelationId::generate() );
		$form_service->publish( new Actor( $s['admin'] ), $this->scope, $form, 1, $now, CorrelationId::generate() );
		( new RegistrationConfigurationService( $this->db, $this->prefix, $s['policy'], $s['tx'], $s['audit'], $s['outbox'] ) )->bind( new Actor( $s['admin'] ), $this->scope, $event, $form, $now, CorrelationId::generate() );

		wp_set_current_user( $s['parent'] );
		$submitted = $this->json( 'POST', '/uop/v1/registrations', array( 'person_id' => $adult->to_string(), 'event_id' => $event->to_string(), 'command_id' => PublicId::generate()->to_string(), 'fields' => array( 'name' => 'Parent' ) ) );
		self::assertSame( 201, $submitted->get_status(), wp_json_encode( $submitted->get_data() ) );
		$id = $submitted->get_data()['public_id'];
		$mine = rest_do_request( new \WP_REST_Request( 'GET', '/uop/v1/registrations' ) );
		self::assertSame( 200, $mine->get_status() );
		self::assertCount( 1, $mine->get_data()['items'] );
		self::assertSame( $id, $mine->get_data()['items'][0]['public_id'] );
		self::assertArrayNotHasKey( 'id', $mine->get_data()['items'][0] );
		self::assertArrayNotHasKey( 'contact_email', $mine->get_data()['items'][0] );

		wp_set_current_user( $s['admin'] );
		$child_registration = $s['submit']->submit( new Actor( $s['admin'] ), $this->scope, $child, $event, null, PublicId::generate(), array( 'name' => 'Child' ), $now, CorrelationId::generate() );
		wp_set_current_user( $s['parent'] );
		self::assertSame( 404, rest_do_request( new \WP_REST_Request( 'GET', '/uop/v1/registrations/' . $child_registration->to_string() ) )->get_status() );

		$child_row = $s['people']->find( $this->scope, $child );
		$s['delegations']->grant( $this->scope, PublicId::generate(), $s['parent'], (int) $child_row['id'], 'profile_view', 'organization', 0, null, $now );
		self::assertSame( 404, rest_do_request( new \WP_REST_Request( 'GET', '/uop/v1/registrations/' . $child_registration->to_string() ) )->get_status() );
		$s['delegations']->grant( $this->scope, PublicId::generate(), $s['parent'], (int) $child_row['id'], 'registration_manage', 'organization', 0, null, $now );
		self::assertSame( 200, rest_do_request( new \WP_REST_Request( 'GET', '/uop/v1/registrations/' . $child_registration->to_string() ) )->get_status() );
		$scoped = rest_do_request( new \WP_REST_Request( 'GET', '/uop/v1/registrations?person_id=' . $child->to_string() ) );
		self::assertSame( 200, $scoped->get_status() );
		self::assertCount( 1, $scoped->get_data()['items'] );
		self::assertSame( $child_registration->to_string(), $scoped->get_data()['items'][0]['public_id'] );

		$no_review = $this->json( 'POST', '/uop/v1/registrations/' . $child_registration->to_string() . '/transitions', array( 'target' => 'review', 'command_id' => PublicId::generate()->to_string() ) );
		self::assertNotSame( 200, $no_review->get_status() );
		$cancel = $this->json( 'POST', '/uop/v1/registrations/' . $id . '/cancel', array( 'command_id' => PublicId::generate()->to_string() ) );
		self::assertSame( 200, $cancel->get_status(), wp_json_encode( $cancel->get_data() ) );
		self::assertSame( 'cancelled', $cancel->get_data()['status'] );
		$after = rest_do_request( new \WP_REST_Request( 'GET', '/uop/v1/registrations/' . $id ) );
		self::assertSame( 'cancelled', $after->get_data()['status'] );

		wp_set_current_user( $s['admin'] );
		$review = $this->json( 'POST', '/uop/v1/registrations/' . $child_registration->to_string() . '/transitions', array( 'target' => 'review', 'command_id' => PublicId::generate()->to_string() ) );
		self::assertSame( 200, $review->get_status(), wp_json_encode( $review->get_data() ) );
		self::assertSame( 'review', $review->get_data()['status'] );
		$history = $this->db->rows( 'SELECT id FROM %i WHERE to_status = %s', array( $this->prefix . 'registration_history', 'cancelled' ) );
		self::assertCount( 1, $history );
	}

	/** Token errors have no subject disclosure and guest writes remain closed. */
	public function test_guest_gate_and_generic_verification_response(): void {
		$this->fixture();
		wp_set_current_user( 0 );
		$guest = $this->json( 'POST', '/uop/v1/registrations', array( 'event_id' => PublicId::generate()->to_string() ) );
		self::assertSame( 503, $guest->get_status() );
		$unknown = PublicId::generate();
		$secret = str_repeat( 'a', 64 );
		$body = array( 'registration_id' => $unknown->to_string(), 'token' => $secret );
		$check = $this->json( 'POST', '/uop/v1/registration-verifications', $body );
		self::assertSame( 202, $check->get_status() );
		self::assertSame( array( 'status' => 'received' ), $check->get_data() );
		self::assertSame( 'private, no-store, max-age=0', $check->get_headers()['Cache-Control'] );
		for ( $i = 0; $i < 9; ++$i ) {
			$this->json( 'POST', '/uop/v1/registration-verifications', $body );
		}
		self::assertSame( 429, $this->json( 'POST', '/uop/v1/registration-verifications', $body )->get_status() );
		delete_transient( 'uop_verify_' . hash( 'sha256', $this->scope->id . ':' . $unknown->to_string() ) );
	}
}
