<?php
/**
 * Standalone registered-member, caregiver and admin browser test seed.
 * Strictly limited to the ephemeral local GitHub runner, never installed.
 */
if ( '1' !== getenv( 'UOP_M610_CI' ) || 'cli' !== PHP_SAPI || 'https://127.0.0.1:8443' !== home_url() ) {
	throw new RuntimeException( 'Refusing member seed outside isolated CI.' );
}
use UOP\Application\Event\EventService;
use UOP\Application\Form\FormService;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyService;
use UOP\Application\Registration\CapacityAllocationService;
use UOP\Application\Registration\RegistrationConfigurationService;
use UOP\Application\Registration\RegistrationEligibilityService;
use UOP\Application\Registration\RegistrationFactsService;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Core\TransactionManager;
use UOP\Domain\Organization\OrgScope;
use UOP\Domain\Registrations\RegistrationStateMachine;
use UOP\Infrastructure\Database\AssignmentRepository;
use UOP\Infrastructure\Database\AuditWriter;
use UOP\Infrastructure\Database\CapacityRepository;
use UOP\Infrastructure\Database\DelegationRepository;
use UOP\Infrastructure\Database\EventRepository;
use UOP\Infrastructure\Database\FormRepository;
use UOP\Infrastructure\Database\OccurrenceRepository;
use UOP\Infrastructure\Database\OutboxRepository;
use UOP\Infrastructure\Database\PersonRepository;
use UOP\Infrastructure\Database\RegistrationFactsRepository;
use UOP\Infrastructure\Database\RegistrationRepository;
use UOP\Infrastructure\Database\WpdbConnection;

global $wpdb;
$db = new WpdbConnection( $wpdb );
$prefix = $wpdb->prefix . 'uop_';
$scope = new OrgScope( (int) get_option( 'uop_default_organization_id' ) );
$admin = get_user_by( 'login', 'uop_test_admin' );
$password = (string) getenv( 'WP_ADMIN_PASSWORD' );
if ( ! $admin || strlen( $password ) < 20 ) {
	throw new RuntimeException( 'Missing disposable admin or generated test password.' );
}
wp_set_current_user( (int) $admin->ID );
$actor = new Actor( (int) $admin->ID );
$people = new PersonRepository( $db, $prefix );
$delegations = new DelegationRepository( $db, $prefix );
$policy = new PolicyService( $people, $delegations, new AssignmentRepository( $db, $prefix ), static fn ( int $id, string $cap ): bool => user_can( $id, $cap ) );
$tx = new TransactionManager( $db, static function ( int $delay ): void {}, static function ( Throwable $error ): void {} );
$audit = new AuditWriter( $db, $prefix );
$outbox = new OutboxRepository( $db, $prefix );
$now = gmdate( 'Y-m-d H:i:s' );
foreach ( array( 'adult' => 'Adult Member', 'parent' => 'Caregiver Member', 'stranger' => 'Unrelated Member' ) as $key => $name ) {
	$login = 'uop_ci_' . $key;
	$user = wp_create_user( $login, $password, $key . '-ci@example.invalid' );
	if ( is_wp_error( $user ) ) {
		throw new RuntimeException( 'Cannot create synthetic ' . $key . ' account.' );
	}
	( new WP_User( $user ) )->set_role( 'subscriber' );
	$person = PublicId::generate();
	$people->create( $scope, $person, $name, $key . '-ci@example.invalid', $now );
	if ( ! $people->link( $scope, $person, $user, $now ) ) {
		throw new RuntimeException( 'Cannot link synthetic person.' );
	}
	update_option( 'uop_m610_ci_' . $key . '_person', $person->to_string(), false );
}
$minor = PublicId::generate();
$people->create( $scope, $minor, 'Caregiver Minor', 'parent-ci@example.invalid', $now );
// Own member login represents the dependent person's self-access, separate
// from the caregiver's explicitly revocable registration-management grant.
$minor_user = wp_create_user( 'uop_ci_minor', $password, 'minor-ci@example.invalid' );
if ( is_wp_error( $minor_user ) ) {
	throw new RuntimeException( 'Cannot create synthetic minor account.' );
}
( new WP_User( $minor_user ) )->set_role( 'subscriber' );
if ( ! $people->link( $scope, $minor, $minor_user, $now ) ) {
	throw new RuntimeException( 'Cannot link synthetic minor to own login.' );
}
update_option( 'uop_m610_ci_minor', $minor->to_string(), false );

