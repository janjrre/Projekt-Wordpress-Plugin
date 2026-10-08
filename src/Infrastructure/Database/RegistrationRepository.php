<?php
/**
 * Scoped immutable registration and snapshot persistence.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use RuntimeException;
use UOP\Core\PublicId;
use UOP\Core\CorrelationId;
use UOP\Domain\Organization\OrgScope;

/** All methods must run inside a transaction owned by an application command. */
final class RegistrationRepository {
	/**
	 * Bind the database and immutable site-specific table prefix.
	 *
	 * @param Connection $db     Transaction-bound database.
	 * @param string     $prefix Validated table prefix.
	 */
	public function __construct( private Connection $db, private string $prefix ) {}

	/**
	 * Lock the person before duplicate checks and scoped registration insertion.
	 *
	 * @param OrgScope $scope  Trusted organization.
	 * @param PublicId $person Opaque person ID.
	 * @return array<string, mixed>|null
	 */
	public function lock_person( OrgScope $scope, PublicId $person ): ?array {
		$rows = $this->db->rows(
			"SELECT id, public_id, status FROM %i WHERE organization_id = %d AND public_id = %s AND status = 'active' LIMIT 1 FOR UPDATE",
			array( $this->prefix . 'persons', $scope->id, $person->to_binary() )
		);
		return $rows[0] ?? null;
	}

	/**
	 * Load the published version configured for an active event.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param PublicId $event Opaque event ID.
	 * @return array<string, mixed>|null
	 */
	public function active_event_form( OrgScope $scope, PublicId $event ): ?array {
		$rows = $this->db->rows(
			"SELECT e.event_post_id, e.registration_open_at, e.registration_close_at, e.require_email_verification, e.status, f.id AS form_id, v.id AS form_version_id, v.public_id AS form_version_public_id, v.schema_json, v.checksum FROM %i e INNER JOIN %i f ON f.id = e.default_form_id AND f.organization_id = e.organization_id INNER JOIN %i v ON v.id = f.current_version_id WHERE e.organization_id = %d AND e.public_id = %s AND e.status = 'active' AND f.status = 'published' AND f.context = 'event' LIMIT 1",
			array( $this->prefix . 'event_settings', $this->prefix . 'forms', $this->prefix . 'form_versions', $scope->id, $event->to_binary() )
		);
		return $rows[0] ?? null;
	}

	/**
	 * Resolve an occurrence within the configured event boundary.
	 *
	 * @param OrgScope      $scope      Trusted organization.
	 * @param int           $event_post Event internal CPT ID.
	 * @param PublicId|null $occurrence Opaque occurrence ID or null for event-wide.
	 * @return int Internal occurrence ID or zero.
	 * @throws RuntimeException For non-owned occurrence.
	 */
	public function occurrence_id( OrgScope $scope, int $event_post, ?PublicId $occurrence ): int {
		if ( null === $occurrence ) {
			return 0;
		}
		$rows = $this->db->rows(
			"SELECT id FROM %i WHERE organization_id = %d AND event_post_id = %d AND public_id = %s AND status = 'active' LIMIT 1",
			array( $this->prefix . 'event_occurrences', $scope->id, $event_post, $occurrence->to_binary() )
		);
		if ( ! $rows ) {
			throw new RuntimeException( 'Occurrence unavailable.' );
		}
		return (int) $rows[0]['id'];
	}

	/**
	 * Find the exact scoped idempotent prior command.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param PublicId $key   Idempotency key.
	 * @return array<string, mixed>|null
	 */
	public function by_submission_key( OrgScope $scope, PublicId $key ): ?array {
		$rows = $this->db->rows(
			'SELECT r.id, r.public_id, r.person_id, r.event_post_id, r.occurrence_id, r.form_version_id, s.payload_json FROM %i r INNER JOIN %i s ON s.id = r.current_snapshot_id WHERE r.organization_id = %d AND r.submission_key = %s LIMIT 1',
			array( $this->prefix . 'registrations', $this->prefix . 'registration_snapshots', $scope->id, $key->to_binary() )
		);
		return $rows[0] ?? null;
	}

