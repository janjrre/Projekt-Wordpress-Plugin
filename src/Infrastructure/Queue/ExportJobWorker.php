<?php
/**
 * Durable Action Scheduler handoff for private export jobs.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Queue;

use UOP\Application\Export\ExportJobService;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\Connection;
use UOP\Infrastructure\Database\ExportJobRepository;

/** Recover queued jobs after scheduler outages; never retry unknown processing. */
final class ExportJobWorker {
	/**
	 * Bind queue services without sharing private contents with queue arguments.
	 *
	 * @param Connection          $db     Live database.
	 * @param string              $prefix Trusted site prefix.
	 * @param ExportJobRepository $jobs   Durable job persistence.
	 * @param ExportJobService    $service Re-authorizing export worker service.
	 */
	public function __construct( private Connection $db, private string $prefix, private ExportJobRepository $jobs, private ExportJobService $service ) {}

	/** Register worker and periodic bounded recovery/cleanup. */
	public function register_hooks(): void {
		add_action( 'uop_generate_export', array( $this, 'generate' ), 10, 2 );
		add_action( 'uop_export_sweep', array( $this, 'sweep' ) );
		add_action( 'uop_export_cleanup', array( $this, 'cleanup' ) );
		add_action(
			'init',
			static function (): void {
				if ( ! function_exists( 'as_schedule_recurring_action' ) || ! function_exists( 'as_next_scheduled_action' ) ) {
					return;
				}
				foreach ( array( 'uop_export_sweep' => 300, 'uop_export_cleanup' => 3600 ) as $hook => $interval ) {
					if ( false === as_next_scheduled_action( $hook, array(), 'uop' ) ) {
						as_schedule_recurring_action( time() + 60, $interval, $hook, array(), 'uop', true );
					}
				}
			},
			25
		);
	}

	/**
	 * Execute the exact queued job. Auth must be rechecked by the service.
	 *
	 * @param int    $organization_id Organization from queue payload.
	 * @param string $uuid            Public job UUID, never file path.
	 */
	public function generate( int $organization_id, string $uuid ): void {
		if ( $organization_id < 1 ) {
			return;
		}
		$this->service->process( new OrgScope( $organization_id ), PublicId::from_string( $uuid ), gmdate( 'Y-m-d H:i:s' ) );
	}

	/** Recover existing queued export work, without duplicating claims. */
	public function sweep(): void {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return;
		}
		foreach ( $this->organizations() as $organization ) {
			$scope = new OrgScope( $organization );
			foreach ( $this->jobs->pending( $scope, gmdate( 'Y-m-d H:i:s' ) ) as $binary ) {
				as_enqueue_async_action( 'uop_generate_export', array( $scope->id, PublicId::from_binary( $binary )->to_string() ), 'uop', true );
			}
		}
	}

	/** Delete only expired private files; a failure remains visible and retryable. */
	public function cleanup(): void {
		foreach ( $this->organizations() as $organization ) {
			$this->service->cleanup( new OrgScope( $organization ), gmdate( 'Y-m-d H:i:s' ) );
		}
	}

	/**
	 * Enumerate bounded known site organizations (V1 single site).
	 *
	 * @return list<int> Trusted internal organization identities.
	 */
	private function organizations(): array {
		$rows = $this->db->rows(
			'SELECT id FROM %i ORDER BY id ASC LIMIT 100',
			array( $this->prefix . 'organizations' )
		);
		return array_map( static fn ( array $row ): int => (int) $row['id'], $rows );
	}
}
