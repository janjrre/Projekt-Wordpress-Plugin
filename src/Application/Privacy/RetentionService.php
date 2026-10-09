<?php
/**
 * Authorization, rule validation and transactional retention batch commands.
 *
 * @package UOP
 */

namespace UOP\Application\Privacy;

use DateTimeImmutable;
use DateTimeZone;
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
use UOP\Infrastructure\Database\RetentionRepository;

/** Only explicitly activated safe-class rules can destroy current private data. */
final class RetentionService {
	/**
	 * Fixed, non-interchangeable class, trigger and action contracts.
	 *
	 * @var array<string,array{trigger:string,action:string}>
	 */
	private const TARGETS = array(
		'profile_values'         => array(
			'trigger' => 'person.created',
			'action'  => 'erase',
		),
		'registration_snapshots' => array(
			'trigger' => 'registration.submitted',
			'action'  => 'anonymize',
		),
		'persons'                => array(
			'trigger' => 'person.created',
			'action'  => 'archive',
		),
	);

	/**
	 * Bind authorized application and durable evidence dependencies.
	 *
	 * @param RetentionRepository $rules Retention data store.
	 * @param PolicyService       $policy Live organization permissions.
	 * @param TransactionManager  $tx Atomic batch boundary.
	 * @param AuditWriter         $audit Safe audit entries.
	 * @param OutboxRepository    $outbox Token-free domain events.
	 */
	public function __construct(
		private RetentionRepository $rules,
		private PolicyService $policy,
		private TransactionManager $tx,
		private AuditWriter $audit,
		private OutboxRepository $outbox
	) {}

	/**
	 * Configure a rule without inventing a legal retention period.
	 *
	 * @param Actor         $actor Current manager.
	 * @param OrgScope      $scope Trusted organization.
	 * @param string        $key Stable unique rule key.
	 * @param string        $data_class Supported data class.
	 * @param string        $trigger Allowed lifecycle anchor.
	 * @param int           $delay Delay in whole days (policy-chosen).
	 * @param string        $action Allowed retention action.
	 * @param bool          $enabled Administrator's explicit opt-in.
	 * @param string        $now Trusted UTC timestamp.
	 * @param CorrelationId $correlation Trace without personal values.
	 * @throws InvalidArgumentException For unsupported configuration.
	 */
	public function create_rule( Actor $actor, OrgScope $scope, string $key, string $data_class, string $trigger, int $delay, string $action, bool $enabled, string $now, CorrelationId $correlation ): void {
		if ( ! preg_match( '/^[a-z][a-z0-9_]{0,99}$/D', $key ) || $delay < 0 || $delay > 36500
			|| ! isset( self::TARGETS[ $data_class ] ) || self::TARGETS[ $data_class ]['trigger'] !== $trigger || self::TARGETS[ $data_class ]['action'] !== $action ) {
			throw new InvalidArgumentException( 'Unsupported retention rule.' );
		}
		self::cutoff( $now, $delay );
		$this->tx->run(
			function () use ( $actor, $scope, $key, $data_class, $trigger, $delay, $action, $enabled, $now, $correlation ): void {
				$this->authorize( $actor, $scope );
				$this->rules->create( $scope, $key, $data_class, $trigger, $delay, $action, $enabled, $now );
				$rule = $this->rules->rule( $scope, $key );
				if ( ! $rule ) {
					throw new RuntimeException( 'Retention rule was not persisted.' );
				}
				$event  = PublicId::generate();
				$object = new PolicyObject( $scope->id, 'organization', $scope->id );
				$this->audit->append( $scope, $actor, 'privacy.retention_rule_created', $object, 'success', $correlation, $event );
				$this->outbox->append( $scope, $event, 'person', (int) $rule['id'], 'privacy.retention_rule_created', $correlation, array( 'reason_code' => 'retention' ) );
			}
		);
	}

	/**
	 * Preview a single bounded keyset page; never reveal actual field contents.
	 *
	 * @param Actor    $actor Authorized privacy manager.
	 * @param OrgScope $scope Trusted organization.
	 * @param string   $key Rule key.
	 * @param int      $cursor Previous page's last internal ID.
	 * @param string   $now Trusted UTC time.
	 * @return array{examined:int,eligible:int,held:int,changed:int,next_cursor:int,done:bool,sample:list<string>}
	 * @throws InvalidArgumentException When the supplied cursor is invalid.
	 */
	public function dry_run( Actor $actor, OrgScope $scope, string $key, int $cursor, string $now ): array {
		$this->authorize( $actor, $scope );
		$rule = $this->active_rule( $scope, $key );
		if ( $cursor < 0 ) {
			throw new InvalidArgumentException( 'Invalid retention cursor.' );
		}
		$cutoff = self::cutoff( $now, (int) $rule['delay_days'] );
		$rows   = $this->rules->candidates( $scope, (string) $rule['data_class'], $cutoff, $cursor );
		return $this->summary( $scope, (string) $rule['data_class'], $rows, $cursor, $cutoff, $now, false );
	}

