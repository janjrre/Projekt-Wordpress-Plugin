<?php
/**
 * Manually scheduled event occurrences, stored in UTC.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use InvalidArgumentException;
use UOP\Core\PublicId;
use UOP\Domain\Events\OccurrenceWindow;
use UOP\Domain\Organization\OrgScope;

/** No recurring schedule engine or locally ambiguous timestamps. */
final class OccurrenceRepository extends ScopedRepository {
	/**
	 * Bind an owner-scoped occurrence lookup.
	 *
	 * @param Connection $db     Database adapter.
	 * @param string     $prefix Trusted prefix.
	 */
	public function __construct( private Connection $db, private string $prefix ) {
		parent::__construct( $db, $prefix . 'event_occurrences', array( 'id', 'public_id', 'organization_id', 'event_post_id', 'start_at', 'end_at', 'timezone', 'status' ) );
	}

	/**
	 * Verify the event scope, then save a manually specified UTC window.
	 *
	 * @param OrgScope         $scope Scoped organization.
	 * @param PublicId         $uuid  Public occurrence identity.
	 * @param int              $post_id WordPress event ID.
	 * @param OccurrenceWindow $window Strict DST-aware occurrence interval.
	 * @param string           $utc_now Current UTC timestamp.
	 * @throws InvalidArgumentException If event belongs to another organization.
	 */
	public function create( OrgScope $scope, PublicId $uuid, int $post_id, OccurrenceWindow $window, string $utc_now ): void {
		$event = $this->db->rows(
			"SELECT event_post_id FROM %i WHERE organization_id = %d AND event_post_id = %d AND status = 'active' LIMIT 1",
			array( $this->prefix . 'event_settings', $scope->id, $post_id )
		);
		if ( ! $event ) {
			throw new InvalidArgumentException( 'Occurrence event must belong to the same active organization.' );
		}
		$this->db->execute(
			'INSERT INTO %i (public_id, organization_id, event_post_id, start_at, end_at, timezone, status, created_at, updated_at) VALUES (%s,%d,%d,%s,%s,%s,%s,%s,%s)',
			array( $this->prefix . 'event_occurrences', $uuid->to_binary(), $scope->id, $post_id, $window->start_utc(), $window->end_utc(), $window->zone, 'scheduled', $utc_now, $utc_now )
		);
	}
	/**
	 * Lock a scheduled occurrence and its owning event while respecting scope.
	 *
	 * @param OrgScope $scope       Organization boundary.
	 * @param PublicId $occurrence Opaque occurrence identity.
	 * @return array<string,mixed>|null Serialized occurrence row.
	 */
	public function lock_for_update( OrgScope $scope, PublicId $occurrence ): ?array {
		$rows = $this->db->rows(
			'SELECT id, event_post_id, start_at, end_at, timezone, status FROM %i WHERE organization_id = %d AND public_id = %s LIMIT 1 FOR UPDATE',
			array( $this->prefix . 'event_occurrences', $scope->id, $occurrence->to_binary() )
		);
		return $rows[0] ?? null;
	}

	/**
	 * Persist only the mutable schedule of one already locked occurrence.
	 *
	 * @param OrgScope         $scope       Organization boundary.
	 * @param PublicId         $occurrence Scoped occurrence identity.
	 * @param OccurrenceWindow $window     DST-validated new interval.
	 * @param string           $utc_now    Trusted UTC timestamp.
	 * @return bool Whether exactly one scheduled occurrence was updated.
	 */
	public function reschedule( OrgScope $scope, PublicId $occurrence, OccurrenceWindow $window, string $utc_now ): bool {
		return 1 === $this->db->execute(
			"UPDATE %i SET start_at = %s, end_at = %s, updated_at = %s WHERE organization_id = %d AND public_id = %s AND status = 'scheduled' AND timezone = %s",
			array( $this->prefix . 'event_occurrences', $window->start_utc(), $window->end_utc(), $utc_now, $scope->id, $occurrence->to_binary(), $window->zone )
		);
	}

	/**
	 * List event occurrences only from their owner organization.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param int      $event_post_id Trusted event post ID from a scoped read.
	 * @return list<array<string, mixed>>
	 */
	public function for_event( OrgScope $scope, int $event_post_id ): array {
		return $this->db->rows(
			'SELECT public_id, start_at, end_at, timezone, status FROM %i WHERE organization_id = %d AND event_post_id = %d ORDER BY start_at ASC, id ASC LIMIT 100',
			array( $this->prefix . 'event_occurrences', $scope->id, $event_post_id )
		);
	}
}
