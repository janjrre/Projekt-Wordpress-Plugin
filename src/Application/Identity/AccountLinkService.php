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
use UOP\Application\Policy\PolicyObject;
use UOP\Core\PublicId;
use UOP\Core\TransactionManager;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\PersonRepository;

/** Only a privileged, scoped command may link an existing person to a user. */
final class AccountLinkService {
	/**
	 * Initialize required dependencies and validated values.
	 *
	 * @param PersonRepository   $persons persons input.
	 * @param PolicyService      $policy policy input.
	 * @param TransactionManager $transactions transactions input.
	 * @throws \RuntimeException When the requested command is rejected.
	 */
	public function __construct(
		private PersonRepository $persons,
		private PolicyService $policy,
		private TransactionManager $transactions
	) {}

	/**
	 * Atomically associate an unlinked person with a WordPress account.
	 *
	 * @param Actor    $actor actor input.
	 * @param OrgScope $scope scope input.
	 * @param PublicId $person_id person id input.
	 * @param int      $user_id user id input.
	 * @param string   $utc_now utc now input.
	 * @throws \RuntimeException When the requested command is rejected.
	 */
	public function link( Actor $actor, OrgScope $scope, PublicId $person_id, int $user_id, string $utc_now ): void {
		$row = $this->persons->find( $scope, $person_id );
		if ( ! $row || ! $this->policy->can( $actor, 'person.link', new PolicyObject( $scope->id, 'person', (int) $row['id'] ) )->allowed ) {
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
