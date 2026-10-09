<?php
/**
 * Tenant-scoped read-only M6 operational queries.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;

/** Keep all SQL away from REST and application presentation models. */
final class M6OperationsRepository {
	/**
	 * Bind the trusted database and site-owned table namespace.
	 *
	 * @param Connection $db     Scoped database seam.
	 * @param string     $prefix Trusted WordPress table prefix.
	 */
	public function __construct( private Connection $db, private string $prefix ) {}

	/**
	 * Read consent decisions tied to one already-authorized registration.
	 *
	 * @param OrgScope $scope        Tenant boundary.
	 * @param int      $registration Scoped registration primary key.
	 * @return list<array<string,mixed>> Private evidence excludes actor identities.
	 */
	public function consents( OrgScope $scope, int $registration ): array {
		return $this->db->rows(
			'SELECT c.public_id, c.decision, c.decided_at, c.supersedes_record_id, c.id, v.public_id AS version_public_id, v.version, d.consent_key FROM %i c INNER JOIN %i v ON v.id = c.definition_version_id INNER JOIN %i d ON d.id = v.definition_id AND d.organization_id = c.organization_id WHERE c.organization_id = %d AND c.registration_id = %d ORDER BY c.id ASC LIMIT 100',
			array( $this->prefix . 'consent_records', $this->prefix . 'consent_versions', $this->prefix . 'consent_definitions', $scope->id, $registration )
		);
	}

	/**
	 * Load a single consent decision and its registration's scoped ownership.
	 *
	 * @param OrgScope $scope Trusted tenant.
	 * @param PublicId $record Opaque consent identifier.
	 * @return array<string,mixed>|null
	 */
	public function consent( OrgScope $scope, PublicId $record ): ?array {
		$rows = $this->db->rows(
			'SELECT c.id, c.public_id, c.decision, c.decided_at, c.subject_person_id, c.supersedes_record_id, c.registration_id, r.event_post_id, v.public_id AS version_public_id, v.version, d.consent_key FROM %i c INNER JOIN %i v ON v.id = c.definition_version_id INNER JOIN %i d ON d.id = v.definition_id AND d.organization_id = c.organization_id INNER JOIN %i r ON r.id = c.registration_id AND r.organization_id = c.organization_id AND r.person_id = c.subject_person_id WHERE c.organization_id = %d AND c.public_id = %s LIMIT 1',
			array( $this->prefix . 'consent_records', $this->prefix . 'consent_versions', $this->prefix . 'consent_definitions', $this->prefix . 'registrations', $scope->id, $record->to_binary() )
		);
		return $rows[0] ?? null;
	}

	/**
	 * List a bounded current bucket/counter view for an organization-owned event.
	 * Counters are informational only; actual allocations use locked M4 services.
	 *
	 * @param OrgScope $scope      Trusted tenant.
	 * @param int      $event_post Scoped event WP post.
	 * @return list<array<string,mixed>>
	 */
	public function capacity( OrgScope $scope, int $event_post ): array {
		return $this->db->rows(
			"SELECT b.public_id, b.label, b.capacity, b.status, b.occurrence_id, (SELECT COUNT(*) FROM %i c WHERE c.organization_id = b.organization_id AND c.bucket_id = b.id AND c.status IN ('confirmed','held')) AS occupied, (SELECT COUNT(*) FROM %i w WHERE w.organization_id = b.organization_id AND w.bucket_id = b.id AND w.status = 'waiting') AS waiting FROM %i b WHERE b.organization_id = %d AND b.event_post_id = %d ORDER BY b.id ASC LIMIT 100",
			array( $this->prefix . 'capacity_claims', $this->prefix . 'waitlist_entries', $this->prefix . 'capacity_buckets', $scope->id, $event_post )
		);
	}

	/**
	 * Give auditors a fixed, metadata-free recent action feed.
	 *
	 * @param OrgScope $scope Trusted tenant.
	 * @return list<array<string,mixed>>
	 */
	public function audit( OrgScope $scope ): array {
		return $this->db->rows(
			'SELECT occurred_at, action, object_type, result, correlation_id FROM %i WHERE organization_id = %d ORDER BY id DESC LIMIT 50',
			array( $this->prefix . 'audit_log', $scope->id )
		);
	}
}
