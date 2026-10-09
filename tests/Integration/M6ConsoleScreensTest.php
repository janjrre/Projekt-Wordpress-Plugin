<?php
/**
 * M6-05 integration: permissioned navigation, audit, system health and consents.
 *
 * @package UOP
 */

namespace UOP\Tests\Integration;

use PHPUnit\Framework\TestCase;
use UOP\Admin\M6ConsentDocumentsScreen;
use UOP\Admin\M6ControlCenterScreen;
use UOP\Application\Consent\ConsentDefinitionService;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyObject;
use UOP\Application\Policy\PolicyService;
use UOP\Application\Query\M6OperationsReadService;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Core\TransactionManager;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\AssignmentRepository;
use UOP\Infrastructure\Database\AuditWriter;
use UOP\Infrastructure\Database\ConsentRepository;
use UOP\Infrastructure\Database\DelegationRepository;
use UOP\Infrastructure\Database\EventRepository;
use UOP\Infrastructure\Database\Installer;
use UOP\Infrastructure\Database\M5OperationsRepository;
use UOP\Infrastructure\Database\M6OperationsRepository;
use UOP\Infrastructure\Database\OutboxRepository;
use UOP\Infrastructure\Database\PersonRepository;
use UOP\Infrastructure\Database\RegistrationReadRepository;
use UOP\Infrastructure\Database\SchemaManifest;
use UOP\Infrastructure\Database\WpdbConnection;

/** Verify the existing domain services remain the only mutation authority. */
final class M6ConsoleScreensTest extends TestCase {
	private WpdbConnection $db;
	private OrgScope $scope;
	private string $prefix;

