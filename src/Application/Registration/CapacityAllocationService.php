<?php
/**
 * Serialized first-pass capacity allocation.
 *
 * @package UOP
 */

namespace UOP\Application\Registration;

use InvalidArgumentException;
use RuntimeException;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyObject;
use UOP\Application\Policy\PolicyService;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Core\TransactionManager;
use UOP\Domain\Organization\OrgScope;
use UOP\Domain\Registrations\RegistrationStateMachine;
use UOP\Infrastructure\Database\AuditWriter;
use UOP\Infrastructure\Database\CapacityRepository;
use UOP\Infrastructure\Database\OutboxRepository;
use UOP\Infrastructure\Database\RegistrationRepository;

/**
 * No claim is inserted until the bucket PK row is locked and occupied claims
 * are counted in the same transaction. Offers and release are a later command.
 */
final class CapacityAllocationService {
	/**
	 * Compose the authoritative allocation boundary.
	 *
	 * @param CapacityRepository       $capacity      Scoped bucket persistence.
	 * @param RegistrationRepository   $registrations Locked registration access.
	 * @param RegistrationStateMachine $states        Frozen transitions.
	 * @param PolicyService            $policy        Live permission decisions.
	 * @param TransactionManager       $tx            Deadlock-aware transaction boundary.
	 * @param AuditWriter              $audit         Append-only minimal audit.
	 * @param OutboxRepository         $outbox        Durable domain events.
	 */
	public function __construct(
		private CapacityRepository $capacity,
		private RegistrationRepository $registrations,
		private RegistrationStateMachine $states,
		private PolicyService $policy,
		private TransactionManager $tx,
		private AuditWriter $audit,
		private OutboxRepository $outbox
	) {}

	/**
	 * Create the first event-wide bucket using an explicit capability check.
	 *
	 * @param Actor         $actor       Current administrative actor.
	 * @param OrgScope      $scope       Trusted organization.
	 * @param PublicId      $event_id    Event public UUID.
	 * @param int           $limit       Positive total capacity.
	 * @param string        $utc_now     Trusted UTC instant.
	 * @param CorrelationId $correlation Request trace.
	 * @return PublicId Bucket public identity.
	 * @throws InvalidArgumentException When capacity is invalid.
	 */
	public function create_general_bucket( Actor $actor, OrgScope $scope, PublicId $event_id, int $limit, string $utc_now, CorrelationId $correlation ): PublicId {
		if ( $limit < 1 || $limit > 1000000 ) {
			throw new InvalidArgumentException( 'Capacity must be positive and bounded.' );
		}
		return $this->tx->run(
			function () use ( $actor, $scope, $event_id, $limit, $utc_now, $correlation ): PublicId {
				$event_post = $this->capacity->event_post( $scope, $event_id );
				if ( null === $event_post ) {
					throw new RuntimeException( 'Event unavailable.' );
				}
				$resource = new PolicyObject( $scope->id, 'event', $event_post, null, $event_post );
				if ( ! $this->policy->can( $actor, 'capacity.manage', $resource )->allowed
					|| ! user_can( $actor->user_id, 'edit_post', $event_post ) ) {
					throw new RuntimeException( 'Capacity configuration not permitted.' );
				}
				$uuid         = PublicId::generate();
				$id           = $this->capacity->create_general( $scope, $uuid, $event_post, $limit, $utc_now );
				$domain_event = PublicId::generate();
				$this->audit->append( $scope, $actor, 'capacity.created', $resource, 'success', $correlation, $domain_event );
				$this->outbox->append( $scope, $domain_event, 'capacity', $id, 'capacity.created', $correlation, array( 'public_id' => $uuid->to_string() ) );
				return $uuid;
			}
		);
	}

