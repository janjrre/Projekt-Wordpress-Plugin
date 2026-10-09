<?php
/**
 * Organization-scoped, policy-projected M6 people and registration queries.
 *
 * @package UOP
 */

namespace UOP\Application\Query;

use UOP\Application\Policy\Actor;
use UOP\Application\Policy\FieldDefinition;
use UOP\Application\Policy\PolicyObject;
use UOP\Application\Policy\PolicyService;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\DelegationRepository;
use UOP\Infrastructure\Database\PersonRepository;
use UOP\Infrastructure\Database\RegistrationReadRepository;

/** This service is shared by REST and the upcoming admin and portal screens. */
final class M6ReadService {
	/**
	 * Shared API contract or operation.
	 *
	 * @param PersonRepository     $people      Organization-scoped person storage.
	 * @param DelegationRepository $delegations Live per-person delegation grants.
	 * @param RegistrationReadRepository $registrations Read-only registration persistence.
	 * @param PolicyService        $policy      Authoritative object and field authorization.
	 */
	public function __construct(
		private PersonRepository $people,
		private DelegationRepository $delegations,
		private RegistrationReadRepository $registrations,
		private PolicyService $policy
	) {}

	/**
	 * List the linked self and live, authorized delegated persons only.
	 *
	 * @param Actor    $actor Current account.
	 * @param OrgScope $scope Server-trusted organization.
	 * @return list<array<string, mixed>> Safe identities.
	 */
	public function my_people( Actor $actor, OrgScope $scope ): array {
		if ( $actor->user_id < 1 ) {
			return array();
		}
		$items = array();
		$self  = $this->people->by_user( $scope, $actor->user_id );
		if ( $self && 'active' === $self['status'] ) {
			$projected = $this->project_person( $actor, $scope, $self );
			if ( null !== $projected ) {
				$items[] = $projected;
			}
		}
		$seen = array();
		if ( $self ) {
			$seen[ (int) $self['id'] ] = true;
		}
		foreach ( $this->delegations->for_actor( $scope, $actor->user_id ) as $grant ) {
			$id = (int) $grant['subject_person_id'];
			if ( isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$row         = $this->people->by_internal_id( $scope, $id );
			if ( ! $row || 'active' !== $row['status'] ) {
				continue;
			}
			$projected = $this->project_person( $actor, $scope, $row );
			if ( null !== $projected ) {
				$items[] = $projected;
			}
		}
		return $items;
	}

	/**
	 * Project a single person; null intentionally hides unauthorized subjects.
	 *
	 * @param Actor    $actor Current account.
	 * @param OrgScope $scope Organization.
	 * @param PublicId $id    Opaque person identifier.
	 * @return array<string, mixed>|null Authorized person metadata.
	 */
	public function person( Actor $actor, OrgScope $scope, PublicId $id ): ?array {
		$row = $this->people->find( $scope, $id );
		return $row ? $this->project_person( $actor, $scope, $row ) : null;
	}

	/**
	 * A display name does not prove a WordPress user or family-email identity.
	 *
	 * @param Actor                $actor Current account.
	 * @param OrgScope             $scope Organization.
	 * @param array<string, mixed> $row   Tenant-checked person row.
	 * @return array<string, mixed>|null
	 */
	private function project_person( Actor $actor, OrgScope $scope, array $row ): ?array {
		$resource = new PolicyObject( $scope->id, 'person', (int) $row['id'], null, null, 'active' !== $row['status'] );
		if ( ! $this->policy->can( $actor, 'person.view', $resource )->allowed ) {
			return null;
		}
		$dto   = array(
			'public_id'    => PublicId::from_binary( (string) $row['public_id'] )->to_string(),
			'display_name' => (string) $row['display_name'],
			'status'       => (string) $row['status'],
			'version'      => (int) $row['version'],
		);
		$email = new FieldDefinition( 'primary_email', 'personal', true, false, false, false );
		if ( null !== $row['primary_email'] && $this->policy->can( $actor, 'person.view', $resource, $email )->allowed ) {
			$dto['primary_email'] = (string) $row['primary_email'];
		}
		return $dto;
	}

	/**
	 * Return one policy-authorized registration without contact or form secrets.
	 *
	 * @param Actor    $actor Current account.
	 * @param OrgScope $scope Organization.
	 * @param PublicId $id    Registration identifier.
	 * @return array<string, mixed>|null
	 */
	public function registration( Actor $actor, OrgScope $scope, PublicId $id ): ?array {
		$row = $this->registrations->find( $scope, $id );
		return $row ? $this->project_registration( $actor, $scope, $row ) : null;
	}

	/**
	 * Return bounded registration summaries for one's own or delegated subject.
	 * Default list scope is the account's own linked person; no staff-wide dump.
	 *
	 * @param Actor         $actor  Current account.
	 * @param OrgScope      $scope  Server scope.
	 * @param PublicId|null $person Requested person or self.
	 * @param PublicId|null $after  Last authorized row's public ID.
	 * @return array<string, mixed>|null Null for hidden/invalid cursor or person.
	 */
	public function registrations( Actor $actor, OrgScope $scope, ?PublicId $person, ?PublicId $after ): ?array {
		$subject = null === $person ? $this->people->by_user( $scope, $actor->user_id ) : $this->people->find( $scope, $person );
		if ( ! $subject || 'active' !== $subject['status'] ) {
			return null === $person ? array(
				'items' => array(),
				'next'  => null,
			) : null;
		}
		$person_id = (int) $subject['id'];
		$after_id  = 0;
		if ( null !== $after ) {
			$cursor = $this->registrations->cursor_for_person( $scope, $person_id, $after );
			if ( null === $cursor || null === $this->registration( $actor, $scope, $after ) ) {
				return null;
			}
			$after_id = $cursor;
		}
		$items = array();
		foreach ( $this->registrations->for_person( $scope, $person_id, $after_id ) as $row ) {
			$dto = $this->project_registration( $actor, $scope, $row );
			if ( null !== $dto ) {
				$items[] = $dto;
			}
		}
		return array(
			'items' => $items,
			'next'  => count( $items ) === 50 ? $items[49]['public_id'] : null,
		);
	}


	/**
	 * Permit registration viewing only under the live registration-specific policy.
	 *
	 * @param Actor                $actor Current account.
	 * @param OrgScope             $scope Organization.
	 * @param array<string, mixed> $row   Scoped query result.
	 * @return array<string, mixed>|null
	 */
	private function project_registration( Actor $actor, OrgScope $scope, array $row ): ?array {
		$resource = new PolicyObject( $scope->id, 'registration', (int) $row['id'], (int) $row['person_id'], (int) $row['event_post_id'] );
		if ( ! $this->policy->can( $actor, 'registration.view', $resource )->allowed ) {
			return null;
		}
		return array(
			'public_id'  => PublicId::from_binary( (string) $row['public_id'] )->to_string(),
			'person_id'  => PublicId::from_binary( (string) $row['person_public_id'] )->to_string(),
			'event_id'   => is_string( $row['event_public_id'] ) ? PublicId::from_binary( $row['event_public_id'] )->to_string() : null,
			'status'     => (string) $row['status'],
			'version'    => (int) $row['version'],
			'created_at' => (string) $row['created_at'],
		);
	}
}
