<?php
/**
 * M6-10 connected WordPress REST journeys with real M2-M6 persistence.
 *
 * @package UOP
 */

namespace UOP\Tests\Integration;

use PHPUnit\Framework\TestCase;
use UOP\Application\Communication\EmailTemplateCatalog;
use UOP\Application\Communication\EmailTemplateRules;
use UOP\Application\Consent\ConsentDefinitionService;
use UOP\Application\Consent\ConsentRecordService;
use UOP\Application\Event\EventService;
use UOP\Application\Form\FormService;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyService;
use UOP\Application\Query\M6OperationsReadService;
use UOP\Application\Query\M6PortalReadService;
use UOP\Application\Query\M6ReadService;
use UOP\Application\Registration\CapacityAllocationService;
use UOP\Application\Registration\CapacityLifecycleService;
use UOP\Application\Registration\EmailVerificationService;
use UOP\Application\Registration\GuestVerificationDeliveryService;
use UOP\Application\Registration\RegistrationConfigurationService;
use UOP\Application\Registration\RegistrationEligibilityService;
use UOP\Application\Registration\RegistrationFactsService;
use UOP\Application\Registration\RegistrationService;
use UOP\Application\Registration\RegistrationTransitionService;
use UOP\Application\Registration\WaitlistOfferDeliveryService;
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
use UOP\Infrastructure\Database\EmailMessageRepository;
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
use UOP\Infrastructure\Database\WaitlistRepository;
use UOP\Infrastructure\Database\WpdbConnection;
use UOP\Infrastructure\Queue\OutboxDispatcher;
use UOP\REST\M6OperationsController;
use UOP\REST\M6PortalController;
use UOP\REST\RegistrationController;

/**
 * A journey creates real WordPress users and posts, then runs REST commands
 * with the production policy, transaction, outbox and capacity implementations.
 */
final class M610EndToEndTest extends TestCase {
	private WpdbConnection $db;
	private OrgScope $scope;
	private string $prefix;

	/** Create a disposable organization and live relational schema. */
	protected function setUp(): void {
		global $wpdb;
		$this->db     = new WpdbConnection( $wpdb );
		$this->prefix = $wpdb->prefix . 'uop_';
		$manifest     = new SchemaManifest( dirname( __DIR__, 2 ) . '/schema/manifest.json' );
		foreach ( array_keys( $manifest->tables() ) as $name ) {
			$this->db->execute( 'DROP TABLE IF EXISTS %i', array( $this->prefix . $name ) );
		}
		foreach ( array( 'uop_db_version', 'uop_data_version', 'uop_migration_progress_1', 'uop_migration_status', 'uop_default_organization_id' ) as $key ) {
			delete_option( $key );
		}
		Installer::runner()->run();
		$this->scope = new OrgScope( (int) get_option( 'uop_default_organization_id' ) );
		if ( ! post_type_exists( 'uop_event' ) ) {
			register_post_type( 'uop_event', array( 'public' => true ) );
		}
		wp_set_current_user( 0 );
	}

