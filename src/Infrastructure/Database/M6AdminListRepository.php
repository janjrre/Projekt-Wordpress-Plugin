<?php
/**
 * Keyset-paged tenant-scoped administrative summaries.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;

/** Read-only SQL never accepts caller-defined column names or SQL fragments. */
final class M6AdminListRepository {
	/**
	 * Bind trusted table names.
	 *
	 * @param Connection $db     Scoped database adapter.
	 * @param string     $prefix Trusted table prefix.
	 */
	public function __construct( private Connection $db, private string $prefix ) {}

	/**
	 * Resolve a person's tenant-owned cursor.
	 *
	 * @param OrgScope $scope Current organization.
	 * @param PublicId $cursor Client-visible opaque cursor.
	 * @return int|null
	 */
	public function person_cursor( OrgScope $scope, PublicId $cursor ): ?int {
		$rows = $this->db->rows(
			'SELECT id FROM %i WHERE organization_id = %d AND public_id = %s LIMIT 1',
			array( $this->prefix . 'persons', $scope->id, $cursor->to_binary() )
		);
		return $rows ? (int) $rows[0]['id'] : null;
	}

	/**
	 * Read 51 opaque person identities so one page can expose at most fifty.
	 *
	 * @param OrgScope $scope Trusted tenant.
	 * @param int      $before Exclusive ID cursor, or zero.
	 * @return list<array<string,mixed>>
	 */
	public function people( OrgScope $scope, int $before ): array {
		$sql  = 'SELECT id, public_id FROM %i WHERE organization_id = %d';
		$args = array( $this->prefix . 'persons', $scope->id );
		if ( $before > 0 ) {
			$sql   .= ' AND id < %d';
			$args[] = $before;
		}
		$sql .= ' ORDER BY id DESC LIMIT 51';
		return $this->db->rows( $sql, $args );
	}

	/**
	 * Resolve a registration cursor in the same scoped status filter.
	 *
	 * @param OrgScope $scope  Trusted tenant.
	 * @param PublicId $cursor Opaque record cursor.
	 * @param string   $status Prevalidated state or empty string.
	 * @return int|null
	 */
	public function registration_cursor( OrgScope $scope, PublicId $cursor, string $status ): ?int {
		$sql  = 'SELECT id FROM %i WHERE organization_id = %d AND public_id = %s';
		$args = array( $this->prefix . 'registrations', $scope->id, $cursor->to_binary() );
		if ( '' !== $status ) {
			$sql   .= ' AND status = %s';
			$args[] = $status;
		}
		$sql .= ' LIMIT 1';
		$rows = $this->db->rows( $sql, $args );
		return $rows ? (int) $rows[0]['id'] : null;
	}

	/**
	 * Read 51 scoped registration identities, never form answers or contacts.
	 *
	 * @param OrgScope $scope  Trusted tenant.
	 * @param int      $before Exclusive cursor or zero.
	 * @param string   $status Prevalidated state or empty string.
	 * @return list<array<string,mixed>>
	 */
	public function registrations( OrgScope $scope, int $before, string $status ): array {
		$sql  = 'SELECT id, public_id FROM %i WHERE organization_id = %d';
		$args = array( $this->prefix . 'registrations', $scope->id );
		if ( $before > 0 ) {
			$sql   .= ' AND id < %d';
			$args[] = $before;
		}
		if ( '' !== $status ) {
			$sql   .= ' AND status = %s';
			$args[] = $status;
		}
		$sql .= ' ORDER BY id DESC LIMIT 51';
		return $this->db->rows( $sql, $args );
	}
}
