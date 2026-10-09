<?php
/**
 * M6-04 staff-wide administrative listing and screens boundary tests.
 *
 * @package UOP
 */

namespace UOP\Tests\Integration;

use PHPUnit\Framework\TestCase;
use UOP\Admin\M6PeopleRegistrationScreen;
use UOP\Application\Event\EventService;
use UOP\Application\Form\FormService;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyService;
use UOP\Application\Query\M6AdminReadService;
use UOP\Application\Query\M6ReadService;
use UOP\Application\Registration\RegistrationConfigurationService;
use UOP\Application\Registration\RegistrationFactsService;
use UOP\Application\Registration\RegistrationService;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Core\TransactionManager;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\AssignmentRepository;
use UOP\Infrastructure\Database\AuditWriter;
use UOP\Infrastructure\Database\DelegationRepository;
use UOP\Infrastructure\Database\EventRepository;
use UOP\Infrastructure\Database\FormRepository;
use UOP\Infrastructure\Database\Installer;
use UOP\Infrastructure\Database\M6AdminListRepository;
use UOP\Infrastructure\Database\OccurrenceRepository;
use UOP\Infrastructure\Database\OutboxRepository;
use UOP\Infrastructure\Database\PersonRepository;
use UOP\Infrastructure\Database\RegistrationFactsRepository;
use UOP\Infrastructure\Database\RegistrationReadRepository;
use UOP\Infrastructure\Database\RegistrationRepository;
use UOP\Infrastructure\Database\SchemaManifest;
use UOP\Infrastructure\Database\WpdbConnection;
use UOP\REST\M6AdminController;

/** Scope validation happens before any staff-wide database enumeration. */
final class M6AdminScreensTest extends TestCase {
	private WpdbConnection $db;
	private OrgScope $scope;
	private string $prefix;

