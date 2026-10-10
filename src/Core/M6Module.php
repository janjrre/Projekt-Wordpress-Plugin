<?php
/**
 * Milestone 6 API module wiring without duplicate domain implementations.
 *
 * @package UOP
 */

namespace UOP\Core;

use UOP\Admin\M6PeopleRegistrationScreen;
use UOP\Admin\M6ControlCenterScreen;
use UOP\Admin\M6ConsentDocumentsScreen;
use UOP\Blocks\M6Blocks;
use UOP\Application\Consent\ConsentDefinitionService;
use UOP\Application\Consent\ConsentRecordService;
use UOP\Application\Identity\PersonService;
use UOP\Application\Policy\PolicyService;
use UOP\Application\Query\M6ReadService;
use UOP\Application\Query\M6AdminReadService;
use UOP\Application\Query\M6PortalReadService;
use UOP\Application\Query\M6OperationsReadService;
use UOP\Application\Registration\CapacityAllocationService;
use UOP\Application\Registration\CapacityLifecycleService;
use UOP\Application\Registration\EmailVerificationService;
use UOP\Application\Registration\GuestVerificationDeliveryService;
use UOP\Application\Communication\EmailTemplateCatalog;
use UOP\Application\Communication\EmailTemplateRules;
use UOP\Core\TransactionManager;
use UOP\Infrastructure\Database\EmailMessageRepository;
use UOP\Infrastructure\Database\RegistrationRepository;
use UOP\Infrastructure\Database\AuditWriter;
use UOP\Infrastructure\Database\OutboxRepository;
use UOP\REST\GuestVerificationLanding;
use UOP\Application\Registration\RegistrationService;
use UOP\Application\Registration\RegistrationTransitionService;
use UOP\Extension\ModuleInterface;
use UOP\Infrastructure\Database\Connection;
use UOP\Infrastructure\Database\ConsentRepository;
use UOP\Infrastructure\Database\EventRepository;
use UOP\Infrastructure\Database\FormRepository;
use UOP\Infrastructure\Database\OccurrenceRepository;
use UOP\Infrastructure\Database\M6OperationsRepository;
use UOP\Infrastructure\Database\M5OperationsRepository;
use UOP\Infrastructure\Database\M6AdminListRepository;
use UOP\Infrastructure\Database\DelegationRepository;
use UOP\Infrastructure\Database\PersonRepository;
use UOP\Infrastructure\Database\RegistrationReadRepository;
use UOP\REST\PeopleController;
use UOP\REST\M6OperationsController;
use UOP\REST\M6AdminController;
use UOP\REST\M6PortalController;
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
			GuestVerificationDeliveryService::class,
			static fn ( ServiceContainer $c ) => new GuestVerificationDeliveryService(
				$c->get( RegistrationRepository::class ),
				$c->get( EmailMessageRepository::class ),
				$c->get( EmailTemplateCatalog::class ),
				$c->get( EmailTemplateRules::class ),
				$c->get( TransactionManager::class ),
				$c->get( AuditWriter::class ),
				$c->get( OutboxRepository::class )
			)
		);
		add_action( 'uop_domain_event', array( $container->get( GuestVerificationDeliveryService::class ), 'on_event' ), 10, 1 );
		$container->set( GuestVerificationLanding::class, static fn () => new GuestVerificationLanding() );
		add_action( 'template_redirect', array( $container->get( GuestVerificationLanding::class ), 'maybe_render' ), 0 );
		$container->set(
			RegistrationController::class,
			static fn ( ServiceContainer $c ) => new RegistrationController(
				$c->get( M6ReadService::class ),
				$c->get( RegistrationService::class ),
				$c->get( RegistrationTransitionService::class ),
				$c->get( CapacityLifecycleService::class ),
				$c->get( EmailVerificationService::class ),
				$c->get( GuestVerificationDeliveryService::class )
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

		$container->set(
			M6AdminListRepository::class,
			static fn ( ServiceContainer $c ) => new M6AdminListRepository( $c->get( Connection::class ), $prefix )
		);
		$container->set(
			M6AdminReadService::class,
			static fn ( ServiceContainer $c ) => new M6AdminReadService(
				$c->get( M6AdminListRepository::class ),
				$c->get( M6ReadService::class ),
				$c->get( PolicyService::class ),
				$c->get( RegistrationReadRepository::class )
			)
		);
		$container->set(
			M6AdminController::class,
			static fn ( ServiceContainer $c ) => new M6AdminController( $c->get( M6AdminReadService::class ) )
		);
		$container->set(
			M6PeopleRegistrationScreen::class,
			static fn ( ServiceContainer $c ) => new M6PeopleRegistrationScreen( $c->get( M6AdminReadService::class ) )
		);
		$container->set(
			M6ControlCenterScreen::class,
			static fn ( ServiceContainer $c ) => new M6ControlCenterScreen(
				$c->get( M6OperationsReadService::class ),
				$c->get( M5OperationsRepository::class )
			)
		);
		add_action( 'admin_menu', array( $container->get( M6ControlCenterScreen::class ), 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $container->get( M6ControlCenterScreen::class ), 'assets' ) );
		$container->set(
			M6ConsentDocumentsScreen::class,
			static fn ( ServiceContainer $c ) => new M6ConsentDocumentsScreen(
				$c->get( M6OperationsReadService::class ),
				$c->get( ConsentRepository::class ),
				$c->get( ConsentDefinitionService::class )
			)
		);
		add_action( 'admin_menu', array( $container->get( M6ConsentDocumentsScreen::class ), 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $container->get( M6ConsentDocumentsScreen::class ), 'assets' ) );
		$container->set(
			M6Blocks::class,
			static fn ( ServiceContainer $c ) => new M6Blocks(
				$c->get( EventRepository::class ),
				$c->get( OccurrenceRepository::class ),
				$c->get( FormRepository::class ),
				$c->get( M6ReadService::class )
			)
		);
		add_action( 'init', array( $container->get( M6Blocks::class ), 'register' ) );
		add_action( 'template_redirect', array( $container->get( M6Blocks::class ), 'private_cache_guard' ), 0 );
		$container->set(
			M6PortalReadService::class,
			static fn ( ServiceContainer $c ) => new M6PortalReadService(
				$c->get( M6ReadService::class ),
				$c->get( PersonRepository::class ),
				$c->get( RegistrationReadRepository::class ),
				$c->get( PolicyService::class )
			)
		);
		$container->set(
			M6PortalController::class,
			static fn ( ServiceContainer $c ) => new M6PortalController( $c->get( M6PortalReadService::class ) )
		);
		add_action( 'rest_api_init', array( $container->get( M6PortalController::class ), 'register' ) );
		add_action( 'rest_api_init', array( $container->get( M6AdminController::class ), 'register' ) );
		add_action( 'admin_menu', array( $container->get( M6PeopleRegistrationScreen::class ), 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $container->get( M6PeopleRegistrationScreen::class ), 'assets' ) );
		add_action( 'rest_api_init', array( $container->get( PeopleController::class ), 'register' ) );
		add_action( 'rest_api_init', array( $container->get( RegistrationController::class ), 'register' ) );
		add_action( 'rest_api_init', array( $container->get( M6OperationsController::class ), 'register' ) );
	}
}
