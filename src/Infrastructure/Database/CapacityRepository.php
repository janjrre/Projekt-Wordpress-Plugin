<?php
/**
 * Bucket-serialized seat claims and waiting list storage.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use RuntimeException;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;

/** A bucket row lock is mandatory before counting or mutating occupied seats. */
final class CapacityRepository {
	/**
	 * Bind the current transactional database session.
	 *
	 * @param Connection $db     Database connection.
	 * @param string     $prefix Validated WordPress site prefix.
	 */
	public function __construct( private Connection $db, private string $prefix ) {}

	/**
	 * Resolve an active event without allowing organization bleed.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param PublicId $event Public event UUID.
	 * @return int|null Existing WordPress event ID.
	 */
	public function event_post( OrgScope $scope, PublicId $event ): ?int {
		$rows = $this->db->rows(
			"SELECT event_post_id FROM %i WHERE organization_id = %d AND public_id = %s AND status = 'active' LIMIT 1",
			array( $this->prefix . 'event_settings', $scope->id, $event->to_binary() )
		);
		return $rows ? (int) $rows[0]['event_post_id'] : null;
	}

	/**
	 * Create a single event-wide general-admission bucket.
	 *
	 * @param OrgScope $scope      Trusted organization.
	 * @param PublicId $public_id  Bucket public UUID.
	 * @param int      $event_post Scoped event post.
	 * @param int      $capacity   Maximum confirmed and held claims.
	 * @param string   $utc_now    UTC timestamp.
	 * @return int Internal bucket ID.
	 * @throws RuntimeException When bucket was not persisted.
	 */
	public function create_general( OrgScope $scope, PublicId $public_id, int $event_post, int $capacity, string $utc_now ): int {
		$this->db->execute(
			"INSERT INTO %i (public_id, organization_id, event_post_id, occurrence_id, bucket_key, label, capacity, priority, status, created_at, updated_at) VALUES (%s,%d,%d,0,'general','General admission',%d,0,'active',%s,%s)",
			array( $this->prefix . 'capacity_buckets', $public_id->to_binary(), $scope->id, $event_post, $capacity, $utc_now, $utc_now )
		);
		$rows = $this->db->rows(
			'SELECT id FROM %i WHERE organization_id = %d AND public_id = %s LIMIT 1',
			array( $this->prefix . 'capacity_buckets', $scope->id, $public_id->to_binary() )
		);
		if ( ! $rows ) {
			throw new RuntimeException( 'Capacity bucket could not be loaded.' );
		}
		return (int) $rows[0]['id'];
	}

	/**
	 * Lock the capacity serialization row before every seat-changing command.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param PublicId $bucket Opaque bucket UUID.
	 * @return array<string, mixed>|null
	 */
	public function lock_bucket( OrgScope $scope, PublicId $bucket ): ?array {
		$rows = $this->db->rows(
			"SELECT id, public_id, organization_id, event_post_id, occurrence_id, capacity, eligibility_json FROM %i WHERE organization_id = %d AND public_id = %s AND status = 'active' LIMIT 1 FOR UPDATE",
			array( $this->prefix . 'capacity_buckets', $scope->id, $bucket->to_binary() )
		);
		return $rows[0] ?? null;
	}

	/**
	 * Read the event verification gate through the bucket's owner organization.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param int      $event_post Internal post identity from locked bucket.
	 * @return bool Whether a verified contact is mandatory.
	 * @throws RuntimeException When the parent event is unavailable.
	 */
	public function requires_verification( OrgScope $scope, int $event_post ): bool {
		$rows = $this->db->rows(
			"SELECT require_email_verification FROM %i WHERE organization_id = %d AND event_post_id = %d AND status = 'active' LIMIT 1",
			array( $this->prefix . 'event_settings', $scope->id, $event_post )
		);
		if ( ! $rows ) {
			throw new RuntimeException( 'Capacity event is unavailable.' );
		}
		return 1 === (int) $rows[0]['require_email_verification'];
	}

	/**
	 * Count actual occupied claim rows while owning the bucket row lock.
	 *
	 * @param OrgScope $scope     Trusted organization.
	 * @param int      $bucket_id Locked internal bucket ID.
	 * @return int Confirmed and held claims only.
	 */
	public function occupied( OrgScope $scope, int $bucket_id ): int {
		$rows = $this->db->rows(
			"SELECT COUNT(*) AS occupied FROM %i WHERE organization_id = %d AND bucket_id = %d AND status IN ('held','confirmed')",
			array( $this->prefix . 'capacity_claims', $scope->id, $bucket_id )
		);
		return (int) $rows[0]['occupied'];
	}

	/**
	 * Reject bypassing a pre-existing queue entry or claim.
	 *
	 * @param OrgScope $scope           Trusted organization.
	 * @param int      $registration_id Locked registration ID.
	 * @return bool Whether any allocation or queue history exists.
	 */
	public function has_allocation( OrgScope $scope, int $registration_id ): bool {
		return (bool) $this->db->rows(
			'SELECT id FROM %i WHERE organization_id = %d AND registration_id = %d LIMIT 1',
			array( $this->prefix . 'capacity_claims', $scope->id, $registration_id )
		) || (bool) $this->db->rows(
			'SELECT id FROM %i WHERE organization_id = %d AND registration_id = %d LIMIT 1',
			array( $this->prefix . 'waitlist_entries', $scope->id, $registration_id )
		);
	}