	/**
	 * Guard duplicate participation for one person, event and occurrence.
	 *
	 * @param OrgScope $scope      Trusted organization.
	 * @param int      $person_id  Locked internal person ID.
	 * @param int      $event_post Internal event post ID.
	 * @param int      $occurrence Internal occurrence ID.
	 * @return bool Whether an existing registration conflicts.
	 */
	public function already_registered( OrgScope $scope, int $person_id, int $event_post, int $occurrence ): bool {
		return (bool) $this->db->rows(
			"SELECT id FROM %i WHERE organization_id = %d AND person_id = %d AND event_post_id = %d AND occurrence_id = %d AND status NOT IN ('rejected','cancelled') LIMIT 1",
			array( $this->prefix . 'registrations', $scope->id, $person_id, $event_post, $occurrence )
		);
	}

	/**
	 * Insert the first registration and its frozen historical snapshot.
	 *
	 * @param OrgScope      $scope       Trusted organization.
	 * @param PublicId      $public_id   Generated registration UUID.
	 * @param PublicId      $submission  Client idempotency UUID.
	 * @param int           $person_id   Scoped locked person ID.
	 * @param int           $actor_id    Authorized WordPress actor.
	 * @param int           $event_post  Scoped event post.
	 * @param int           $occurrence  Scoped occurrence or zero.
	 * @param int           $form_version Frozen published version.
	 * @param PublicId      $version_uuid Published version public UUID.
	 * @param array         $fields      Normalized validated snapshot input.
	 * @phpstan-param array<string, mixed> $fields
	 * @param array         $values Frozen query projection.
	 * @phpstan-param array<string, list<array{slot:string,value:string|int,ordinal:int}>> $values
	 * @param array         $types       Frozen type mapping.
	 * @phpstan-param array<string, string> $types
	 * @param string        $utc_now     Timestamp.
	 * @param CorrelationId $correlation Command trace.
	 * @return int Registration internal ID.
	 * @throws RuntimeException If any required write fails.
	 */
	public function insert(
		OrgScope $scope,
		PublicId $public_id,
		PublicId $submission,
		int $person_id,
		int $actor_id,
		int $event_post,
		int $occurrence,
		int $form_version,
		PublicId $version_uuid,
		array $fields,
		array $values,
		array $types,
		string $utc_now,
		CorrelationId $correlation
	): int {
		$this->db->execute(
			"INSERT INTO %i (public_id, submission_key, organization_id, person_id, actor_user_id, event_post_id, occurrence_id, form_version_id, status, source, submitted_at, created_at, updated_at) VALUES (%s,%s,%d,%d,%d,%d,%d,%d,'submitted','portal',%s,%s,%s)",
			array( $this->prefix . 'registrations', $public_id->to_binary(), $submission->to_binary(), $scope->id, $person_id, $actor_id, $event_post, $occurrence, $form_version, $utc_now, $utc_now, $utc_now )
		);
		$ids = $this->db->rows(
			'SELECT id FROM %i WHERE organization_id = %d AND public_id = %s LIMIT 1',
			array( $this->prefix . 'registrations', $scope->id, $public_id->to_binary() )
		);
		if ( ! $ids ) {
			throw new RuntimeException( 'Registration insert failed.' );
		}
		$id            = (int) $ids[0]['id'];
		$snapshot      = array(
			'schema_version'         => 1,
			'form_version_public_id' => $version_uuid->to_string(),
			'fields'                 => $fields,
		);
		$json          = (string) wp_json_encode( $snapshot, JSON_THROW_ON_ERROR );
		$snapshot_uuid = PublicId::generate();
		$this->db->execute(
			'INSERT INTO %i (public_id, registration_id, revision, form_version_id, payload_json, payload_hash, created_by_user_id, reason, created_at) VALUES (%s,%d,1,%d,%s,%s,%d,%s,%s)',
			array( $this->prefix . 'registration_snapshots', $snapshot_uuid->to_binary(), $id, $form_version, $json, hash( 'sha256', $json, true ), $actor_id, 'submission', $utc_now )
		);
		$snap_rows = $this->db->rows(
			'SELECT id FROM %i WHERE registration_id = %d AND public_id = %s LIMIT 1',
			array( $this->prefix . 'registration_snapshots', $id, $snapshot_uuid->to_binary() )
		);
		if ( ! $snap_rows ) {
			throw new RuntimeException( 'Registration snapshot insert failed.' );
		}
		$snapshot_id = (int) $snap_rows[0]['id'];
		foreach ( $values as $key => $entries ) {
			foreach ( $entries as $entry ) {
				$slot = $entry['slot'];
				if ( ! in_array( $slot, array( 'value_string', 'value_text', 'value_date', 'value_boolean', 'value_decimal' ), true ) ) {
					throw new RuntimeException( 'Unsupported projection slot.' );
				}
				$this->db->execute(
					'INSERT INTO %i (registration_id, snapshot_id, field_key, data_type, sensitivity, ordinal, %i) VALUES (%d,%d,%s,%s,%s,%d,%s)',
					array( $this->prefix . 'registration_values', $slot, $id, $snapshot_id, $key, $types[ $key ], 'personal', $entry['ordinal'], $entry['value'] )
				);
			}
		}
		$this->db->execute(
			'UPDATE %i SET current_snapshot_id = %d WHERE organization_id = %d AND id = %d',
			array( $this->prefix . 'registrations', $snapshot_id, $scope->id, $id )
		);
		$this->db->execute(
			'INSERT INTO %i (registration_id, command_id, from_status, to_status, actor_user_id, correlation_id, created_at) VALUES (%d,%s,NULL,%s,%d,%s,%s)',
			array( $this->prefix . 'registration_history', $id, $submission->to_binary(), 'submitted', $actor_id, $correlation->to_binary(), $utc_now )
		);
		return $id;
	}
	/**
	 * Lock one current registration under its owner organization.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param PublicId $uuid  Registration public identity.
	 * @return array<string, mixed>|null
	 */
	public function lock_registration( OrgScope $scope, PublicId $uuid ): ?array {
		$rows = $this->db->rows(
			'SELECT id, public_id, person_id, event_post_id, status FROM %i WHERE organization_id = %d AND public_id = %s LIMIT 1 FOR UPDATE',
			array( $this->prefix . 'registrations', $scope->id, $uuid->to_binary() )
		);
		return $rows[0] ?? null;
	}

