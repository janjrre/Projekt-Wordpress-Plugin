<?php
/**
 * Capacity-safe cancellation, offers and acceptance commands.
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
use UOP\Infrastructure\Database\WaitlistRepository;

/**
 * Every seat-changing operation first locks its bucket primary key.
 * Cleartext offer tokens never reach audit or the transactional outbox.
 */
final class CapacityLifecycleService {
	/**
	 * Compose the capacity-aware command boundary.
	 *
	 * @param WaitlistRepository       $queue    Scoped waitlist/claim persistence.
	 * @param CapacityRepository       $capacity Current occupancy queries.
	 * @param RegistrationStateMachine $states  Fixed transition graph.
	 * @param PolicyService            $policy   Current capability and object policy.
	 * @param TransactionManager       $tx       Atomic InnoDB transaction.
	 * @param AuditWriter              $audit    Minimal append-only evidence.
	 * @param OutboxRepository         $outbox   Durable domain events.
	 */
	public function __construct(
		private WaitlistRepository $queue,
		private CapacityRepository $capacity,
		private RegistrationStateMachine $states,
		private PolicyService $policy,
		private TransactionManager $tx,
		private AuditWriter $audit,
		private OutboxRepository $outbox
	) {}

	/**
	 * Cancel an accepted, offered or waiting registration and release its seat.
	 *
	 * Returning no token is intentional: the cancelling subject must never
	 * receive another applicant's private offer link. The next authorized
	 * manager offer is a separate command until M5 delivers messages securely.
	 *
	 * @param Actor         $actor       Authorized self, delegate or manager.
	 * @param OrgScope      $scope       Trusted organization.
	 * @param PublicId      $registration Registration public identity.
	 * @param PublicId      $command     Idempotency identity.
	 * @param string        $utc_now     UTC cancellation instant.
	 * @param CorrelationId $correlation Request trace.
	 * @throws RuntimeException On unauthorized or inconsistent state.
	 */
	public function cancel( Actor $actor, OrgScope $scope, PublicId $registration, PublicId $command, string $utc_now, CorrelationId $correlation ): void {
		$bucket_id = $this->queue->bucket_for_registration( $scope, $registration );
		if ( null === $bucket_id ) {
			throw new RuntimeException( 'Registration has no capacity allocation.' );
		}
		$this->tx->run(
			function () use ( $actor, $scope, $registration, $command, $utc_now, $correlation, $bucket_id ): void {
				$bucket = $this->queue->lock_bucket( $scope, $bucket_id );
				if ( ! $bucket ) {
					throw new RuntimeException( 'Capacity scope was removed.' );
				}
				$lookup = $this->queue->bucket_for_registration( $scope, $registration );
				if ( $lookup !== $bucket_id ) {
					throw new RuntimeException( 'Registration moved to another bucket.' );
				}
				$row    = $this->registration_from_public( $scope, $registration, $bucket_id );
				$domain_object = $this->resource( $scope, $row );
				if ( ! $this->policy->can( $actor, 'registration.cancel', $domain_object )->allowed ) {
					throw new RuntimeException( 'Cancellation is not authorized.' );
				}
				if ( 'cancelled' === $row['status'] ) {
					return;
				}
				if ( ! in_array( $row['status'], array( 'accepted', 'waitlisted', 'offered' ), true ) ) {
					throw new RuntimeException( 'Registration is not in a cancellable allocation state.' );
				}
				$this->states->assert_transition( (string) $row['status'], 'cancelled' );
				$this->queue->release( $scope, (int) $row['id'], $bucket_id, $utc_now );
				$this->queue->transition( $scope, (int) $row['id'], (string) $row['status'], 'cancelled', $command, $actor->user_id, $utc_now, $correlation );
				$this->record( $scope, $actor, $domain_object, 'registration.cancelled', $registration, 'cancelled', $correlation );
			}
		);
	}

