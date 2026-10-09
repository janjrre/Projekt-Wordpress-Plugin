<?php
/**
 * Permission-projected capacity, consent and audit read models.
 *
 * @package UOP
 */

namespace UOP\Application\Query;

use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyObject;
use UOP\Application\Policy\PolicyService;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\ConsentRepository;
use UOP\Infrastructure\Database\EventRepository;
use UOP\Infrastructure\Database\M6OperationsRepository;
use UOP\Infrastructure\Database\RegistrationReadRepository;

/** No private audit payload, raw contact field or database primary key escapes. */
final class M6OperationsReadService {
	/**
	 * Bind only current policy and tenant-scoped read repositories.
	 *
	 * @param M6OperationsRepository    $operations    Bounded operational summaries.
	 * @param RegistrationReadRepository $registrations Scoped registration lookup.
	 * @param EventRepository            $events        Scoped event lookup.
	 * @param ConsentRepository          $documents     Immutable published consent versions.
	 * @param PolicyService              $policy        Authoritative live object policy.
	 */
	public function __construct(
		private M6OperationsRepository $operations,
		private RegistrationReadRepository $registrations,
		private EventRepository $events,
		private ConsentRepository $documents,
		private PolicyService $policy
	) {}

	/**
	 * Return current bucket counts only to event capacity managers.
	 *
	 * @param Actor    $actor Authenticated actor.
	 * @param OrgScope $scope Organization scope.
	 * @param PublicId $event Public event identity.
	 * @return array<string,mixed>|null
	 */
	public function capacity( Actor $actor, OrgScope $scope, PublicId $event ): ?array {
		$row = $this->events->by_public( $scope, $event );
		if ( ! $row ) {
			return null;
		}
		$post = (int) $row['event_post_id'];
		if ( ! $this->policy->can( $actor, 'capacity.manage', new PolicyObject( $scope->id, 'event', $post, null, $post ) )->allowed ) {
			return null;
		}
		$items = array();
		foreach ( $this->operations->capacity( $scope, $post ) as $bucket ) {
			$items[] = array(
				'public_id' => PublicId::from_binary( (string) $bucket['public_id'] )->to_string(),
				'label'     => (string) $bucket['label'],
				'capacity'  => (int) $bucket['capacity'],
				'occupied'  => (int) $bucket['occupied'],
				'waiting'   => (int) $bucket['waiting'],
				'status'    => (string) $bucket['status'],
			);
		}
		return array( 'event_id' => $event->to_string(), 'buckets' => $items );
	}

	/**
	 * Show append-only, version-pinned decisions for an authorized registration.
	 *
	 * @param Actor    $actor Current actor.
	 * @param OrgScope $scope Trusted tenant.
	 * @param PublicId $id    Registration identity.
	 * @return array<string,mixed>|null
	 */
	public function registration_consents( Actor $actor, OrgScope $scope, PublicId $id ): ?array {
		$row = $this->registrations->find( $scope, $id );
		if ( ! $row || ! $this->policy->can( $actor, 'registration.view', new PolicyObject( $scope->id, 'registration', (int) $row['id'], (int) $row['person_id'], (int) $row['event_post_id'] ) )->allowed ) {
			return null;
		}
		$items = array();
		foreach ( $this->operations->consents( $scope, (int) $row['id'] ) as $evidence ) {
			$items[] = $this->evidence( $evidence );
		}
		return array( 'registration_id' => $id->to_string(), 'items' => $items );
	}

	/**
	 * Confirm that a consent action belongs to a currently authorized subject.
	 *
	 * @param Actor    $actor Current actor.
	 * @param OrgScope $scope Trusted tenant.
	 * @param PublicId $id    Consent record public identity.
	 * @param string   $action Either registration.view or registration.cancel.
	 * @return array<string,mixed>|null
	 */
	public function consent( Actor $actor, OrgScope $scope, PublicId $id, string $action = 'registration.view' ): ?array {
		if ( ! in_array( $action, array( 'registration.view', 'registration.cancel' ), true ) ) {
			return null;
		}
		$row = $this->operations->consent( $scope, $id );
		if ( ! $row || ! $this->policy->can( $actor, $action, new PolicyObject( $scope->id, 'registration', (int) $row['registration_id'], (int) $row['subject_person_id'], (int) $row['event_post_id'] ) )->allowed ) {
			return null;
		}
		return $this->evidence( $row );
	}

	/**
	 * Return one immutable consent text and fingerprint only to privacy managers.
	 *
	 * @param Actor    $actor Current actor.
	 * @param OrgScope $scope Trusted tenant.
	 * @param PublicId $id    Published document version identity.
	 * @return array<string,mixed>|null
	 */
	public function consent_version( Actor $actor, OrgScope $scope, PublicId $id ): ?array {
		if ( ! $this->allowed( $actor, $scope, 'privacy.manage' ) ) {
			return null;
		}
		$row = $this->documents->version( $scope, $id );
		return $row ? array(
			'public_id' => $id->to_string(),
			'key'       => (string) $row['consent_key'],
			'version'   => (int) $row['version'],
			'content'   => (string) $row['content'],
			'sha256'    => bin2hex( (string) $row['document_hash'] ),
		) : null;
	}

	/**
	 * Return only safe action names and correlation UUIDs to auditors.
	 *
	 * @param Actor    $actor Current actor.
	 * @param OrgScope $scope Trusted tenant.
	 * @return array<string,mixed>|null
	 */
	public function audit( Actor $actor, OrgScope $scope ): ?array {
		if ( ! $this->allowed( $actor, $scope, 'audit.view' ) ) {
			return null;
		}
		$items = array();
		foreach ( $this->operations->audit( $scope ) as $row ) {
			$items[] = array(
				'occurred_at' => (string) $row['occurred_at'],
				'action'      => (string) $row['action'],
				'object_type' => (string) $row['object_type'],
				'result'      => (string) $row['result'],
				'correlation' => PublicId::from_binary( (string) $row['correlation_id'] )->to_string(),
			);
		}
		return array( 'items' => $items );
	}

	/**
	 * Check one organization-wide management action.
	 *
	 * @param Actor    $actor Current actor.
	 * @param OrgScope $scope Tenant.
	 * @param string   $action Known organization capability.
	 * @return bool
	 */
	public function allowed( Actor $actor, OrgScope $scope, string $action ): bool {
		return $this->policy->can( $actor, $action, new PolicyObject( $scope->id, 'organization', $scope->id ) )->allowed;
	}

	/**
	 * Convert a stored consent to minimum non-identifying evidence fields.
	 *
	 * @param array<string,mixed> $row Scoped evidence.
	 * @return array<string,mixed>
	 */
	private function evidence( array $row ): array {
		return array(
			'public_id' => PublicId::from_binary( (string) $row['public_id'] )->to_string(),
			'key'       => (string) $row['consent_key'],
			'version'   => PublicId::from_binary( (string) $row['version_public_id'] )->to_string(),
			'decision'  => (string) $row['decision'],
			'decided_at'=> (string) $row['decided_at'],
		);
	}
}
