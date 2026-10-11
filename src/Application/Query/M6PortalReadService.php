<?php
/**
 * M6-07 portal presentation DTOs with fresh object-specific permissions.
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
use UOP\Infrastructure\Database\PersonRepository;
use UOP\Infrastructure\Database\RegistrationReadRepository;

/** Rendering does not grant rights; each REST command rechecks the live policy. */
final class M6PortalReadService {
	/**
	 * Reuse existing private DTO projections and authorization.
	 *
	 * @param M6ReadService              $reads         Existing person and registration projection.
	 * @param PersonRepository           $people        Scoped subject identity.
	 * @param RegistrationReadRepository $registrations Scoped stored registration owner.
	 * @param PolicyService              $policy        Live object and field rights.
	 */
	public function __construct(
		private M6ReadService $reads,
		private PersonRepository $people,
		private RegistrationReadRepository $registrations,
		private PolicyService $policy
	) {}

	/**
	 * Offer only live self/delegated persons, never matching by family email.
	 *
	 * @param Actor    $actor Authenticated WordPress user.
	 * @param OrgScope $scope Trusted site organization.
	 * @return array<string,mixed> Minimal display and action capabilities.
	 */
	public function subjects( Actor $actor, OrgScope $scope ): array {
		$items = array();
		foreach ( $this->reads->my_people( $actor, $scope ) as $person ) {
			$id      = PublicId::from_string( (string) $person['public_id'] );
			$subject = $this->people->find( $scope, $id );
			if ( ! $subject || 'active' !== $subject['status'] ) {
				continue;
			}
			$object       = new PolicyObject( $scope->id, 'person', (int) $subject['id'] );
			$registration = new PolicyObject( $scope->id, 'registration', (int) $subject['id'], (int) $subject['id'] );
			$name         = new FieldDefinition( 'display_name', 'personal', true, true, true, true );
			$items[]      = array(
				'public_id'        => $person['public_id'],
				'display_name'     => $person['display_name'],
				'version'          => $person['version'],
				'can_edit_name'    => $this->policy->can( $actor, 'person.edit', $object, $name )->allowed,
				'can_view_entries' => $this->policy->can( $actor, 'registration.view', $registration )->allowed,
			);
		}
		return array( 'items' => $items );
	}

	/**
	 * Page registration DTOs through M6; add no raw PII or unchecked actions.
	 *
	 * @param Actor         $actor  Current authenticated account.
	 * @param OrgScope      $scope  Trusted site scope.
	 * @param PublicId      $person Selected public person.
	 * @param PublicId|null $after  Page cursor.
	 * @return array<string,mixed>|null Hide inaccessible subjects/cursors.
	 */
	public function registrations( Actor $actor, OrgScope $scope, PublicId $person, ?PublicId $after ): ?array {
		$selected = $this->reads->person( $actor, $scope, $person );
		if ( null === $selected ) {
			return null;
		}
		$subject = $this->people->find( $scope, $person );
		if ( ! $subject || 'active' !== $subject['status'] ) {
			return null;
		}
		$check = new PolicyObject( $scope->id, 'registration', (int) $subject['id'], (int) $subject['id'] );
		if ( ! $this->policy->can( $actor, 'registration.view', $check )->allowed ) {
			return null;
		}
		$page = $this->reads->registrations( $actor, $scope, $person, $after );
		if ( null === $page ) {
			return null;
		}
		$items = array();
		foreach ( $page['items'] as $dto ) {
			$row = $this->registrations->find( $scope, PublicId::from_string( (string) $dto['public_id'] ) );
			if ( ! $row || (int) $row['person_id'] !== (int) $subject['id'] ) {
				continue;
			}
			$object            = new PolicyObject( $scope->id, 'registration', (int) $row['id'], (int) $row['person_id'], (int) $row['event_post_id'] );
			$dto['can_cancel'] = in_array( $dto['status'], array( 'submitted', 'review', 'accepted', 'waitlisted', 'offered' ), true )
				&& $this->policy->can( $actor, 'registration.cancel', $object )->allowed;
			$items[]           = $dto;
		}
		return array(
			'items' => $items,
			'next'  => $page['next'],
		);
	}
}