	/**
	 * Wire the exact services used by the actual plugin, not a fake API.
	 *
	 * @return array<string,mixed> Services and WordPress users.
	 */
	private function fixture(): array {
		$admin  = wp_create_user( 'uop_m610_admin_' . bin2hex( random_bytes( 4 ) ), wp_generate_password( 24 ), 'admin_' . bin2hex( random_bytes( 4 ) ) . '@example.invalid' );
		$parent = wp_create_user( 'uop_m610_parent_' . bin2hex( random_bytes( 4 ) ), wp_generate_password( 24 ), 'parent_' . bin2hex( random_bytes( 4 ) ) . '@example.invalid' );
		self::assertIsInt( $admin );
		self::assertIsInt( $parent );
		( new \WP_User( $admin ) )->set_role( 'administrator' );
		( new \WP_User( $parent ) )->set_role( 'subscriber' );
		wp_set_current_user( $admin );

		$people      = new PersonRepository( $this->db, $this->prefix );
		$delegations = new DelegationRepository( $this->db, $this->prefix );
		$policy      = new PolicyService(
			$people,
			$delegations,
			new AssignmentRepository( $this->db, $this->prefix ),
			static fn ( int $id, string $cap ): bool => $id === $admin
		);
		$tx          = new TransactionManager( $this->db, static function ( int $delay ): void {}, static function ( \Throwable $error ): void {} );
		$audit       = new AuditWriter( $this->db, $this->prefix );
		$outbox      = new OutboxRepository( $this->db, $this->prefix );
		$reg_repo    = new RegistrationRepository( $this->db, $this->prefix );
		$facts       = new RegistrationFactsService( new RegistrationFactsRepository( $this->db, $this->prefix ), $policy );
		$eligibility = new RegistrationEligibilityService( $reg_repo, $facts );
		$reads       = new M6ReadService( $people, $delegations, new RegistrationReadRepository( $this->db, $this->prefix ), $policy );
		$docs        = new ConsentRepository( $this->db, $this->prefix );
		$records     = new ConsentRecordService( new ConsentRecordRepository( $this->db, $this->prefix ), $docs, $policy, $tx, $audit, $outbox );
		$submit      = new RegistrationService( $reg_repo, $policy, $tx, $audit, $outbox, $facts, $people, $records );
		$transitions = new RegistrationTransitionService( $reg_repo, new RegistrationStateMachine(), $policy, $tx, $audit, $outbox );
		$lifecycle   = new CapacityLifecycleService( new WaitlistRepository( $this->db, $this->prefix ), new CapacityRepository( $this->db, $this->prefix ), new RegistrationStateMachine(), $policy, $tx, $audit, $outbox, $eligibility );
		$seats       = new CapacityAllocationService( new CapacityRepository( $this->db, $this->prefix ), $reg_repo, new RegistrationStateMachine(), $policy, $tx, $audit, $outbox, $eligibility );
		$verification = new EmailVerificationService( $reg_repo, $policy, $tx, $audit, $outbox );
		$form        = new FormService( new FormRepository( $this->db, $this->prefix ), $policy, $tx, $audit, $outbox );
		$event       = new EventService( new EventRepository( $this->db, $this->prefix ), new OccurrenceRepository( $this->db, $this->prefix ), $policy, $tx, $audit, $outbox );
		$config      = new RegistrationConfigurationService( $this->db, $this->prefix, $policy, $tx, $audit, $outbox );

		return compact( 'admin', 'parent', 'people', 'delegations', 'policy', 'tx', 'audit', 'outbox', 'reg_repo', 'reads', 'docs', 'records', 'submit', 'transitions', 'lifecycle', 'seats', 'verification', 'form', 'event', 'config' );
	}

	/**
	 * Register actual command and read controllers on the WordPress REST server.
	 *
	 * @param array<string,mixed> $s            Real application services.
	 * @param GuestVerificationDeliveryService|null $guest Guest challenge dispatcher.
	 * @param WaitlistOfferDeliveryService|null $offers Offer dispatcher.
	 */
	private function routes( array $s, ?GuestVerificationDeliveryService $guest = null, ?WaitlistOfferDeliveryService $offers = null ): void {
		( new RegistrationController( $s['reads'], $s['submit'], $s['transitions'], $s['lifecycle'], $s['verification'], $guest, $offers ) )->register();
		( new M6PortalController(
			new M6PortalReadService( $s['reads'], $s['people'], new RegistrationReadRepository( $this->db, $this->prefix ), $s['policy'] )
		) )->register();
		( new M6OperationsController(
			new M6OperationsReadService(
				new M6OperationsRepository( $this->db, $this->prefix ),
				new RegistrationReadRepository( $this->db, $this->prefix ),
				new EventRepository( $this->db, $this->prefix ),
				$s['docs'],
				$s['policy']
			),
			new ConsentDefinitionService( $s['docs'], $s['policy'], $s['tx'], $s['audit'], $s['outbox'] ),
			$s['records'],
			$s['seats']
		) )->register();
	}