	/**
	 * Offer the earliest waiting registration one real expiring held claim.
	 *
	 * The returned secret is only for an authenticated privileged caller and
	 * must be delivered through the protected M5 communications service.
	 *
	 * @param Actor         $actor       Authorized manager, not a guest.
	 * @param OrgScope      $scope       Trusted organization.
	 * @param PublicId      $bucket_id   Bucket public UUID.
	 * @param PublicId      $command     One-time command identity.
	 * @param string        $utc_now     UTC offer time.
	 * @param CorrelationId $correlation Request trace.
	 * @return array{public_id:string,token:string}|null No seat or waiter.
	 * @throws RuntimeException When an offer is forbidden.
	 */
	public function offer_next( Actor $actor, OrgScope $scope, PublicId $bucket_id, PublicId $command, string $utc_now, CorrelationId $correlation ): ?array {
		return $this->tx->run(
			function () use ( $actor, $scope, $bucket_id, $command, $utc_now, $correlation ): ?array {
				$bucket = $this->capacity->lock_bucket( $scope, $bucket_id );
				if ( ! $bucket ) {
					throw new RuntimeException( 'Offer bucket unavailable.' );
				}
				$event = new PolicyObject( $scope->id, 'event', (int) $bucket['event_post_id'], null, (int) $bucket['event_post_id'] );
				if ( ! $this->policy->can( $actor, 'capacity.manage', $event )->allowed
					|| ! user_can( $actor->user_id, 'edit_post', (int) $bucket['event_post_id'] ) ) {
					throw new RuntimeException( 'Offer creation is not authorized.' );
				}
				if ( 0 !== (int) $bucket['occurrence_id'] || null !== $bucket['eligibility_json'] ) {
					throw new RuntimeException( 'This bucket requires another eligibility strategy.' );
				}
				if ( $this->capacity->occupied( $scope, (int) $bucket['id'] ) >= (int) $bucket['capacity'] ) {
					return null;
				}
				$waiting = $this->queue->next_waiter( $scope, (int) $bucket['id'] );
				if ( ! $waiting ) {
					return null;
				}
				$row = $this->queue->registration( $scope, (int) $waiting['registration_id'] );
				if ( ! $row || 'waitlisted' !== $row['status'] || (int) $row['event_post_id'] !== (int) $bucket['event_post_id'] ) {
					throw new RuntimeException( 'FIFO queue state changed.' );
				}
				if ( $this->capacity->requires_verification( $scope, (int) $bucket['event_post_id'] ) && null === $row['email_verified_at'] ) {
					throw new RuntimeException( 'Queue head requires verification.' );
				}
				$this->states->assert_transition( 'waitlisted', 'offered' );
				$token   = bin2hex( random_bytes( 32 ) );
				$offer   = PublicId::generate();
				$expires = gmdate( 'Y-m-d H:i:s', strtotime( $utc_now . ' UTC +48 hours' ) );
				$this->queue->hold( $scope, (int) $bucket['id'], (int) $waiting['id'], (int) $row['id'], $offer, hash( 'sha256', $token, true ), $utc_now, $expires );
				$this->queue->transition( $scope, (int) $row['id'], 'waitlisted', 'offered', $command, $actor->user_id, $utc_now, $correlation );
				$this->record( $scope, $actor, $this->resource( $scope, $row ), 'registration.offered', PublicId::from_binary( $row['public_id'] ), 'offered', $correlation );
				return array(
					'public_id' => $offer->to_string(),
					'token'     => $token,
				);
			}
		);
	}

	/**
	 * Accept a nonexpired offer with both token possession and actor policy.
	 *
	 * @param Actor         $actor       Current account or valid delegate.
	 * @param OrgScope      $scope       Trusted organization.
	 * @param PublicId      $offer_id    Opaque offer identity.
	 * @param string        $token       64-digit bearer secret, never logged.
	 * @param PublicId      $command     Idempotent command UUID.
	 * @param string        $utc_now     UTC acceptance time.
	 * @param CorrelationId $correlation Trace identity.
	 * @throws InvalidArgumentException For an invalid token format.
	 * @throws RuntimeException When scope, token or status is no longer valid.
	 */
	public function accept_offer( Actor $actor, OrgScope $scope, PublicId $offer_id, string $token, PublicId $command, string $utc_now, CorrelationId $correlation ): void {
		if ( ! preg_match( '/^[a-f0-9]{64}$/D', $token ) ) {
			throw new InvalidArgumentException( 'Invalid offer token.' );
		}
		$bucket_id = $this->queue->bucket_for_offer( $scope, $offer_id );
		if ( null === $bucket_id ) {
			throw new RuntimeException( 'Offer unavailable.' );
		}
		$this->tx->run(
			function () use ( $actor, $scope, $offer_id, $token, $command, $utc_now, $correlation, $bucket_id ): void {
				$bucket = $this->queue->lock_bucket( $scope, $bucket_id );
				$offer  = $this->queue->offer( $scope, $offer_id );
				if ( ! $bucket || ! $offer || 'offered' !== $offer['status'] || $utc_now >= $offer['expires_at'] ) {
					throw new RuntimeException( 'Offer expired or unavailable.' );
				}
				if ( ! hash_equals( $offer['token_hash'], hash( 'sha256', $token, true ) ) ) {
					throw new RuntimeException( 'Invalid offer token.' );
				}
				$row = $this->queue->registration( $scope, (int) $offer['registration_id'] );
				if ( ! $row || 'offered' !== $row['status'] || (int) $row['event_post_id'] !== (int) $bucket['event_post_id'] ) {
					throw new RuntimeException( 'Registration offer is no longer active.' );
				}
				if ( ! $this->policy->can( $actor, 'registration.create', $this->resource( $scope, $row ) )->allowed ) {
					throw new RuntimeException( 'Offer acceptance is not authorized.' );
				}
				$this->states->assert_transition( 'offered', 'accepted' );
				$this->queue->accept( $scope, $offer, $utc_now );
				$this->queue->transition( $scope, (int) $row['id'], 'offered', 'accepted', $command, $actor->user_id, $utc_now, $correlation );
				$this->record( $scope, $actor, $this->resource( $scope, $row ), 'registration.offer_accepted', PublicId::from_binary( $row['public_id'] ), 'accepted', $correlation );
			}
		);
	}

