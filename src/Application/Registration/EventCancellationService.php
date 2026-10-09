<?php
/**
 * Transactional cancellation of a complete event and its participants.
 *
 * @package UOP
 */

namespace UOP\Application\Registration;

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
use UOP\Infrastructure\Database\EventCancellationRepository;
use UOP\Infrastructure\Database\OutboxRepository;

/** A cancellation is committed only when every affected registration is closed. */
final class EventCancellationService {
	/**
	 * @param EventCancellationRepository $events Scoped persistence.
	 * @param RegistrationStateMachine    $states Transition validator.
	 * @param PolicyService               $policy Live actor permissions.
	 * @param TransactionManager          $tx Atomic operation and deadlock retries.
	 * @param AuditWriter                 $audit Append-only evidence.
	 * @param OutboxRepository            $outbox Durable notification signals.
	 */
	public function __construct(
		private EventCancellationRepository $events,
		private RegistrationStateMachine $states,
		private PolicyService $policy,
		private TransactionManager $tx,
		private AuditWriter $audit,
		private OutboxRepository $outbox
	) {}

	/**
	 * Cancel all nonterminal registrations, claims and private offers.
	 *
	 * The event, buckets and registration rows are locked in one transaction.
	 * An idempotent replay of an already cancelled event returns zero and emits
	 * no new events. No email or other external side effect occurs before commit.
	 *
	 * @param Actor         $actor       Current event manager.
	 * @param OrgScope      $scope       Trusted organization.
	 * @param PublicId      $event_id    Scoped public event identity.
	 * @param PublicId      $command     Cancellation idempotency identity.
	 * @param string        $utc_now     Trusted UTC instant.
	 * @param CorrelationId $correlation Request trace.
	 * @return int Number of newly cancelled registrations.
	 * @throws RuntimeException When scope, actor or operational state is denied.
	 */
	public function cancel( Actor $actor, OrgScope $scope, PublicId $event_id, PublicId $command, string $utc_now, CorrelationId $correlation ): int {
		return $this->tx->run(
			function () use ( $actor, $scope, $event_id, $command, $utc_now, $correlation ): int {
				$event = $this->events->lock_event( $scope, $event_id );
				if ( ! $event ) {
					throw new RuntimeException( 'Event unavailable.' );
				}
				$post_id  = (int) $event['event_post_id'];
				$resource = new PolicyObject( $scope->id, 'event', $post_id, null, $post_id );
				if ( ! $this->policy->can( $actor, 'event.manage', $resource )->allowed
					|| ! user_can( $actor->user_id, 'edit_post', $post_id ) ) {
					throw new RuntimeException( 'Event cancellation is not authorized.' );
				}
				if ( 'cancelled' === $event['status'] ) {
					return 0;
				}
				if ( 'active' !== $event['status'] ) {
					throw new RuntimeException( 'Event status cannot be cancelled.' );
				}
				$this->events->lock_buckets( $scope, $post_id );
				$this->events->close_event( $scope, $post_id, $utc_now );
				$after = 0;
				$count = 0;
				do {
					$rows = $this->events->active_registrations_after( $scope, $post_id, $after );
					foreach ( $rows as $row ) {
						$id       = (int) $row['id'];
						$previous = (string) $row['status'];
						$after    = $id;
						$this->states->assert_transition( $previous, 'cancelled' );
						$this->events->release_registration( $scope, $id, $utc_now );
						$this->events->cancel_registration( $scope, $id, $previous, PublicId::generate(), $actor->user_id, $utc_now, $correlation );
						$registration = new PolicyObject( $scope->id, 'registration', $id, (int) $row['person_id'], $post_id );
						$event_uuid   = PublicId::generate();
						$this->audit->append( $scope, $actor, 'registration.event_cancelled', $registration, 'success', $correlation, $event_uuid, array( 'previous_status' => $previous, 'new_status' => 'cancelled' ) );
						$this->outbox->append(
							$scope,
							$event_uuid,
							'registration',
							$id,
							'registration.event_cancelled',
							$correlation,
							array(
								'public_id'       => PublicId::from_binary( $row['public_id'] )->to_string(),
								'previous_status' => $previous,
								'new_status'      => 'cancelled',
								'reason_code'     => 'event_cancelled',
							)
						);
						++$count;
					}
				} while ( count( $rows ) === 100 );
				$event_uuid = PublicId::generate();
				$this->audit->append( $scope, $actor, 'event.cancelled', $resource, 'success', $correlation, $event_uuid, array( 'command_id' => $command->to_string() ) );
				$this->outbox->append( $scope, $event_uuid, 'event', $post_id, 'event.cancelled', $correlation, array( 'public_id' => $event_id->to_string(), 'command_id' => $command->to_string(), 'status' => 'cancelled' ) );
				return $count;
			}
		);
	}
}