	/**
	 * Use genuine WP REST dispatch, including registered permission callbacks.
	 *
	 * @param string              $method HTTP verb.
	 * @param string              $path   Registered route.
	 * @param array<string,mixed> $body   JSON command.
	 * @return \WP_REST_Response HTTP envelope from WordPress.
	 */
	private function json( string $method, string $path, array $body ): \WP_REST_Response {
		$request = new \WP_REST_Request( $method, $path );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $body ) );
		return rest_do_request( $request );
	}

	/**
	 * Publish a real immutable V1 form for a public WordPress event post.
	 *
	 * @param array<string,mixed> $s       Real services.
	 * @param bool                $verify  Require email verification.
	 * @return PublicId Public event.
	 */
	private function event( array $s, bool $verify ): PublicId {
		wp_set_current_user( $s['admin'] );
		$now  = gmdate( 'Y-m-d H:i:s' );
		$post = wp_insert_post( array( 'post_type' => 'uop_event', 'post_title' => 'M610 REST Journey', 'post_status' => 'publish' ), true );
		self::assertIsInt( $post );
		$event = $s['event']->configure( new Actor( $s['admin'] ), $this->scope, $post, 'Europe/Berlin', $now, CorrelationId::generate() );
		$form  = $s['form']->create(
			new Actor( $s['admin'] ),
			$this->scope,
			'm610_journey',
			'Registration',
			'event',
			array(
				'schema_version' => 1,
				'fields' => array(
					array( 'key' => 'name', 'type' => 'text', 'label' => 'Participant name', 'required' => true ),
					array( 'key' => 'contact', 'type' => 'email', 'label' => 'Contact email', 'required' => true ),
				),
			),
			$now,
			CorrelationId::generate()
		);
		$s['form']->publish( new Actor( $s['admin'] ), $this->scope, $form, 1, $now, CorrelationId::generate() );
		$s['config']->bind( new Actor( $s['admin'] ), $this->scope, $event, $form, $now, CorrelationId::generate() );
		$this->db->execute(
			'UPDATE %i SET visibility = %s, require_email_verification = %d WHERE organization_id = %d AND public_id = %s',
			array( $this->prefix . 'event_settings', 'public', $verify ? 1 : 0, $this->scope->id, $event->to_binary() )
		);
		return $event;
	}

	/**
	 * Adult self-service, delegated child, revocation and manager decision
	 * traverse real REST commands with live WordPress actor switching.
	 */
	public function test_adult_child_admin_audit_and_revocation_via_rest(): void {
		$s     = $this->fixture();
		$event = $this->event( $s, false );
		$this->routes( $s );
		$now    = gmdate( 'Y-m-d H:i:s' );
		$adult  = PublicId::generate();
		$child  = PublicId::generate();
		$s['people']->create( $this->scope, $adult, 'Parent Person', 'shared@example.invalid', $now );
		$s['people']->create( $this->scope, $child, 'Minor Child', 'shared@example.invalid', $now );
		self::assertTrue( $s['people']->link( $this->scope, $adult, $s['parent'], $now ) );
		wp_set_current_user( $s['parent'] );
		$subjects = rest_do_request( new \WP_REST_Request( 'GET', '/uop/v1/me/portal' ) );
		self::assertSame( 200, $subjects->get_status() );
		self::assertSame( array( $adult->to_string() ), array_column( $subjects->get_data()['items'], 'public_id' ) );

		$child_cmd = array(
			'person_id' => $child->to_string(),
			'event_id' => $event->to_string(),
			'command_id' => PublicId::generate()->to_string(),
			'fields' => array( 'name' => 'Minor', 'contact' => 'shared@example.invalid' ),
		);
		self::assertSame( 409, $this->json( 'POST', '/uop/v1/registrations', $child_cmd )->get_status(), 'Matching family email is not a delegation.' );

		$adult_cmd = array(
			'person_id' => $adult->to_string(),
			'event_id' => $event->to_string(),
			'command_id' => PublicId::generate()->to_string(),
			'fields' => array( 'name' => 'Parent', 'contact' => 'shared@example.invalid' ),
		);
		$invalid = $adult_cmd;
		$invalid['command_id'] = PublicId::generate()->to_string();
		$invalid['fields'] = array( 'contact' => 'shared@example.invalid' );
		self::assertSame( 422, $this->json( 'POST', '/uop/v1/registrations', $invalid )->get_status() );
		$submitted = $this->json( 'POST', '/uop/v1/registrations', $adult_cmd );
		self::assertSame( 201, $submitted->get_status(), (string) wp_json_encode( $submitted->get_data() ) );
		self::assertSame( $submitted->get_data()['public_id'], $this->json( 'POST', '/uop/v1/registrations', $adult_cmd )->get_data()['public_id'] );
		$adult_id = $submitted->get_data()['public_id'];
		$altered = $adult_cmd;
		$altered['fields']['name'] = 'Changed after success';
		self::assertSame( 409, $this->json( 'POST', '/uop/v1/registrations', $altered )->get_status() );

		$child_row = $s['people']->find( $this->scope, $child );
		$grant_id  = PublicId::generate();
		$s['delegations']->grant( $this->scope, $grant_id, $s['parent'], (int) $child_row['id'], 'registration_manage', 'organization', 0, null, $now );
		$after_grant = rest_do_request( new \WP_REST_Request( 'GET', '/uop/v1/me/portal' ) );
		self::assertContains( $child->to_string(), array_column( $after_grant->get_data()['items'], 'public_id' ) );
		$child_response = $this->json( 'POST', '/uop/v1/registrations', $child_cmd );
		self::assertSame( 201, $child_response->get_status(), (string) wp_json_encode( $child_response->get_data() ) );
		$child_id = $child_response->get_data()['public_id'];
		self::assertTrue( $s['delegations']->revoke( $this->scope, $grant_id, $now ) );
		self::assertSame( 404, rest_do_request( new \WP_REST_Request( 'GET', '/uop/v1/registrations/' . $child_id ) )->get_status() );
		self::assertSame( 404, $this->json( 'POST', '/uop/v1/registrations/' . $child_id . '/cancel', array( 'command_id' => PublicId::generate()->to_string() ) )->get_status() );
		self::assertSame( 409, $this->json( 'POST', '/uop/v1/registrations', array_replace( $child_cmd, array( 'command_id' => PublicId::generate()->to_string() ) ) )->get_status() );
		$after_revoke = rest_do_request( new \WP_REST_Request( 'GET', '/uop/v1/me/portal' ) );
		self::assertSame( array( $adult->to_string() ), array_column( $after_revoke->get_data()['items'], 'public_id' ) );

		wp_set_current_user( $s['admin'] );
		$review = $this->json( 'POST', '/uop/v1/registrations/' . $adult_id . '/transitions', array( 'command_id' => PublicId::generate()->to_string(), 'target' => 'review' ) );
		self::assertSame( 200, $review->get_status(), (string) wp_json_encode( $review->get_data() ) );
		$bucket = $s['seats']->create_general_bucket( new Actor( $s['admin'] ), $this->scope, $event, 1, $now, CorrelationId::generate() );
		$accepted = $this->json( 'POST', '/uop/v1/registrations/' . $adult_id . '/allocation', array( 'bucket_id' => $bucket->to_string(), 'command_id' => PublicId::generate()->to_string() ) );
		self::assertSame( 200, $accepted->get_status(), (string) wp_json_encode( $accepted->get_data() ) );
		self::assertSame( 'accepted', $accepted->get_data()['status'] );
		$reject = $this->json( 'POST', '/uop/v1/registrations/' . $child_id . '/transitions', array( 'command_id' => PublicId::generate()->to_string(), 'target' => 'rejected' ) );
		self::assertSame( 200, $reject->get_status() );
		$audit = rest_do_request( new \WP_REST_Request( 'GET', '/uop/v1/audit' ) );
		self::assertSame( 200, $audit->get_status() );
		self::assertNotEmpty( $audit->get_data()['items'] );
		self::assertArrayNotHasKey( 'actor_user_id', $audit->get_data()['items'][0] );
		$history = $this->db->rows( 'SELECT to_status FROM %i WHERE organization_id = %d', array( $this->prefix . 'registration_history', $this->scope->id ) );
		self::assertContains( 'accepted', array_column( $history, 'to_status' ) );
		self::assertContains( 'rejected', array_column( $history, 'to_status' ) );
	}

	/**
	 * Full public REST and queued-mail journey: guest intake, real outbox,
	 * private one-time contact link, manager seats and guest offer acceptance.
	 */
	public function test_guest_verification_capacity_waitlist_offer_via_real_rest(): void {
		$s       = $this->fixture();
		$event   = $this->event( $s, true );
		$catalog = new EmailTemplateCatalog();
		$rules   = new EmailTemplateRules( $catalog );
		$guest   = new GuestVerificationDeliveryService(
			$s['reg_repo'],
			new EmailMessageRepository( $this->db, $this->prefix ),
			$catalog,
			$rules,
			$s['tx'],
			$s['audit'],
			$s['outbox']
		);
		$offers = new WaitlistOfferDeliveryService( new EmailMessageRepository( $this->db, $this->prefix ), $catalog, $rules, $s['tx'] );
		$s['lifecycle']->set_offer_delivery( $offers );
		$this->routes( $s, $guest, $offers );
		$enabled = static fn (): bool => true;
		$secure  = static fn ( string $url ): string => str_replace( 'http://', 'https://', $url );
		$https   = $_SERVER['HTTPS'] ?? null;
		try {
			$_SERVER['HTTPS'] = 'on';
			add_filter( 'home_url', $secure );
			wp_set_current_user( 0 );
			$anonymous = array(
				'event_id' => $event->to_string(),
				'command_id' => PublicId::generate()->to_string(),
				'fields' => array( 'name' => 'Guest', 'contact' => 'm610-guest@example.invalid' ),
			);
			self::assertSame( 503, $this->json( 'POST', '/uop/v1/registrations/guest', $anonymous )->get_status() );
			add_filter( 'uop_guest_verification_enabled', $enabled );
			add_filter( 'uop_waitlist_offer_enabled', $enabled );
			self::assertTrue( $guest->ready() );
			self::assertTrue( $offers->ready() );
			$received = $this->json( 'POST', '/uop/v1/registrations/guest', $anonymous );
			self::assertSame( 202, $received->get_status(), (string) wp_json_encode( $received->get_data() ) );
			self::assertSame( array( 'status' => 'received' ), $received->get_data() );
			self::assertSame( 202, $this->json( 'POST', '/uop/v1/registrations/guest', $anonymous )->get_status() );
			$rows = $this->db->rows(
				'SELECT public_id, email_verified_at FROM %i WHERE organization_id = %d AND source = %s',
				array( $this->prefix . 'registrations', $this->scope->id, 'guest' )
			);
			self::assertCount( 1, $rows );
			self::assertNull( $rows[0]['email_verified_at'] );
			$guest_id = PublicId::from_binary( (string) $rows[0]['public_id'] );
			$events = $this->db->rows(
				'SELECT event_uuid FROM %i WHERE organization_id = %d AND event_name = %s',
				array( $this->prefix . 'domain_events', $this->scope->id, 'registration.email_verification_required' )
			);
			self::assertCount( 1, $events );
			$listener = array( $guest, 'on_scoped_event' );
			add_action( 'uop_scoped_domain_event', $listener, 10, 2 );
			try {
				( new OutboxDispatcher( $this->db, $s['outbox'], $this->prefix ) )->consume( $this->scope->id, PublicId::from_binary( (string) $events[0]['event_uuid'] )->to_string() );
			} finally {
				remove_action( 'uop_scoped_domain_event', $listener, 10 );
			}
			$mail = $this->db->rows(
				'SELECT body_text, recipient FROM %i WHERE organization_id = %d AND template_key = %s',
				array( $this->prefix . 'email_messages', $this->scope->id, 'email_verification' )
			);
			self::assertCount( 1, $mail );
			self::assertSame( 'm610-guest@example.invalid', $mail[0]['recipient'] );
			self::assertSame( 1, preg_match( '/#registration_id=([a-f0-9-]{36})&token=([a-f0-9]{64})/', $mail[0]['body_text'], $match ) );
			self::assertSame( $guest_id->to_string(), $match[1] );
			self::assertSame( 202, $this->json( 'POST', '/uop/v1/registration-verifications', array( 'registration_id' => $match[1], 'token' => str_repeat( '0', 64 ) ) )->get_status() );
			self::assertSame( 202, $this->json( 'POST', '/uop/v1/registration-verifications', array( 'registration_id' => $match[1], 'token' => $match[2] ) )->get_status() );
			self::assertSame( 202, $this->json( 'POST', '/uop/v1/registration-verifications', array( 'registration_id' => $match[1], 'token' => $match[2] ) )->get_status() );
			$verified = $this->db->rows( 'SELECT email_verified_at, email_verification_token_hash FROM %i WHERE organization_id = %d AND public_id = %s', array( $this->prefix . 'registrations', $this->scope->id, $guest_id->to_binary() ) );
			self::assertNotNull( $verified[0]['email_verified_at'] );
			self::assertNull( $verified[0]['email_verification_token_hash'] );

			wp_set_current_user( $s['admin'] );
			$first_person = PublicId::generate();
			$now          = gmdate( 'Y-m-d H:i:s' );
			$s['people']->create( $this->scope, $first_person, 'Existing member', null, $now );
			$first = $this->json( 'POST', '/uop/v1/registrations', array(
				'person_id' => $first_person->to_string(),
				'event_id' => $event->to_string(),
				'command_id' => PublicId::generate()->to_string(),
				'fields' => array( 'name' => 'Existing member', 'contact' => 'm610-member@example.invalid' ),
			) );
			self::assertSame( 201, $first->get_status() );
			$first_id = PublicId::from_string( $first->get_data()['public_id'] );
			$challenge = $s['verification']->issue( new Actor( $s['admin'] ), $this->scope, $first_id, $now, CorrelationId::generate() );
			wp_set_current_user( 0 );
			self::assertSame( 202, $this->json( 'POST', '/uop/v1/registration-verifications', array( 'registration_id' => $first_id->to_string(), 'token' => $challenge['token'] ) )->get_status() );

			wp_set_current_user( $s['admin'] );
			$bucket = $s['seats']->create_general_bucket( new Actor( $s['admin'] ), $this->scope, $event, 1, $now, CorrelationId::generate() );
			$allocation = static fn ( string $id ): array => array( 'bucket_id' => $bucket->to_string(), 'command_id' => PublicId::generate()->to_string() );
			$first_seat = $this->json( 'POST', '/uop/v1/registrations/' . $first_id->to_string() . '/allocation', $allocation( $first_id->to_string() ) );
			self::assertSame( 'accepted', $first_seat->get_data()['status'] );
			$queue_seat = $this->json( 'POST', '/uop/v1/registrations/' . $guest_id->to_string() . '/allocation', $allocation( $guest_id->to_string() ) );
			self::assertSame( 200, $queue_seat->get_status() );
			self::assertSame( 'waitlisted', $queue_seat->get_data()['status'] );
			$cancel = $this->json( 'POST', '/uop/v1/registrations/' . $first_id->to_string() . '/cancel', array( 'command_id' => PublicId::generate()->to_string() ) );
			self::assertSame( 200, $cancel->get_status() );
			$offer = $s['lifecycle']->offer_next( new Actor( $s['admin'] ), $this->scope, $bucket, PublicId::generate(), gmdate( 'Y-m-d H:i:s' ), CorrelationId::generate() );
			self::assertNotNull( $offer );
			$sent = $this->db->rows( 'SELECT recipient, body_text FROM %i WHERE organization_id = %d AND template_key = %s', array( $this->prefix . 'email_messages', $this->scope->id, 'waitlist_offer' ) );
			self::assertCount( 1, $sent );
			self::assertSame( 'm610-guest@example.invalid', $sent[0]['recipient'] );
			self::assertSame( 1, preg_match( '/#offer_id=([a-f0-9-]{36})&token=([a-f0-9]{64})/', $sent[0]['body_text'], $offer_match ) );
			wp_set_current_user( 0 );
			$accept = array( 'offer_id' => $offer_match[1], 'token' => $offer_match[2], 'command_id' => PublicId::generate()->to_string() );
			self::assertSame( 202, $this->json( 'POST', '/uop/v1/waitlist-offers/guest-accept', $accept )->get_status() );
			self::assertSame( 202, $this->json( 'POST', '/uop/v1/waitlist-offers/guest-accept', $accept )->get_status() );
			$final = $this->db->rows( 'SELECT status FROM %i WHERE organization_id = %d AND public_id = %s', array( $this->prefix . 'registrations', $this->scope->id, $guest_id->to_binary() ) );
			self::assertSame( 'accepted', $final[0]['status'] );
			self::assertSame( 1, (int) $this->db->rows( 'SELECT COUNT(*) AS n FROM %i WHERE organization_id = %d AND status = %s', array( $this->prefix . 'capacity_claims', $this->scope->id, 'confirmed' ) )[0]['n'] );
			$offer_status = $this->db->rows( 'SELECT status FROM %i WHERE organization_id = %d AND public_id = %s', array( $this->prefix . 'waitlist_offers', $this->scope->id, PublicId::from_string( $offer_match[1] )->to_binary() ) );
			self::assertSame( 'accepted', $offer_status[0]['status'] );
			$_SERVER['HTTPS'] = 'off';
			self::assertSame( 503, $this->json( 'POST', '/uop/v1/waitlist-offers/guest-accept', $accept )->get_status() );
		} finally {
			remove_filter( 'uop_guest_verification_enabled', $enabled );
			remove_filter( 'uop_waitlist_offer_enabled', $enabled );
			remove_filter( 'home_url', $secure );
			if ( null === $https ) {
				unset( $_SERVER['HTTPS'] );
			} else {
				$_SERVER['HTTPS'] = $https;
			}
			wp_set_current_user( 0 );
		}
	}
}
