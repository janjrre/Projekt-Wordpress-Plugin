<?php
/**
 * Non-destructive, resumable V2 age evidence schema migration.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

/** No legacy self-reported date or guardian claim is migrated into verified evidence. */
final class AgeAttestationMigration implements Migration {
	/**
	 * @param Connection $db Scoped database.
	 * @param string     $prefix Trusted WordPress prefix.
	 * @param string     $charset WordPress DDL charset/collation.
	 * @param string     $collation Verified exact collation.
	 */
	public function __construct( private Connection $db, private string $prefix, private string $charset, private string $collation ) {}

	/** @return int The second schema version. */
	public function version(): int {
		return 2;
	}

	/** @param MigrationContext $context Resumable migration checkpoint. */
	public function up( MigrationContext $context ): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$context->step(
			'age_attestation_table',
			function (): void {
				$this->db->apply_schema( AgeAttestationSchema::ddl( $this->prefix, $this->charset ), Installer::lock_name() );
			},
			fn () => AgeAttestationSchema::verify( $this->db, $this->prefix, $this->collation )
		);
	}

	/** @param MigrationContext $context Runner verification. */
	public function verify( MigrationContext $context ): void {
		AgeAttestationSchema::verify( $this->db, $this->prefix, $this->collation );
	}
}
