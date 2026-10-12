<?php
/**
 * Append-only, tenant- and event-scoped age threshold attestations.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use RuntimeException;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;

/** Stores only a reviewed threshold result, not birth dates or identity documents. */
final class AgeAttestationRepository {
	/**
	 * @param Connection $db Database connection.
	 * @param string     $prefix Trusted site-specific table prefix.
	 */
	public function __construct( private Connection $db, private string $prefix ) {}

	/**
	 * Find the latest immutable decision (including a revocation tombstone).
	 *
	 * @param OrgScope $scope Tenant scope.
	 * @param int      $person Internal subject ID.
	 * @param int      $event Scoped WordPress event ID.
	 * @param int      $threshold Age threshold.
	 * @return array<string,mixed>|null
	 */
	public function latest( OrgScope $scope, int $person, int $event, int $threshold ): ?array {
		$rows = $this->db->rows(
			'SELECT a.id, a.public_id, a.outcome, a.status, a.method_key, a.checked_at, a.valid_until, a.reviewer_user_id FROM %i a INNER JOIN %i p ON p.id = a.person_id AND p.organization_id = a.organization_id AND p.status = %s INNER JOIN %i e ON e.event_post_id = a.event_post_id AND e.organization_id = a.organization_id AND e.status = %s WHERE a.organization_id = %d AND a.person_id = %d AND a.event_post_id = %d AND a.threshold_age = %d ORDER BY a.id DESC LIMIT 1 FOR UPDATE',
			array( $this->prefix . 'age_attestations', $this->prefix . 'persons', 'active', $this->prefix . 'event_settings', 'active', $scope->id, $person, $event, $threshold )
		);
		return $rows[0] ?? null;
	}

	/**
	 * Verify a positive purpose-bound class at the time of action and event.
	 *
	 * @param OrgScope $scope Tenant.
	 * @param int      $person Subject.
	 * @param int      $event Event.
	 * @param int      $threshold Limit.
	 * @param string   $outcome 'gte' or 'lt'.
	 * @param string   $now UTC action time.
	 * @param string   $event_start UTC date/time from scheduled occurrence.
	 */
	public function matches( OrgScope $scope, int $person, int $event, int $threshold, string $outcome, string $now, string $event_start ): bool {
		$row = $this->latest( $scope, $person, $event, $threshold );
		return null !== $row && 'verified' === $row['status'] && $outcome === $row['outcome']
			&& (string) $row['checked_at'] <= $now && (string) $row['valid_until'] >= $now && (string) $row['valid_until'] >= $event_start;
	}

	/**
	 * Write one vetted or revoked decision without altering earlier evidence.
	 *
	 * @param OrgScope $scope Organization.
	 * @param PublicId $uuid Unique public attestation.
	 * @param int      $person Scoped subject ID.
	 * @param int      $event Published event post ID.
	 * @param int      $threshold Threshold.
	 * @param string   $outcome 'gte' or 'lt'.
	 * @param string   $status 'verified' or 'revoked'.
	 * @param int      $reviewer Authenticated independent reviewer.
	 * @param string   $method Fixed method key, or 'revoked'.
	 * @param string   $now UTC checked/created at.
	 * @param string   $expiry UTC expiry.
	 * @return int New internal immutable attestation ID.
	 */
	public function append( OrgScope $scope, PublicId $uuid, int $person, int $event, int $threshold, string $outcome, string $status, int $reviewer, string $method, string $now, string $expiry ): int {
		if ( $person < 1 || $event < 1 || $reviewer < 1 || $threshold < 1 || $threshold > 120
			|| ! in_array( $outcome, array( 'gte', 'lt' ), true ) || ! in_array( $status, array( 'verified', 'revoked' ), true ) ) {
			throw new RuntimeException( 'Age attestation record is invalid.' );
		}
		$this->db->execute(
			'INSERT INTO %i (public_id, organization_id, person_id, event_post_id, threshold_age, outcome, status, reviewer_user_id, method_key, checked_at, valid_until, created_at) VALUES (%s,%d,%d,%d,%d,%s,%s,%d,%s,%s,%s,%s)',
			array( $this->prefix . 'age_attestations', $uuid->to_binary(), $scope->id, $person, $event, $threshold, $outcome, $status, $reviewer, $method, $now, $expiry, $now )
		);
		$row = $this->db->rows(
			'SELECT id FROM %i WHERE organization_id = %d AND public_id = %s LIMIT 1',
			array( $this->prefix . 'age_attestations', $scope->id, $uuid->to_binary() )
		);
		if ( ! $row ) {
			throw new RuntimeException( 'Age attestation was not persisted.' );
		}
		return (int) $row[0]['id'];
	}
}
