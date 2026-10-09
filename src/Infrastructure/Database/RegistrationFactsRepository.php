<?php
/**
 * Organization-verified authoritative registration eligibility facts.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use UOP\Domain\Organization\OrgScope;

/** Reads trusted person profile values and scheduled event start instants. */
final class RegistrationFactsRepository {
	/**
	 * Construct the scoped read repository.
	 *
	 * @param Connection $db     Active transaction connection.
	 * @param string     $prefix Trusted WordPress table prefix.
	 */
	public function __construct( private Connection $db, private string $prefix ) {}

	/**
	 * Load one named field and its values under the owning active person.
	 *
	 * @param OrgScope $scope     Tenant scope.
	 * @param int      $person_id Authorized subject ID.
	 * @param string   $field_key Defined profile key.
	 * @return list<array<string, mixed>> Field metadata repeated per typed value.
	 */
	public function profile_value( OrgScope $scope, int $person_id, string $field_key ): array {
		return $this->db->rows(
			"SELECT f.field_key, f.data_type, f.sensitivity, f.subject_view, f.subject_edit, f.delegate_view, f.delegate_edit, v.ordinal, v.value_string, v.value_text, v.value_decimal, v.value_date, v.value_boolean FROM %i p INNER JOIN %i f ON f.organization_id = p.organization_id AND f.status = 'active' AND f.field_key = %s LEFT JOIN %i v ON v.person_id = p.id AND v.field_id = f.id WHERE p.organization_id = %d AND p.id = %d AND p.status = 'active' ORDER BY v.ordinal ASC LIMIT 101 FOR UPDATE",
			array( $this->prefix . 'persons', $this->prefix . 'profile_fields', $field_key, $this->prefix . 'profile_values', $scope->id, $person_id )
		);
	}

	/**
	 * Resolve a unique scheduled occurrence start; ambiguous events fail closed.
	 *
	 * @param OrgScope $scope      Tenant scope.
	 * @param int      $event_post Scoped event post key.
	 * @param int      $occurrence  Scoped occurrence ID or zero.
	 * @return string|null Trusted UTC timestamp when unambiguous.
	 */
	public function event_start( OrgScope $scope, int $event_post, int $occurrence ): ?string {
		if ( $occurrence > 0 ) {
			$rows = $this->db->rows(
				"SELECT start_at FROM %i WHERE organization_id = %d AND event_post_id = %d AND id = %d AND status = 'scheduled' LIMIT 1 FOR UPDATE",
				array( $this->prefix . 'event_occurrences', $scope->id, $event_post, $occurrence )
			);
			return $rows[0]['start_at'] ?? null;
		}
		$rows = $this->db->rows(
			"SELECT start_at FROM %i WHERE organization_id = %d AND event_post_id = %d AND status = 'scheduled' ORDER BY start_at ASC, id ASC LIMIT 2 FOR UPDATE",
			array( $this->prefix . 'event_occurrences', $scope->id, $event_post )
		);
		return 1 === count( $rows ) ? (string) $rows[0]['start_at'] : null;
	}
}