	/**
	 * Expire one held offer using the bucket lock and restore queue order.
	 *
	 * @param Actor         $actor       Authorized capacity manager.
	 * @param OrgScope      $scope       Trusted organization.
	 * @param PublicId      $offer_id    Offer public identity.
	 * @param PublicId      $command     Idempotent expiry command.
	 * @param string        $utc_now     Trusted UTC time.
	 * @param CorrelationId $correlation Trace identity.
	 * @return bool False when already expired or acknowledged.
	 */
	public function expire_offer( Actor $actor, OrgScope $scope, PublicId $offer_id, PublicId $command, string $utc_now, CorrelationId $correlation ): bool {
		$bucket_id = $this->queue->bucket_for_offer( $scope, $offer_id );
		if ( null === $bucket_id ) {
			return false;
		}
		return $this->tx->run(
			function () use ( $actor, $scope, $offer_id, $command, $utc_now, $correlation, $bucket_id ): bool {
				$bucket = $this->queue->lock_bucket( $scope, $bucket_id );
				if ( ! $bucket ) {
					return false;
				}
				$event = new PolicyObject( $scope->id, 'event', (int) $bucket['event_post_id'], null, (int) $bucket['event_post_id'] );
				if ( ! $this->policy->can( $actor, 'capacity.manage', $event )->allowed ) {
					throw new RuntimeException( 'Offer expiry is not authorized.' );
				}
				$offer = $this->queue->offer( $scope, $offer_id );
				if ( ! $offer || 'offered' !== $offer['status'] || $utc_now < $offer['expires_at'] ) {
					return false;
				}
				$row = $this->queue->registration( $scope, (int) $offer['registration_id'] );
				if ( ! $row || 'offered' !== $row['status'] ) {
					throw new RuntimeException( 'Offer and registration are inconsistent.' );
				}
				$this->states->assert_transition( 'offered', 'waitlisted' );
				$this->queue->expire( $scope, $offer, $utc_now );
				$this->queue->transition( $scope, (int) $row['id'], 'offered', 'waitlisted', $command, $actor->user_id, $utc_now, $correlation );
				$this->record( $scope, $actor, $this->resource( $scope, $row ), 'registration.offer_expired', PublicId::from_binary( $row['public_id'] ), 'waitlisted', $correlation );
				return true;
			}
		);
	}

	/**
	 * Resolve the locked registration after a trusted bucket lookup.
	 *
	 * @param OrgScope $scope        Trusted organization.
	 * @param PublicId $registration Registration public ID.
	 * @param int      $bucket       Bucket already locked.
	 * @return array<string, mixed>
	 * @throws RuntimeException When no matching registration is available.
	 */
	private function registration_from_public( OrgScope $scope, PublicId $registration, int $bucket ): array {
		$rows = $this->queue->registration_id( $scope, $registration );
		$row  = null !== $rows ? $this->queue->registration( $scope, $rows ) : null;
		if ( ! $row || $this->queue->bucket_for_registration( $scope, $registration ) !== $bucket ) {
			throw new RuntimeException( 'Registration was not in the locked bucket.' );
		}
		return $row;
	}

	/**
	 * Construct a policy object without exposing internal IDs in public DTOs.
	 *
	 * @param OrgScope             $scope Trusted organization.
	 * @param array<string, mixed> $row   Scoped registration row.
	 * @return PolicyObject
	 */
	private function resource( OrgScope $scope, array $row ): PolicyObject {
		return new PolicyObject( $scope->id, 'registration', (int) $row['id'], (int) $row['person_id'], (int) $row['event_post_id'] );
	}

	/**
	 * Persist audit and outbox records within the current transaction.
	 *
	 * @param OrgScope      $scope       Trusted organization.
	 * @param Actor         $actor       Authorized acting user.
	 * @param PolicyObject  $domain_object    Current registration reference.
	 * @param string        $action      Stable event name.
	 * @param PublicId      $registration Registration public UUID.
	 * @param string        $new_status  Business result.
	 * @param CorrelationId $correlation Trace identifier.
	 */
	private function record( OrgScope $scope, Actor $actor, PolicyObject $domain_object, string $action, PublicId $registration, string $new_status, CorrelationId $correlation ): void {
		$event = PublicId::generate();
		$this->audit->append( $scope, $actor, $action, $domain_object, 'success', $correlation, $event, array( 'new_status' => $new_status ) );
		$this->outbox->append(
			$scope,
			$event,
			'registration',
			$domain_object->id,
			$action,
			$correlation,
			array(
				'public_id'  => $registration->to_string(),
				'new_status' => $new_status,
			)
		);
	}
}
