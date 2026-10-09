<?php
/**
 * Private export job lifecycle with fresh authorization at every boundary.
 *
 * @package UOP
 */

namespace UOP\Application\Export;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyObject;
use UOP\Application\Policy\PolicyService;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Core\TransactionManager;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\AuditWriter;
use UOP\Infrastructure\Database\ExportJobRepository;
use UOP\Infrastructure\Database\OutboxRepository;
use UOP\Infrastructure\Export\ExportStorageInterface;

/** M5-07: no unscoped job access, untrusted file URL or reuse of revoked rights. */
final class ExportJobService {
	/**
	 * Bind policy, database, immutable renderer and protected storage.
	 *
	 * @param ExportJobRepository    $jobs Durable job metadata.
	 * @param PersonExportGenerator  $generator Allowlisted CSV rendering.
	 * @param ExportStorageInterface $storage Server-only private file driver.
	 * @param PolicyService          $policy Live authorization.
	 * @param TransactionManager     $tx Database atomicity.
	 * @param AuditWriter            $audit Non-sensitive audit evidence.
	 * @param OutboxRepository       $outbox Token-free durable handoff.
	 */
	public function __construct(
		private ExportJobRepository $jobs,
		private PersonExportGenerator $generator,
		private ExportStorageInterface $storage,
		private PolicyService $policy,
		private TransactionManager $tx,
		private AuditWriter $audit,
		private OutboxRepository $outbox
	) {}

	/**
	 * Create a 24-hour export command. An exact replay does not create another job.
	 *
	 * @param Actor         $actor Authenticated exporting user.
	 * @param OrgScope      $scope Organization.
	 * @param PublicId      $command Client-generated idempotency UUID.
	 * @param array         $columns Safe columns.
	 * @param string        $status Optional status filter.
	 * @param string        $now UTC timestamp.
	 * @param CorrelationId $correlation Audit trace.
	 * @phpstan-param list<string> $columns
	 * @return PublicId Private job identity, not a file URL.
	 * @throws InvalidArgumentException When the input contract is invalid.
	 * @throws RuntimeException When authorization or idempotency is denied.
	 */
	public function request( Actor $actor, OrgScope $scope, PublicId $command, array $columns, string $status, string $now, CorrelationId $correlation ): PublicId {
		$this->validate( $columns, $status );
		$expiry = self::expiry( $now );
		return $this->tx->run(
			function () use ( $actor, $scope, $command, $columns, $status, $now, $expiry, $correlation ): PublicId {
				$this->authorize( $actor, $scope );
				$previous = $this->jobs->by_command( $scope, $command );
				if ( $previous ) {
					if ( (int) $actor->user_id !== (int) $previous['actor_user_id']
						|| 'persons' !== (string) $previous['resource_type']
						|| 'csv' !== (string) $previous['format']
						|| json_decode( (string) $previous['filters_json'], true, 8, JSON_THROW_ON_ERROR ) !== array( 'status' => $status )
						|| json_decode( (string) $previous['columns_json'], true, 8, JSON_THROW_ON_ERROR ) !== $columns ) {
						throw new RuntimeException( 'Export command cannot be reused with different parameters.' );
					}
					return PublicId::from_binary( $previous['public_id'] );
				}
				$id = PublicId::generate();
				$this->jobs->create( $scope, $id, $command, $actor->user_id, 'persons', 'csv', array( 'status' => $status ), $columns, $now, $expiry );
				$stored = $this->jobs->find( $scope, $id );
				if ( ! $stored ) {
					throw new RuntimeException( 'Export job could not be reloaded.' );
				}
				$event  = PublicId::generate();
				$object = new PolicyObject( $scope->id, 'organization', $scope->id );
				$this->audit->append( $scope, $actor, 'export.queued', $object, 'success', $correlation, $event );
				$this->outbox->append( $scope, $event, 'export', (int) $stored['id'], 'export.queued', $correlation, array( 'public_id' => $id->to_string() ) );
				$this->tx->after_commit(
					static function () use ( $scope, $id ): void {
						if ( function_exists( 'as_enqueue_async_action' ) ) {
							as_enqueue_async_action( 'uop_generate_export', array( $scope->id, $id->to_string() ), 'uop', true );
						}
					}
				);
				return $id;
			}
		);
	}