	/**
	 * Resolve a previously committed transition command within organization.
	 *
	 * @param OrgScope $scope   Trusted organization.
	 * @param PublicId $command Command UUID.
	 * @return array<string, mixed>|null
	 */
	public function transition_by_command( OrgScope $scope, PublicId $command ): ?array {
		$rows = $this->db->rows(
			'SELECT h.registration_id, h.to_status FROM %i h INNER JOIN %i r ON r.id = h.registration_id WHERE r.organization_id = %d AND h.command_id = %s LIMIT 1',
			array( $this->prefix . 'registration_history', $this->prefix . 'registrations', $scope->id, $command->to_binary() )
		);
		return $rows[0] ?? null;
	}

	/**
	 * Persist an authorized non-capacity transition and history atomically.
	 *
	 * @param OrgScope      $scope       Trusted organization.
	 * @param int           $id          Locked registration ID.
	 * @param string        $from        Current status.
	 * @param string        $to          Validated target status.
	 * @param PublicId      $command     Idempotent command UUID.
	 * @param int           $actor_id    Authorized actor ID.
	 * @param string        $utc_now     UTC timestamp.
	 * @param CorrelationId $correlation Trace identity.
	 * @throws RuntimeException When optimistic transition fails.
	 */
	public function transition( OrgScope $scope, int $id, string $from, string $to, PublicId $command, int $actor_id, string $utc_now, CorrelationId $correlation ): void {
		$updated = $this->db->execute(
			'UPDATE %i SET status = %s, version = version + 1, updated_at = %s WHERE organization_id = %d AND id = %d AND status = %s',
			array( $this->prefix . 'registrations', $to, $utc_now, $scope->id, $id, $from )
		);
		if ( 1 !== $updated ) {
			throw new RuntimeException( 'Registration state changed during transition.' );
		}
		$this->db->execute(
			'INSERT INTO %i (registration_id, command_id, from_status, to_status, actor_user_id, correlation_id, created_at) VALUES (%d,%s,%s,%s,%d,%s,%s)',
			array( $this->prefix . 'registration_history', $id, $command->to_binary(), $from, $to, $actor_id, $correlation->to_binary(), $utc_now )
		);
	}
}