	/** Rebuild a disposable organization and all relevant M5/M6 tables. */
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
		$_POST = array();
		$_SERVER['REQUEST_METHOD'] = 'GET';
	}

	/**
	 * Build real scoped readers with M2 policy and audit, no mock allow-all calls.
	 *
	 * @return array<string,mixed>
	 */
	private function fixture(): array {
		$admin = wp_create_user( 'uop_m605_admin_' . bin2hex( random_bytes( 5 ) ), wp_generate_password( 24 ), 'audit_' . bin2hex( random_bytes( 5 ) ) . '@example.invalid' );
		$other = wp_create_user( 'uop_m605_other_' . bin2hex( random_bytes( 5 ) ), wp_generate_password( 24 ), 'other_' . bin2hex( random_bytes( 5 ) ) . '@example.invalid' );
		self::assertIsInt( $admin );
		self::assertIsInt( $other );
		( new \WP_User( $admin ) )->set_role( 'administrator' );
		( new \WP_User( $other ) )->set_role( 'subscriber' );
		$role = get_role( 'administrator' );
		self::assertNotNull( $role );
		foreach ( array( 'uop_manage_settings', 'uop_manage_privacy', 'uop_view_audit', 'uop_manage_forms', 'uop_send_communications', 'uop_export_data', 'uop_view_people', 'uop_view_registrations' ) as $cap ) {
			$role->add_cap( $cap );
		}
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
		$reads   = new M6OperationsReadService(
			new M6OperationsRepository( $this->db, $this->prefix ),
			new RegistrationReadRepository( $this->db, $this->prefix ),
			new EventRepository( $this->db, $this->prefix ),
			$docs,
			$policy
		);
		$overview = new M6ControlCenterScreen( $reads, new M5OperationsRepository( $this->db, $this->prefix ) );
		$consents = new M6ConsentDocumentsScreen(
			$reads,
			$docs,
			new ConsentDefinitionService( $docs, $policy, $tx, $audit, $outbox )
		);
		return compact( 'admin', 'other', 'actor', 'audit', 'docs', 'reads', 'overview', 'consents' );
	}

	/**
	 * Collect a permitted server-rendered view for source leak detection.
	 *
	 * @param callable $callback Guarded screen render.
	 * @return string
	 */
	private function markup( callable $callback ): string {
		ob_start();
		try {
			$callback();
			return (string) ob_get_clean();
		} catch ( \Throwable $error ) {
			ob_end_clean();
			throw $error;
		}
	}

	/** The overview reuses M3 and M5 entry points and avoids private envelopes. */
	public function test_overview_renders_authorized_diagnostics_and_schema_health(): void {
		$s = $this->fixture();
		$id = PublicId::generate();
		$this->db->execute(
			'INSERT INTO %i (public_id,organization_id,idempotency_key,recipient,template_key,subject,body_text,status,attempts,queued_at,created_at,updated_at,template_revision,template_hash,locale,last_error_code) VALUES (%s,%d,%s,%s,%s,%s,%s,%s,%d,%s,%s,%s,%d,%s,%s,%s)',
			array(
				$this->prefix . 'email_messages',
				$id->to_binary(),
				$this->scope->id,
				random_bytes( 32 ),
				'secret-member@example.invalid',
				'registration_received',
				'Secret Subject',
				'Private message content',
				'failed',
				1,
				'2030-01-01 00:00:00',
				'2030-01-01 00:00:00',
				'2030-01-01 00:00:00',
				1,
				random_bytes( 32 ),
				'de_DE',
				'wp_mail_failed',
			)
		);
		$markup = $this->markup( array( $s['overview'], 'overview' ) );
		self::assertStringContainsString( 'UOP Control Center', $markup );
		self::assertStringContainsString( 'uop-forms', $markup );
		self::assertStringContainsString( 'uop-operations', $markup );
		self::assertStringContainsString( 'uop-consents', $markup );
		self::assertStringContainsString( 'uop-audit', $markup );
		self::assertStringContainsString( 'System health', $markup );
		self::assertStringNotContainsString( 'secret-member@example.invalid', $markup );
		self::assertStringNotContainsString( 'Private message content', $markup );
		self::assertStringNotContainsString( $id->to_string(), $markup );
		self::assertSame( array(), $s['docs']->summaries( new OrgScope( $this->scope->id + 1 ) ) );
	}

	/** Audit rows are readable without actor, object, metadata or email disclosure. */
	public function test_audit_shows_only_allowlisted_fields_and_rejects_other_users(): void {
		$s = $this->fixture();
		$s['audit']->append(
			$this->scope,
			$s['actor'],
			'consent.version_published',
			new PolicyObject( $this->scope->id, 'organization', $this->scope->id ),
			'success',
			CorrelationId::generate(),
			PublicId::generate(),
			array( 'reason_code' => 'safe_event' )
		);
		$html = $this->markup( array( $s['overview'], 'audit' ) );
		self::assertStringContainsString( 'consent.version_published', $html );
		self::assertStringContainsString( 'Correlation', $html );
		self::assertStringNotContainsString( 'safe_event', $html );
		self::assertStringNotContainsString( (string) $s['admin'], $html );
		self::assertSame( 1, count( $s['reads']->audit( $s['actor'], $this->scope )['items'] ) );
		wp_set_current_user( $s['other'] );
		self::assertNull( $s['reads']->audit( new Actor( $s['other'] ), $this->scope ) );
		try {
			$s['overview']->audit();
			self::fail( 'Subscriber accessed audit administration.' );
		} catch ( \WPDieException ) {
			self::assertTrue( true );
		}
		try {
			$s['overview']->overview();
			self::fail( 'Subscriber accessed privileged system health.' );
		} catch ( \WPDieException ) {
			self::assertTrue( true );
		}
	}

	/** Draft and publication use nonce-gated M5 services with append-only versions. */
	public function test_consent_editor_respects_nonce_and_immutable_publications(): void {
		$s = $this->fixture();
		$html = $this->markup( array( $s['consents'], 'render' ) );
		self::assertStringContainsString( 'Create draft definition', $html );
		self::assertStringContainsString( 'No consent definitions yet.', $html );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST = array(
			'_wpnonce'          => 'invalid',
			'uop_consent_action' => 'create',
			'uop_consent_key'    => 'image',
			'uop_consent_title'  => 'Image permission',
		);
		$denied = $this->markup( array( $s['consents'], 'render' ) );
		self::assertStringContainsString( 'Request rejected', $denied );
		self::assertSame( array(), $s['docs']->summaries( $this->scope ) );
		$_POST['_wpnonce'] = wp_create_nonce( 'uop_m6_consents' );
		$created = $this->markup( array( $s['consents'], 'render' ) );
		self::assertStringContainsString( 'Consent definition draft created', $created );
		$draft = $s['docs']->summaries( $this->scope );
		self::assertCount( 1, $draft );
		self::assertSame( 'draft', $draft[0]['status'] );
		$_POST = array(
			'_wpnonce'               => wp_create_nonce( 'uop_m6_consents' ),
			'uop_consent_action'      => 'publish',
			'uop_consent_definition'  => PublicId::from_binary( (string) $draft[0]['public_id'] )->to_string(),
			'uop_consent_content'     => 'I explicitly allow use of my image in the event programme.',
		);
		$published = $this->markup( array( $s['consents'], 'render' ) );
		self::assertStringContainsString( 'New immutable consent version published.', $published );
		self::assertStringNotContainsString( 'I explicitly allow use of my image', $published );
		$versions = $s['docs']->summaries( $this->scope );
		self::assertSame( 1, (int) $versions[0]['version'] );
		self::assertSame( 'active', $versions[0]['status'] );
		self::assertCount( 1, $this->db->rows( 'SELECT id FROM %i', array( $this->prefix . 'consent_versions' ) ) );
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_POST = array();
		wp_set_current_user( $s['other'] );
		try {
			$s['consents']->render();
			self::fail( 'Unauthorized viewer accessed consent document editor.' );
		} catch ( \WPDieException ) {
			self::assertTrue( true );
		}
	}
}
