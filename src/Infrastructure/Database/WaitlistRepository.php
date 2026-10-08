<?php
/**
 * Organization-bound capacity release and offer storage.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use RuntimeException;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;

/** All state changes require the owning capacity bucket lock first. */
final class WaitlistRepository {
	/**
	 * Bind the transaction-bound connection.
	 *
	 * @param Connection $db     Current database connection.
	 * @param string     $prefix Verified site-specific table prefix.
	 */
	public function __construct( private Connection $db, private string $prefix ) {}

	/**
	 * Locate the bucket without locking the registration out of order.
	 *
	 * @param OrgScope $scope        Trusted organization.
	 * @param PublicId $registration Opaque registration identifier.
	 * @return int|null Bucket identity if registration has an allocation.
	 */
	public function bucket_for_registration( OrgScope $scope, PublicId $registration ): ?int {
		$rows = $this->db->rows(
			'SELECT COALESCE(c.bucket_id, w.bucket_id) AS bucket_id FROM %i r LEFT JOIN %i c ON c.registration_id = r.id AND c.organization_id = r.organization_id LEFT JOIN %i w ON w.registration_id = r.id AND w.organization_id = r.organization_id WHERE r.organization_id = %d AND r.public_id = %s LIMIT 1',
			array( $this->prefix . 'registrations', $this->prefix . 'capacity_claims', $this->prefix . 'waitlist_entries', $scope->id, $registration->to_binary() )
		);
		return $rows && null !== $rows[0]['bucket_id'] ? (int) $rows[0]['bucket_id'] : null;
	}

	/**
	 * Find an offer's bucket without acquiring a registration lock.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param PublicId $offer Public offer identifier.
	 * @return int|null Bucket key or null.
	 */
	public function bucket_for_offer( OrgScope $scope, PublicId $offer ): ?int {
		$rows = $this->db->rows(
			'SELECT bucket_id FROM %i WHERE organization_id = %d AND public_id = %s LIMIT 1',
			array( $this->prefix . 'waitlist_offers', $scope->id, $offer->to_binary() )
		);
		return $rows ? (int) $rows[0]['bucket_id'] : null;
	}

	/**
	 * Lock one organization-owned bucket using the global lock order.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param int      $id    Internal bucket identity.
	 * @return array<string, mixed>|null
	 */
	public function lock_bucket( OrgScope $scope, int $id ): ?array {
		$rows = $this->db->rows(
			'SELECT id, event_post_id, occurrence_id, capacity, status FROM %i WHERE organization_id = %d AND id = %d LIMIT 1 FOR UPDATE',
			array( $this->prefix . 'capacity_buckets', $scope->id, $id )
		);
		return $rows[0] ?? null;
	}

	/**
	 * Select FIFO queue head while owning the capacity bucket lock.
	 *
	 * @param OrgScope $scope  Trusted organization.
	 * @param int      $bucket Locked bucket identity.
	 * @return array<string, mixed>|null
	 */
	public function next_waiter( OrgScope $scope, int $bucket ): ?array {
		$rows = $this->db->rows(
			"SELECT w.id, w.registration_id, r.public_id AS registration_public_id FROM %i w INNER JOIN %i r ON r.id = w.registration_id AND r.organization_id = w.organization_id WHERE w.organization_id = %d AND w.bucket_id = %d AND w.status = 'waiting' AND r.status = 'waitlisted' ORDER BY w.priority DESC, w.joined_at ASC, w.id ASC LIMIT 1",
			array( $this->prefix . 'waitlist_entries', $this->prefix . 'registrations', $scope->id, $bucket )
		);
		return $rows[0] ?? null;
	}

	/**
	 * Lock the referenced registration after the bucket lock.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param int      $id    Registration identity.
	 * @return array<string, mixed>|null
	 */
	public function registration( OrgScope $scope, int $id ): ?array {
		$rows = $this->db->rows(
			'SELECT id, public_id, person_id, event_post_id, status, occurrence_id, email_verified_at FROM %i WHERE organization_id = %d AND id = %d LIMIT 1 FOR UPDATE',
			array( $this->prefix . 'registrations', $scope->id, $id )
		);
		return $rows[0] ?? null;
	}

	/**
	 * Resolve a public registration to its internal key within one organization.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param PublicId $id    Registration public UUID.
	 * @return int|null
	 */
	public function registration_id( OrgScope $scope, PublicId $id ): ?int {
		$rows = $this->db->rows(
			'SELECT id FROM %i WHERE organization_id = %d AND public_id = %s LIMIT 1',
			array( $this->prefix . 'registrations', $scope->id, $id->to_binary() )
		);
		return $rows ? (int) $rows[0]['id'] : null;
	}