// Separate guardian-review journey, deliberately isolated from the prior
// caregiver delegation and registration-state browser scenarios.
$review_parent_user = wp_create_user( 'uop_ci_review_parent', $password, 'review-parent-ci@example.invalid' );
if ( is_wp_error( $review_parent_user ) ) {
	throw new RuntimeException( 'Cannot create synthetic review claimant account.' );
}
( new WP_User( $review_parent_user ) )->set_role( 'subscriber' );
$review_parent = PublicId::generate();
$review_child  = PublicId::generate();
$people->create( $scope, $review_parent, 'Review Representative', 'review-family@example.invalid', $now );
$people->create( $scope, $review_child, 'Review Dependent', 'review-family@example.invalid', $now );
if ( ! $people->link( $scope, $review_parent, $review_parent_user, $now ) ) {
	throw new RuntimeException( 'Cannot link synthetic guardian reviewer claimant.' );
}
update_option( 'uop_m610_ci_review_child', $review_child->to_string(), false );
$post = wp_insert_post( array( 'post_type' => 'uop_event', 'post_status' => 'publish', 'post_title' => 'UOP CI members-only signup' ), true );
if ( is_wp_error( $post ) || ! is_int( $post ) ) {
	throw new RuntimeException( 'Cannot create member event.' );
}
$event = ( new EventService( new EventRepository( $db, $prefix ), new OccurrenceRepository( $db, $prefix ), $policy, $tx, $audit, $outbox ) )
	->configure( $actor, $scope, $post, 'Europe/Berlin', $now, CorrelationId::generate() );
$form_service = new FormService( new FormRepository( $db, $prefix ), $policy, $tx, $audit, $outbox );
$form = $form_service->create(
	$actor, $scope, 'm610_ci_member', 'Member registration', 'event',
	array( 'schema_version' => 1, 'fields' => array(
		array( 'key' => 'name', 'type' => 'text', 'label' => 'Participant name', 'required' => true ),
		array( 'key' => 'contact', 'type' => 'email', 'label' => 'Contact email', 'required' => true ),
	) ),
	$now, CorrelationId::generate()
);
$form_service->publish( $actor, $scope, $form, 1, $now, CorrelationId::generate() );
( new RegistrationConfigurationService( $db, $prefix, $policy, $tx, $audit, $outbox ) )
	->bind( $actor, $scope, $event, $form, $now, CorrelationId::generate() );
$db->execute(
	'UPDATE %i SET visibility = %s, require_email_verification = %d WHERE organization_id = %d AND public_id = %s',
	array( $prefix . 'event_settings', 'public', 0, $scope->id, $event->to_binary() )
);
$reg_repo = new RegistrationRepository( $db, $prefix );
$facts = new RegistrationFactsService( new RegistrationFactsRepository( $db, $prefix ), $policy );
$seats = new CapacityAllocationService(
	new CapacityRepository( $db, $prefix ), $reg_repo, new RegistrationStateMachine(),
	$policy, $tx, $audit, $outbox, new RegistrationEligibilityService( $reg_repo, $facts )
);
$bucket = $seats->create_general_bucket( $actor, $scope, $event, 2, $now, CorrelationId::generate() );
foreach ( array(
	'member_page' => '<!-- wp:uop/registration-form {"eventId":"' . $event->to_string() . '"} /-->',
	'portal_page' => '<!-- wp:uop/portal /--><!-- wp:uop/my-registrations /-->',
) as $key => $content ) {
	$page = wp_insert_post(
		array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'UOP ' . $key, 'post_content' => $content ), true
	);
	if ( is_wp_error( $page ) || ! is_int( $page ) ) {
		throw new RuntimeException( 'Cannot create ' . $key );
	}
	update_option( 'uop_m610_ci_' . $key, $page, false );
}
update_option( 'uop_m610_ci_member_event', $event->to_string(), false );
update_option( 'uop_m610_ci_member_bucket', $bucket->to_string(), false );
echo "Disposable independent member, parent, minor, unrelated account and admin scenario prepared.\n";
