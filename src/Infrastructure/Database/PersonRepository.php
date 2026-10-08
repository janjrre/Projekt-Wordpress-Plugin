<?php
/**
 * Organization-scoped person persistence.
 *
 * @package UOP
 */
namespace UOP\Infrastructure\Database;

use InvalidArgumentException;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;

/** Internal rows are never API response objects. */
final class PersonRepository extends ScopedRepository {
	private string $table;
	private Connection $db;

	public function __construct( Connection $db, string $prefix ) {
		$this->db    = $db;
		$this->table = $prefix . 'persons';
		parent::__construct(
			$db,
			$this->table,
			array( 'id', 'public_id', 'organization_id', 'wp_user_id', 'display_name', 'primary_email', 'status', 'version', 'archived_at' ),
			array( 'status' )
		);
	}

	/** @return array<string, mixed>|null */
	public function by_user( OrgScope $scope, int $user_id ): ?array {
		$rows = $this->db->rows(
			'SELECT id, public_id, organization_id, wp_user_id, display_name, primary_email, status, version, archived_at FROM %i WHERE organization_id = %d AND wp_user_id = %d LIMIT 1',
			array( $this->table, $scope->id, $user_id )
		);
		return $rows[0] ?? null;
	}

	/** @return array<string, mixed>|null */
	public function by_internal_id( OrgScope $scope, int $person_id ): ?array {
		$rows = $this->db->rows(
			'SELECT id, public_id, organization_id, wp_user_id, display_name, primary_email, status, version, archived_at FROM %i WHERE organization_id = %d AND id = %d LIMIT 1',
			array( $this->table, $scope->id, $person_id )
		);
		return $rows[0] ?? null;
	}

	/** Create an organization-owned person; no email-based identity inference. */
	public function create( OrgScope $scope, PublicId $id, string $name, ?string $email, string $utc_now ): void {
		if ( '' === trim( $name ) || mb_strlen( $name ) > 191 ) {
			throw new InvalidArgumentException( 'Invalid person display name.' );
		}
		$this->db->execute(
			'INSERT INTO %i (public_id, organization_id, display_name, primary_email, status, created_at, updated_at) VALUES (%s, %d, %s, %s, %s, %s, %s)',
			array( $this->table, $id->to_binary(), $scope->id, $name, $email, 'active', $utc_now, $utc_now )
		);
	}

	/** Claim an unlinked person, never replace an existing account link. */
	public function link( OrgScope $scope, PublicId $person_id, int $user_id, string $utc_now ): bool {
		if ( $user_id < 1 ) {
			throw new InvalidArgumentException( 'Invalid WordPress user ID.' );
		}
		return 1 === $this->db->execute(
			"UPDATE %i SET wp_user_id = %d, version = version + 1, updated_at = %s WHERE organization_id = %d AND public_id = %s AND wp_user_id IS NULL AND status = 'active'",
			array( $this->table, $user_id, $utc_now, $scope->id, $person_id->to_binary() )
		);
	}

	/** Unlink only the deleted account, leaving the person and registrations intact. */
	public function unlink_user( OrgScope $scope, int $user_id, string $utc_now ): int {
		return $this->db->execute(
			'UPDATE %i SET wp_user_id = NULL, version = version + 1, updated_at = %s WHERE organization_id = %d AND wp_user_id = %d',
			array( $this->table, $utc_now, $scope->id, $user_id )
		);
	}
}
