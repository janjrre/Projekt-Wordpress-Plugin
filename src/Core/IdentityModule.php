<?php
/**
 * Composition of the M2 identity and policy foundations.
 *
 * @package UOP
 */

namespace UOP\Core;

use UOP\Application\Identity\AccountLinkService;
use UOP\Application\Policy\{CapabilityRegistry, PolicyService, ProjectionService};
use UOP\Extension\ModuleInterface;
use UOP\Infrastructure\Database\{AssignmentRepository, Connection, DelegationRepository, PersonRepository, RelationshipRepository, WpdbConnection};

/** All persistence services are private to application command/query services. */
final class IdentityModule implements ModuleInterface {
	/**
	 * Return the unique module registration key.
	 *
	 * @return string
	 */
	public function key(): string {
		return 'identity';
	}

	/**
	 * Register lazy application services and WordPress hooks.
	 *
	 * @param ServiceContainer $container container input.
	 */
	public function register( ServiceContainer $container ): void {
		global $wpdb;
		$prefix = $wpdb->prefix . 'uop_';
		$container->set( Connection::class, static fn () => new WpdbConnection( $wpdb ) );
		$container->set( PersonRepository::class, static fn ( ServiceContainer $c ) => new PersonRepository( $c->get( Connection::class ), $prefix ) );
		$container->set( RelationshipRepository::class, static fn ( ServiceContainer $c ) => new RelationshipRepository( $c->get( Connection::class ), $prefix ) );
		$container->set( DelegationRepository::class, static fn ( ServiceContainer $c ) => new DelegationRepository( $c->get( Connection::class ), $prefix ) );
		$container->set( AssignmentRepository::class, static fn ( ServiceContainer $c ) => new AssignmentRepository( $c->get( Connection::class ), $prefix ) );
		$container->set(
			PolicyService::class,
			static fn ( ServiceContainer $c ) => new PolicyService(
				$c->get( PersonRepository::class ),
				$c->get( DelegationRepository::class ),
				$c->get( AssignmentRepository::class ),
				static fn ( int $user_id, string $capability ): bool => user_can( $user_id, $capability )
			)
		);
		$container->set( ProjectionService::class, static fn ( ServiceContainer $c ) => new ProjectionService( $c->get( PolicyService::class ) ) );
		$container->set( TransactionManager::class, static fn ( ServiceContainer $c ) => new TransactionManager( $c->get( Connection::class ), static fn ( int $microseconds ) => usleep( $microseconds ), static fn ( \Throwable $error ) => update_option( 'uop_post_commit_error', array( 'type' => get_class( $error ), 'code' => $error->getCode() ), false ) ) );
		$container->set( AccountLinkService::class, static fn ( ServiceContainer $c ) => new AccountLinkService( $c->get( PersonRepository::class ), $c->get( PolicyService::class ), $c->get( TransactionManager::class ) ) );
		CapabilityRegistry::install();
	}
}
