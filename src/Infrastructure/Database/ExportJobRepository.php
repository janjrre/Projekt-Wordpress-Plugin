<?php
/**
 * Tenant-scoped, idempotent export job state persistence.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use RuntimeException;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;

/** Job state is durable; file bytes are never stored in WordPress or public media. */
final class ExportJobRepository {
	/**
	 * Bind connection and trusted table namespace.
	 *
	 * @param Connection $db Transaction-owned connection.
	 * @param string     $prefix Trusted site table prefix.
	 */
	public function __construct( private Connection $db, private string $prefix ) {}

	/**
	 * Find the exact existing client command inside its tenant.
	 *
	 * @param OrgScope $scope Scoped organization.
	 * @param PublicId $command Stable client command.
	 * @return array<string,mixed>|null
	 */
	public function by_command( OrgScope $scope, PublicId $command ): ?array {
		$rows = $this->db->rows(
			'SELECT id, public_id, organization_id, command_id, actor_user_id, resource_type, format, filters_json, columns_json, status, expires_at FROM %i WHERE organization_id = %d AND command_id = %s LIMIT 1',
			array( $this->prefix . 'export_jobs', $scope->id, $command->to_binary() )
		);
		return $rows[0] ?? null;
	}

	/**
	 * Persist an immutable validated job request.
	 *
	 * @param OrgScope            $scope Scoped organization.
	 * @param PublicId            $uuid Job public identity.
	 * @param PublicId            $command Client command.
	 * @param int                 $actor_id Owner WordPress user.
	 * @param string              $resource_type Fixed resource type.
	 * @param string              $format Fixed file format.
	 * @param array<string,mixed> $filters Strict filters.
	 * @param array               $columns Allowlisted projection columns.
	 * @param string              $now UTC creation time.
	 * @param string              $expires UTC expiry.
	 * @phpstan-param list<string> $columns
	 */
	public function create( OrgScope $scope, PublicId $uuid, PublicId $command, int $actor_id, string $resource_type, string $format, array $filters, array $columns, string $now, string $expires ): void {
		$this->db->execute(
			"INSERT INTO %i (public_id,organization_id,command_id,actor_user_id,resource_type,format,status,filters_json,columns_json,expires_at,created_at,updated_at) VALUES (%s,%d,%s,%d,%s,%s,'queued',%s,%s,%s,%s,%s)",
			array( $this->prefix . 'export_jobs', $uuid->to_binary(), $scope->id, $command->to_binary(), $actor_id, $resource_type, $format, wp_json_encode( $filters, JSON_THROW_ON_ERROR ), wp_json_encode( $columns, JSON_THROW_ON_ERROR ), $expires, $now, $now )
		);
	}

	/**
	 * Return one job without ever returning stored file system paths in public DTOs.
	 *
	 * @param OrgScope $scope Scoped organization.
	 * @param PublicId $uuid Job public identity.
	 * @param bool     $lock Whether the calling transaction holds a row lock.
	 * @return array<string,mixed>|null
	 */
	public function find( OrgScope $scope, PublicId $uuid, bool $lock = false ): ?array {
		$rows = $this->db->rows(
			'SELECT id, public_id, organization_id, command_id, actor_user_id, resource_type, format, status, filters_json, columns_json, row_count, storage_key, content_sha256, error_code, created_at, started_at, completed_at, expires_at, downloaded_at FROM %i WHERE organization_id = %d AND public_id = %s LIMIT 1' . ( $lock ? ' FOR UPDATE' : '' ),
			array( $this->prefix . 'export_jobs', $scope->id, $uuid->to_binary() )
		);
		return $rows[0] ?? null;
	}

	/**
	 * Claim a queued job once, avoiding duplicate worker generation.
	 *
	 * @param OrgScope $scope Scoped organization.
	 * @param PublicId $uuid Job public identity.
	 * @param string   $now Trusted UTC now.
	 * @return array<string,mixed>|null Claimed job with preserved request.
	 * @throws RuntimeException If its state changes during claim.
	 */
	public function claim( OrgScope $scope, PublicId $uuid, string $now ): ?array {
		$row = $this->find( $scope, $uuid, true );
		if ( ! $row || 'queued' !== $row['status'] || (string) $row['expires_at'] <= $now ) {
			return null;
		}
		if ( 1 !== $this->db->execute(
			"UPDATE %i SET status = 'processing', started_at = %s, updated_at = %s WHERE organization_id = %d AND id = %d AND status = 'queued'",
			array( $this->prefix . 'export_jobs', $now, $now, $scope->id, (int) $row['id'] )
		) ) {
			throw new RuntimeException( 'Export job claim failed.' );
		}
		return $row;
	}

