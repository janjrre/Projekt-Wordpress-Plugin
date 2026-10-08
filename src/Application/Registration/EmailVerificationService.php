<?php
/**
 * Verified-contact token lifecycle for registration identity evidence.
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
use UOP\Infrastructure\Database\AuditWriter;
use UOP\Infrastructure\Database\OutboxRepository;
use UOP\Infrastructure\Database\RegistrationRepository;

/**
 * A verified contact does not link a WP account to a person.
 * Email dispatch and public controllers are intentionally not part of M4.
 */
final class EmailVerificationService {
	/**
	 * Inject scoped storage and authorization.
	 *
	 * @param RegistrationRepository $registrations Owner-bound registration storage.
	 * @param PolicyService          $policy        Live actor permissions.
	 * @param TransactionManager     $tx            Atomic command transactions.
	 * @param AuditWriter            $audit         Safe audit evidence.
	 * @param OutboxRepository       $outbox        Durable event history.
	 */
	public function __construct(
		private RegistrationRepository $registrations,
		private PolicyService $policy,
		private TransactionManager $tx,
		private AuditWriter $audit,
		private OutboxRepository $outbox
	) {}

	/**
	 * Issue an expiring secret for an authorized registration's contact address.
	 *
	 * The result must be passed to a trusted mail dispatcher, not an unaudited
	 * REST response or logged request. A newer challenge invalidates the old.
	 *
	 * @param Actor         $actor        Current authenticated actor.
	 * @param OrgScope      $scope        Trusted organization.
	 * @param PublicId      $registration Public registration identity.
	 * @param string        $utc_now      Trusted UTC clock.
	 * @param CorrelationId $correlation  Request trace.
	 * @return array{email:string,token:string} Private mail-dispatch details.
	 * @throws RuntimeException When contact or policy is unavailable.
	 */
	public function issue( Actor $actor, OrgScope $scope, PublicId $registration, string $utc_now, CorrelationId $correlation ): array {
		return $this->tx->run(
			function () use ( $actor, $scope, $registration, $utc_now, $correlation ): array {
				$row = $this->registrations->lock_registration( $scope, $registration );
				if ( ! $row || ! is_string( $row['contact_email'] ) || ! is_email( $row['contact_email'] ) || null !== $row['email_verified_at'] ) {
					throw new RuntimeException( 'Registration has no unverified contact.' );
				}
				$object = new PolicyObject( $scope->id, 'registration', (int) $row['id'], (int) $row['person_id'], (int) $row['event_post_id'] );
				if ( ! $this->policy->can( $actor, 'registration.create', $object )->allowed
					&& ! $this->policy->can( $actor, 'registration.review', $object )->allowed ) {
					throw new RuntimeException( 'Verification request denied.' );
				}
				$token   = bin2hex( random_bytes( 32 ) );
				$expires = gmdate( 'Y-m-d H:i:s', strtotime( $utc_now . ' UTC +24 hours' ) );
				if ( ! $this->registrations->challenge( $scope, (int) $row['id'], hash( 'sha256', $token, true ), $expires, $utc_now ) ) {
					throw new RuntimeException( 'Verification issuance failed.' );
				}
				$event = PublicId::generate();
				$this->audit->append( $scope, $actor, 'registration.verification_issued', $object, 'success', $correlation, $event );
				$this->outbox->append( $scope, $event, 'registration', (int) $row['id'], 'registration.verification_issued', $correlation, array( 'public_id' => $registration->to_string() ) );
				return array(
					'email' => (string) $row['contact_email'],
					'token' => $token,
				);
			}
		);
	}

	/**
	 * Consume a token once and append atomic evidence without changing status.
	 *
	 * Possession of the random secret verifies email control, not identity or
	 * delegation; no new account or business permission is granted.
	 *
	 * @param OrgScope      $scope        Trusted organization.
	 * @param PublicId      $registration Opaque registration identity.
	 * @param string        $token        Secret from a private verification URL.
	 * @param string        $utc_now      Trusted UTC clock.
	 * @param CorrelationId $correlation  Request trace.
	 * @return bool True only for a fresh, valid token.
	 * @throws InvalidArgumentException For malformed token encoding.
	 */
	public function verify( OrgScope $scope, PublicId $registration, string $token, string $utc_now, CorrelationId $correlation ): bool {
		if ( ! preg_match( '/^[a-f0-9]{64}$/D', $token ) ) {
			throw new InvalidArgumentException( 'Invalid verification token.' );
		}
		return $this->tx->run(
			function () use ( $scope, $registration, $token, $utc_now, $correlation ): bool {
				$row = $this->registrations->lock_registration( $scope, $registration );
				if ( ! $row || null !== $row['email_verified_at'] || null === $row['contact_email'] ) {
					return false;
				}
				if ( ! $this->registrations->verify( $scope, (int) $row['id'], hash( 'sha256', $token, true ), $utc_now ) ) {
					return false;
				}
				$event  = PublicId::generate();
				$object = new PolicyObject( $scope->id, 'registration', (int) $row['id'], (int) $row['person_id'], (int) $row['event_post_id'] );
				$this->audit->append( $scope, new Actor( 0 ), 'registration.email_verified', $object, 'success', $correlation, $event );
				$this->outbox->append( $scope, $event, 'registration', (int) $row['id'], 'registration.email_verified', $correlation, array( 'public_id' => $registration->to_string() ) );
				return true;
			}
		);
	}
}