	/**
	 * Return the current offer while holding the corresponding bucket lock.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param PublicId $offer Offer public identity.
	 * @return array<string, mixed>|null
	 */
	public function offer( OrgScope $scope, PublicId $offer ): ?array {
		$rows = $this->db->rows(
			'SELECT id, bucket_id, registration_id, waitlist_entry_id, claim_id, token_hash, expires_at, status FROM %i WHERE organization_id = %d AND public_id = %s LIMIT 1',
			array( $this->prefix . 'waitlist_offers', $scope->id, $offer->to_binary() )
		);
		return $rows[0] ?? null;
	}

	/**
	 * Move one waiting row into a held claim and hashed token offer.
	 *
	 * @param OrgScope $scope        Trusted organization.
	 * @param int      $bucket       Bucket already locked.
	 * @param int      $entry        Selected FIFO entry identity.
	 * @param int      $registration Locked waiting registration identity.
	 * @param PublicId $offer        New public offer ID.
	 * @param string   $token_hash   Binary SHA256, never plaintext.
	 * @param string   $utc_now      UTC creation instant.
	 * @param string   $expires      UTC expiry instant.
	 * @throws RuntimeException When the waiting row changed.
	 */
	public function hold( OrgScope $scope, int $bucket, int $entry, int $registration, PublicId $offer, string $token_hash, string $utc_now, string $expires ): void {
		$updated = $this->db->execute(
			"UPDATE %i SET status = 'offered', updated_at = %s WHERE id = %d AND organization_id = %d AND bucket_id = %d AND registration_id = %d AND status = 'waiting'",
			array( $this->prefix . 'waitlist_entries', $utc_now, $entry, $scope->id, $bucket, $registration )
		);
		if ( 1 !== $updated ) {
			throw new RuntimeException( 'Waitlist entry changed before offer.' );
		}
		$rows = $this->db->rows(
			'SELECT id, status FROM %i WHERE organization_id = %d AND registration_id = %d LIMIT 1',
			array( $this->prefix . 'capacity_claims', $scope->id, $registration )
		);
		if ( $rows ) {
			if ( 'released' !== $rows[0]['status'] ) {
				throw new RuntimeException( 'Registration already holds an occupied claim.' );
			}
			$changed = $this->db->execute(
				"UPDATE %i SET status = 'held', expires_at = %s, released_at = NULL, updated_at = %s WHERE organization_id = %d AND id = %d AND status = 'released'",
				array( $this->prefix . 'capacity_claims', $expires, $utc_now, $scope->id, (int) $rows[0]['id'] )
			);
			if ( 1 !== $changed ) {
				throw new RuntimeException( 'Released claim changed during re-offer.' );
			}
		} else {
			$claim = PublicId::generate();
			$this->db->execute(
				"INSERT INTO %i (public_id, organization_id, bucket_id, registration_id, status, expires_at, created_at, updated_at) VALUES (%s,%d,%d,%d,'held',%s,%s,%s)",
				array( $this->prefix . 'capacity_claims', $claim->to_binary(), $scope->id, $bucket, $registration, $expires, $utc_now, $utc_now )
			);
			$rows = $this->db->rows(
				'SELECT id, status FROM %i WHERE organization_id = %d AND registration_id = %d AND public_id = %s LIMIT 1',
				array( $this->prefix . 'capacity_claims', $scope->id, $registration, $claim->to_binary() )
			);
		}
		if ( ! $rows ) {
			throw new RuntimeException( 'Held claim missing.' );
		}
		$this->db->execute(
			"INSERT INTO %i (public_id, organization_id, waitlist_entry_id, registration_id, bucket_id, claim_id, token_hash, status, offered_at, expires_at) VALUES (%s,%d,%d,%d,%d,%d,%s,'offered',%s,%s)",
			array( $this->prefix . 'waitlist_offers', $offer->to_binary(), $scope->id, $entry, $registration, $bucket, (int) $rows[0]['id'], $token_hash, $utc_now, $expires )
		);
	}

	/**
	 * Release a confirmed or held claim and invalidate live offers.
	 *
	 * @param OrgScope $scope        Trusted organization.
	 * @param int      $registration Locked registration.
	 * @param int      $bucket       Locked bucket.
	 * @param string   $utc_now      UTC time.
	 */
	public function release( OrgScope $scope, int $registration, int $bucket, string $utc_now ): void {
		$this->db->execute(
			"UPDATE %i SET status = 'released', released_at = %s, updated_at = %s WHERE organization_id = %d AND bucket_id = %d AND registration_id = %d AND status IN ('confirmed','held')",
			array( $this->prefix . 'capacity_claims', $utc_now, $utc_now, $scope->id, $bucket, $registration )
		);
		$this->db->execute(
			"UPDATE %i SET status = 'cancelled', updated_at = %s WHERE organization_id = %d AND bucket_id = %d AND registration_id = %d AND status IN ('waiting','offered')",
			array( $this->prefix . 'waitlist_entries', $utc_now, $scope->id, $bucket, $registration )
		);
		$this->db->execute(
			"UPDATE %i SET status = 'cancelled' WHERE organization_id = %d AND bucket_id = %d AND registration_id = %d AND status = 'offered'",
			array( $this->prefix . 'waitlist_offers', $scope->id, $bucket, $registration )
		);
	}

