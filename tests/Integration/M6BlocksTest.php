<?php
/**
 * M6-06 dynamic block contract, public visibility, scoped data and privacy.
 *
 * @package UOP
 */

namespace UOP\Tests\Integration;

use PHPUnit\Framework\TestCase;
use UOP\Application\Event\EventService;
use UOP\Application\Form\FormService;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyService;
use UOP\Application\Query\M6ReadService;
use UOP\Application\Registration\RegistrationConfigurationService;
use UOP\Application\Registration\RegistrationFactsService;
use UOP\Application\Registration\RegistrationService;
use UOP\Blocks\M6Blocks;
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
use UOP\Infrastructure\Database\OccurrenceRepository;
use UOP\Infrastructure\Database\OutboxRepository;
use UOP\Infrastructure\Database\PersonRepository;
use UOP\Infrastructure\Database\RegistrationFactsRepository;
use UOP\Infrastructure\Database\RegistrationReadRepository;
use UOP\Infrastructure\Database\RegistrationRepository;
use UOP\Infrastructure\Database\SchemaManifest;
use UOP\Infrastructure\Database\WpdbConnection;

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
		return compact( 'admin', 'self', 'other', 'actor', 'people', 'events', 'forms', 'blocks', 'event', 'form', 'config', 'submit' );
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
		self::assertStringContainsString( 'Submission through this block will be enabled', $html );
		self::assertStringNotContainsString( '<form', $html );
		self::assertStringNotContainsString( 'draft_schema_json', $html );
		self::assertStringNotContainsString( '<script', $html );
		self::assertStringNotContainsString( 'Public name requirement', $s['blocks']->render( 'registration-form', array( 'eventId' => PublicId::generate()->to_string() ) ) );
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
}
