<?php
/**
 * Safe non-capacity registration transition commands.
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
use UOP\Infrastructure\Database\OutboxRepository;
use UOP\Infrastructure\Database\RegistrationRepository;

/**
 * Cannot accept, waitlist or release capacity: those states require bucket
 * locking, claims, eligibility, and a dedicated M4 capacity application service.
 */
final class RegistrationTransitionService {
	/**
	 * Compose immutable history and current policy checks.
	 *
	 * @param RegistrationRepository   $registrations Scoped storage.
	 * @param RegistrationStateMachine $states        Frozen transition graph.
	 * @param PolicyService            $policy        Authorization service.
	 * @param TransactionManager       $tx            Transaction manager.
	 * @param AuditWriter              $audit         Audit writer.
	 * @param OutboxRepository         $outbox        Durable domain events.
	 */
	public function __construct(
		private RegistrationRepository $registrations,
		private RegistrationStateMachine $states,
		private PolicyService $policy,
		private TransactionManager $tx,
		private AuditWriter $audit,
		private OutboxRepository $outbox
	) {}

	/**
	 * Move a submitted or reviewed registration without touching capacity.
	 *
	 * @param Actor         $actor       Current WordPress actor.
	 * @param OrgScope      $scope       Trusted organization.
	 * @param PublicId      $id          Registration public UUID.
	 * @param string        $target      Only review, rejected or cancelled.
	 * @param PublicId      $command_id  Stable idempotent command ID.
	 * @param string        $utc_now     Trusted UTC clock.
	 * @param CorrelationId $correlation Trace identity.
	 * @throws InvalidArgumentException For forbidden transition targets.
	 */
	public function transition( Actor $actor, OrgScope $scope, PublicId $id, string $target, PublicId $command_id, string $utc_now, CorrelationId $correlation ): void {
		if ( ! in_array( $target, array( 'review', 'rejected', 'cancelled' ), true ) ) {
			throw new InvalidArgumentException( 'Capacity-dependent transitions require a dedicated command.' );
		}
		$this->tx->run(
			function () use ( $actor, $scope, $id, $target, $command_id, $utc_now, $correlation ): void {
				$row = $this->registrations->lock_registration( $scope, $id );
				if ( ! $row ) {
					throw new RuntimeException( 'Registration unavailable.' );
				}
				$resource = new PolicyObject( $scope->id, 'registration', (int) $row['id'], (int) $row['person_id'], (int) $row['event_post_id'] );
				$action   = 'cancelled' === $target ? 'registration.cancel' : 'registration.review';
				if ( ! $this->policy->can( $actor, $action, $resource )->allowed ) {
					throw new RuntimeException( 'Registration transition denied.' );
				}
				$prior = $this->registrations->transition_by_command( $scope, $command_id );
				if ( $prior ) {
					if ( (int) $prior['registration_id'] !== (int) $row['id'] || $prior['to_status'] !== $target ) {
						throw new RuntimeException( 'Command UUID already used for another transition.' );
					}
					return;
				}
				if ( ! in_array( $row['status'], array( 'submitted', 'review' ), true ) ) {
					throw new RuntimeException( 'A capacity-aware transition is required.' );
				}
				$this->states->assert_transition( (string) $row['status'], $target );
				$this->registrations->transition( $scope, (int) $row['id'], (string) $row['status'], $target, $command_id, $actor->user_id, $utc_now, $correlation );
				$event = PublicId::generate();
				$this->audit->append(
					$scope,
					$actor,
					'registration.transitioned',
					$resource,
					'success',
					$correlation,
					$event,
					array(
						'previous_status' => (string) $row['status'],
						'new_status'      => $target,
					)
				);
				$this->outbox->append(
					$scope,
					$event,
					'registration',
					(int) $row['id'],
					'registration.transitioned',
					$correlation,
					array(
						'public_id'       => $id->to_string(),
						'previous_status' => (string) $row['status'],
						'new_status'      => $target,
					)
				);
			}
		);
	}
}
