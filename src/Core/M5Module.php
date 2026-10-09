<?php
/**
 * Consent and communications milestone service composition.
 *
 * @package UOP
 */

namespace UOP\Core;

use UOP\Application\Consent\ConsentDefinitionService;
use UOP\Application\Policy\PolicyService;
use UOP\Extension\ModuleInterface;
use UOP\Infrastructure\Database\AuditWriter;
use UOP\Infrastructure\Database\Connection;
use UOP\Infrastructure\Database\ConsentRepository;
use UOP\Infrastructure\Database\OutboxRepository;

/** Register private M5 services; public routes require a later M6 gate. */
final class M5Module implements ModuleInterface {
	/**
	 * Canonical module identifier.
	 *
	 * @return string
	 */
	public function key(): string {
		return 'consent_privacy_communications';
	}

	/**
	 * Install consent services in the scoped application container.
	 *
	 * @param ServiceContainer $container Shared service container.
	 */
	public function register( ServiceContainer $container ): void {
		global $wpdb;
		$prefix = $wpdb->prefix . 'uop_';
		$container->set( ConsentRepository::class, static fn ( ServiceContainer $c ) => new ConsentRepository( $c->get( Connection::class ), $prefix ) );
		$container->set(
			ConsentDefinitionService::class,
			static fn ( ServiceContainer $c ) => new ConsentDefinitionService(
				$c->get( ConsentRepository::class ),
				$c->get( PolicyService::class ),
				$c->get( TransactionManager::class ),
				$c->get( AuditWriter::class ),
				$c->get( OutboxRepository::class )
			)
		);
	}
}
