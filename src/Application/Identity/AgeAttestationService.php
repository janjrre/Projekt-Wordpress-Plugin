<?php
/**
 * Independent staff review of event-specific age threshold evidence.
 *
 * @package UOP
 */

namespace UOP\Application\Identity;

use InvalidArgumentException;
use RuntimeException;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyObject;
use UOP\Application\Policy\PolicyService;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Core\TransactionManager;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\AgeAttestationRepository;
use UOP\Infrastructure\Database\AuditWriter;
use UOP\Infrastructure\Database\Connection;
use UOP\Infrastructure\Database\OutboxRepository;
use UOP\Infrastructure\Database\PersonRepository;

/** A review never creates a guardian grant or consent evidence. */
final class AgeAttestationService {
	/** Fixed, non-document metadata, without identity numbers or uploads. */
	private const METHODS = array( 'in_person_review', 'trusted_register', 'authorized_offline_review' );

	/**
	 * Compose the independent reviewer service and auditable persistence.
	 *
	 * @param AgeAttestationRepository $evidence Event-specific append-only records.
	 * @param PersonRepository         $people Active tenant-scoped people.
	 * @param Connection               $db Database.
	 * @param string                   $prefix Valid site prefix.
	 * @param PolicyService            $policy Current permissions.
	 * @param TransactionManager       $tx Transactions.
	 * @param AuditWriter              $audit Audit.
	 * @param OutboxRepository         $outbox Outbox.
	 */
	public function __construct(
		private AgeAttestationRepository $evidence,
		private PersonRepository $people,
		private Connection $db,
		private string $prefix,
		private PolicyService $policy,
		private TransactionManager $tx,
		private AuditWriter $audit,
		private OutboxRepository $outbox
	) {}

	/**
	 * Append a verified threshold outcome or an immediate revocation tombstone.
	 *
	 * @param Actor         $reviewer Independent authorized staff member.
	 * @param OrgScope      $scope Tenant.
	 * @param PublicId      $subject Subject UUID, never accepted as an integer.
	 * @param int           $event_post Published and scoped event.
	 * @param int           $threshold Integer age threshold.
	 * @param string        $outcome 'gte' or 'lt' (last verified class on revocation).
	 * @param string        $method Fixed verification method.
	 * @param string        $valid_until UTC datetime expiry.
	 * @param bool          $checked Explicit actual evidence-check acknowledgement.
	 * @param bool          $revoke Revoke instead of verify.
	 * @param string        $now Current UTC instant.
	 * @param CorrelationId $correlation Correlation reference.
	 * @return PublicId Newly appended evidence UUID.
	 * @throws InvalidArgumentException For invalid attestation details.
	 * @throws RuntimeException For denied, stale or out-of-scope reviews.
	 */
	public function decide( Actor $reviewer, OrgScope $scope, PublicId $subject, int $event_post, int $threshold, string $outcome, string $method, string $valid_until, bool $checked, bool $revoke, string $now, CorrelationId $correlation ): PublicId {
		if ( $reviewer->user_id < 1 || ! in_array( $outcome, array( 'gte', 'lt' ), true ) || $threshold < 1 || $threshold > 120
			|| ( ! $revoke && ( ! $checked || ! in_array( $method, self::METHODS, true ) ) ) ) {
			throw new InvalidArgumentException( 'An independent age review and supported evidence method are required.' );
		}
		$expires     = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $valid_until, new \DateTimeZone( 'UTC' ) );
		$review_date = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $now, new \DateTimeZone( 'UTC' ) );
		if ( ! $expires || $expires->format( 'Y-m-d H:i:s' ) !== $valid_until || ! $review_date
			|| $review_date->format( 'Y-m-d H:i:s' ) !== $now || ( ! $revoke && ( $valid_until <= $now || $expires->getTimestamp() > $review_date->getTimestamp() + 366 * DAY_IN_SECONDS ) ) ) {
			throw new InvalidArgumentException( 'Age attestation expiry must be bounded and after its review.' );
		}
		return $this->tx->run(
			function () use ( $reviewer, $scope, $subject, $event_post, $threshold, $outcome, $method, $valid_until, $checked, $revoke, $now, $correlation ): PublicId {
				$person = $this->people->find( $scope, $subject );
				$own    = $this->people->by_user( $scope, $reviewer->user_id );
				if ( ! $person || 'active' !== $person['status'] || ( $own && (int) $own['id'] === (int) $person['id'] ) ) {
					throw new RuntimeException( 'Age reviewer cannot approve self or an unavailable person.' );
				}
				$event = $this->db->rows(
					"SELECT event_post_id FROM %i WHERE organization_id = %d AND event_post_id = %d AND status = 'active' LIMIT 1 FOR UPDATE",
					array( $this->prefix . 'event_settings', $scope->id, $event_post )
				);
				$post  = get_post( $event_post );
				if ( ! $event || ! $post || 'uop_event' !== $post->post_type || 'publish' !== $post->post_status ) {
					throw new RuntimeException( 'Age attestation event is unavailable or unpublished.' );
				}
				$object = new PolicyObject( $scope->id, 'event', $event_post, (int) $person['id'], $event_post );
				$org    = new PolicyObject( $scope->id, 'organization', $scope->id );
				if ( ! user_can( $reviewer->user_id, 'edit_post', $event_post )
					|| ! $this->policy->can( $reviewer, 'organization.manage', $org )->allowed
					|| ! $this->policy->can( $reviewer, 'event.manage', $object )->allowed ) {
					throw new RuntimeException( 'Event age review requires manager permissions.' );
				}
				$prior = $this->evidence->latest( $scope, (int) $person['id'], $event_post, $threshold );
				if ( $revoke && ( ! $prior || 'verified' !== $prior['status'] ) ) {
					throw new RuntimeException( 'Only the current verified age class can be revoked.' );
				}
				if ( $revoke && $outcome !== $prior['outcome'] ) {
					throw new RuntimeException( 'Revocation must target the currently verified class.' );
				}
				$uuid     = PublicId::generate();
				$status   = $revoke ? 'revoked' : 'verified';
				$id       = $this->evidence->append(
					$scope,
					$uuid,
					(int) $person['id'],
					$event_post,
					$threshold,
					$outcome,
					$status,
					$reviewer->user_id,
					$revoke ? 'revoked' : $method,
					$now,
					$revoke ? $now : $valid_until
				);
				$event_id = PublicId::generate();
				$this->audit->append( $scope, $reviewer, 'age.' . $status, $object, 'success', $correlation, $event_id, array( 'reason_code' => $revoke ? 'revoked' : 'offline_evidence_checked' ) );
				$this->outbox->append( $scope, $event_id, 'person', (int) $person['id'], 'age.' . $status, $correlation, array( 'public_id' => $uuid->to_string() ) );
				return $uuid;
			}
		);
	}
}
