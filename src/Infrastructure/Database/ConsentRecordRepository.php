<?php
/**
 * Immutable, subject-scoped consent evidence persistence.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use RuntimeException;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;

/** Decisions are append-only and never reused as editable registration values. */
final class ConsentRecordRepository {
	/**
	 * Bind trusted database and table prefix.
	 *
	 * @param Connection $db     Shared transaction connection.
	 * @param string     $prefix Trusted WordPress prefix.
	 */
	public function __construct( private Connection $db, private string $prefix ) {}

	/**
	 * Insert consent for an existing registration and its exact subject.
	 *
	 * @param OrgScope $scope      Owning organization.
	 * @param PublicId $uuid       New record identity.
	 * @param int      $version_id Verified immutable document internal ID.
	 * @param int      $person_id  Trusted subject person.
	 * @param int      $actor_id   Authenticated actor or zero for guest.
	 * @param int      $registration Trusted newly created registration ID.
	 * @param string   $decision   Explicit granted or denied.
	 * @param string   $context    Trusted self, delegate, manager or guest.
	 * @param string   $now        Trusted UTC timestamp.
	 * @return int Internal consent record key.
	 * @throws RuntimeException If the subject or version is outside the scope.
	 */
	public function record_submission( OrgScope $scope, PublicId $uuid, int $version_id, int $person_id, int $actor_id, int $registration, string $decision, string $context, string $now ): int {
		$rows = $this->db->rows(
			'SELECT r.id FROM %i r INNER JOIN %i p ON p.id = r.person_id AND p.organization_id = r.organization_id INNER JOIN %i v ON v.id = %d INNER JOIN %i d ON d.id = v.definition_id AND d.organization_id = r.organization_id WHERE r.organization_id = %d AND r.id = %d AND r.person_id = %d AND p.status = %s LIMIT 1',
			array( $this->prefix . 'registrations', $this->prefix . 'persons', $this->prefix . 'consent_versions', $version_id, $this->prefix . 'consent_definitions', $scope->id, $registration, $person_id, 'active' )
		);
		if ( ! $rows || ! in_array( $decision, array( 'granted', 'denied' ), true )
			|| ! in_array( $context, array( 'self', 'delegate', 'manager', 'guest' ), true ) ) {
			throw new RuntimeException( 'Consent evidence could not be scoped.' );
		}
		$this->db->execute(
			'INSERT INTO %i (public_id, organization_id, definition_version_id, subject_person_id, actor_user_id, registration_id, decision, supersedes_record_id, auth_context, evidence_json, decided_at) VALUES (%s,%d,%d,%d,NULLIF(%d,0),%d,%s,NULL,%s,NULL,%s)',
			array( $this->prefix . 'consent_records', $uuid->to_binary(), $scope->id, $version_id, $person_id, $actor_id, $registration, $decision, $context, $now )
		);
		return $this->inserted_id( $scope, $uuid );
	}

	/**
	 * Serialize competing withdrawals on the original record.
	 *
	 * @param OrgScope $scope  Owning organization.
	 * @param PublicId $record Historical consent record.
	 * @return array<string, mixed>|null Including registration event identity.
	 */
	public function lock_record( OrgScope $scope, PublicId $record ): ?array {
		$rows = $this->db->rows(
			'SELECT c.id, c.public_id, c.definition_version_id, c.subject_person_id, c.registration_id, c.decision, c.supersedes_record_id, c.actor_user_id, r.event_post_id FROM %i c INNER JOIN %i v ON v.id = c.definition_version_id INNER JOIN %i d ON d.id = v.definition_id AND d.organization_id = c.organization_id LEFT JOIN %i r ON r.id = c.registration_id AND r.organization_id = c.organization_id AND r.person_id = c.subject_person_id WHERE c.organization_id = %d AND c.public_id = %s LIMIT 1 FOR UPDATE',
			array( $this->prefix . 'consent_records', $this->prefix . 'consent_versions', $this->prefix . 'consent_definitions', $this->prefix . 'registrations', $scope->id, $record->to_binary() )
		);
		return $rows[0] ?? null;
	}

