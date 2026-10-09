<?php
/**
 * Bounded, tenant-scoped retention selection and redaction operations.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use RuntimeException;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;

/** Only three explicit V1 retention targets are writable; no arbitrary SQL rules. */
final class RetentionRepository {
	/**
	 * Bind the SQL adapter and site-specific table namespace.
	 *
	 * @param Connection $db     Transaction-owned database connection.
	 * @param string     $prefix Trusted WordPress site table prefix.
	 */
	public function __construct( private Connection $db, private string $prefix ) {}

	/**
	 * Create a manually configured rule, disabled unless explicitly enabled.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param string   $key   Machine-readable key.
	 * @param string   $data_class Supported data class.
	 * @param string   $trigger Supported lifecycle anchor.
	 * @param int      $delay Number of days after anchor.
	 * @param string   $action Supported action.
	 * @param bool     $enabled Explicit operational opt-in.
	 * @param string   $now UTC command timestamp.
	 */
	public function create( OrgScope $scope, string $key, string $data_class, string $trigger, int $delay, string $action, bool $enabled, string $now ): void {
		$this->db->execute(
			'INSERT INTO %i (organization_id, rule_key, data_class, trigger_type, delay_days, action, enabled, settings_json, created_at, updated_at) VALUES (%d,%s,%s,%s,%d,%s,%d,NULL,%s,%s)',
			array( $this->prefix . 'retention_rules', $scope->id, $key, $data_class, $trigger, $delay, $action, $enabled ? 1 : 0, $now, $now )
		);
	}

	/**
	 * Resolve only this organization's named rule.
	 *
	 * @param OrgScope $scope Tenant boundary.
	 * @param string   $key Configured rule key.
	 * @return array<string,mixed>|null
	 */
	public function rule( OrgScope $scope, string $key ): ?array {
		$rows = $this->db->rows(
			'SELECT id, organization_id, rule_key, data_class, trigger_type, delay_days, action, enabled FROM %i WHERE organization_id = %d AND rule_key = %s LIMIT 1',
			array( $this->prefix . 'retention_rules', $scope->id, $key )
		);
		return $rows[0] ?? null;
	}

	/**
	 * One bounded keyset page. Candidates still need a hold/revalidation check.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param string   $data_class Supported fixed target class.
	 * @param string   $cutoff Inclusive UTC eligibility cutoff.
	 * @param int      $after Last examined internal ID (zero initially).
	 * @return list<array<string,mixed>>
	 * @throws RuntimeException For unsupported data classes.
	 */
	public function candidates( OrgScope $scope, string $data_class, string $cutoff, int $after ): array {
		if ( 'registration_snapshots' === $data_class ) {
			return $this->db->rows(
				"SELECT s.id, s.public_id FROM %i s INNER JOIN %i r ON r.id = s.registration_id INNER JOIN %i p ON p.id = r.person_id AND p.organization_id = r.organization_id WHERE r.organization_id = %d AND s.id > %d AND r.submitted_at <= %s AND r.status IN ('cancelled','rejected') AND s.redacted_at IS NULL AND s.payload_json IS NOT NULL ORDER BY s.id ASC LIMIT 25",
				array( $this->prefix . 'registration_snapshots', $this->prefix . 'registrations', $this->prefix . 'persons', $scope->id, $after, $cutoff )
			);
		}
		if ( 'profile_values' === $data_class ) {
			return $this->db->rows(
				"SELECT p.id, p.public_id FROM %i p WHERE p.organization_id = %d AND p.id > %d AND p.created_at <= %s AND p.status = 'active' AND EXISTS (SELECT 1 FROM %i v WHERE v.person_id = p.id) ORDER BY p.id ASC LIMIT 25",
				array( $this->prefix . 'persons', $scope->id, $after, $cutoff, $this->prefix . 'profile_values' )
			);
		}
		if ( 'persons' === $data_class ) {
			return $this->db->rows(
				"SELECT p.id, p.public_id FROM %i p WHERE p.organization_id = %d AND p.id > %d AND p.created_at <= %s AND p.status = 'active' AND p.archived_at IS NULL ORDER BY p.id ASC LIMIT 25",
				array( $this->prefix . 'persons', $scope->id, $after, $cutoff )
			);
		}
		throw new RuntimeException( 'Unsupported retention data class.' );
	}

