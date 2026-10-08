<?php
/**
 * M4 registration core dependency composition.
 *
 * @package UOP
 */

namespace UOP\Core;

use UOP\Application\Registration\RegistrationConfigurationService;
use UOP\Application\Registration\RegistrationService;
use UOP\Application\Registration\RegistrationTransitionService;
use UOP\Domain\Registrations\RegistrationStateMachine;
use UOP\Application\Policy\PolicyService;
use UOP\Extension\ModuleInterface;
use UOP\Infrastructure\Database\AuditWriter;
use UOP\Infrastructure\Database\Connection;
use UOP\Infrastructure\Database\OutboxRepository;
use UOP\Infrastructure\Database\RegistrationRepository;

/** Application-only milestone: no public submission route before all checks exist. */
final class M4Module implements ModuleInterface {
	/**
	 * Return the canonical module identifier.
	 *
	 * @return string
	 */
	public function key(): string {
		return 'registration_core';
	}

	/**
	 * Register identity-aware registration application services.
	 *
	 * @param ServiceContainer $container Active service registry.
	 */
	public function register( ServiceContainer $container ): void {
		global $wpdb;
		$prefix = $wpdb->prefix . 'uop_';
		$container->set( RegistrationStateMachine::class, static fn () => new RegistrationStateMachine() );
		$container->set( RegistrationRepository::class, static fn ( ServiceContainer $c ) => new RegistrationRepository( $c->get( Connection::class ), $prefix ) );
		$container->set(
			RegistrationService::class,
			static fn ( ServiceContainer $c ) => new RegistrationService(
				$c->get( RegistrationRepository::class ),
				$c->get( PolicyService::class ),
				$c->get( TransactionManager::class ),
				$c->get( AuditWriter::class ),
				$c->get( OutboxRepository::class )
			)
		);
		$container->set(
			RegistrationConfigurationService::class,
			static fn ( ServiceContainer $c ) => new RegistrationConfigurationService(
				$c->get( Connection::class ),
				$prefix,
				$c->get( PolicyService::class ),
				$c->get( TransactionManager::class ),
				$c->get( AuditWriter::class ),
				$c->get( OutboxRepository::class )
			)
		);
		$container->set(
			RegistrationTransitionService::class,
			static fn ( ServiceContainer $c ) => new RegistrationTransitionService(
				$c->get( RegistrationRepository::class ),
				$c->get( RegistrationStateMachine::class ),
				$c->get( PolicyService::class ),
				$c->get( TransactionManager::class ),
				$c->get( AuditWriter::class ),
				$c->get( OutboxRepository::class )
			)
		);
	}
}
