<?php
/**
 * Persistence composition root.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use UOP\Core\Bootstrap;
use UOP\Core\SystemClock;
use UOP\Core\TransactionManager;
/** Only infrastructure is wired here; no business repositories or features. */
final class Installer {
	/**
	 * Construct the site-specific runner.
	 *
	 * @return MigrationRunner
	 */
	public static function runner(): MigrationRunner {
		global $wpdb;
		$db            = new WpdbConnection( $wpdb );
		$state         = new MigrationState( $db, $wpdb->options );
		$transactions  = new TransactionManager(
			$db,
			static function ( int $microseconds ): void {
				usleep( $microseconds ); },
			static function ( \Throwable $error ): void {
				// Only stable diagnostic metadata; no exception message, SQL or values.
				update_option(
					'uop_post_commit_error',
					array(
						'type' => get_class( $error ),
						'code' => $error->getCode(),
					),
					false
				);
			}
		);
		$manifest      = new SchemaManifest( dirname( __DIR__, 3 ) . '/schema/manifest.json' );
		$prefix        = $wpdb->prefix . 'uop_';
		$inspector     = new SchemaInspector( $db, $manifest, $prefix, $wpdb->collate );
		$migration     = new InitialMigration( $db, $state, $manifest, $inspector, new SystemClock(), $prefix, $wpdb->get_charset_collate() );
		$age_migration = new AgeAttestationMigration( $db, $prefix, $wpdb->get_charset_collate(), $wpdb->collate );
		return new MigrationRunner( $db, $state, new MigrationRegistry( array( $migration, $age_migration ) ), $transactions, Bootstrap::errors( ... ), self::lock_name() );
	}

	/**
	 * Get a lock unique to this database and site.
	 *
	 * @return string
	 */
	public static function lock_name(): string {
		global $wpdb;
		$database = ( new WpdbConnection( $wpdb ) )->rows( 'SELECT DATABASE() AS name' );
		return 'uop_migrate_' . substr( hash( 'sha256', (string) $database[0]['name'] . ':' . $wpdb->prefix ), 0, 48 );
	}

	/** Apply initial installation or resume an interrupted upgrade. */
	public static function ensure_current(): void {
		if ( 2 !== (int) get_option( 'uop_db_version', 0 ) || 2 !== (int) get_option( 'uop_data_version', 0 ) ) {
			self::runner()->run();
		}
	}

	/**
	 * Report stored migration state in WordPress Site Health.
	 *
	 * @return array<string, mixed>
	 */
	public static function health(): array {
		MigrationState::register_option_reads();
		$state = array();
		$good  = false;
		try {
			$state = get_option( 'uop_migration_status', array() );
			$good  = 2 === (int) get_option( 'uop_db_version', 0 ) && 2 === (int) get_option( 'uop_data_version', 0 ) && 'complete' === ( $state['status'] ?? '' );
			if ( Bootstrap::errors() ) {
				$good  = false;
				$state = array( 'status' => 'unsupported_environment' );
			} else {
				global $wpdb;
				$db        = new WpdbConnection( $wpdb );
				$manifest  = new SchemaManifest( dirname( __DIR__, 3 ) . '/schema/manifest.json' );
				$inspector = new SchemaInspector( $db, $manifest, $wpdb->prefix . 'uop_', $wpdb->collate );
				$inspector->verify_all();
				AgeAttestationSchema::verify( $db, $wpdb->prefix . 'uop_', $wpdb->collate );
				// Schema versions alone cannot prove that the transactional V1
				// default-organization seed still exists and matches its pointer.
				// This health check is strictly read-only; never repair data here.
				$default_id = (int) get_option( 'uop_default_organization_id', 0 );
				$seed       = $db->rows( 'SELECT id, status FROM %i WHERE slug = %s', array( $wpdb->prefix . 'uop_organizations', 'default' ) );
				if ( 1 !== count( $seed ) || $default_id < 1 || $default_id !== (int) $seed[0]['id'] || 'active' !== $seed[0]['status'] ) {
					throw new \RuntimeException( 'Default organization seed is inconsistent.' );
				}
			}
		} catch ( \Throwable $error ) {
			$good  = false;
			$state = array(
				'status'     => 'verification_failed',
				'error_type' => get_class( $error ),
				'error_code' => $error->getCode(),
			);
		}
		$diagnostic = sprintf(
			/* translators: 1: migration status, 2: safe exception class, 3: numeric error code. */
			__( 'Migration state: %1$s. Error type: %2$s. Error code: %3$d.', 'uop-core' ),
			(string) ( $state['status'] ?? 'not_started' ),
			(string) ( $state['error_type'] ?? 'none' ),
			(int) ( $state['error_code'] ?? 0 )
		);
		return array(
			'label'       => $good ? __( 'UOP Core schema is current', 'uop-core' ) : __( 'UOP Core migration requires attention', 'uop-core' ),
			'status'      => $good ? 'good' : 'critical',
			'badge'       => array(
				'label' => 'UOP Core',
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html__( 'UOP Core requires schema and data version 2 (the frozen 28-table V1 plus an age-attestation table). Retry an interrupted migration after resolving the database or environment failure.', 'uop-core' ) . '</p><p>' . esc_html( $diagnostic ) . '</p>',
			'actions'     => '',
			'test'        => 'uop_schema',
		);
	}
}
