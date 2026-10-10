<?php
/**
 * M6-06 dynamic block contract, public visibility, scoped data and privacy.
 *
 * @package UOP
 */

namespace UOP\Tests\Integration;

use PHPUnit\Framework\TestCase;
use UOP\Application\Communication\EmailTemplateCatalog;
use UOP\Application\Communication\EmailTemplateRules;
use UOP\Application\Event\DomainEventDto;
use UOP\Application\Registration\GuestVerificationDeliveryService;
use UOP\Application\Registration\EmailVerificationService;
use UOP\Application\Registration\CapacityLifecycleService;
use UOP\Application\Registration\RegistrationEligibilityService;
use UOP\Infrastructure\Database\EmailMessageRepository;
use UOP\Infrastructure\Database\CapacityRepository;
use UOP\Infrastructure\Database\WaitlistRepository;
use UOP\Infrastructure\Queue\EmailDeliveryWorker;
use UOP\Infrastructure\Queue\OutboxDispatcher;
use UOP\REST\RegistrationController;
use UOP\Application\Event\EventService;
use UOP\Application\Form\FormService;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyService;
use UOP\Application\Query\M6ReadService;
use UOP\Application\Query\M6PortalReadService;
use UOP\Application\Registration\RegistrationConfigurationService;
use UOP\Application\Registration\RegistrationFactsService;
use UOP\Application\Registration\RegistrationService;
use UOP\Application\Registration\RegistrationTransitionService;
use UOP\Blocks\M6Blocks;
use UOP\Blocks\M6InteractivityAdapter;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Core\TransactionManager;
use UOP\Domain\Organization\OrgScope;
use UOP\Domain\Registrations\RegistrationStateMachine;
use UOP\Infrastructure\Database\AssignmentRepository;
use UOP\Infrastructure\Database\AuditWriter;
use UOP\Infrastructure\Database\DelegationRepository;
use UOP\Infrastructure\Database\EventRepository;
use UOP\Infrastructure\Database\FormRepository;
use UOP\Infrastructure\Database\Installer;
use UOP\Infrastructure\Database\OccurrenceRepository;
use UOP\Infrastructure\Database\OutboxRepository;
use UOP\Infrastructure\Database\PersonRepository;
use UOP\Infrastructure\Database\RegistrationFactsRepository;
use UOP\Infrastructure\Database\RegistrationReadRepository;
use UOP\Infrastructure\Database\RegistrationRepository;
use UOP\Infrastructure\Database\SchemaManifest;
use UOP\Infrastructure\Database\WpdbConnection;
use UOP\REST\M6PortalController;

/** Prove public content and private account content never share authorization. */
final class M6BlocksTest extends TestCase {
	private WpdbConnection $db;
	private OrgScope $scope;
	private string $prefix;