	/**
	 * Decide acceptance vs. waitlisting under an InnoDB bucket row lock.
	 *
	 * @param Actor         $actor       Authorized event reviewer.
	 * @param OrgScope      $scope       Trusted organization.
	 * @param PublicId      $registration Registration public UUID.
	 * @param PublicId      $bucket      Locked capacity bucket UUID.
	 * @param PublicId      $command_id  Stable idempotent command UUID.
	 * @param string        $utc_now     Trusted UTC instant.
	 * @param CorrelationId $correlation Request trace.
	 * @return string 'accepted' or 'waitlisted'.
	 * @throws RuntimeException When permission or allocation precondition fails.
	 */
	public function decide( Actor $actor, OrgScope $scope, PublicId $registration, PublicId $bucket, PublicId $command_id, string $utc_now, CorrelationId $correlation ): string {
		return $this->tx->run(
			function () use ( $actor, $scope, $registration, $bucket, $command_id, $utc_now, $correlation ): string {
				// Global lock order begins with bucket PK; never lock registration first.
				$locked = $this->capacity->lock_bucket( $scope, $bucket );
				if ( ! $locked ) {
					throw new RuntimeException( 'Capacity bucket unavailable.' );
				}
				$row = $this->registrations->lock_registration( $scope, $registration );
				if ( ! $row || (int) $row['event_post_id'] !== (int) $locked['event_post_id']
					|| 0 !== (int) $locked['occurrence_id'] ) {
					throw new RuntimeException( 'Registration and bucket scope mismatch.' );
				}
				$resource = new PolicyObject( $scope->id, 'registration', (int) $row['id'], (int) $row['person_id'], (int) $row['event_post_id'] );
				if ( ! $this->policy->can( $actor, 'registration.review', $resource )->allowed
					|| ! $this->policy->can( $actor, 'capacity.manage', $resource )->allowed ) {
					throw new RuntimeException( 'Capacity decision not permitted.' );
				}
				$prior = $this->capacity->prior_decision( $scope, $command_id );
				if ( $prior ) {
					if ( (int) $prior['registration_id'] !== (int) $row['id'] || ( 'bucket:' . $bucket->to_string() ) !== $prior['reason_code']
						|| ! in_array( $prior['to_status'], array( 'accepted', 'waitlisted' ), true ) ) {
						throw new RuntimeException( 'Idempotency command has another owner.' );
					}
					return (string) $prior['to_status'];
				}
				if ( ! in_array( $row['status'], array( 'submitted', 'review' ), true )
					|| $this->capacity->has_allocation( $scope, (int) $row['id'] ) ) {
					throw new RuntimeException( 'Registration requires a dedicated release or offer command.' );
				}
				if ( null !== $locked['eligibility_json'] && '' !== $locked['eligibility_json'] ) {
					throw new RuntimeException( 'Bucket eligibility needs a verified snapshot decision.' );
				}
				if ( $this->capacity->requires_verification( $scope, (int) $locked['event_post_id'] )
					&& null === $row['email_verified_at'] ) {
					throw new RuntimeException( 'Email verification is required before acceptance.' );
				}
				$occupied = $this->capacity->occupied( $scope, (int) $locked['id'] );
				$target   = $occupied < (int) $locked['capacity'] && ! $this->capacity->has_waiters( $scope, (int) $locked['id'] ) ? 'accepted' : 'waitlisted';
				$this->states->assert_transition( (string) $row['status'], $target );
				if ( 'accepted' === $target ) {
					$this->capacity->confirm( $scope, (int) $locked['id'], (int) $row['id'], $utc_now );
				} else {
					$this->capacity->waitlist( $scope, (int) $locked['id'], (int) $row['id'], $utc_now );
				}
				$this->capacity->record_decision( $scope, (int) $row['id'], (string) $row['status'], $target, $bucket, $command_id, $actor->user_id, $utc_now, $correlation );
				$event = PublicId::generate();
				$this->audit->append( $scope, $actor, 'registration.capacity_decided', $resource, 'success', $correlation, $event, array( 'new_status' => $target ) );
				$this->outbox->append(
					$scope,
					$event,
					'registration',
					(int) $row['id'],
					'registration.capacity_decided',
					$correlation,
					array(
						'public_id'  => $registration->to_string(),
						'new_status' => $target,
					)
				);
				return $target;
			}
		);
	}
}