	/**
	 * Produce a minimal status DTO without filesystem keys or private error details.
	 *
	 * @param Actor    $actor Requester.
	 * @param OrgScope $scope Tenant.
	 * @param PublicId $job Job UUID.
	 * @param string   $now Trusted UTC timestamp.
	 * @return array{public_id:string,status:string,row_count:int|null,expires_at:string,error_code:string|null}
	 * @throws RuntimeException When the job is invisible or access revoked.
	 */
	public function status( Actor $actor, OrgScope $scope, PublicId $job, string $now ): array {
		$row = $this->owned( $actor, $scope, $job );
		return array(
			'public_id'  => $job->to_string(),
			'status'     => (string) $row['expires_at'] <= $now ? 'expired' : (string) $row['status'],
			'row_count'  => null === $row['row_count'] ? null : (int) $row['row_count'],
			'expires_at' => (string) $row['expires_at'],
			'error_code' => null === $row['error_code'] ? null : (string) $row['error_code'],
		);
	}

	/**
	 * Claim, re-authorize and generate only one queued export.
	 *
	 * @param OrgScope $scope Tenant queue argument.
	 * @param PublicId $job Public job UUID from trusted scheduler.
	 * @param string   $now Trusted UTC timestamp.
	 * @throws RuntimeException When export generation fails safely.
	 */
	public function process( OrgScope $scope, PublicId $job, string $now ): void {
		$claim = $this->tx->run(
			function () use ( $scope, $job, $now ): ?array {
				$row = $this->jobs->find( $scope, $job, true );
				if ( ! $row || 'queued' !== $row['status'] || (string) $row['expires_at'] <= $now ) {
					return null;
				}
				$actor = new Actor( (int) $row['actor_user_id'] );
				if ( ! $this->permitted( $actor, $scope ) ) {
					$this->jobs->claim( $scope, $job, $now );
					$this->jobs->fail( $scope, $job, 'authorization_revoked', $now );
					return null;
				}
				return $this->jobs->claim( $scope, $job, $now );
			}
		);
		if ( null === $claim ) {
			return;
		}
		$key = null;
		try {
			$actor = new Actor( (int) $claim['actor_user_id'] );
			$this->authorize( $actor, $scope );
			$filters = json_decode( (string) $claim['filters_json'], true, 8, JSON_THROW_ON_ERROR );
			$columns = json_decode( (string) $claim['columns_json'], true, 8, JSON_THROW_ON_ERROR );
			if ( ! is_array( $filters ) || ! is_string( $filters['status'] ?? null ) || ! is_array( $columns ) ) {
				throw new RuntimeException( 'Malformed stored export request.' );
			}
			$this->validate( $columns, $filters['status'] );
			$payload = $this->generator->generate( $actor, $scope, $columns, $filters['status'] );
			$this->authorize( $actor, $scope );
			$key = $this->storage->write( $payload['body'] );
			$this->tx->run(
				function () use ( $scope, $job, $key, $payload, $now ): void {
					$stored = $this->jobs->find( $scope, $job, true );
					if ( ! $stored || ! $this->permitted( new Actor( (int) $stored['actor_user_id'] ), $scope ) ) {
						throw new RuntimeException( 'Export authorization was revoked before completion.' );
					}
					$this->jobs->complete( $scope, $job, $key, hash( 'sha256', $payload['body'], true ), $payload['count'], $now );
				}
			);
		} catch ( Throwable ) {
			if ( null !== $key ) {
				$this->storage->delete( $key );
			}
			$this->tx->run( fn () => $this->jobs->fail( $scope, $job, 'generation_failed', $now ) );
			throw new RuntimeException( 'Private export generation failed.' );
		}
	}

