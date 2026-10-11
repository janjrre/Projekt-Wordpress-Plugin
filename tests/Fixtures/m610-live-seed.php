<?php
/**
 * Disposable WP-CLI seed for the REAL HTTPS WordPress + Mailpit browser journey.
 * Not packaged with the plugin and never meant for a public WordPress endpoint.
 */
if ( '1' !== getenv( 'UOP_M610_CI' ) || 'cli' !== PHP_SAPI
	|| 'https://127.0.0.1:8443' !== home_url() ) {
	throw new RuntimeException( 'Refusing to seed outside the disposable GitHub HTTPS runner.' );
}
use UOP\Application\Event\EventService;
use UOP\Application\Form\FormService;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyService;
use UOP\Application\Registration\RegistrationConfigurationService;
use UOP\Core\CorrelationId;
use UOP\Core\TransactionManager;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\AssignmentRepository;
use UOP\Infrastructure\Database\AuditWriter;
use UOP\Infrastructure\Database\DelegationRepository;
use UOP\Infrastructure\Database\EventRepository;
use UOP\Infrastructure\Database\FormRepository;
use UOP\Infrastructure\Database\OccurrenceRepository;
use UOP\Infrastructure\Database\OutboxRepository;
use UOP\Infrastructure\Database\PersonRepository;
use UOP\Infrastructure\Database\WpdbConnection;

global $wpdb;
$db = new WpdbConnection( $wpdb );
$prefix = $wpdb->prefix . 'uop_';
$scope = new OrgScope( (int) get_option( 'uop_default_organization_id' ) );
$admin = get_user_by( 'login', 'uop_test_admin' );
if ( ! $admin || ! $scope->id ) {
	throw new RuntimeException( 'Disposable UOP admin or organization missing.' );
}
wp_set_current_user( (int) $admin->ID );
$actor = new Actor( (int) $admin->ID );
$policy = new PolicyService(
	new PersonRepository( $db, $prefix ),
	new DelegationRepository( $db, $prefix ),
	new AssignmentRepository( $db, $prefix ),
	static fn ( int $id, string $cap ): bool => user_can( $id, $cap )
);
$tx = new TransactionManager( $db, static function ( int $delay ): void {}, static function ( Throwable $error ): void {} );
$audit = new AuditWriter( $db, $prefix );
$outbox = new OutboxRepository( $db, $prefix );
$now = gmdate( 'Y-m-d H:i:s' );
$post_id = wp_insert_post(
	array( 'post_type' => 'uop_event', 'post_status' => 'publish', 'post_title' => 'UOP HTTPS guest registration CI' ),
	true
);
if ( is_wp_error( $post_id ) || ! is_int( $post_id ) ) {
	throw new RuntimeException( 'Cannot create disposable UOP event.' );
}
$event = ( new EventService(
	new EventRepository( $db, $prefix ),
	new OccurrenceRepository( $db, $prefix ), $policy, $tx, $audit, $outbox
) )->configure( $actor, $scope, $post_id, 'Europe/Berlin', $now, CorrelationId::generate() );
$form_service = new FormService( new FormRepository( $db, $prefix ), $policy, $tx, $audit, $outbox );
$form = $form_service->create(
	$actor, $scope, 'm610_ci_live', 'CI registration', 'event',
	array(
		'schema_version' => 1,
		'fields' => array(
			array( 'key' => 'name', 'type' => 'text', 'label' => 'Participant name', 'required' => true ),
			array( 'key' => 'contact', 'type' => 'email', 'label' => 'Contact email', 'required' => true ),
		),
	),
	$now, CorrelationId::generate()
);
$form_service->publish( $actor, $scope, $form, 1, $now, CorrelationId::generate() );
( new RegistrationConfigurationService( $db, $prefix, $policy, $tx, $audit, $outbox ) )
	->bind( $actor, $scope, $event, $form, $now, CorrelationId::generate() );
$db->execute(
	'UPDATE %i SET visibility = %s, require_email_verification = %d WHERE organization_id = %d AND public_id = %s',
	array( $prefix . 'event_settings', 'public', 1, $scope->id, $event->to_binary() )
);
$page_id = wp_insert_post(
	array(
		'post_type' => 'page',
		'post_status' => 'publish',
		'post_title' => 'UOP CI Live Guest Form',
		'post_content' => '<!-- wp:uop/registration-form {"eventId":"' . $event->to_string() . '"} /-->',
	), true
);
if ( is_wp_error( $page_id ) || ! is_int( $page_id ) ) {
	throw new RuntimeException( 'Cannot create disposable guest form page.' );
}
update_option( 'uop_m610_ci_event', $event->to_string(), false );
update_option( 'uop_m610_ci_page', $page_id, false );
echo "Disposable public UOP event + Gutenberg form page created.\n";
