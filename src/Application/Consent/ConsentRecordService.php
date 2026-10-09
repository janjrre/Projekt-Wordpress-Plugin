<?php
/**
 * Authoritative consent decisions and explicit, append-only withdrawal.
 *
 * @package UOP
 */

namespace UOP\Application\Consent;

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
use UOP\Infrastructure\Database\ConsentRecordRepository;
use UOP\Infrastructure\Database\ConsentRepository;
use UOP\Infrastructure\Database\OutboxRepository;

/**
 * Registration submissions call record_submission inside their existing
 * transaction. Withdrawal owns a new transaction and never edits old evidence.
 */
final class ConsentRecordService {
	/**
	 * Compose immutable evidence and the authoritative security boundary.
	 *
	 * @param ConsentRecordRepository $records Consent persistence.
	 * @param ConsentRepository       $documents Immutable published versions.
	 * @param PolicyService           $policy Current object authorizations.
	 * @param TransactionManager      $tx     Transactional commands.
	 * @param AuditWriter             $audit  Minimal audit log.
	 * @param OutboxRepository        $outbox Durable domain events.
	 */
	public function __construct(
		private ConsentRecordRepository $records,
		private ConsentRepository $documents,
		private PolicyService $policy,
		private TransactionManager $tx,
		private AuditWriter $audit,
		private OutboxRepository $outbox
	) {}

	/**
	 * Append evidence for one field in a newly inserted registration.
	 *
	 * Only an owning RegistrationService calls this inside the same DB
	 * transaction as the registration and its canonical immutable snapshot.
	 * No unbound client-defined document ID, actor context or policy is trusted.
	 *
	 * @param Actor         $actor        WordPress actor, or guest zero.
	 * @param OrgScope      $scope        Trusted organization.
	 * @param int           $person_id    Already scoped subject.
	 * @param int           $event_post   Trusted published event ID.
	 * @param int           $registration Newly inserted registration ID.
	 * @param PublicId      $version      Immutable version pinned at form publish.
	 * @param bool          $decision     Explicit yes or no.
	 * @param bool          $required     True only for compulsory consent.
	 * @param bool          $guest        Server-validated guest path.
	 * @param string        $now          UTC evidence timestamp.
	 * @param CorrelationId $correlation  Command correlation.
	 * @return array{evidence:array{record_public_id:string,definition_key:string,version:int,version_public_id:string,decision:string},reference_id:int}
	 * @throws RuntimeException When document, policy or compulsory consent is invalid.
	 */
	public function record_submission( Actor $actor, OrgScope $scope, int $person_id, int $event_post, int $registration, PublicId $version, bool $decision, bool $required, bool $guest, string $now, CorrelationId $correlation ): array {
		if ( $required && ! $decision ) {
			throw new RuntimeException( 'Required consent was not granted.' );
		}
		$document = $this->documents->version( $scope, $version );
		if ( ! $document ) {
			throw new RuntimeException( 'Published consent document unavailable.' );
		}
		$object  = new PolicyObject( $scope->id, 'registration', $registration, $person_id, $event_post );
		$context = 'guest';
		if ( $guest ) {
			if ( 0 !== $actor->user_id ) {
				throw new RuntimeException( 'Invalid anonymous consent actor.' );
			}
		} else {
			$decision_policy = $this->policy->can( $actor, 'registration.create', $object );
			if ( ! $decision_policy->allowed ) {
				throw new RuntimeException( 'Consent decision access denied.' );
			}
			$context = self::context( $decision_policy->reason );
		}
		$uuid  = PublicId::generate();
		$value = $decision ? 'granted' : 'denied';
		$id    = $this->records->record_submission(
			$scope,
			$uuid,
			(int) $document['id'],
			$person_id,
			$actor->user_id,
			$registration,
			$value,
			$context,
			$now
		);
		$event = PublicId::generate();
		$this->audit->append( $scope, $actor, 'consent.decided', $object, 'success', $correlation, $event );
		$this->outbox->append( $scope, $event, 'consent', $id, 'consent.decided', $correlation, array( 'public_id' => $uuid->to_string() ) );
		return array(
			'evidence'     => array(
				'record_public_id'  => $uuid->to_string(),
				'definition_key'    => (string) $document['consent_key'],
				'version'           => (int) $document['version'],
				'version_public_id' => $version->to_string(),
				'decision'          => $value,
			),
			'reference_id' => $id,
		);
	}

