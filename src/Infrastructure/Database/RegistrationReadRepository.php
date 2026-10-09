<?php
/**
 * Explicit, scoped read-only registration DTO persistence.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;

/** SQL stays in infrastructure; authorization and projection stay in services. */
final class RegistrationReadRepository {
	/**
	 * Bind trusted database and multisite-safe prefix.
	 *
	 * @param Connection $db     Scoped connection.
	 * @param string     $prefix Fixed site prefix.
	 */
	public function __construct( private Connection $db, private string $prefix ) {}

	/**
	 * Join only approved public-identity and status columns.
	 *
	 * @return string Fixed SELECT projection.
	 */
	private function select(): string {
		return 'SELECT r.id, r.public_id, r.person_id, r.event_post_id, r.status, r.version, r.created_at, p.public_id AS person_public_id, e.public_id AS event_public_id FROM %i r INNER JOIN %i p ON p.id = r.person_id AND p.organization_id = r.organization_id LEFT JOIN %i e ON e.organization_id = r.organization_id AND e.event_post_id = r.event_post_id';
	}

	/**
	 * Load one registration, never including private emails or form snapshots.
	 *
	 * @param OrgScope $scope Server-trusted organization.
	 * @param PublicId $id    Registration UUID.
	 * @return array<string, mixed>|null
	 */
	public function find( OrgScope $scope, PublicId $id ): ?array {
		$rows = $this->db->rows(
			$this->select() . ' WHERE r.organization_id = %d AND r.public_id = %s LIMIT 1',
			array( $this->prefix . 'registrations', $this->prefix . 'persons', $this->prefix . 'event_settings', $scope->id, $id->to_binary() )
		);
		return $rows[0] ?? null;
	}

	/**
	 * Resolve a cursor only for a registration owned by the selected person.
	 *
	 * @param OrgScope $scope     Trusted tenant.
	 * @param int      $person_id Internal subject identity obtained from scoped person lookup.
	 * @param PublicId $cursor    Public registration marker.
	 * @return int|null Internal key usable within the same scoped query only.
	 */
	public function cursor_for_person( OrgScope $scope, int $person_id, PublicId $cursor ): ?int {
		$rows = $this->db->rows(
			'SELECT id FROM %i WHERE organization_id = %d AND person_id = %d AND public_id = %s LIMIT 1',
			array( $this->prefix . 'registrations', $scope->id, $person_id, $cursor->to_binary() )
		);
		return $rows ? (int) $rows[0]['id'] : null;
	}

	/**
	 * Page at most fifty registrations for one scoped subject.
	 *
	 * @param OrgScope $scope    Trusted organization.
	 * @param int      $person_id Scoped person key.
	 * @param int      $before   Exclusive internal key (zero for first page).
	 * @return list<array<string, mixed>> Sanitization occurs in M6ReadService.
	 */
	public function for_person( OrgScope $scope, int $person_id, int $before = 0 ): array {
		$sql  = $this->select() . ' WHERE r.organization_id = %d AND r.person_id = %d';
		$args = array( $this->prefix . 'registrations', $this->prefix . 'persons', $this->prefix . 'event_settings', $scope->id, $person_id );
		if ( $before > 0 ) {
			$sql   .= ' AND r.id < %d';
			$args[] = $before;
		}
		$sql   .= ' ORDER BY r.id DESC LIMIT %d';
		$args[] = 50;
		return $this->db->rows( $sql, $args );
	}
}
