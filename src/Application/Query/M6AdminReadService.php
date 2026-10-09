<?php
/**
 * Manager-only M6 person and registration administration read models.
 *
 * @package UOP
 */

namespace UOP\Application\Query;

use InvalidArgumentException;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\FieldDefinition;
use UOP\Application\Policy\PolicyObject;
use UOP\Application\Policy\PolicyService;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\M6AdminListRepository;
use UOP\Infrastructure\Database\RegistrationReadRepository;

/**
 * Staff-wide queries demand organization authorization; each returned object
 * also passes the exact field/object policy shared with the member endpoints.
 */
final class M6AdminReadService {
	/**
	 * Reuse the authoritative M6 detail projection and policy.
	 *
	 * @param M6AdminListRepository      $lists         Tenant-owned opaque cursors.
	 * @param M6ReadService              $reads         Shared per-object projection.
	 * @param PolicyService              $policy        Live authorization.
	 * @param RegistrationReadRepository $registrations Trusted registration owner lookup.
	 */
	public function __construct( private M6AdminListRepository $lists, private M6ReadService $reads, private PolicyService $policy, private RegistrationReadRepository $registrations ) {}

	/**
	 * Authorize an organization-wide administrative list, never a self grant.
	 *
	 * @param Actor    $actor Current WordPress user.
	 * @param OrgScope $scope Server-resolved tenant.
	 * @param string   $kind  people or registrations.
	 * @return bool
	 */
	public function can_list( Actor $actor, OrgScope $scope, string $kind ): bool {
		$action = match ( $kind ) {
			'people'        => 'person.view',
			'registrations' => 'registration.view',
			default         => '',
		};
		return '' !== $action && $this->policy->can( $actor, $action, new PolicyObject( $scope->id, 'organization', $scope->id ) )->allowed;
	}

	/**
	 * Project one bounded page of administrative person summaries.
	 *
	 * @param Actor         $actor  Current manager.
	 * @param OrgScope      $scope  Trusted tenant.
	 * @param PublicId|null $cursor Previous opaque page anchor.
	 * @return array<string,mixed>|null Null means inaccessible or invalid cursor.
	 */
	public function people( Actor $actor, OrgScope $scope, ?PublicId $cursor ): ?array {
		if ( ! $this->can_list( $actor, $scope, 'people' ) ) {
			return null;
		}
		$before = null === $cursor ? 0 : $this->lists->person_cursor( $scope, $cursor );
		if ( null === $before ) {
			return null;
		}
		$rows  = $this->lists->people( $scope, $before );
		$more  = count( $rows ) > 50;
		$items = array();
		foreach ( array_slice( $rows, 0, 50 ) as $row ) {
			$id  = PublicId::from_binary( (string) $row['public_id'] );
			$dto = $this->reads->person( $actor, $scope, $id );
			if ( null !== $dto ) {
				$resource = new PolicyObject( $scope->id, 'person', (int) $row['id'] );
				$field    = new FieldDefinition( 'display_name', 'personal', true, true, true, true );

				$dto['can_edit'] = $this->policy->can( $actor, 'person.edit', $resource, $field )->allowed;
				$items[] = $dto;
			}
		}
		$last = $rows[49]['public_id'] ?? null;
		return array(
			'items' => $items,
			'next'  => $more && is_string( $last ) ? PublicId::from_binary( $last )->to_string() : null,
		);
	}

	/**
	 * Project one bounded, optionally status-filtered registration page.
	 *
	 * @param Actor         $actor  Current manager.
	 * @param OrgScope      $scope  Trusted tenant.
	 * @param PublicId|null $cursor Previous opaque page anchor.
	 * @param string        $status Valid V1 lifecycle state or empty.
	 * @return array<string,mixed>|null
	 * @throws InvalidArgumentException For invalid V1 status values.
	 */
	public function registrations( Actor $actor, OrgScope $scope, ?PublicId $cursor, string $status ): ?array {
		if ( ! $this->can_list( $actor, $scope, 'registrations' ) ) {
			return null;
		}
		if ( '' !== $status && ! in_array( $status, array( 'submitted', 'review', 'accepted', 'waitlisted', 'offered', 'rejected', 'cancelled' ), true ) ) {
			throw new InvalidArgumentException( 'Unknown registration status filter.' );
		}
		$before = null === $cursor ? 0 : $this->lists->registration_cursor( $scope, $cursor, $status );
		if ( null === $before ) {
			return null;
		}
		$rows  = $this->lists->registrations( $scope, $before, $status );
		$more  = count( $rows ) > 50;
		$items = array();
		foreach ( array_slice( $rows, 0, 50 ) as $row ) {
			$id  = PublicId::from_binary( (string) $row['public_id'] );
			$dto = $this->reads->registration( $actor, $scope, $id );
			if ( null !== $dto ) {
				$person = $this->reads->person( $actor, $scope, PublicId::from_string( (string) $dto['person_id'] ) );

				$dto['person_name'] = $person ? (string) $person['display_name'] : '';

				$owner = $this->registrations->find( $scope, $id );
				if ( $owner ) {
					$object = new PolicyObject( $scope->id, 'registration', (int) $owner['id'], (int) $owner['person_id'], (int) $owner['event_post_id'] );

					$dto['can_review']   = $this->policy->can( $actor, 'registration.review', $object )->allowed;
					$dto['can_cancel']   = $this->policy->can( $actor, 'registration.cancel', $object )->allowed;
					$dto['can_allocate'] = $dto['can_review'] && $this->policy->can( $actor, 'capacity.manage', $object )->allowed;
				}
				$items[] = $dto;
			}
		}
		$last = $rows[49]['public_id'] ?? null;
		return array(
			'items' => $items,
			'next'  => $more && is_string( $last ) ? PublicId::from_binary( $last )->to_string() : null,
		);
	}
}