	/**
	 * Re-evaluate legal holds and live state before any destructive operation.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param string   $data_class Exact retention target.
	 * @param int      $id Candidate internal key.
	 * @param string   $cutoff Eligibility cutoff.
	 * @param string   $now Trusted UTC point for legal holds.
	 * @param bool     $lock Whether inside the mutation transaction.
	 * @return array{object_id:int,subject_id:int}|null Null when held or no longer eligible.
	 * @throws RuntimeException For unsupported data classes.
	 */
	public function eligible( OrgScope $scope, string $data_class, int $id, string $cutoff, string $now, bool $lock ): ?array {
		$lock_sql = $lock ? ' FOR UPDATE' : '';
		if ( 'registration_snapshots' === $data_class ) {
			$rows = $this->db->rows(
				"SELECT s.id, r.id AS registration_id, r.person_id, r.retention_hold_until AS registration_hold, p.retention_hold_until AS person_hold FROM %i s INNER JOIN %i r ON r.id = s.registration_id INNER JOIN %i p ON p.id = r.person_id AND p.organization_id = r.organization_id WHERE r.organization_id = %d AND s.id = %d AND r.submitted_at <= %s AND r.status IN ('cancelled','rejected') AND s.redacted_at IS NULL AND s.payload_json IS NOT NULL LIMIT 1" . $lock_sql,
				array( $this->prefix . 'registration_snapshots', $this->prefix . 'registrations', $this->prefix . 'persons', $scope->id, $id, $cutoff )
			);
			if ( ! $rows || $this->held( $rows[0]['registration_hold'], $now ) || $this->held( $rows[0]['person_hold'], $now ) ) {
				return null;
			}
			return array( 'object_id' => (int) $rows[0]['registration_id'], 'subject_id' => (int) $rows[0]['person_id'] );
		}
		if ( ! in_array( $data_class, array( 'persons', 'profile_values' ), true ) ) {
			throw new RuntimeException( 'Unsupported retention target.' );
		}
		$rows = $this->db->rows(
			"SELECT id, retention_hold_until FROM %i WHERE organization_id = %d AND id = %d AND created_at <= %s AND status = 'active' AND archived_at IS NULL LIMIT 1" . $lock_sql,
			array( $this->prefix . 'persons', $scope->id, $id, $cutoff )
		);
		if ( ! $rows || $this->held( $rows[0]['retention_hold_until'], $now ) ) {
			return null;
		}
		$blocking = $this->db->rows(
			"SELECT id FROM %i WHERE organization_id = %d AND person_id = %d AND (status NOT IN ('cancelled','rejected') OR retention_hold_until > %s) LIMIT 1" . $lock_sql,
			array( $this->prefix . 'registrations', $scope->id, $id, $now )
		);
		if ( $blocking ) {
			return null;
		}
		if ( 'profile_values' === $data_class && ! $this->db->rows( 'SELECT id FROM %i WHERE person_id = %d LIMIT 1' . $lock_sql, array( $this->prefix . 'profile_values', $id ) ) ) {
			return null;
		}
		return array(
				'object_id' => $id,
				'subject_id' => $id,
			);
	}

	/**
	 * Erase, anonymize or archive exactly one previously locked eligible candidate.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param string   $data_class Exact fixed data class.
	 * @param int      $id Current candidate key.
	 * @param string   $now Trusted UTC mutation timestamp.
	 * @return int Number of rows changed; a positive result requires audit.
	 * @throws RuntimeException When the redaction fails or the class is invalid.
	 */
	public function apply( OrgScope $scope, string $data_class, int $id, string $now ): int {
		if ( 'profile_values' === $data_class ) {
			return $this->db->execute(
				'DELETE v FROM %i v INNER JOIN %i p ON p.id = v.person_id WHERE p.organization_id = %d AND p.id = %d',
				array( $this->prefix . 'profile_values', $this->prefix . 'persons', $scope->id, $id )
			);
		}
		if ( 'registration_snapshots' === $data_class ) {
			$deleted = $this->db->execute(
				'DELETE v FROM %i v INNER JOIN %i s ON s.id = v.snapshot_id INNER JOIN %i r ON r.id = s.registration_id WHERE r.organization_id = %d AND s.id = %d',
				array( $this->prefix . 'registration_values', $this->prefix . 'registration_snapshots', $this->prefix . 'registrations', $scope->id, $id )
			);
			$updated = $this->db->execute(
				'UPDATE %i s INNER JOIN %i r ON r.id = s.registration_id SET s.payload_json = NULL, s.payload_hash = NULL, s.redacted_at = %s WHERE r.organization_id = %d AND s.id = %d AND s.redacted_at IS NULL',
				array( $this->prefix . 'registration_snapshots', $this->prefix . 'registrations', $now, $scope->id, $id )
			);
			if ( 1 !== $updated ) {
				throw new RuntimeException( 'Immutable snapshot redaction was not applied.' );
			}
			return $updated + $deleted;
		}
		if ( 'persons' === $data_class ) {
			return $this->db->execute(
				"UPDATE %i SET status = 'archived', archived_at = %s, updated_at = %s, version = version + 1 WHERE organization_id = %d AND id = %d AND status = 'active' AND archived_at IS NULL",
				array( $this->prefix . 'persons', $now, $now, $scope->id, $id )
			);
		}
		throw new RuntimeException( 'Unsupported retention action.' );
	}

	/**
	 * Compare stored UTC legal hold with the command time.
	 *
	 * @param mixed  $until Nullable stored DATETIME.
	 * @param string $now Trusted UTC timestamp.
	 * @return bool True only while the hold is still active.
	 */
	private function held( mixed $until, string $now ): bool {
		return null !== $until && (string) $until > $now;
	}
}