	/**
	 * Resolve whether a record has already been superseded.
	 *
	 * @param OrgScope $scope      Owning organization.
	 * @param int      $record_id  Locked predecessor.
	 * @return array<string, mixed>|null Superseding record.
	 */
	public function successor( OrgScope $scope, int $record_id ): ?array {
		$rows = $this->db->rows(
			'SELECT public_id, id, decision FROM %i WHERE organization_id = %d AND supersedes_record_id = %d LIMIT 1',
			array( $this->prefix . 'consent_records', $scope->id, $record_id )
		);
		return $rows[0] ?? null;
	}

	/**
	 * Append a withdrawal without ever changing earlier evidence.
	 *
	 * @param OrgScope $scope   Owning organization.
	 * @param PublicId $uuid    One-time withdrawal command identity.
	 * @param array    $old     Locked predecessor.
	 * @phpstan-param array<string, mixed> $old
	 * @param int      $actor_id WordPress account taking this action.
	 * @param string   $context  Live self, delegate or manager context.
	 * @param string   $now      UTC decision timestamp.
	 * @return int New append-only row ID.
	 * @throws RuntimeException When the original is unavailable.
	 */
	public function withdraw( OrgScope $scope, PublicId $uuid, array $old, int $actor_id, string $context, string $now ): int {
		if ( ! in_array( $context, array( 'self', 'delegate', 'manager' ), true ) || $actor_id < 1
			|| 'granted' !== $old['decision'] || null !== $old['supersedes_record_id'] ) {
			throw new RuntimeException( 'Only a current grant can be withdrawn.' );
		}
		$this->db->execute(
			'INSERT INTO %i (public_id, organization_id, definition_version_id, subject_person_id, actor_user_id, registration_id, decision, supersedes_record_id, auth_context, evidence_json, decided_at) VALUES (%s,%d,%d,%d,%d,NULLIF(%d,0),%s,%d,%s,NULL,%s)',
			array( $this->prefix . 'consent_records', $uuid->to_binary(), $scope->id, (int) $old['definition_version_id'], (int) $old['subject_person_id'], $actor_id, (int) ( $old['registration_id'] ?? 0 ), 'withdrawn', (int) $old['id'], $context, $now )
		);
		return $this->inserted_id( $scope, $uuid );
	}

	/**
	 * Load one append-only record by its public UUID.
	 *
	 * @param OrgScope $scope Tenant boundary.
	 * @param PublicId $uuid  Trusted identity.
	 * @return array<string, mixed>|null
	 */
	public function find( OrgScope $scope, PublicId $uuid ): ?array {
		$rows = $this->db->rows(
			'SELECT id, subject_person_id, registration_id, decision, supersedes_record_id, definition_version_id, actor_user_id, auth_context FROM %i WHERE organization_id = %d AND public_id = %s LIMIT 1',
			array( $this->prefix . 'consent_records', $scope->id, $uuid->to_binary() )
		);
		return $rows[0] ?? null;
	}

	/**
	 * Lookup generated internal key without relying on connection-global LAST_INSERT_ID.
	 *
	 * @param OrgScope $scope Tenant boundary.
	 * @param PublicId $uuid  New evidence UUID.
	 * @return int
	 * @throws RuntimeException When the evidence insert failed.
	 */
	private function inserted_id( OrgScope $scope, PublicId $uuid ): int {
		$rows = $this->db->rows(
			'SELECT id FROM %i WHERE organization_id = %d AND public_id = %s LIMIT 1',
			array( $this->prefix . 'consent_records', $scope->id, $uuid->to_binary() )
		);
		if ( ! $rows ) {
			throw new RuntimeException( 'Consent record was not persisted.' );
		}
		return (int) $rows[0]['id'];
	}
}
