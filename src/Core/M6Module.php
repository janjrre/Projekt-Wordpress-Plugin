<?php
/**
 * Milestone 6 API module wiring without duplicate domain implementations.
 *
 * @package UOP
 */

namespace UOP\Core;

use UOP\Application\Consent\ConsentDefinitionService;
use UOP\Application\Consent\ConsentRecordService;
use UOP\Application\Identity\PersonService;
use UOP\Application\Policy\PolicyService;
use UOP\Application\Query\M6ReadService;
use UOP\Application\Query\M6OperationsReadService;
use UOP\Application\Registration\CapacityAllocationService;
use UOP\Application\Registration\CapacityLifecycleService;
use UOP\Application\Registration\EmailVerificationService;
use UOP\Application\Registration\RegistrationService;
use UOP\Application\Registration\RegistrationTransitionService;
use UOP\Extension\ModuleInterface;
use UOP\Infrastructure\Database\Connection;
use UOP\Infrastructure\Database\ConsentRepository;
use UOP\Infrastructure\Database\EventRepository;
use UOP\Infrastructure\Database\M6OperationsRepository;
use UOP\Infrastructure\Database\DelegationRepository;
use UOP\Infrastructure\Database\PersonRepository;
use UOP\Infrastructure\Database\RegistrationReadRepository;
use UOP\REST\PeopleController;
use UOP\REST\M6OperationsController;
use UOP\REST\RegistrationController;

/** Presentation adapters do not bypass policy, history or capacity locks. */
final class M6Module implements ModuleInterface {
	/** Identify the M6 presentation adapter milestone.
	 *
	 * @return string
	 */
	public function key(): string {
		return 'admin_portal_rest';
	}

	/**
	 * Shared API contract or operation.
	 *
	 * @param ServiceContainer $container Shared scoped dependencies.
	 */
	public function register( ServiceContainer $container ): void {
		global $wpdb;
		$prefix = $wpdb->prefix . 'uop_';
		$container->set(
			RegistrationReadRepository::class,
			static fn ( ServiceContainer $c ) => new RegistrationReadRepository( $c->get( Connection::class ), $prefix )
		);
		$container->set(
			M6ReadService::class,
			static fn ( ServiceContainer $c ) => new M6ReadService(
				$c->get( PersonRepository::class ),
				$c->get( DelegationRepository::class ),
				$c->get( RegistrationReadRepository::class ),
				$c->get( PolicyService::class )
			)
		);
		$container->set(
			PeopleController::class,
			static fn ( ServiceContainer $c ) => new PeopleController( $c->get( M6ReadService::class ), $c->get( PersonService::class ) )
		);
		$container->set(
			RegistrationController::class,
			static fn ( ServiceContainer $c ) => new RegistrationController(
				$c->get( M6ReadService::class ),
				$c->get( RegistrationService::class ),
				$c->get( RegistrationTransitionService::class ),
				$c->get( CapacityLifecycleService::class ),
				$c->get( EmailVerificationService::class )
			)
		);

		$container->set(
			M6OperationsRepository::class,
			static fn ( ServiceContainer $c ) => new M6OperationsRepository( $c->get( Connection::class ), $prefix )
		);
		$container->set(
			M6OperationsReadService::class,
			static fn ( ServiceContainer $c ) => new M6OperationsReadService(
				$c->get( M6OperationsRepository::class ),
				$c->get( RegistrationReadRepository::class ),
				$c->get( EventRepository::class ),
				$c->get( ConsentRepository::class ),
				$c->get( PolicyService::class )
			)
		);
		$container->set(
			M6OperationsController::class,
			static fn ( ServiceContainer $c ) => new M6OperationsController(
				$c->get( M6OperationsReadService::class ),
				$c->get( ConsentDefinitionService::class ),
				$c->get( ConsentRecordService::class ),
				$c->get( CapacityAllocationService::class )
			)
		);
		add_action( 'rest_api_init', array( $container->get( PeopleController::class ), 'register' ) );
		add_action( 'rest_api_init', array( $container->get( RegistrationController::class ), 'register' ) );
		add_action( 'rest_api_init', array( $container->get( M6OperationsController::class ), 'register' ) );
	}
}
