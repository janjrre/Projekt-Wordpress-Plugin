<?php
/**
 * Serialized migration execution and failure reporting.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use Closure;
use RuntimeException;
use Throwable;
use UOP\Core\TransactionManager;
/** Connection-scoped advisory locks survive DDL commits and release on connection loss. */
final class MigrationRunner {
	/**
	 * Bind migration infrastructure.
	 *
	 * @param Connection         $db Connection.
	 * @param MigrationState     $state Durable state.
	 * @param MigrationRegistry  $registry Ordered registry.
	 * @param TransactionManager $transactions Transaction manager.
	 * @param Closure            $environment Read-only gate.
	 * @param string             $lock_name Database and site-specific lock.
	 * @phpstan-param Closure(): list<string> $environment
	 */
	public function __construct(
		private Connection $db,
		private MigrationState $state,
		private MigrationRegistry $registry,
		private TransactionManager $transactions,
		private Closure $environment,
		private string $lock_name
	) {}

	/**
	 * Resume after the last verified checkpoint.
	 *
	 * @throws RuntimeException If unsupported, busy or a downgrade.
	 * @throws Throwable If a migration fails.
	 */
	public function run(): void {
		if ( ( $this->environment )() ) {
			throw new RuntimeException( 'Environment is unsupported; migration was not started.' );
		}
		$lock = $this->db->rows( 'SELECT GET_LOCK(%s, 0) AS acquired', array( $this->lock_name ) );
		if ( 1 !== (int) ( $lock[0]['acquired'] ?? 0 ) ) {
			throw new RuntimeException( 'Another migration runner is active.' );
		}
		try {
			$installed = (int) $this->state->read( 'uop_db_version', 0 );
			if ( $installed > $this->registry->latest() ) {
				throw new RuntimeException( 'Schema downgrade is not supported.' );
			}
			foreach ( $this->registry->pending( $installed ) as $migration ) {
				$this->state->write(
					'uop_migration_status',
					array(
						'status'  => 'running',
						'version' => $migration->version(),
					)
				);
				$context = new MigrationContext( $this->state, $this->transactions, $migration->version() );
				$migration->up( $context );
				$migration->verify( $context );
				$this->transactions->run(
					function () use ( $migration ): void {
						$this->state->write( 'uop_db_version', $migration->version() );
						$this->state->write( 'uop_data_version', $migration->version() );
						$this->state->write( 'uop_migration_progress_' . $migration->version(), array() );
						$this->state->write(
							'uop_migration_status',
							array(
								'status'  => 'complete',
								'version' => $migration->version(),
							)
						);
					}
				);
			}
		} catch ( Throwable $error ) {
			$this->state->write(
				'uop_migration_status',
				array(
					'status'     => 'failed',
					'error_type' => get_class( $error ),
					'error_code' => $error->getCode(),
				)
			);
			throw $error;
		} finally {
			$this->db->rows( 'SELECT RELEASE_LOCK(%s) AS released', array( $this->lock_name ) );
		}
	}
}