	/**
	 * Read only the authorized exact bytes; never expose the storage path.
	 *
	 * @param Actor    $actor Requesting user.
	 * @param OrgScope $scope Tenant.
	 * @param PublicId $job Public job UUID.
	 * @param string   $now Trusted UTC time.
	 * @return string Authorized and hash-verified CSV bytes.
	 * @throws RuntimeException For expired, corrupt, unfinished or revoked exports.
	 */
	public function download( Actor $actor, OrgScope $scope, PublicId $job, string $now ): string {
		$row = $this->owned( $actor, $scope, $job );
		if ( 'ready' !== $row['status'] || (string) $row['expires_at'] <= $now || null === $row['storage_key'] || null === $row['content_sha256'] ) {
			throw new RuntimeException( 'Export is not available for download.' );
		}
		$bytes = $this->storage->read( (string) $row['storage_key'] );
		if ( ! hash_equals( (string) $row['content_sha256'], hash( 'sha256', $bytes, true ) ) ) {
			throw new RuntimeException( 'Private export checksum mismatch.' );
		}
		$this->tx->run(
			function () use ( $actor, $scope, $job, $now ): void {
				$latest = $this->owned( $actor, $scope, $job, true );
				if ( 'ready' !== $latest['status'] || (string) $latest['expires_at'] <= $now ) {
					throw new RuntimeException( 'Export expired or access changed.' );
				}
				$this->jobs->downloaded( $scope, $job, $now );
			}
		);
		return $bytes;
	}

	/**
	 * Delete expired files before clearing references in bounded batches.
	 *
	 * @param OrgScope $scope Tenant boundary.
	 * @param string   $now Trusted UTC time.
	 * @return int Number of expired jobs processed.
	 * @throws RuntimeException When deletion fails.
	 */
	public function cleanup( OrgScope $scope, string $now ): int {
		$rows = $this->jobs->expired( $scope, $now );
		foreach ( $rows as $row ) {
			if ( null !== $row['storage_key'] ) {
				$this->storage->delete( (string) $row['storage_key'] );
			}
			$this->tx->run( fn () => $this->jobs->expire( $scope, (int) $row['id'], $now ) );
		}
		return count( $rows );
	}

	/**
	 * Resolve live access and the original owner, not merely the job UUID.
	 *
	 * @param Actor    $actor Authenticated user.
	 * @param OrgScope $scope Tenant.
	 * @param PublicId $job Public job UUID.
	 * @param bool     $lock Lock the job during a DB transaction.
	 * @return array<string,mixed> Job internal row.
	 * @throws RuntimeException When missing or unauthorized.
	 */
	private function owned( Actor $actor, OrgScope $scope, PublicId $job, bool $lock = false ): array {
		$row = $this->jobs->find( $scope, $job, $lock );
		if ( ! $row || (int) $row['actor_user_id'] !== $actor->user_id || ! $this->permitted( $actor, $scope ) ) {
			throw new RuntimeException( 'Export job is unavailable.' );
		}
		return $row;
	}

	/**
	 * Fail closed using the same tenant policy at each boundary.
	 *
	 * @param Actor    $actor Current actor.
	 * @param OrgScope $scope Tenant.
	 * @return bool
	 */
	private function permitted( Actor $actor, OrgScope $scope ): bool {
		return $this->policy->can( $actor, 'export.create', new PolicyObject( $scope->id, 'organization', $scope->id ) )->allowed;
	}

	/**
	 * Reject all unknown filters, duplicate/hidden columns and broad data projections.
	 *
	 * @param array  $columns Selected columns.
	 * @param string $status Exact status filter or empty.
	 * @phpstan-param list<string> $columns
	 * @throws InvalidArgumentException For non-allowlisted input.
	 */
	private function validate( array $columns, string $status ): void {
		CsvExportSchema::columns( $columns );
		if ( ! in_array( $status, array( '', 'active' ), true ) ) {
			throw new InvalidArgumentException( 'Export projection is not allowed.' );
		}
	}

	/**
	 * Require permission before scheduling.
	 *
	 * @param Actor    $actor Actor.
	 * @param OrgScope $scope Tenant.
	 * @throws RuntimeException For denied exports.
	 */
	private function authorize( Actor $actor, OrgScope $scope ): void {
		if ( ! $this->permitted( $actor, $scope ) ) {
			throw new RuntimeException( 'Export permission is denied.' );
		}
	}

	/**
	 * Compute a strict 24-hour expiry independent of server locale.
	 *
	 * @param string $now Trusted UTC command timestamp.
	 * @return string UTC deadline.
	 * @throws InvalidArgumentException For invalid timestamps.
	 */
	private static function expiry( string $now ): string {
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $now, new DateTimeZone( 'UTC' ) );
		if ( false === $date || $date->format( 'Y-m-d H:i:s' ) !== $now ) {
			throw new InvalidArgumentException( 'Invalid export timestamp.' );
		}
		return $date->modify( '+24 hours' )->format( 'Y-m-d H:i:s' );
	}
}
