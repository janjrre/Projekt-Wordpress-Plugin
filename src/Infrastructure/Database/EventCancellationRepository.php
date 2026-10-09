<?php
/**
 * Organization-scoped event cancellation persistence.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use RuntimeException;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;

/**
 * An event cancellation locks the event, then all its buckets, then registrations.
 * The caller owns one transaction throughout the complete cancellation.
 */
final class EventCancellationRepository {
	/**
	 * @param Connection $db     Transaction-bound database.
	 * @param string     $prefix Trusted WordPress site prefix.
	 */
	public function __construct( private Connection $db, private string $prefix ) {}

	/**
	 * Lock the current event status, never a repeatable-read snapshot.
	 *
	 * @param OrgScope $scope Organization boundary.
	 * @param PublicId $event Event public identity.
	 * @return array<string, mixed>|null
	 */
	public function lock_event( OrgScope $scope, PublicId $event ): ?array {
		$rows = $this->db->rows(
			'SELECT event_post_id, public_id, status FROM %i WHERE organization_id = %d AND public_id = %s LIMIT 1 FOR UPDATE',
			array( $this->prefix . 'event_settings', $scope->id, $event->to_binary() )
		);
		return $rows[0] ?? null;
	}

	/**
	 * Lock every capacity serialization row before releasing any claims.
	 * The locked event settings row prevents new authorized bucket creation.
	 *
	 * @param OrgScope $scope Organization boundary.
	 * @param int      $post   Event CPT ID.
	 */
	public function lock_buckets( OrgScope $scope, int $post ): void {
		$this->db->rows(
			'SELECT id FROM %i WHERE organization_id = %d AND event_post_id = %d ORDER BY id ASC FOR UPDATE',
			array( $this->prefix . 'capacity_buckets', $scope->id, $post )
		);
	}

	/**
	 * Close admission and invalidate all associated buckets.
	 *
	 * @param OrgScope $scope   Organization boundary.
	 * @param int      $post    Locked event post.
	 * @param string   $utc_now Trusted UTC time.
	 */
	public function close_event( OrgScope $scope, int $post, string $utc_now ): void {
		$updated = $this->db->execute(
			"UPDATE %i SET status = 'cancelled', version = version + 1, updated_at = %s WHERE organization_id = %d AND event_post_id = %d AND status = 'active'",
			array( $this->prefix . 'event_settings', $utc_now, $scope->id, $post )
		);
		if ( 1 !== $updated ) {
			throw new RuntimeException( 'Event cancellation state was changed.' );
		}
		$this->db->execute(
			"UPDATE %i SET status = 'cancelled', updated_at = %s WHERE organization_id = %d AND event_post_id = %d AND status <> 'cancelled'",
			array( $this->prefix . 'capacity_buckets', $utc_now, $scope->id, $post )
		);
	}

	/**
	 * Lock an ordered bounded page so no registration escapes cleanup.
	 *
	 * @param OrgScope $scope Organization boundary.
	 * @param int      $post   Event post.
	 * @param int      $after  Last processed primary key.
	 * @return list<array<string, mixed>>
	 */
	public function active_registrations_after( OrgScope $scope, int $post, int $after ): array {
		return $this->db->rows(
			"SELECT id, public_id, person_id, status FROM %i WHERE organization_id = %d AND event_post_id = %d AND id > %d AND status IN ('submitted','review','accepted','waitlisted','offered') ORDER BY id ASC LIMIT 100 FOR UPDATE",
			array( $this->prefix . 'registrations', $scope->id, $post, $after )
		);
	}

	/**
	 * Release seats and invalidate offers, without removing historical rows.
	 *
	 * @param OrgScope $scope Organization boundary.
	 * @param int      $registration Locked registration key.
	 * @param string   $utc_now Trusted UTC time.
	 */
	public function release_registration( OrgScope $scope, int $registration, string $utc_now ): void {
		$this->db->execute(
			"UPDATE %i SET status = 'released', released_at = %s, updated_at = %s WHERE organization_id = %d AND registration_id = %d AND status IN ('confirmed','held')",
			array( $this->prefix . 'capacity_claims', $utc_now, $utc_now, $scope->id, $registration )
		);
		$this->db->execute(
			"UPDATE %i SET status = 'cancelled', updated_at = %s WHERE organization_id = %d AND registration_id = %d AND status IN ('waiting','offered')",
			array( $this->prefix . 'waitlist_entries', $utc_now, $scope->id, $registration )
		);
		$this->db->execute(
			"UPDATE %i SET status = 'cancelled' WHERE organization_id = %d AND registration_id = %d AND status = 'offered'",
			array( $this->prefix . 'waitlist_offers', $scope->id, $registration )
		);
	}

	/**
	 * Close one registration while preserving its immutable snapshots.
	 *
	 * @param OrgScope      $scope        Organization boundary.
	 * @param int           $registration Locked registration key.
	 * @param string        $previous     Previous observed status.
	 * @param PublicId      $command      New per-registration history UUID.
	 * @param int           $actor_id     Manager user ID.
	 * @param string        $utc_now      Trusted UTC time.
	 * @param CorrelationId $correlation  Request correlation.
	 */
	public function cancel_registration( OrgScope $scope, int $registration, string $previous, PublicId $command, int $actor_id, string $utc_now, CorrelationId $correlation ): void {
		$updated = $this->db->execute(
			"UPDATE %i SET status = 'cancelled', status_reason_code = 'event_cancelled', cancelled_at = %s, email_verification_token_hash = NULL, verification_expires_at = NULL, version = version + 1, updated_at = %s WHERE organization_id = %d AND id = %d AND status = %s",
			array( $this->prefix . 'registrations', $utc_now, $utc_now, $scope->id, $registration, $previous )
		);
		if ( 1 !== $updated ) {
			throw new RuntimeException( 'Event registration cancellation was superseded.' );
		}
		$this->db->execute(
			'INSERT INTO %i (registration_id, command_id, from_status, to_status, reason_code, actor_user_id, correlation_id, created_at) VALUES (%d,%s,%s,%s,%s,%d,%s,%s)',
			array( $this->prefix . 'registration_history', $registration, $command->to_binary(), $previous, 'cancelled', 'event_cancelled', $actor_id, $correlation->to_binary(), $utc_now )
		);
	}
}