	/**
	 * Mutate at most 25 examined candidates under one durable transaction.
	 *
	 * @param Actor         $actor Current privacy manager.
	 * @param OrgScope      $scope Trusted organization.
	 * @param string        $key Published retention rule key.
	 * @param int           $cursor Last examined key; resume safely from it.
	 * @param string        $now Trusted UTC command timestamp.
	 * @param CorrelationId $correlation One trace for the whole batch.
	 * @return array{examined:int,eligible:int,held:int,changed:int,next_cursor:int,done:bool,sample:list<string>}
	 * @throws InvalidArgumentException When the supplied cursor is invalid.
	 */
	public function run_batch( Actor $actor, OrgScope $scope, string $key, int $cursor, string $now, CorrelationId $correlation ): array {
		if ( $cursor < 0 ) {
			throw new InvalidArgumentException( 'Invalid retention cursor.' );
		}
		return $this->tx->run(
			function () use ( $actor, $scope, $key, $cursor, $now, $correlation ): array {
				$this->authorize( $actor, $scope );
				$rule = $this->active_rule( $scope, $key );
				$data_class = (string) $rule['data_class'];
				$cutoff = self::cutoff( $now, (int) $rule['delay_days'] );
				$rows = $this->rules->candidates( $scope, $data_class, $cutoff, $cursor );
				return $this->summary( $scope, $data_class, $rows, $cursor, $cutoff, $now, true, $actor, $correlation );
			}
		);
	}

	/**
	 * Count and process exactly one bounded page, with hold checks re-evaluated.
	 *
	 * @param OrgScope           $scope Tenant boundary.
	 * @param string             $data_class Fixed retention target.
	 * @param array              $rows Bounded candidate page.
	 * @phpstan-param list<array<string,mixed>> $rows
	 * @param int                $cursor Previous examined key.
	 * @param string             $cutoff Policy cutoff.
	 * @param string             $now UTC time.
	 * @param bool               $execute Whether to mutate.
	 * @param Actor|null         $actor Live actor during mutation.
	 * @param CorrelationId|null $correlation Batch trace.
	 * @return array{examined:int,eligible:int,held:int,changed:int,next_cursor:int,done:bool,sample:list<string>}
	 * @throws RuntimeException When an eligible target could not be changed.
	 */
	private function summary( OrgScope $scope, string $data_class, array $rows, int $cursor, string $cutoff, string $now, bool $execute, ?Actor $actor = null, ?CorrelationId $correlation = null ): array {
		$eligible = 0;
		$held = 0;
		$changed = 0;
		$sample = array();
		foreach ( $rows as $row ) {
			$id = (int) $row['id'];
			$cursor = $id;
			$target = $this->rules->eligible( $scope, $data_class, $id, $cutoff, $now, $execute );
			if ( null === $target ) {
				++$held;
				continue;
			}
			++$eligible;
			if ( count( $sample ) < 5 ) {
				$sample[] = PublicId::from_binary( $row['public_id'] )->to_string();
			}
			if ( ! $execute ) {
				continue;
			}
			if ( null === $actor || null === $correlation ) {
				throw new RuntimeException( 'Retention execution lacks a trusted actor or trace.' );
			}
			$count = $this->rules->apply( $scope, $data_class, $id, $now );
			if ( $count < 1 ) {
				throw new RuntimeException( 'Eligible retention target did not change.' );
			}
			++$changed;
			$object_type = 'registration_snapshots' === $data_class ? 'registration' : 'person';
			$object = new PolicyObject( $scope->id, $object_type, $target['object_id'], $target['subject_id'] );
			$event = PublicId::generate();
			$this->audit->append( $scope, $actor, 'privacy.retention_executed', $object, 'success', $correlation, $event, array( 'reason_code' => 'retention' ) );
			$this->outbox->append( $scope, $event, $object_type, $target['object_id'], 'privacy.retention_executed', $correlation, array( 'reason_code' => 'retention' ) );
		}
		return array(
			'examined' => count( $rows ),
			'eligible' => $eligible,
			'held' => $held,
			'changed' => $changed,
			'next_cursor' => $cursor,
			'done' => count( $rows ) < 25,
			'sample' => $sample,
		);
	}

	/**
	 * Load an operationally enabled and strictly supported rule.
	 *
	 * @param OrgScope $scope Tenant boundary.
	 * @param string   $key Rule key.
	 * @return array<string,mixed>
	 * @throws RuntimeException When missing, disabled or internally corrupt.
	 */
	private function active_rule( OrgScope $scope, string $key ): array {
		$rule = $this->rules->rule( $scope, $key );
		if ( ! $rule || 1 !== (int) $rule['enabled'] ) {
			throw new RuntimeException( 'Retention rule is not enabled.' );
		}
		$data_class = (string) $rule['data_class'];
		if ( ! isset( self::TARGETS[ $data_class ] )
			|| self::TARGETS[ $data_class ]['trigger'] !== $rule['trigger_type']
			|| self::TARGETS[ $data_class ]['action'] !== $rule['action'] ) {
			throw new RuntimeException( 'Retention rule configuration is no longer supported.' );
		}
		return $rule;
	}

	/**
	 * Require current organization-scoped privacy privilege on every command.
	 *
	 * @param Actor    $actor Current authenticated user.
	 * @param OrgScope $scope Organization.
	 * @throws RuntimeException On a denied action.
	 */
	private function authorize( Actor $actor, OrgScope $scope ): void {
		if ( ! $this->policy->can( $actor, 'privacy.manage', new PolicyObject( $scope->id, 'organization', $scope->id ) )->allowed ) {
			throw new RuntimeException( 'Retention management is not permitted.' );
		}
	}

	/**
	 * Derive cutoff only from a bounded valid UTC timestamp and configured day count.
	 *
	 * @param string $now UTC command timestamp.
	 * @param int    $delay Configured retention days.
	 * @return string UTC cutoff timestamp.
	 * @throws InvalidArgumentException On invalid dates.
	 */
	private static function cutoff( string $now, int $delay ): string {
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $now, new DateTimeZone( 'UTC' ) );
		if ( false === $date || $date->format( 'Y-m-d H:i:s' ) !== $now || $delay < 0 || $delay > 36500 ) {
			throw new InvalidArgumentException( 'Invalid retention UTC timestamp or delay.' );
		}
		return $date->modify( '-' . $delay . ' days' )->format( 'Y-m-d H:i:s' );
	}
}