	/**
	 * Accept one live offer and its real held seat.
	 *
	 * @param OrgScope $scope   Trusted organization.
	 * @param array    $offer   Scoped offer read after the bucket lock.
	 * @phpstan-param array<string, mixed> $offer
	 * @param string   $utc_now UTC acceptance instant.
	 * @throws RuntimeException For no longer held seats.
	 */
	public function accept( OrgScope $scope, array $offer, string $utc_now ): void {
		$changed = $this->db->execute(
			"UPDATE %i SET status = 'confirmed', expires_at = NULL, updated_at = %s WHERE organization_id = %d AND id = %d AND bucket_id = %d AND registration_id = %d AND status = 'held'",
			array( $this->prefix . 'capacity_claims', $utc_now, $scope->id, (int) $offer['claim_id'], (int) $offer['bucket_id'], (int) $offer['registration_id'] )
		);
		if ( 1 !== $changed ) {
			throw new RuntimeException( 'Reserved seat was lost.' );
		}
		$this->db->execute(
			"UPDATE %i SET status = 'accepted', accepted_at = %s WHERE organization_id = %d AND id = %d AND status = 'offered'",
			array( $this->prefix . 'waitlist_offers', $utc_now, $scope->id, (int) $offer['id'] )
		);
		$this->db->execute(
			"UPDATE %i SET status = 'accepted', updated_at = %s WHERE organization_id = %d AND id = %d AND status = 'offered'",
			array( $this->prefix . 'waitlist_entries', $utc_now, $scope->id, (int) $offer['waitlist_entry_id'] )
		);
	}

	/**
	 * Expire a held seat and move the applicant to the tail of the queue.
	 *
	 * @param OrgScope $scope   Trusted organization.
	 * @param array    $offer   Scoped offer after bucket serialization lock.
	 * @phpstan-param array<string, mixed> $offer
	 * @param string   $utc_now UTC expiry instant.
	 * @throws RuntimeException For a missing held claim.
	 */
	public function expire( OrgScope $scope, array $offer, string $utc_now ): void {
		$changed = $this->db->execute(
			"UPDATE %i SET status = 'released', released_at = %s, updated_at = %s WHERE organization_id = %d AND id = %d AND status = 'held'",
			array( $this->prefix . 'capacity_claims', $utc_now, $utc_now, $scope->id, (int) $offer['claim_id'] )
		);
		if ( 1 !== $changed ) {
			throw new RuntimeException( 'Offer no longer owns a held seat.' );
		}
		$this->db->execute(
			"UPDATE %i SET status = 'expired' WHERE organization_id = %d AND id = %d AND status = 'offered'",
			array( $this->prefix . 'waitlist_offers', $scope->id, (int) $offer['id'] )
		);
		$this->db->execute(
			"UPDATE %i SET status = 'waiting', joined_at = %s, updated_at = %s WHERE organization_id = %d AND id = %d AND status = 'offered'",
			array( $this->prefix . 'waitlist_entries', $utc_now, $utc_now, $scope->id, (int) $offer['waitlist_entry_id'] )
		);
	}

	/**
	 * Store a state/history command after the bucket and registration locks.
	 *
	 * @param OrgScope      $scope       Trusted organization.
	 * @param int           $id          Locked registration.
	 * @param string        $from        Observed status.
	 * @param string        $to          Validated new status.
	 * @param PublicId      $command     Idempotent command UUID.
	 * @param int           $actor_id    Current WordPress actor ID.
	 * @param string        $utc_now     UTC instant.
	 * @param CorrelationId $correlation Trace ID.
	 * @throws RuntimeException When status changes unexpectedly.
	 */
	public function transition( OrgScope $scope, int $id, string $from, string $to, PublicId $command, int $actor_id, string $utc_now, CorrelationId $correlation ): void {
		$changed = $this->db->execute(
			'UPDATE %i SET status = %s, accepted_at = CASE WHEN %s = %s THEN %s ELSE accepted_at END, cancelled_at = CASE WHEN %s = %s THEN %s ELSE cancelled_at END, version = version + 1, updated_at = %s WHERE organization_id = %d AND id = %d AND status = %s',
			array( $this->prefix . 'registrations', $to, $to, 'accepted', $utc_now, $to, 'cancelled', $utc_now, $utc_now, $scope->id, $id, $from )
		);
		if ( 1 !== $changed ) {
			throw new RuntimeException( 'Registration transition was superseded.' );
		}
		$this->db->execute(
			'INSERT INTO %i (registration_id, command_id, from_status, to_status, actor_user_id, correlation_id, created_at) VALUES (%d,%s,%s,%s,%d,%s,%s)',
			array( $this->prefix . 'registration_history', $id, $command->to_binary(), $from, $to, $actor_id > 0 ? $actor_id : 0, $correlation->to_binary(), $utc_now )
		);
	}
}