	/** Set up fresh operational tables and a real organization. */
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
		wp_set_current_user( 0 );
	}

	/**
	 * Build the real policy-backed list, detail and screen services.
	 *
	 * @return array<string,mixed>
	 */
	private function fixture(): array {
		$admin = wp_create_user( 'uop_m604_admin_' . bin2hex( random_bytes( 4 ) ), wp_generate_password( 24 ), 'staff_' . bin2hex( random_bytes( 4 ) ) . '@example.invalid' );
		$other = wp_create_user( 'uop_m604_other_' . bin2hex( random_bytes( 4 ) ), wp_generate_password( 24 ), 'other_' . bin2hex( random_bytes( 4 ) ) . '@example.invalid' );
		self::assertIsInt( $admin );
		self::assertIsInt( $other );
		( new \WP_User( $admin ) )->set_role( 'administrator' );
		( new \WP_User( $other ) )->set_role( 'subscriber' );
		wp_set_current_user( $admin );
		$actor       = new Actor( $admin );
		$people      = new PersonRepository( $this->db, $this->prefix );
		$delegations = new DelegationRepository( $this->db, $this->prefix );
		$policy      = new PolicyService(
			$people,
			$delegations,
			new AssignmentRepository( $this->db, $this->prefix ),
			static fn ( int $id, string $cap ): bool => $id === $admin
		);
		$reads = new M6AdminReadService(
			new M6AdminListRepository( $this->db, $this->prefix ),
			new M6ReadService( $people, $delegations, new RegistrationReadRepository( $this->db, $this->prefix ), $policy ),
			$policy
		);
		( new M6AdminController( $reads ) )->register();
		$tx     = new TransactionManager( $this->db, static function ( int $delay ): void {}, static function ( \Throwable $error ): void {} );
		$audit  = new AuditWriter( $this->db, $this->prefix );
		$outbox = new OutboxRepository( $this->db, $this->prefix );
		$event  = new EventService( new EventRepository( $this->db, $this->prefix ), new OccurrenceRepository( $this->db, $this->prefix ), $policy, $tx, $audit, $outbox );
		$form   = new FormService( new FormRepository( $this->db, $this->prefix ), $policy, $tx, $audit, $outbox );
		$submit = new RegistrationService( new RegistrationRepository( $this->db, $this->prefix ), $policy, $tx, $audit, $outbox, new RegistrationFactsService( new RegistrationFactsRepository( $this->db, $this->prefix ), $policy ), $people );
		$config = new RegistrationConfigurationService( $this->db, $this->prefix, $policy, $tx, $audit, $outbox );
		return compact( 'admin', 'other', 'actor', 'people', 'reads', 'event', 'form', 'submit', 'config' );
	}

	/**
	 * Dispatch one GET with optional URL parameters.
	 *
	 * @param string $path Absolute REST path.
	 * @return \WP_REST_Response
	 */
	private function get( string $path ): \WP_REST_Response {
		return rest_do_request( new \WP_REST_Request( 'GET', $path ) );
	}

	/** Staff lists are not widened to subscribers, including same-email family. */
	public function test_admin_person_pagination_is_scoped_and_never_grants_family_access(): void {
		$s   = $this->fixture();
		$now = gmdate( 'Y-m-d H:i:s' );
		$ids = array();
		for ( $i = 0; $i < 54; $i++ ) {
			$id    = PublicId::generate();
			$ids[] = $id;
			$s['people']->create( $this->scope, $id, 'Member ' . $i, 'family@example.invalid', $now );
		}
		$first = $this->get( '/uop/v1/admin/people' );
		self::assertSame( 200, $first->get_status() );
		self::assertCount( 50, $first->get_data()['items'] );
		self::assertSame( $ids[53]->to_string(), $first->get_data()['items'][0]['public_id'] );
		self::assertNotEmpty( $first->get_data()['next'] );
		self::assertTrue( $first->get_data()['items'][0]['can_edit'] );
		self::assertArrayNotHasKey( 'wp_user_id', $first->get_data()['items'][0] );
		$next = $this->get( '/uop/v1/admin/people?after=' . $first->get_data()['next'] );
		self::assertSame( 200, $next->get_status() );
		self::assertCount( 4, $next->get_data()['items'] );
		self::assertNull( $next->get_data()['next'] );

		wp_set_current_user( $s['other'] );
		self::assertSame( 403, $this->get( '/uop/v1/admin/people' )->get_status() );
		self::assertSame( 403, $this->get( '/uop/v1/admin/registrations' )->get_status() );
		self::assertFalse( $s['reads']->can_list( new Actor( $s['other'] ), $this->scope, 'people' ) );
		wp_set_current_user( $s['admin'] );
		self::assertSame( 400, $this->get( '/uop/v1/admin/people?after=guess' )->get_status() );
		self::assertSame( 404, $this->get( '/uop/v1/admin/people?after=' . PublicId::generate()->to_string() )->get_status() );
		self::assertSame( 400, $this->get( '/uop/v1/admin/registrations?status=unknown' )->get_status() );
	}

	/** Filter and opaque cursor only traverse registration rows within the tenant. */
	public function test_admin_registration_page_filters_and_object_projection(): void {
		$s   = $this->fixture();
		$now = gmdate( 'Y-m-d H:i:s' );
		if ( ! post_type_exists( 'uop_event' ) ) {
			register_post_type( 'uop_event', array( 'public' => true ) );
		}
		$post = wp_insert_post( array( 'post_type' => 'uop_event', 'post_title' => 'Manager event', 'post_status' => 'publish' ), true );
		self::assertIsInt( $post );
		$event = $s['event']->configure( $s['actor'], $this->scope, $post, 'Europe/Berlin', $now, CorrelationId::generate() );
		$form  = $s['form']->create(
			$s['actor'],
			$this->scope,
			'm604_form',
			'Signup',
			'event',
			array(
				'schema_version' => 1,
				'fields'         => array( array( 'key' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true ) ),
			),
			$now,
			CorrelationId::generate()
		);
		$s['form']->publish( $s['actor'], $this->scope, $form, 1, $now, CorrelationId::generate() );
		$s['config']->bind( $s['actor'], $this->scope, $event, $form, $now, CorrelationId::generate() );
		$person = PublicId::generate();
		$s['people']->create( $this->scope, $person, 'Registration owner', null, $now );
		$registration = $s['submit']->submit( $s['actor'], $this->scope, $person, $event, null, PublicId::generate(), array( 'name' => 'Member' ), $now, CorrelationId::generate() );
		$all = $this->get( '/uop/v1/admin/registrations' );
		self::assertSame( 200, $all->get_status(), wp_json_encode( $all->get_data() ) );
		self::assertSame( $registration->to_string(), $all->get_data()['items'][0]['public_id'] );
		self::assertSame( 'Registration owner', $all->get_data()['items'][0]['person_name'] );
		self::assertArrayNotHasKey( 'payload_json', $all->get_data()['items'][0] );
		$submitted = $this->get( '/uop/v1/admin/registrations?status=submitted' );
		self::assertCount( 1, $submitted->get_data()['items'] );
		$missing = $this->get( '/uop/v1/admin/registrations?status=rejected' );
		self::assertSame( 200, $missing->get_status() );
		self::assertSame( array(), $missing->get_data()['items'] );
		self::assertSame( 404, $this->get( '/uop/v1/admin/registrations?status=submitted&after=' . PublicId::generate()->to_string() )->get_status() );
	}

	/** Only an authorized administrator can render its privileged page mount. */
	public function test_admin_hosts_guard_markup_and_recheck_organization(): void {
		$s      = $this->fixture();
		$screen = new M6PeopleRegistrationScreen( $s['reads'] );
		ob_start();
		$screen->render_people();
		$html = (string) ob_get_clean();
		self::assertStringContainsString( 'uop-m6-admin-root', $html );
		self::assertStringContainsString( 'data-resource="people"', $html );
		wp_set_current_user( $s['other'] );
		try {
			$screen->render_people();
			self::fail( 'Unauthorized administration screen rendered.' );
		} catch ( \WPDieException ) {
			self::assertTrue( true );
		}
	}
}
