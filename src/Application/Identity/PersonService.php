<?php
/**
 * Audited organization-scoped person commands.
 *
 * @package UOP
 */

namespace UOP\Application\Identity;

use RuntimeException;
use InvalidArgumentException;
use UOP\Application\Policy\FieldDefinition;
use UOP\Application\Event\PostCommitPublisher;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyObject;
use UOP\Application\Policy\PolicyService;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Core\TransactionManager;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\AuditWriter;
use UOP\Infrastructure\Database\OutboxRepository;
use UOP\Infrastructure\Database\PersonRepository;

/** Person records have no implicit account from an email address. */
final class PersonService {
	/**
	 * Bind scoped infrastructure and policy.
	 *
	 * @param PersonRepository    $people      Person persistence.
	 * @param PolicyService       $policy      Authorization boundary.
	 * @param TransactionManager  $tx          Transaction boundary.
	 * @param AuditWriter         $audit       Durable audit log.
	 * @param OutboxRepository    $outbox      Durable outbox.
	 * @param PostCommitPublisher $publisher   Best-effort public hooks.
	 */
	public function __construct(
		private PersonRepository $people,
		private PolicyService $policy,
		private TransactionManager $tx,
		private AuditWriter $audit,
		private OutboxRepository $outbox,
		private PostCommitPublisher $publisher
	) {}

	/**
	 * Create an organization-owned person after authoritative policy checks.
	 *
	 * @param Actor         $actor       Request actor.
	 * @param OrgScope      $scope       Trusted organization.
	 * @param string        $name        Validated display name.
	 * @param string|null   $email       Optional contact email, never an identity key.
	 * @param string        $utc_now     UTC timestamp.
	 * @param CorrelationId $correlation Command trace.
	 * @return PublicId
	 * @throws RuntimeException If the actor lacks permission.
	 */
	public function create( Actor $actor, OrgScope $scope, string $name, ?string $email, string $utc_now, CorrelationId $correlation ): PublicId {
		$permission = new PolicyObject( $scope->id, 'organization', $scope->id );
		if ( ! $this->policy->can( $actor, 'person.create', $permission )->allowed ) {
			throw new RuntimeException( 'Person creation not permitted.' );
		}
		$person_uuid = PublicId::generate();
		$event_uuid  = PublicId::generate();
		$this->tx->run(
			function () use ( $actor, $scope, $name, $email, $utc_now, $correlation, $person_uuid, $event_uuid ): void {
				$this->people->create( $scope, $person_uuid, $name, $email, $utc_now );
				$row = $this->people->find( $scope, $person_uuid );
				if ( ! $row ) {
					throw new RuntimeException( 'Created person could not be reloaded.' );
				}
				$object = new PolicyObject( $scope->id, 'person', (int) $row['id'] );
				$this->audit->append( $scope, $actor, 'person.created', $object, 'success', $correlation, $event_uuid );
				$this->outbox->append( $scope, $event_uuid, 'person', (int) $row['id'], 'person.created', $correlation, array( 'public_id' => $person_uuid->to_string() ) );
				$this->publisher->after_commit( 'person.created', $event_uuid, $person_uuid );
			}
		);
		return $person_uuid;
	}

	/**
	 * Rename a person without altering account linkage or contact identity.
	 * The same central field-level policy is used for self/delegated actions.
	 *
	 * @param Actor         $actor       Current actor.
	 * @param OrgScope      $scope       Trusted organization.
	 * @param PublicId      $person      Person UUID.
	 * @param int           $version     Expected optimistic version.
	 * @param string        $name        New human-readable name.
	 * @param string        $utc_now     UTC change timestamp.
	 * @param CorrelationId $correlation Command trace.
	 * @throws InvalidArgumentException When the new name is invalid.
	 * @throws RuntimeException When unauthorized, absent or version-conflicted.
	 */
	public function rename( Actor $actor, OrgScope $scope, PublicId $person, int $version, string $name, string $utc_now, CorrelationId $correlation ): void {
		$name = trim( $name );
		if ( $version < 1 || '' === $name || mb_strlen( $name ) > 191 ) {
			throw new InvalidArgumentException( 'Invalid person revision or name.' );
		}
		$this->tx->run(
			function () use ( $actor, $scope, $person, $version, $name, $utc_now, $correlation ): void {
				$row = $this->people->find( $scope, $person );
				if ( ! $row || 'active' !== $row['status'] ) {
					throw new RuntimeException( 'Person is not available.' );
				}
				$object = new PolicyObject( $scope->id, 'person', (int) $row['id'] );
				$field  = new FieldDefinition( 'display_name', 'personal', true, true, true, true );
				if ( ! $this->policy->can( $actor, 'person.edit', $object, $field )->allowed ) {
					throw new RuntimeException( 'Person update denied.' );
				}
				if ( ! $this->people->rename( $scope, $person, $version, $name, $utc_now ) ) {
					throw new RuntimeException( 'Person revision changed.' );
				}
				$event = PublicId::generate();
				$this->audit->append( $scope, $actor, 'person.updated', $object, 'success', $correlation, $event );
				$this->outbox->append( $scope, $event, 'person', (int) $row['id'], 'person.updated', $correlation, array( 'public_id' => $person->to_string() ) );
			}
		);
	}
}
