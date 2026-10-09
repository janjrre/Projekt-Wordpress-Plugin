<?php
/**
 * Minimized M5 operations read model for privacy-safe administration.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use InvalidArgumentException;
use UOP\Domain\Organization\OrgScope;

/** Aggregates reveal counts and opaque IDs, never emails, tokens or message bodies. */
final class M5OperationsRepository {
	/**
	 * Bind site-scoped storage.
	 *
	 * @param Connection $db Live scoped database connection.
	 * @param string     $prefix Trusted site-specific table prefix.
	 */
	public function __construct( private Connection $db, private string $prefix ) {}

	/**
	 * Return grouped counts for an explicitly allowlisted operational resource.
	 *
	 * @param OrgScope $scope Authorized organization.
	 * @param string   $resource Allowlisted resource identifier.
	 * @return array<string,int> State labels mapped to aggregate counts.
	 * @throws InvalidArgumentException For an unknown resource.
	 */
	public function counts( OrgScope $scope, string $resource ): array {
		$table = match ( $resource ) {
			'email'  => 'email_messages',
			'export' => 'export_jobs',
			default  => throw new InvalidArgumentException( 'Unsupported diagnostic resource.' ),
		};
		$rows = $this->db->rows(
			'SELECT status, COUNT(*) AS total FROM %i WHERE organization_id = %d GROUP BY status ORDER BY status ASC LIMIT 20',
			array( $this->prefix . $table, $scope->id )
		);
		$result = array();
		foreach ( $rows as $row ) {
			$result[ (string) $row['status'] ] = (int) $row['total'];
		}
		return $result;
	}

	/**
	 * List bounded failed/ambiguous messages with public IDs only.
	 *
	 * @param OrgScope $scope Authorized organization.
	 * @return list<array<string,mixed>> Status, attempts, failure codes and public IDs.
	 */
	public function problem_messages( OrgScope $scope ): array {
		return $this->db->rows(
			"SELECT public_id, status, attempts, last_error_code, queued_at FROM %i WHERE organization_id = %d AND status IN ('failed','sending') ORDER BY id DESC LIMIT 20",
			array( $this->prefix . 'email_messages', $scope->id )
		);
	}

	/**
	 * List bounded failed/expired exports with no request filters or storage key.
	 *
	 * @param OrgScope $scope Authorized organization.
	 * @param string   $now   Current trusted UTC time.
	 * @return list<array<string,mixed>> Safe status, failure code and deadline.
	 */
	public function problem_exports( OrgScope $scope, string $now ): array {
		return $this->db->rows(
			"SELECT public_id, status, error_code, expires_at FROM %i WHERE organization_id = %d AND (status IN ('failed','expired') OR (expires_at <= %s AND status <> 'expired')) ORDER BY id DESC LIMIT 20",
			array( $this->prefix . 'export_jobs', $scope->id, $now )
		);
	}

	/**
	 * List bounded organization retention rules without private rule settings.
	 *
	 * @param OrgScope $scope Authorized organization.
	 * @return list<array<string,mixed>> Non-sensitive configured policy labels.
	 */
	public function retention_rules( OrgScope $scope ): array {
		return $this->db->rows(
			'SELECT rule_key, data_class, action, delay_days, enabled FROM %i WHERE organization_id = %d ORDER BY id DESC LIMIT 40',
			array( $this->prefix . 'retention_rules', $scope->id )
		);
	}

	/**
	 * Group safe code-only retention failures from the durable outbox.
	 *
	 * @param OrgScope $scope Authorized organization.
	 * @return list<array<string,mixed>> Only failure categories, counts and attempt ceilings.
	 */
	public function retention_failures( OrgScope $scope ): array {
		return $this->db->rows(
			"SELECT attempts, COUNT(*) AS total FROM %i WHERE organization_id = %d AND event_name = 'privacy.retention_executed' AND published_at IS NULL AND attempts > 0 GROUP BY attempts ORDER BY attempts DESC LIMIT 5",
			array( $this->prefix . 'domain_events', $scope->id )
		);
	}
}
