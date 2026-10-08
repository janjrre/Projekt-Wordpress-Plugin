<?php
/**
 * Active actor assignments used by the policy boundary.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use UOP\Domain\Organization\OrgScope;

/** Validated organization-owned service. */
final class AssignmentRepository {
	/**
	 * Initialize required dependencies and validated values.
	 *
	 * @param Connection $db db input.
	 * @param string     $prefix prefix input.
	 */
	public function __construct( private Connection $db, private string $prefix ) {}

	/**
	 * Query non-expired actor assignments without caching.
	 *
	 * @param OrgScope $scope scope input.
	 * @param int      $user_id user id input.
	 * @return list<array<string, mixed>>
	 */
	public function active_for( OrgScope $scope, int $user_id ): array {
		if ( $user_id < 1 ) {
			return array();
		}
		return $this->db->rows(
			"SELECT id, role_key, scope_type, scope_id, sensitivity_ceiling FROM %i WHERE organization_id = %d AND user_id = %d AND status = 'active' AND (valid_from IS NULL OR valid_from <= UTC_TIMESTAMP()) AND (valid_to IS NULL OR valid_to > UTC_TIMESTAMP()) ORDER BY id ASC LIMIT 100",
			array( $this->prefix . 'actor_assignments', $scope->id, $user_id )
		);
	}

	/**
	 * Revoke an actor's live grants within an organization.
	 *
	 * @param OrgScope $scope scope input.
	 * @param int      $user_id user id input.
	 * @param string   $utc_now utc now input.
	 * @return int
	 */
	public function revoke_for_actor( OrgScope $scope, int $user_id, string $utc_now ): int {
		return $this->db->execute(
			"UPDATE %i SET status = 'revoked', updated_at = %s WHERE organization_id = %d AND user_id = %d AND status = 'active'",
			array( $this->prefix . 'actor_assignments', $utc_now, $scope->id, $user_id )
		);
	}
}
