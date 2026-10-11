<?php
/**
 * Non-authorizing person relationships.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use InvalidArgumentException;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;

/** A relationship is evidence of a link, never a permission grant. */
final class RelationshipRepository {
	/**
	 * Initialize required dependencies and validated values.
	 *
	 * @param Connection $db db input.
	 * @param string     $prefix prefix input.
	 */
	public function __construct( private Connection $db, private string $prefix ) {}

	/**
	 * Find an organization-owned resource by public ID.
	 *
	 * @param OrgScope $scope scope input.
	 * @param PublicId $id id input.
	 * @return array<string, mixed>|null
	 */
	public function find( OrgScope $scope, PublicId $id ): ?array {
		$rows = $this->db->rows(
			'SELECT id, public_id, organization_id, from_person_id, to_person_id, type_key, status, valid_from, valid_to FROM %i WHERE organization_id = %d AND public_id = %s LIMIT 1',
			array( $this->prefix . 'relationships', $scope->id, $id->to_binary() )
		);
		return $rows[0] ?? null;
	}

	/**
	 * Create a validated organization-owned record.
	 *
	 * @param OrgScope $scope scope input.
	 * @param PublicId $id id input.
	 * @param int      $from_id from id input.
	 * @param int      $to_id to id input.
	 * @param string   $type type input.
	 * @param string   $utc_now utc now input.
	 * @throws \InvalidArgumentException When input violates invariants.
	 */
	public function create( OrgScope $scope, PublicId $id, int $from_id, int $to_id, string $type, string $utc_now ): void {
		if ( $from_id < 1 || $to_id < 1 || $from_id === $to_id || ! in_array( $type, array( 'guardian_of', 'representative_of' ), true ) ) {
			throw new InvalidArgumentException( 'Invalid relationship.' );
		}
		$people = $this->db->rows(
			"SELECT id FROM %i WHERE organization_id = %d AND status = 'active' AND id IN (%d, %d)",
			array( $this->prefix . 'persons', $scope->id, $from_id, $to_id )
		);
		if ( 2 !== count( $people ) ) {
			throw new InvalidArgumentException( 'Relationship subjects are outside the organization.' );
		}
		$this->db->execute(
			'INSERT INTO %i (public_id, organization_id, from_person_id, to_person_id, type_key, status, created_at, updated_at) VALUES (%s, %d, %d, %d, %s, %s, %s, %s)',
			array( $this->prefix . 'relationships', $id->to_binary(), $scope->id, $from_id, $to_id, $type, 'active', $utc_now, $utc_now )
		);
	}
	/**
	 * An authenticated subject claim remains non-authorizing until reviewed.
	 * Duplicate or previously rejected claims are not silently reactivated.
	 */
	public function request_guardian( OrgScope $scope, PublicId $id, int $from_id, int $to_id, string $now ): void {
		if ( $from_id < 1 || $to_id < 1 || $from_id === $to_id ) {
			throw new InvalidArgumentException( 'Invalid guardian claim.' );
		}
		$this->db->execute(
			"INSERT INTO %i (public_id, organization_id, from_person_id, to_person_id, type_key, status, created_at, updated_at) VALUES (%s,%d,%d,%d,'guardian_of','pending',%s,%s)",
			array( $this->prefix . 'relationships', $id->to_binary(), $scope->id, $from_id, $to_id, $now, $now )
		);
	}

	/**
	 * Private reviewer queue. Never return account emails or evidence documents.
	 *
	 * @return list<array<string,mixed>>
	 */
	public function pending_guardians( OrgScope $scope ): array {
		return $this->db->rows(
			"SELECT r.public_id, r.created_at, claimant.display_name AS claimant, subject.display_name AS subject FROM %i r INNER JOIN %i claimant ON claimant.id = r.from_person_id AND claimant.organization_id = r.organization_id AND claimant.status = 'active' INNER JOIN %i subject ON subject.id = r.to_person_id AND subject.organization_id = r.organization_id AND subject.status = 'active' WHERE r.organization_id = %d AND r.type_key = 'guardian_of' AND r.status = 'pending' ORDER BY r.id ASC LIMIT 50",
			array( $this->prefix . 'relationships', $this->prefix . 'persons', $this->prefix . 'persons', $scope->id )
		);
	}

	/**
	 * Lock a pending guardian request to prevent competing review decisions.
	 *
	 * @return array<string,mixed>|null
	 */
	public function lock_pending_guardian( OrgScope $scope, PublicId $id ): ?array {
		$rows = $this->db->rows(
			"SELECT id, public_id, from_person_id, to_person_id FROM %i WHERE organization_id = %d AND public_id = %s AND type_key = 'guardian_of' AND status = 'pending' LIMIT 1 FOR UPDATE",
			array( $this->prefix . 'relationships', $scope->id, $id->to_binary() )
		);
		return $rows[0] ?? null;
	}

	/**
	 * Persist an explicit review decision; never apply unreviewed authorization.
	 * The metadata is restricted to fixed status codes, not proof documents.
	 */
	public function decide_guardian( OrgScope $scope, int $id, string $status, string $now ): bool {
		if ( ! in_array( $status, array( 'active', 'rejected' ), true ) ) {
			throw new InvalidArgumentException( 'Unknown guardian decision.' );
		}
		return 1 === $this->db->execute(
			"UPDATE %i SET status = %s, updated_at = %s WHERE organization_id = %d AND id = %d AND type_key = 'guardian_of' AND status = 'pending'",
			array( $this->prefix . 'relationships', $status, $now, $scope->id, $id )
		);
	}
}