	/**
	 * Withdraw one current granted consent without changing its historic snapshot.
	 *
	 * Replaying the same withdrawal UUID is idempotent. A second, different
	 * withdrawal of an already superseded record is rejected.
	 *
	 * @param Actor         $actor       Current authenticated owner or delegate.
	 * @param OrgScope      $scope       Trusted organization.
	 * @param PublicId      $record      Current granted evidence UUID.
	 * @param PublicId      $withdrawal  Unique command and new evidence UUID.
	 * @param string        $now         UTC decision timestamp.
	 * @param CorrelationId $correlation Request trace.
	 * @return PublicId Newly created superseding record public ID.
	 * @throws RuntimeException On stale, unauthorized or ineligible withdrawal.
	 */
	public function withdraw( Actor $actor, OrgScope $scope, PublicId $record, PublicId $withdrawal, string $now, CorrelationId $correlation ): PublicId {
		if ( $actor->user_id < 1 || $record->to_string() === $withdrawal->to_string() ) {
			throw new RuntimeException( 'Invalid consent withdrawal actor or identity.' );
		}
		return $this->tx->run(
			function () use ( $actor, $scope, $record, $withdrawal, $now, $correlation ): PublicId {
				$old = $this->records->lock_record( $scope, $record );
				if ( ! $old || 'granted' !== $old['decision'] || null !== $old['supersedes_record_id']
					|| (int) ( $old['event_post_id'] ?? 0 ) < 1 ) {
					throw new RuntimeException( 'Current consent evidence unavailable.' );
				}
				$object     = new PolicyObject( $scope->id, 'registration', (int) $old['registration_id'], (int) $old['subject_person_id'], (int) $old['event_post_id'] );
				$permission = $this->policy->can( $actor, 'registration.cancel', $object );
				if ( ! $permission->allowed ) {
					throw new RuntimeException( 'Consent withdrawal access denied.' );
				}
				$successor = $this->records->successor( $scope, (int) $old['id'] );
				if ( $successor ) {
					if ( hash_equals( (string) $successor['public_id'], $withdrawal->to_binary() ) && 'withdrawn' === $successor['decision'] ) {
						return $withdrawal;
					}
					throw new RuntimeException( 'Consent grant has already been superseded.' );
				}
				$context = self::context( $permission->reason );
				$id      = $this->records->withdraw( $scope, $withdrawal, $old, $actor->user_id, $context, $now );
				$event   = PublicId::generate();
				$this->audit->append( $scope, $actor, 'consent.withdrawn', $object, 'success', $correlation, $event );
				$this->outbox->append( $scope, $event, 'consent', $id, 'consent.withdrawn', $correlation, array( 'public_id' => $withdrawal->to_string() ) );
				return $withdrawal;
			}
		);
	}

	/**
	 * Translate policy results into the canonical evidence actor context.
	 *
	 * @param string $reason Trusted PolicyService allow reason.
	 * @return string
	 * @throws InvalidArgumentException For unknown authorization modes.
	 */
	private static function context( string $reason ): string {
		return match ( $reason ) {
			'ALLOW_SELF'           => 'self',
			'ALLOW_DELEGATION'     => 'delegate',
			'ALLOW_ORG_ASSIGNMENT' => 'manager',
			default                => throw new InvalidArgumentException( 'Unknown consent authorization context.' ),
		};
	}
}