	/**
	 * Count currently waiting registrations; a free seat must serve FIFO first.
	 *
	 * @param OrgScope $scope     Trusted organization.
	 * @param int      $bucket_id Locked bucket primary key.
	 * @return bool True if the bucket has an outstanding queue.
	 */
	public function has_waiters( OrgScope $scope, int $bucket_id ): bool {
		return (bool) $this->db->rows(
			"SELECT id FROM %i WHERE organization_id = %d AND bucket_id = %d AND status IN ('waiting','offered') LIMIT 1",
			array( $this->prefix . 'waitlist_entries', $scope->id, $bucket_id )
		);
	}

	/**
	 * Create exactly one confirmed claim inside the locked bucket transaction.
	 *
	 * @param OrgScope $scope           Trusted organization.
	 * @param int      $bucket_id       Locked bucket primary key.
	 * @param int      $registration_id Locked registration primary key.
	 * @param string   $utc_now         UTC timestamp.
	 */
	public function confirm( OrgScope $scope, int $bucket_id, int $registration_id, string $utc_now ): void {
		$this->db->execute(
			"INSERT INTO %i (public_id, organization_id, bucket_id, registration_id, status, created_at, updated_at) VALUES (%s,%d,%d,%d,'confirmed',%s,%s)",
			array( $this->prefix . 'capacity_claims', PublicId::generate()->to_binary(), $scope->id, $bucket_id, $registration_id, $utc_now, $utc_now )
		);
	}

	/**
	 * Enter a full bucket's deterministic FIFO queue.
	 *
	 * @param OrgScope $scope           Trusted organization.
	 * @param int      $bucket_id       Locked bucket primary key.
	 * @param int      $registration_id Locked registration primary key.
	 * @param string   $utc_now         UTC timestamp.
	 */
	public function waitlist( OrgScope $scope, int $bucket_id, int $registration_id, string $utc_now ): void {
		$this->db->execute(
			"INSERT INTO %i (public_id, organization_id, bucket_id, registration_id, priority, status, joined_at, updated_at) VALUES (%s,%d,%d,%d,0,'waiting',%s,%s)",
			array( $this->prefix . 'waitlist_entries', PublicId::generate()->to_binary(), $scope->id, $bucket_id, $registration_id, $utc_now, $utc_now )
		);
	}

	/**
	 * Change a registration's state with its historical command identity.
	 *
	 * @param OrgScope      $scope       Trusted organization.
	 * @param int           $id          Locked registration ID.
	 * @param string        $from        Previous business status.
	 * @param string        $to          New business status.
	 * @param PublicId      $bucket      Bucket public identity for idempotency.
	 * @param PublicId      $command     Client-generated command identity.
	 * @param int           $actor       Authorized reviewer.
	 * @param string        $utc_now     UTC timestamp.
	 * @param CorrelationId $correlation Request trace.
	 * @throws RuntimeException If row changed during the transaction.
	 */
	public function record_decision( OrgScope $scope, int $id, string $from, string $to, PublicId $bucket, PublicId $command, int $actor, string $utc_now, CorrelationId $correlation ): void {
		$updated = $this->db->execute(
			'UPDATE %i SET status = %s, accepted_at = CASE WHEN %s = %s THEN %s ELSE accepted_at END, waitlisted_at = CASE WHEN %s = %s THEN %s ELSE waitlisted_at END, version = version + 1, updated_at = %s WHERE organization_id = %d AND id = %d AND status = %s',
			array( $this->prefix . 'registrations', $to, $to, 'accepted', $utc_now, $to, 'waitlisted', $utc_now, $utc_now, $scope->id, $id, $from )
		);
		if ( 1 !== $updated ) {
			throw new RuntimeException( 'Registration state changed during allocation.' );
		}
		$this->db->execute(
			'INSERT INTO %i (registration_id, command_id, from_status, to_status, reason_code, actor_user_id, correlation_id, created_at) VALUES (%d,%s,%s,%s,%s,%d,%s,%s)',
			array( $this->prefix . 'registration_history', $id, $command->to_binary(), $from, $to, 'bucket:' . $bucket->to_string(), $actor, $correlation->to_binary(), $utc_now )
		);
	}

	/**
	 * Look up the prior allocation command without exposing another tenant.
	 *
	 * @param OrgScope $scope   Trusted organization.
	 * @param PublicId $command Idempotent command UUID.
	 * @return array<string, mixed>|null
	 */
	public function prior_decision( OrgScope $scope, PublicId $command ): ?array {
		$rows = $this->db->rows(
			'SELECT h.registration_id, h.to_status, h.reason_code FROM %i h INNER JOIN %i r ON r.id = h.registration_id WHERE r.organization_id = %d AND h.command_id = %s LIMIT 1',
			array( $this->prefix . 'registration_history', $this->prefix . 'registrations', $scope->id, $command->to_binary() )
		);
		return $rows[0] ?? null;
	}
}
