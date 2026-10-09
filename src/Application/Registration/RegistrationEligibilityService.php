<?php
/**
 * Eligibility decisions against immutable submissions and live profile facts.
 *
 * @package UOP
 */

namespace UOP\Application\Registration;

use RuntimeException;
use UOP\Application\Policy\Actor;
use UOP\Domain\Conditions\ConditionEngine;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\RegistrationRepository;

/** Evaluates only server-stored and policy-authorized condition inputs. */
final class RegistrationEligibilityService {
	/**
	 * Compose authorization-safe profile facts and sealed submission reads.
	 *
	 * @param RegistrationRepository   $registrations Immutable registration snapshots.
	 * @param RegistrationFactsService $facts         Current authorized person facts.
	 */
	public function __construct( private RegistrationRepository $registrations, private RegistrationFactsService $facts ) {}

	/**
	 * Reevaluate the current bucket restriction for an allocated registration.
	 *
	 * @param Actor                $actor        Authorized decision maker.
	 * @param OrgScope             $scope        Tenant boundary.
	 * @param array<string, mixed> $registration Already scoped and locked registration.
	 * @param array<string, mixed> $bucket       Already scoped and locked bucket.
	 * @throws RuntimeException When a rule is malformed or no longer satisfied.
	 */
	public function assert_eligible( Actor $actor, OrgScope $scope, array $registration, array $bucket ): void {
		if ( null === ( $bucket['eligibility_json'] ?? null ) || '' === $bucket['eligibility_json'] ) {
			return;
		}
		if ( (int) $registration['event_post_id'] !== (int) $bucket['event_post_id'] ) {
			throw new RuntimeException( 'Eligibility and event scope mismatch.' );
		}
		$ast = json_decode( (string) $bucket['eligibility_json'], true, 64, JSON_THROW_ON_ERROR );
		if ( ! is_array( $ast ) ) {
			throw new RuntimeException( 'Bucket eligibility is malformed.' );
		}
		$trusted = $this->facts->load(
			$actor,
			$scope,
			(int) $registration['person_id'],
			(int) $registration['event_post_id'],
			(int) $registration['occurrence_id'],
			array( $ast )
		);
		$stored = $this->registrations->snapshot_fields( $scope, (int) $registration['id'] );
		$engine = new ConditionEngine();
		if ( ! $engine->evaluate(
			$ast,
			array(
				'profile'      => $trusted['profile'],
				'registration' => $stored,
			),
			$trusted['contexts']
		) ) {
			throw new RuntimeException( 'Bucket eligibility requirements were not met.' );
		}
	}
}