	/** Install actual tenant-scoped persistence and reset user context. */
	protected function setUp(): void {
		global $wpdb;
		$this->db     = new WpdbConnection( $wpdb );
		$this->prefix = $wpdb->prefix . 'uop_';
		$manifest     = new SchemaManifest( dirname( __DIR__, 2 ) . '/schema/manifest.json' );
		foreach ( array_keys( $manifest->tables() ) as $table ) {
			$this->db->execute( 'DROP TABLE IF EXISTS %i', array( $this->prefix . $table ) );
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
	 * Compose real public projections, audited commands and private reads.
	 *
	 * @return array<string,mixed>
	 */
	private function fixture(): array {
		$admin = wp_create_user( 'uop_m606_admin_' . bin2hex( random_bytes( 4 ) ), wp_generate_password( 24 ), 'admin_' . bin2hex( random_bytes( 4 ) ) . '@example.invalid' );
		$self  = wp_create_user( 'uop_m606_self_' . bin2hex( random_bytes( 4 ) ), wp_generate_password( 24 ), 'self_' . bin2hex( random_bytes( 4 ) ) . '@example.invalid' );
		$other = wp_create_user( 'uop_m606_other_' . bin2hex( random_bytes( 4 ) ), wp_generate_password( 24 ), 'other_' . bin2hex( random_bytes( 4 ) ) . '@example.invalid' );
		self::assertIsInt( $admin );
		self::assertIsInt( $self );
		self::assertIsInt( $other );
		( new \WP_User( $admin ) )->set_role( 'administrator' );
		( new \WP_User( $self ) )->set_role( 'subscriber' );
		( new \WP_User( $other ) )->set_role( 'subscriber' );
		$actor       = new Actor( $admin );
		$people      = new PersonRepository( $this->db, $this->prefix );
		$delegations = new DelegationRepository( $this->db, $this->prefix );
		$policy      = new PolicyService(
			$people,
			$delegations,
			new AssignmentRepository( $this->db, $this->prefix ),
			static fn ( int $id, string $cap ): bool => $id === $admin
		);
		$events      = new EventRepository( $this->db, $this->prefix );
		$occurrences = new OccurrenceRepository( $this->db, $this->prefix );
		$forms       = new FormRepository( $this->db, $this->prefix );
		$reads       = new M6ReadService( $people, $delegations, new RegistrationReadRepository( $this->db, $this->prefix ), $policy );
		$blocks      = new M6Blocks( $events, $occurrences, $forms, $reads );
		$tx          = new TransactionManager( $this->db, static function ( int $delay ): void {}, static function ( \Throwable $error ): void {} );
		$audit       = new AuditWriter( $this->db, $this->prefix );
		$outbox      = new OutboxRepository( $this->db, $this->prefix );
		$event       = new EventService( $events, $occurrences, $policy, $tx, $audit, $outbox );
		$form        = new FormService( $forms, $policy, $tx, $audit, $outbox );
		$config      = new RegistrationConfigurationService( $this->db, $this->prefix, $policy, $tx, $audit, $outbox );
		$submit      = new RegistrationService( new RegistrationRepository( $this->db, $this->prefix ), $policy, $tx, $audit, $outbox, new RegistrationFactsService( new RegistrationFactsRepository( $this->db, $this->prefix ), $policy ), $people );
		wp_set_current_user( $admin );
		return compact( 'admin', 'self', 'other', 'actor', 'people', 'delegations', 'events', 'forms', 'blocks', 'event', 'form', 'config', 'submit', 'reads', 'policy' );
	}

	/**
	 * Create an operational event but do not rely on its default private state.
	 *
	 * @param array<string,mixed> $s         Test services.
	 * @param string              $title     A safe event name.
	 * @param string              $post      WordPress post status.
	 * @param string              $visibility Event publication visibility.
	 * @return PublicId
	 */
	private function make_event( array $s, string $title, string $post, string $visibility ): PublicId {
		$id = wp_insert_post( array( 'post_type' => 'uop_event', 'post_title' => $title, 'post_status' => $post ), true );
		self::assertIsInt( $id );
		$event = $s['event']->configure( $s['actor'], $this->scope, $id, 'Europe/Berlin', gmdate( 'Y-m-d H:i:s' ), CorrelationId::generate() );
		$this->db->execute(
			'UPDATE %i SET visibility = %s WHERE organization_id = %d AND event_post_id = %d',
			array( $this->prefix . 'event_settings', $visibility, $this->scope->id, $id )
		);
		return $event;
	}

	/** All five block types register with core metadata and render callbacks. */
	public function test_block_registration_and_gutenberg_metadata(): void {
		$s = $this->fixture();
		$s['blocks']->register();
		foreach ( array( 'event-list', 'event-details', 'registration-form', 'portal', 'my-registrations' ) as $slug ) {
			$type = \WP_Block_Type_Registry::get_instance()->get_registered( 'uop/' . $slug );
			self::assertNotNull( $type );
			self::assertIsCallable( $type->render_callback );
			self::assertSame( 'uop-m6-block-editor', $type->editor_script );
			self::assertSame( 'uop-m6-blocks', $type->style );
			self::assertFalse( $type->supports['html'] );
		}
	}

	/** A UUID from another tenant, an unlisted event or an unpublished post is hidden. */
	public function test_event_blocks_never_disclose_nonpublic_content(): void {
		$s       = $this->fixture();
		$public  = $this->make_event( $s, 'Visible Event 606', 'publish', 'public' );
		$private = $this->make_event( $s, 'Secret Event 606', 'publish', 'private' );
		$draft   = $this->make_event( $s, 'Draft Event 606', 'draft', 'public' );
		wp_set_current_user( 0 );
		$list = $s['blocks']->render( 'event-list', array( 'limit' => 12 ) );
		self::assertStringContainsString( 'Visible Event 606', $list );
		self::assertStringNotContainsString( 'Secret Event 606', $list );
		self::assertStringNotContainsString( 'Draft Event 606', $list );
		self::assertStringContainsString( 'Visible Event 606', $s['blocks']->render( 'event-details', array( 'eventId' => $public->to_string() ) ) );
		self::assertStringNotContainsString( 'Secret Event 606', $s['blocks']->render( 'event-details', array( 'eventId' => $private->to_string() ) ) );
		self::assertStringNotContainsString( 'Draft Event 606', $s['blocks']->render( 'event-details', array( 'eventId' => $draft->to_string() ) ) );
		self::assertStringNotContainsString( 'Visible Event 606', $s['blocks']->render( 'event-details', array( 'eventId' => PublicId::generate()->to_string() ) ) );
		self::assertSame( '', $s['blocks']->render( 'unknown', array() ) );
		$other_scope = new OrgScope( $this->scope->id + 1 );
		self::assertNull( $s['events']->by_public( $other_scope, $public ) );
	}

	/** Only the published form snapshot can appear; guest submission stays disabled. */
	public function test_registration_block_uses_immutable_published_schema_without_submit(): void {
		$s     = $this->fixture();
		$event = $this->make_event( $s, 'Public Registration 606', 'publish', 'public' );
		$form  = $s['form']->create(
			$s['actor'],
			$this->scope,
			'm606_form',
			'Signup',
			'event',
			array(
				'schema_version' => 1,
				'fields'         => array( array( 'key' => 'screen_name', 'type' => 'text', 'label' => 'Public name requirement', 'required' => true ) ),
			),
			gmdate( 'Y-m-d H:i:s' ),
			CorrelationId::generate()
		);
		$before = $s['blocks']->render( 'registration-form', array( 'eventId' => $event->to_string() ) );
		self::assertStringContainsString( 'No published registration form', $before );
		$s['form']->publish( $s['actor'], $this->scope, $form, 1, gmdate( 'Y-m-d H:i:s' ), CorrelationId::generate() );
		$s['config']->bind( $s['actor'], $this->scope, $event, $form, gmdate( 'Y-m-d H:i:s' ), CorrelationId::generate() );
		wp_set_current_user( 0 );
		$html = $s['blocks']->render( 'registration-form', array( 'eventId' => $event->to_string() ) );
		self::assertStringContainsString( 'Public name requirement', $html );
		self::assertStringContainsString( 'Preview only: values are not saved or sent.', $html );
		self::assertStringNotContainsString( '<form', $html );
		self::assertStringNotContainsString( 'draft_schema_json', $html );
		self::assertStringNotContainsString( '<script', $html );
		self::assertStringNotContainsString( 'Public name requirement', $s['blocks']->render( 'registration-form', array( 'eventId' => PublicId::generate()->to_string() ) ) );
	}

	/**
	 * The block must remain non-submitting until the operational guest opt-in
	 * and HTTPS readiness gates are active. Authorized members use WP nonce.
	 */
	public function test_m610_registration_form_html_obeys_guest_and_member_gates(): void {
		$s     = $this->fixture();
		$event = $this->make_event( $s, 'M610 Public Signup', 'publish', 'public' );
		$form  = $s['form']->create(
			$s['actor'],
			$this->scope,
			'm610_public_signup',
			'Signup',
			'event',
			array(
				'schema_version' => 1,
				'fields' => array(
					array( 'key' => 'participant', 'type' => 'text', 'label' => 'Participant name', 'required' => true ),
					array( 'key' => 'contact', 'type' => 'email', 'label' => 'Contact email', 'required' => true ),
					array(
						'key' => 'reason',
						'type' => 'text',
						'label' => 'Optional reason',
						'required' => false,
						'visible_when' => array(
							'schema_version' => 1,
							'all' => array( array( 'source' => 'registration', 'field' => 'participant', 'operator' => 'exists' ) ),
						),
					),
				),
			),
			gmdate( 'Y-m-d H:i:s' ),
			CorrelationId::generate()
		);
		$s['form']->publish( $s['actor'], $this->scope, $form, 1, gmdate( 'Y-m-d H:i:s' ), CorrelationId::generate() );
		$s['config']->bind( $s['actor'], $this->scope, $event, $form, gmdate( 'Y-m-d H:i:s' ), CorrelationId::generate() );
		$this->db->execute(
			'UPDATE %i SET require_email_verification = 1 WHERE organization_id = %d AND public_id = %s',
			array( $this->prefix . 'event_settings', $this->scope->id, $event->to_binary() )
		);
		$repo = new RegistrationRepository( $this->db, $this->prefix );
		$tx   = new TransactionManager( $this->db, static function ( int $delay ): void {}, static function ( \Throwable $error ): void {} );
		$aud  = new AuditWriter( $this->db, $this->prefix );
		$out  = new OutboxRepository( $this->db, $this->prefix );
		$mail = new EmailTemplateCatalog();
		$delivery = new GuestVerificationDeliveryService(
			$repo,
			new EmailMessageRepository( $this->db, $this->prefix ),
			$mail,
			new EmailTemplateRules( $mail ),
			$tx,
			$aud,
			$out
		);
		$blocks = new M6Blocks(
			$s['events'],
			new OccurrenceRepository( $this->db, $this->prefix ),
			$s['forms'],
			$s['reads'],
			new \UOP\Infrastructure\Database\ConsentRepository( $this->db, $this->prefix ),
			$delivery
		);
		$old_https = $_SERVER['HTTPS'] ?? null;
		$secure_url = static fn ( string $url ): string => str_replace( 'http://', 'https://', $url );
		$opt_in = static fn (): bool => true;
		try {
			$_SERVER['HTTPS'] = 'on';
			add_filter( 'home_url', $secure_url );
			wp_set_current_user( 0 );
			$disabled = $blocks->render( 'registration-form', array( 'eventId' => $event->to_string() ) );
			self::assertStringNotContainsString( '<form', $disabled );
			self::assertStringContainsString( 'Preview only:', $disabled );

			add_filter( 'uop_guest_verification_enabled', $opt_in );
			$guest = $blocks->render( 'registration-form', array( 'eventId' => $event->to_string() ) );
			self::assertStringContainsString( '<form class="uop-m6-registration"', $guest );
			self::assertStringContainsString( 'data-uop-guest="1"', $guest );
			self::assertStringContainsString( 'data-uop-field="contact"', $guest );
			self::assertStringContainsString( 'data-uop-condition="', $guest );
			self::assertStringContainsString( 'data-uop-submit disabled', $guest );
			self::assertStringNotContainsString( 'wp_rest', $guest );
			self::assertStringNotContainsString( 'primary_email', $guest );

			wp_set_current_user( $s['self'] );
			$member = $blocks->render( 'registration-form', array( 'eventId' => $event->to_string() ) );
			self::assertStringContainsString( 'data-uop-guest="0"', $member );
			self::assertSame( 1, preg_match( '/<label for="(uop-subject-[^"]+)">.*?<select id="\1"/s', $member ) );
			self::assertStringContainsString( 'data-uop-subject required disabled', $member );
			self::assertTrue( defined( 'DONOTCACHEPAGE' ) );
		} finally {
			remove_filter( 'uop_guest_verification_enabled', $opt_in );
			remove_filter( 'home_url', $secure_url );
			if ( null === $old_https ) {
				unset( $_SERVER['HTTPS'] );
			} else {
				$_SERVER['HTTPS'] = $old_https;
			}
			wp_set_current_user( 0 );
		}
	}

	/**
	 * Never convert a private profile predicate into a public form directive.
	 * The published schema is still read from the real WordPress database.
	 */
	public function test_m610_secure_form_blocks_private_conditions_and_unpinned_consents(): void {
		$s = $this->fixture();
		$renderer = new \UOP\Blocks\M6SubmissionForm(
			new \UOP\Infrastructure\Database\ConsentRepository( $this->db, $this->prefix )
		);
		$condition = array(
			'schema_version' => 1,
			'all' => array( array( 'source' => 'profile', 'field' => 'birthdate', 'operator' => 'exists' ) ),
		);
		$blocked = $renderer->render(
			$this->scope,
			PublicId::generate()->to_string(),
			array( 'fields' => array( array( 'key' => 'private', 'label' => 'Private', 'type' => 'text', 'required' => true, 'visible_when' => $condition ) ) ),
			true,
			array()
		);
		self::assertStringContainsString( 'private eligibility check', $blocked );
		self::assertStringNotContainsString( '<form', $blocked );
		self::assertStringNotContainsString( 'birthdate', $blocked );

		$no_consent = $renderer->render(
			$this->scope,
			PublicId::generate()->to_string(),
			array( 'fields' => array( array( 'key' => 'permission', 'label' => 'Permission', 'type' => 'consent', 'required' => true, 'consent_version_public_id' => PublicId::generate()->to_string() ) ) ),
			true,
			array()
		);
		self::assertStringContainsString( 'consent documents are unavailable', $no_consent );
		self::assertStringNotContainsString( '<form', $no_consent );
	}

	/** Member/delegate summaries are isolated across accounts and disabled for caching. */
	public function test_my_registrations_refuses_anonymous_and_unrelated_accounts(): void {
		$s   = $this->fixture();
		$now = gmdate( 'Y-m-d H:i:s' );
		$one = PublicId::generate();
		$two = PublicId::generate();
		$s['people']->create( $this->scope, $one, 'Alpha Participant', 'shared@example.invalid', $now );
		$s['people']->create( $this->scope, $two, 'Beta Participant', 'shared@example.invalid', $now );
		self::assertTrue( $s['people']->link( $this->scope, $one, $s['self'], $now ) );
		self::assertTrue( $s['people']->link( $this->scope, $two, $s['other'], $now ) );
		$event = $this->make_event( $s, 'Open Signup 606', 'publish', 'public' );
		$form  = $s['form']->create(
			$s['actor'], $this->scope, 'm606_signup', 'Signup', 'event',
			array(
				'schema_version' => 1,
				'fields'         => array( array( 'key' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true ) ),
			),
			$now, CorrelationId::generate()
		);
		$s['form']->publish( $s['actor'], $this->scope, $form, 1, $now, CorrelationId::generate() );
		$s['config']->bind( $s['actor'], $this->scope, $event, $form, $now, CorrelationId::generate() );
		$s['submit']->submit( $s['actor'], $this->scope, $one, $event, null, PublicId::generate(), array( 'name' => 'Alpha' ), $now, CorrelationId::generate() );
		wp_set_current_user( 0 );
		$guest = $s['blocks']->render( 'my-registrations' );
		self::assertStringContainsString( 'Sign in', $guest );
		self::assertStringNotContainsString( 'Alpha Participant', $guest );
		wp_set_current_user( $s['other'] );
		$other = $s['blocks']->render( 'my-registrations' );
		self::assertStringNotContainsString( 'Alpha Participant', $other );
		wp_set_current_user( $s['self'] );
		$self = $s['blocks']->render( 'my-registrations' );
		self::assertStringContainsString( 'Alpha Participant', $self );
		self::assertStringContainsString( 'submitted', $self );
		self::assertTrue( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE );
	}

	/** One account's unrelated shared email never creates a person assignment. */
	public function test_portal_subjects_respect_live_delegation_and_revocation(): void {
		$s         = $this->fixture();
		$child     = PublicId::generate();
		$stranger  = PublicId::generate();
		$self      = PublicId::generate();
		$now       = gmdate( 'Y-m-d H:i:s' );
		$s['people']->create( $this->scope, $child, 'Delegated Child', 'family@example.invalid', $now );
		$s['people']->create( $this->scope, $stranger, 'Unrelated Person', 'family@example.invalid', $now );
		$s['people']->create( $this->scope, $self, 'Current Account', 'family@example.invalid', $now );
		self::assertTrue( $s['people']->link( $this->scope, $self, $s['self'], $now ) );
		self::assertTrue( $s['people']->link( $this->scope, $stranger, $s['other'], $now ) );
		$grant = PublicId::generate();
		$s['delegations']->grant( $this->scope, $grant, $s['self'], (int) $s['people']->find( $this->scope, $child )['id'], 'registration_manage', 'organization', 0, null, $now );
		$service = new M6PortalReadService(
			$s['reads'], $s['people'],
			new RegistrationReadRepository( $this->db, $this->prefix ), $s['policy']
		);
		( new M6PortalController( $service ) )->register();
		wp_set_current_user( $s['self'] );
		$get     = rest_do_request( new \WP_REST_Request( 'GET', '/uop/v1/me/portal' ) );
		$names   = array_column( $get->get_data()['items'], 'display_name' );
		self::assertSame( 200, $get->get_status() );
		self::assertContains( 'Current Account', $names );
		self::assertContains( 'Delegated Child', $names );
		self::assertNotContains( 'Unrelated Person', $names );
		$children = array_values( array_filter( $get->get_data()['items'], static fn ( array $person ): bool => 'Delegated Child' === $person['display_name'] ) );
		self::assertTrue( $children[0]['can_view_entries'] );
		self::assertFalse( $children[0]['can_edit_name'] );

		$event = $this->make_event( $s, 'Delegated Signup 607', 'publish', 'public' );
		$form  = $s['form']->create(
			$s['actor'],
			$this->scope,
			'm607_signup',
			'Signup',
			'event',
			array(
				'schema_version' => 1,
				'fields'         => array( array( 'key' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true ) ),
			),
			$now,
			CorrelationId::generate()
		);
		wp_set_current_user( $s['admin'] );
		$s['form']->publish( $s['actor'], $this->scope, $form, 1, $now, CorrelationId::generate() );
		$s['config']->bind( $s['actor'], $this->scope, $event, $form, $now, CorrelationId::generate() );
		$registration = $s['submit']->submit( $s['actor'], $this->scope, $child, $event, null, PublicId::generate(), array( 'name' => 'Child' ), $now, CorrelationId::generate() );
		wp_set_current_user( $s['self'] );
		$page = rest_do_request( new \WP_REST_Request( 'GET', '/uop/v1/me/portal/registrations?person_id=' . $child->to_string() ) );
		self::assertSame( 200, $page->get_status() );
		self::assertSame( $registration->to_string(), $page->get_data()['items'][0]['public_id'] );
		self::assertTrue( $page->get_data()['items'][0]['can_cancel'] );
		self::assertArrayNotHasKey( 'contact_email', $page->get_data()['items'][0] );
		self::assertArrayNotHasKey( 'payload_json', $page->get_data()['items'][0] );

		self::assertTrue( $s['delegations']->revoke( $this->scope, $grant, $now ) );
		$revoked = rest_do_request( new \WP_REST_Request( 'GET', '/uop/v1/me/portal' ) );
		self::assertNotContains( 'Delegated Child', array_column( $revoked->get_data()['items'], 'display_name' ) );
		$denied = rest_do_request( new \WP_REST_Request( 'GET', '/uop/v1/me/portal/registrations?person_id=' . $child->to_string() ) );
		self::assertSame( 404, $denied->get_status() );
		$stranger_page = rest_do_request( new \WP_REST_Request( 'GET', '/uop/v1/me/portal/registrations?person_id=' . $stranger->to_string() ) );
		self::assertSame( 404, $stranger_page->get_status() );
		// A UI from before revocation must also fail when it issues a command.
		$transition = new RegistrationTransitionService(
			new RegistrationRepository( $this->db, $this->prefix ),
			new RegistrationStateMachine(),
			$s['policy'],
			new TransactionManager( $this->db, static function ( int $delay ): void {}, static function ( \Throwable $error ): void {} ),
			new AuditWriter( $this->db, $this->prefix ),
			new OutboxRepository( $this->db, $this->prefix )
		);
		try {
			$transition->transition(
				new Actor( $s['self'] ),
				$this->scope,
				$registration,
				'cancelled',
				PublicId::generate(),
				$now,
				CorrelationId::generate()
			);
			self::fail( 'A revoked delegation was able to cancel a registration.' );
		} catch ( \RuntimeException ) {
			self::assertTrue( true );
		}
	}

	/** Read-only delegation is selectable as a profile but cannot list registrations. */
	public function test_portal_profile_only_grant_does_not_expose_registration_data(): void {
		$s     = $this->fixture();
		$child = PublicId::generate();
		$now   = gmdate( 'Y-m-d H:i:s' );
		$s['people']->create( $this->scope, $child, 'Profile Only Child', null, $now );
		$s['delegations']->grant( $this->scope, PublicId::generate(), $s['self'], (int) $s['people']->find( $this->scope, $child )['id'], 'profile_view', 'organization', 0, null, $now );
		$service = new M6PortalReadService(
			$s['reads'], $s['people'],
			new RegistrationReadRepository( $this->db, $this->prefix ), $s['policy']
		);
		( new M6PortalController( $service ) )->register();
		wp_set_current_user( $s['self'] );
		$subjects = rest_do_request( new \WP_REST_Request( 'GET', '/uop/v1/me/portal' ) );
		self::assertSame( 200, $subjects->get_status() );
		self::assertSame( 'Profile Only Child', $subjects->get_data()['items'][0]['display_name'] );
		self::assertFalse( $subjects->get_data()['items'][0]['can_view_entries'] );
		$entries = rest_do_request( new \WP_REST_Request( 'GET', '/uop/v1/me/portal/registrations?person_id=' . $child->to_string() ) );
		self::assertSame( 404, $entries->get_status() );
		wp_set_current_user( 0 );
		self::assertSame( 403, rest_do_request( new \WP_REST_Request( 'GET', '/uop/v1/me/portal' ) )->get_status() );
	}

	/** Login-only assets and client roots never reveal nonce to anonymous users. */
	public function test_portal_block_enqueues_private_assets_and_safe_markup(): void {
		$s = $this->fixture();
		wp_set_current_user( 0 );
		$anon = $s['blocks']->render( 'portal' );
		self::assertStringContainsString( 'Sign in', $anon );
		self::assertStringNotContainsString( 'data-uop-portal-root', $anon );
		wp_set_current_user( $s['self'] );
		$portal = $s['blocks']->render( 'portal' );
		$mine   = $s['blocks']->render( 'my-registrations' );
		self::assertStringContainsString( 'data-uop-portal-root="portal"', $portal );
		self::assertStringContainsString( 'data-uop-portal-root="registrations"', $mine );
		self::assertTrue( wp_script_is( 'uop-m6-portal', 'enqueued' ) );
		self::assertTrue( wp_style_is( 'uop-m6-portal', 'enqueued' ) );
		self::assertStringNotContainsString( 'wp_rest', $portal );
		self::assertTrue( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE );
	}


	/**
	 * Public filters and disclosures use the core Interactivity API, preserving
	 * public-only server fallbacks and keyboard-focusable semantics.
	 */
	public function test_interactive_public_event_filter_and_dates_disclosure(): void {
		$s = $this->fixture();
		$s['blocks']->register();
		$this->make_event( $s, 'Interactive Public Show', 'publish', 'public' );
		$this->make_event( $s, 'Private Unlisted Show', 'publish', 'private' );
		wp_set_current_user( 0 );
		$list = $s['blocks']->render( 'event-list', array( 'limit' => 10 ) );
		self::assertStringContainsString( 'Interactive Public Show', $list );
		self::assertStringNotContainsString( 'Private Unlisted Show', $list );
		self::assertStringContainsString( 'data-wp-interactive="uop/m6"', $list );
		self::assertStringContainsString( 'data-wp-on--input="actions.filterEvents"', $list );
		self::assertStringContainsString( 'data-wp-bind--hidden="state.eventHidden"', $list );
		self::assertStringContainsString( 'No matching events', $list );
		$event = $this->make_event( $s, 'Public Event Details', 'publish', 'public' );
		$html  = $s['blocks']->render( 'event-details', array( 'eventId' => $event->to_string() ) );
		self::assertStringContainsString( 'Public Event Details', $html );
		self::assertStringContainsString( 'data-wp-on--click="actions.toggleDisclosure"', $html );
		self::assertStringContainsString( 'data-wp-bind--aria-expanded="context.open"', $html );
		self::assertStringContainsString( 'data-wp-bind--hidden="state.disclosureClosed"', $html );
	}

	/**
	 * The form preview never submits, conditionally hides dependent fields,
	 * and does not serialize private profile predicates to guest HTML.
	 */
	public function test_interactive_form_preview_conditions_and_private_predicates(): void {
		$s = $this->fixture();
		$s['blocks']->register();
		$event = $this->make_event( $s, 'Safe Preview Event', 'publish', 'public' );
		$form  = $s['form']->create(
			$s['actor'],
			$this->scope,
			'm608_conditional',
			'Preview',
			'event',
			array(
				'schema_version' => 1,
				'fields' => array(
					array( 'key' => 'attendance', 'type' => 'checkbox', 'label' => 'Attending', 'required' => false ),
					array(
						'key' => 'reason',
						'type' => 'text',
						'label' => 'Attendance reason',
						'required' => false,
						'visible_when' => array(
							'schema_version' => 1,
							'all' => array( array( 'source' => 'registration', 'field' => 'attendance', 'operator' => 'eq', 'value' => true ) ),
						),
					),
					array(
						'key' => 'sensitive',
						'type' => 'textarea',
						'label' => 'Private profile fact',
						'required' => false,
						'visible_when' => array(
							'schema_version' => 1,
							'all' => array( array( 'source' => 'profile', 'field' => 'sensitive_field', 'operator' => 'exists' ) ),
						),
					),
				),
			),
			gmdate( 'Y-m-d H:i:s' ),
			CorrelationId::generate()
		);
		$s['form']->publish( $s['actor'], $this->scope, $form, 1, gmdate( 'Y-m-d H:i:s' ), CorrelationId::generate() );
		$s['config']->bind( $s['actor'], $this->scope, $event, $form, gmdate( 'Y-m-d H:i:s' ), CorrelationId::generate() );
		wp_set_current_user( 0 );
		$html = $s['blocks']->render( 'registration-form', array( 'eventId' => $event->to_string() ) );
		self::assertStringContainsString( 'data-wp-interactive="uop/m6"', $html );
		self::assertStringContainsString( 'data-wp-on--input="actions.changeField"', $html );
		self::assertStringContainsString( 'data-wp-bind--hidden="state.fieldHidden"', $html );
		self::assertStringContainsString( 'Attendance reason', $html );
		self::assertStringContainsString( ' data-uop-field="attendance"', $html );
		self::assertStringNotContainsString( 'Private profile fact', $html );
		self::assertStringNotContainsString( 'sensitive_field', $html );
		self::assertStringNotContainsString( '<form', $html );
		self::assertStringNotContainsString( 'action=', $html );
		self::assertStringNotContainsString( 'name="attendance"', $html );
		self::assertStringContainsString( 'not saved or sent', $html );
	}


	/**
	 * Require actual HTML semantics, associated notes and preview-only controls.
	 * No simulated consent checkbox or executable registration form is allowed.
	 */
	public function test_m609_preview_has_accessible_labels_radio_group_and_no_submit(): void {
		$html = ( new M6InteractivityAdapter() )->fields(
			array(
				'fields' => array(
					array( 'key' => 'display', 'label' => 'Person display name', 'required' => true, 'type' => 'text' ),
					array( 'key' => 'availability', 'label' => 'Available days', 'required' => true, 'type' => 'radio', 'options' => array( 'Monday', 'Tuesday' ) ),
					array( 'key' => 'privacy', 'label' => 'Consent requirement', 'required' => false, 'type' => 'consent' ),
				),
			)
		);
		self::assertStringContainsString( '<fieldset><legend>Available days</legend>', $html );
		self::assertStringContainsString( 'type="radio"', $html );
		self::assertStringContainsString( 'aria-describedby="uop-preview-note-', $html );
		self::assertStringContainsString( 'Conditional fields require JavaScript', $html );
		self::assertStringContainsString( 'Consent requirement', $html );
		self::assertStringNotContainsString( 'type="submit"', $html );
		self::assertStringNotContainsString( '<form', $html );
		self::assertStringNotContainsString( 'type="checkbox"', $html );

		self::assertSame( 2, preg_match_all( '/type="radio" name="([^"]+)"/', $html, $matches ) );
		self::assertSame( $matches[1][0], $matches[1][1], 'Radio choices must share one HTML name in their own preview group.' );
		self::assertSame( 3, preg_match_all( '/aria-describedby="([^"]+)"/', $html, $references ) );
		foreach ( array_unique( $references[1] ) as $reference ) {
			self::assertStringContainsString( 'id="' . $reference . '"', $html );
		}
		self::assertSame( 0, preg_match( '/<label[^>]+for="([^"]+)"[^>]*>Consent requirement/', $html ) );
	}


	/**
	 * End-to-end guest challenge is queued after a committed anonymous
	 * registration, never echoed to REST or token-free event history.
	 */
	public function test_guest_verification_queue_link_single_use_and_safe_rest_receipt(): void {
		$s = $this->fixture();
		$site = static fn ( string $url ): string => str_replace( 'http://', 'https://', $url );
		$enabled = static fn (): bool => true;
		$original_https = $_SERVER['HTTPS'] ?? null;
		unset( $_SERVER['HTTPS'] );
		add_filter( 'uop_guest_verification_enabled', $enabled );
		add_filter( 'home_url', $site );
		try {
			$now   = gmdate( 'Y-m-d H:i:s' );
			$event = $this->make_event( $s, 'Guest Verified Event', 'publish', 'public' );
			$form  = $s['form']->create(
				$s['actor'],
				$this->scope,
				'm603_guest_form',
				'Guest form',
				'event',
				array(
					'schema_version' => 1,
					'fields' => array(
						array( 'key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true ),
						array( 'key' => 'contact', 'label' => 'Email', 'type' => 'email', 'required' => true ),
					),
				),
				$now,
				CorrelationId::generate()
			);
			$s['form']->publish( $s['actor'], $this->scope, $form, 1, $now, CorrelationId::generate() );
			$s['config']->bind( $s['actor'], $this->scope, $event, $form, $now, CorrelationId::generate() );
			$this->db->execute(
				'UPDATE %i SET require_email_verification = 1 WHERE organization_id = %d AND public_id = %s',
				array( $this->prefix . 'event_settings', $this->scope->id, $event->to_binary() )
			);
			$repo = new RegistrationRepository( $this->db, $this->prefix );
			$tx   = new TransactionManager( $this->db, static function ( int $delay ): void {}, static function ( \Throwable $error ): void {} );
			$aud  = new AuditWriter( $this->db, $this->prefix );
			$out  = new OutboxRepository( $this->db, $this->prefix );
			$msg  = new EmailMessageRepository( $this->db, $this->prefix );
			$delivery = new GuestVerificationDeliveryService( $repo, $msg, new EmailTemplateCatalog(), new EmailTemplateRules( new EmailTemplateCatalog() ), $tx, $aud, $out );
			self::assertTrue( $delivery->ready() );

			$verification = new EmailVerificationService( $repo, $s['policy'], $tx, $aud, $out );
			$transition   = new RegistrationTransitionService( $repo, new RegistrationStateMachine(), $s['policy'], $tx, $aud, $out );
			$eligibility  = new RegistrationEligibilityService( $repo, new RegistrationFactsService( new RegistrationFactsRepository( $this->db, $this->prefix ), $s['policy'] ) );
			$capacity     = new CapacityLifecycleService(
				new WaitlistRepository( $this->db, $this->prefix ),
				new CapacityRepository( $this->db, $this->prefix ),
				new RegistrationStateMachine(),
				$s['policy'],
				$tx,
				$aud,
				$out,
				$eligibility
			);
			( new RegistrationController( $s['reads'], $s['submit'], $transition, $capacity, $verification, $delivery ) )->register();
			wp_set_current_user( 0 );
			// The configured HTTPS URL alone must not admit an HTTP REST request.
			$http_probe = new \WP_REST_Request( 'POST', '/uop/v1/registrations/guest' );
			$http_probe->set_header( 'Content-Type', 'application/json' );
			$http_probe->set_body( '{}' );
			self::assertSame( 503, rest_do_request( $http_probe )->get_status() );
			$http_verify = new \WP_REST_Request( 'POST', '/uop/v1/registration-verifications' );
			$http_verify->set_header( 'Content-Type', 'application/json' );
			$http_verify->set_body( '{}' );
			self::assertSame( 503, rest_do_request( $http_verify )->get_status() );
			$_SERVER['HTTPS'] = 'on';
			// Password-protected posts are not public guest destinations, even if event settings say public.
			$event_row = $s['events']->by_public( $this->scope, $event );
			self::assertNotNull( $event_row );
			wp_update_post( array( 'ID' => (int) $event_row['event_post_id'], 'post_password' => 'protected' ) );
			try {
				$s['submit']->submit_guest( $this->scope, $event, null, PublicId::generate(), array( 'name' => 'Guest', 'contact' => 'testguest@example.invalid' ), $now, CorrelationId::generate() );
				self::fail( 'Password-protected event admitted an anonymous guest.' );
			} catch ( \RuntimeException ) {
				self::assertTrue( true );
			}
			wp_update_post( array( 'ID' => (int) $event_row['event_post_id'], 'post_password' => '' ) );
			$body = array(
				'event_id' => $event->to_string(),
				'command_id' => PublicId::generate()->to_string(),
				'fields' => array( 'name' => 'Guest', 'contact' => 'testguest@example.invalid' ),
			);
			$request = new \WP_REST_Request( 'POST', '/uop/v1/registrations/guest' );
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $body ) );
			$response = rest_do_request( $request );
			self::assertSame( 202, $response->get_status() );
			self::assertSame( array( 'status' => 'received' ), $response->get_data() );
			$registrations = $this->db->rows( "SELECT public_id, source, email_verified_at FROM %i WHERE organization_id = %d AND source = 'guest'", array( $this->prefix . 'registrations', $this->scope->id ) );
			self::assertCount( 1, $registrations );
			self::assertNull( $registrations[0]['email_verified_at'] );
			$registration = PublicId::from_binary( (string) $registrations[0]['public_id'] );
			$event_message = new DomainEventDto( PublicId::generate()->to_string(), 'registration.email_verification_required', $registration->to_string() );
			// A cross-organization replay cannot locate or mail this registration.
			$delivery->on_scoped_event( new OrgScope( $this->scope->id + 500 ), $event_message );
			$before = $this->db->rows(
				"SELECT id FROM %i WHERE organization_id = %d AND template_key = 'email_verification'",
				array( $this->prefix . 'email_messages', $this->scope->id )
			);
			self::assertCount( 0, $before );
			// Run the actual committed outbox dispatch path with trusted scope.
			$signals = $this->db->rows(
				"SELECT event_uuid FROM %i WHERE organization_id = %d AND event_name = 'registration.email_verification_required' LIMIT 1",
				array( $this->prefix . 'domain_events', $this->scope->id )
			);
			self::assertCount( 1, $signals );
			$scoped_handler = array( $delivery, 'on_scoped_event' );
			add_action( 'uop_scoped_domain_event', $scoped_handler, 10, 2 );
			try {
				( new OutboxDispatcher( $this->db, $out, $this->prefix ) )->consume(
					$this->scope->id,
					PublicId::from_binary( (string) $signals[0]['event_uuid'] )->to_string()
				);
			} finally {
				remove_action( 'uop_scoped_domain_event', $scoped_handler, 10 );
			}
			$delivery->on_scoped_event( $this->scope, $event_message );

			$mail = $this->db->rows(
				"SELECT public_id, recipient, body_text, subject FROM %i WHERE organization_id = %d AND template_key = 'email_verification'",
				array( $this->prefix . 'email_messages', $this->scope->id )
			);
			self::assertCount( 1, $mail );
			self::assertSame( 'testguest@example.invalid', $mail[0]['recipient'] );
			self::assertSame( 1, preg_match( '/#registration_id=[a-f0-9-]{36}&token=([a-f0-9]{64})/', $mail[0]['body_text'], $found ) );
			$token = $found[1];
			self::assertStringNotContainsString( 'token=', $mail[0]['subject'] );
			$events = $this->db->rows( "SELECT payload_json FROM %i WHERE event_name IN ('registration.email_verification_required','registration.verification_issued')", array( $this->prefix . 'domain_events' ) );
			self::assertGreaterThanOrEqual( 2, count( $events ) );
			foreach ( $events as $entry ) {
				self::assertStringNotContainsString( $token, (string) $entry['payload_json'] );
			}
			$saved = $repo->lock_registration( $this->scope, $registration );
			self::assertNotSame( $token, $saved['email_verification_token_hash'] );
			self::assertSame( hash( 'sha256', $token, true ), $saved['email_verification_token_hash'] );

			// An expired bearer must leave the record unverified while returning the same generic receipt.
			$this->db->execute(
				'UPDATE %i SET verification_expires_at = %s WHERE organization_id = %d AND public_id = %s',
				array( $this->prefix . 'registrations', gmdate( 'Y-m-d H:i:s', time() - 60 ), $this->scope->id, $registration->to_binary() )
			);
			$verify = new \WP_REST_Request( 'POST', '/uop/v1/registration-verifications' );
			$verify->set_header( 'Content-Type', 'application/json' );
			$verify->set_body( (string) wp_json_encode( array( 'registration_id' => $registration->to_string(), 'token' => $token ) ) );
			$expired = rest_do_request( $verify );
			self::assertSame( 202, $expired->get_status() );
			self::assertNull( $repo->lock_registration( $this->scope, $registration )['email_verified_at'] );
			$this->db->execute(
				'UPDATE %i SET verification_expires_at = %s WHERE organization_id = %d AND public_id = %s',
				array( $this->prefix . 'registrations', gmdate( 'Y-m-d H:i:s', time() + 3600 ), $this->scope->id, $registration->to_binary() )
			);
			$first  = rest_do_request( $verify );
			$second = rest_do_request( $verify );
			self::assertSame( 202, $first->get_status() );
			self::assertSame( $first->get_data(), $second->get_data() );
			self::assertNotNull( $repo->lock_registration( $this->scope, $registration )['email_verified_at'] );
			self::assertNull( $repo->lock_registration( $this->scope, $registration )['email_verification_token_hash'] );
			self::assertNull( $delivery->queue( $this->scope, $registration, $now ) );

			$worker = new EmailDeliveryWorker( $this->db, $this->prefix, $msg, $tx );
			$captured = array();
			$filter = static function ( $previous, array $details ) use ( &$captured ) {
				$captured[] = $details;
				return true;
			};
			add_filter( 'pre_wp_mail', $filter, 10, 2 );
			try {
				$worker->deliver( $this->scope->id, PublicId::from_binary( $mail[0]['public_id'] )->to_string() );
				$worker->deliver( $this->scope->id, PublicId::from_binary( $mail[0]['public_id'] )->to_string() );
			} finally {
				remove_filter( 'pre_wp_mail', $filter, 10 );
			}
			self::assertCount( 1, $captured );
			self::assertStringContainsString( $token, $captured[0]['message'] );
		} finally {
			remove_filter( 'uop_guest_verification_enabled', $enabled );
			remove_filter( 'home_url', $site );
			if ( null === $original_https ) {
				unset( $_SERVER['HTTPS'] );
			} else {
				$_SERVER['HTTPS'] = $original_https;
			}
		}
		// Restoring the default closed gate must deny new public intake.
		$blocked = rest_do_request( $request );
		self::assertSame( 503, $blocked->get_status() );
	}

}
