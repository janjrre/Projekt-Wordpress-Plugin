<?php
/**
 * Explicit account linking; no inference from matching email.
 *
 * @package UOP
 */
namespace UOP\Application\Identity;

use RuntimeException;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyService;
use UOP\Application\Policy\Resource;
use UOP\Core\PublicId;
use UOP\Core\TransactionManager;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\PersonRepository;

/** Only a privileged, scoped command may link an existing person to a user. */
final class AccountLinkService {
	public function __construct(
		private PersonRepository $persons,
		private PolicyService $policy,
		private TransactionManager $transactions
	) {}

	public function link( Actor $actor, OrgScope $scope, PublicId $person_id, int $user_id, string $utc_now ): void {
		$row = $this->persons->find( $scope, $person_id );
		if ( ! $row || ! $this->policy->can( $actor, 'person.link', new Resource( $scope->id, 'person', (int) $row['id'] ) )->allowed ) {
			throw new RuntimeException( 'Account link not permitted.' );
		}
		if ( ! get_user_by( 'id', $user_id ) ) {
			throw new RuntimeException( 'Unknown WordPress account.' );
		}
		$this->transactions->run(
			function () use ( $scope, $person_id, $user_id, $utc_now ): void {
				if ( ! $this->persons->link( $scope, $person_id, $user_id, $utc_now ) ) {
					throw new RuntimeException( 'Person is already linked or inactive.' );
				}
			}
		);
	}
}