	/**
	 * Freeze the private file reference and its SHA-256 digest.
	 *
	 * @param OrgScope $scope Scoped organization.
	 * @param PublicId $uuid Job public identity.
	 * @param string   $key Opaque relative private storage key.
	 * @param string   $hash Binary SHA-256 of exported bytes.
	 * @param int      $count Number of data records.
	 * @param string   $now Trusted UTC timestamp.
	 * @throws RuntimeException If processing claim was lost.
	 */
	public function complete( OrgScope $scope, PublicId $uuid, string $key, string $hash, int $count, string $now ): void {
		if ( 1 !== $this->db->execute(
			"UPDATE %i SET status = 'ready', storage_key = %s, content_sha256 = %s, row_count = %d, completed_at = %s, updated_at = %s WHERE organization_id = %d AND public_id = %s AND status = 'processing' AND expires_at > %s",
			array( $this->prefix . 'export_jobs', $key, $hash, $count, $now, $now, $scope->id, $uuid->to_binary(), $now )
		) ) {
			throw new RuntimeException( 'Export job completion was not persisted.' );
		}
	}

	/**
	 * Record only a safe failure category, not private error content.
	 *
	 * @param OrgScope $scope Scoped organization.
	 * @param PublicId $uuid Job public identity.
	 * @param string   $code Fixed error code.
	 * @param string   $now Trusted UTC timestamp.
	 */
	public function fail( OrgScope $scope, PublicId $uuid, string $code, string $now ): void {
		$this->db->execute(
			"UPDATE %i SET status = 'failed', error_code = %s, error_message = NULL, updated_at = %s WHERE organization_id = %d AND public_id = %s AND status = 'processing'",
			array( $this->prefix . 'export_jobs', $code, $now, $scope->id, $uuid->to_binary() )
		);
	}

	/**
	 * Recover bounded queued jobs for an explicitly scoped worker sweep.
	 *
	 * @param OrgScope $scope Tenant boundary.
	 * @param string   $now Trusted UTC point of service.
	 * @return list<string> Export public UUIDs as binary database bytes.
	 */
	public function pending( OrgScope $scope, string $now ): array {
		$rows = $this->db->rows(
			"SELECT public_id FROM %i WHERE organization_id = %d AND status = 'queued' AND expires_at > %s ORDER BY id ASC LIMIT 50",
			array( $this->prefix . 'export_jobs', $scope->id, $now )
		);
		return array_map( static fn ( array $row ): string => (string) $row['public_id'], $rows );
	}

	/**
	 * Timestamp an authorized download without modifying the bytes.
	 *
	 * @param OrgScope $scope Scoped organization.
	 * @param PublicId $uuid Job public identity.
	 * @param string   $now Trusted UTC timestamp.
	 */
	public function downloaded( OrgScope $scope, PublicId $uuid, string $now ): void {
		$this->db->execute(
			"UPDATE %i SET downloaded_at = %s WHERE organization_id = %d AND public_id = %s AND status = 'ready' AND expires_at > %s",
			array( $this->prefix . 'export_jobs', $now, $scope->id, $uuid->to_binary(), $now )
		);
	}

	/**
	 * Bounded expiry sweep with private references for cleanup.
	 *
	 * @param OrgScope $scope Tenant boundary.
	 * @param string   $now Trusted UTC timestamp.
	 * @return list<array<string,mixed>> Expired queue and storage items.
	 */
	public function expired( OrgScope $scope, string $now ): array {
		return $this->db->rows(
			"SELECT id, public_id, organization_id, storage_key FROM %i WHERE organization_id = %d AND expires_at <= %s AND status <> 'expired' ORDER BY id ASC LIMIT 50",
			array( $this->prefix . 'export_jobs', $scope->id, $now )
		);
	}

	/**
	 * Commit expiry only after deleting any stored file.
	 *
	 * @param OrgScope $scope Tenant boundary.
	 * @param int      $id Internal job key already returned by expiry selection.
	 * @param string   $now Trusted UTC timestamp.
	 */
	public function expire( OrgScope $scope, int $id, string $now ): void {
		$this->db->execute(
			"UPDATE %i SET status = 'expired', storage_key = NULL, content_sha256 = NULL, updated_at = %s WHERE organization_id = %d AND id = %d AND expires_at <= %s AND status <> 'expired'",
			array( $this->prefix . 'export_jobs', $now, $scope->id, $id, $now )
		);
	}
}
