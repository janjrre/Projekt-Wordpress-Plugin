<?php
/**
 * Consent and communications milestone service composition.
 *
 * @package UOP
 */

namespace UOP\Core;

use UOP\Application\Consent\ConsentDefinitionService;
use UOP\Application\Consent\ConsentRecordService;
use UOP\Application\Communication\EmailTemplateCatalog;
use UOP\Application\Communication\EmailTemplateRules;
use UOP\Application\Communication\EmailTemplateService;
use UOP\Application\Communication\EmailMessageService;
use UOP\Application\Policy\PolicyService;
use UOP\Application\Privacy\WordPressPrivacyAdapter;
use UOP\Application\Privacy\RetentionService;
use UOP\Extension\ModuleInterface;
use UOP\Infrastructure\Database\AuditWriter;
use UOP\Infrastructure\Database\Connection;
use UOP\Infrastructure\Database\ConsentRepository;
use UOP\Infrastructure\Database\ConsentRecordRepository;
use UOP\Infrastructure\Database\OutboxRepository;
use UOP\Infrastructure\Database\PrivacyAccountGateway;
use UOP\Infrastructure\Database\RetentionRepository;
use UOP\Infrastructure\Database\EmailTemplateRepository;
use UOP\Infrastructure\Database\EmailMessageRepository;
use UOP\Infrastructure\Queue\EmailDeliveryWorker;

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
		$container->set( EmailTemplateCatalog::class, static fn () => new EmailTemplateCatalog() );
		$container->set( EmailTemplateRules::class, static fn ( ServiceContainer $c ) => new EmailTemplateRules( $c->get( EmailTemplateCatalog::class ) ) );
		$container->set( EmailTemplateRepository::class, static fn ( ServiceContainer $c ) => new EmailTemplateRepository( $c->get( Connection::class ), $prefix ) );
		$container->set(
			EmailTemplateService::class,
			static fn ( ServiceContainer $c ) => new EmailTemplateService(
				$c->get( EmailTemplateCatalog::class ),
				$c->get( EmailTemplateRules::class ),
				$c->get( EmailTemplateRepository::class ),
				$c->get( PolicyService::class ),
				$c->get( TransactionManager::class ),
				$c->get( AuditWriter::class ),
				$c->get( OutboxRepository::class )
			)
		);
		$container->set( EmailMessageRepository::class, static fn ( ServiceContainer $c ) => new EmailMessageRepository( $c->get( Connection::class ), $prefix ) );
		$container->set(
			EmailMessageService::class,
			static fn ( ServiceContainer $c ) => new EmailMessageService(
				$c->get( EmailTemplateService::class ),
				$c->get( EmailTemplateRules::class ),
				$c->get( EmailMessageRepository::class ),
				$c->get( PolicyService::class ),
				$c->get( TransactionManager::class ),
				$c->get( AuditWriter::class ),
				$c->get( OutboxRepository::class )
			)
		);
		$container->set(
			EmailDeliveryWorker::class,
			static fn ( ServiceContainer $c ) => new EmailDeliveryWorker(
				$c->get( Connection::class ),
				$prefix,
				$c->get( EmailMessageRepository::class ),
				$c->get( TransactionManager::class )
			)
		);
		$container->get( EmailDeliveryWorker::class )->register_hooks();
		$container->set( RetentionRepository::class, static fn ( ServiceContainer $c ) => new RetentionRepository( $c->get( Connection::class ), $prefix ) );
		$container->set(
			RetentionService::class,
			static fn ( ServiceContainer $c ) => new RetentionService(
				$c->get( RetentionRepository::class ),
				$c->get( PolicyService::class ),
				$c->get( TransactionManager::class ),
				$c->get( AuditWriter::class ),
				$c->get( OutboxRepository::class )
			)
		);
		$container->set( PrivacyAccountGateway::class, static fn ( ServiceContainer $c ) => new PrivacyAccountGateway( $c->get( Connection::class ), $prefix ) );
		$container->set(
			WordPressPrivacyAdapter::class,
			static fn ( ServiceContainer $c ) => new WordPressPrivacyAdapter(
				$c->get( PrivacyAccountGateway::class ),
				$c->get( TransactionManager::class ),
				$c->get( AuditWriter::class ),
				$c->get( OutboxRepository::class )
			)
		);
		$container->get( WordPressPrivacyAdapter::class )->register_hooks();
		$container->set( ConsentRepository::class, static fn ( ServiceContainer $c ) => new ConsentRepository( $c->get( Connection::class ), $prefix ) );
		$container->set( ConsentRecordRepository::class, static fn ( ServiceContainer $c ) => new ConsentRecordRepository( $c->get( Connection::class ), $prefix ) );
		$container->set(
			ConsentRecordService::class,
			static fn ( ServiceContainer $c ) => new ConsentRecordService(
				$c->get( ConsentRecordRepository::class ),
				$c->get( ConsentRepository::class ),
				$c->get( PolicyService::class ),
				$c->get( TransactionManager::class ),
				$c->get( AuditWriter::class ),
				$c->get( OutboxRepository::class )
			)
		);
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
