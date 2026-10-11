<?php
/**
 * Genuine plugin-outbox/mail hooks and backend capacity actions for disposable
 * Playwright E2E only. Never accept browser-controlled input/IDs or print tokens.
 */
if ( '1' !== getenv( 'UOP_M610_CI' ) || 'cli' !== PHP_SAPI
	|| 'https://127.0.0.1:8443' !== home_url() ) {
	throw new RuntimeException( 'Refusing UOP CI E2E actions on non-disposable installation.' );
}
use UOP\Application\Communication\EmailTemplateCatalog;
use UOP\Application\Communication\EmailTemplateRules;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyService;
use UOP\Application\Registration\CapacityAllocationService;
use UOP\Application\Registration\CapacityLifecycleService;
use UOP\Application\Registration\RegistrationEligibilityService;
use UOP\Application\Registration\RegistrationFactsService;
use UOP\Application\Registration\RegistrationService;
use UOP\Application\Registration\WaitlistOfferDeliveryService;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Core\TransactionManager;
use UOP\Domain\Organization\OrgScope;
use UOP\Domain\Registrations\RegistrationStateMachine;
use UOP\Infrastructure\Database\AssignmentRepository;
use UOP\Infrastructure\Database\AuditWriter;
use UOP\Infrastructure\Database\CapacityRepository;
use UOP\Infrastructure\Database\DelegationRepository;
use UOP\Infrastructure\Database\EmailMessageRepository;
use UOP\Infrastructure\Database\OutboxRepository;
use UOP\Infrastructure\Database\PersonRepository;
use UOP\Infrastructure\Database\RegistrationFactsRepository;
use UOP\Infrastructure\Database\RegistrationRepository;
use UOP\Infrastructure\Database\WaitlistRepository;
use UOP\Infrastructure\Database\WpdbConnection;
global $wpdb;
$db = new WpdbConnection( $wpdb );
$prefix = $wpdb->prefix . 'uop_';
$scope = new OrgScope( (int) get_option( 'uop_default_organization_id' ) );
$event = PublicId::from_string( (string) get_option( 'uop_m610_ci_event' ) );
$action = getenv( 'UOP_M610_ACTION' );
$admin = get_user_by( 'login', 'uop_test_admin' );
if ( ! $admin ) {
	throw new RuntimeException( 'Disposable administrator is missing.' );
}
$guests = $db->rows(
	'SELECT public_id, status, email_verified_at FROM %i WHERE organization_id = %d AND contact_email = %s AND source = %s',
	array( $prefix . 'registrations', $scope->id, 'guest-ci@example.invalid', 'guest' )
);
if ( 1 !== count( $guests ) ) {
	throw new RuntimeException( 'Expected exactly one real guest registration.' );
}
$guest = PublicId::from_binary( (string) $guests[0]['public_id'] );
if ( 'drain-verification' === $action || 'drain-offer' === $action ) {
	// Consume only the current organization's committed events, through its
	// registered service hook. The outbox emits no bearer tokens.
	$events = $db->rows(
		'SELECT event_uuid FROM %i WHERE organization_id = %d AND event_name = %s AND published_at IS NULL',
		array( $prefix . 'domain_events', $scope->id, 'registration.email_verification_required' )
	);
	foreach ( $events as $row ) {
		do_action( 'uop_outbox_consume', $scope->id, PublicId::from_binary( (string) $row['event_uuid'] )->to_string() );
	}
	$messages = $db->rows(
		'SELECT public_id FROM %i WHERE organization_id = %d AND status = %s',
		array( $prefix . 'email_messages', $scope->id, 'queued' )
	);
	foreach ( $messages as $row ) {
		do_action( 'uop_mail_deliver', $scope->id, PublicId::from_binary( (string) $row['public_id'] )->to_string() );
	}
	echo "Committed outbox consumed and queued UOP mail delivered via isolated SMTP.\n";
	return;
}
if ( 'check-verified' === $action ) {
	if ( null === $guests[0]['email_verified_at'] ) {
		throw new RuntimeException( 'Browser action did not verify persisted guest email.' );
	}
	echo "Guest verified in real UOP database.\n";
	return;
}
if ( 'offer' === $action ) {
	if ( null === $guests[0]['email_verified_at'] ) {
		throw new RuntimeException( 'Cannot allocate an unverified guest.' );
	}
	wp_set_current_user( (int) $admin->ID );
	$actor = new Actor( (int) $admin->ID );
	$people = new PersonRepository( $db, $prefix );
	$policy = new PolicyService( $people, new DelegationRepository( $db, $prefix ),
		new AssignmentRepository( $db, $prefix ), static fn ( int $id, string $cap ): bool => user_can( $id, $cap ) );
	$tx = new TransactionManager( $db, static function ( int $delay ): void {}, static function ( Throwable $error ): void {} );
	$audit = new AuditWriter( $db, $prefix );
	$outbox = new OutboxRepository( $db, $prefix );
	$registrations = new RegistrationRepository( $db, $prefix );
	$facts = new RegistrationFactsService( new RegistrationFactsRepository( $db, $prefix ), $policy );
	$eligibility = new RegistrationEligibilityService( $registrations, $facts );
	$now = gmdate( 'Y-m-d H:i:s' );
	$person = PublicId::generate();
	$people->create( $scope, $person, 'CI member', null, $now );
	$member = ( new RegistrationService( $registrations, $policy, $tx, $audit, $outbox, $facts, $people ) )
		->submit( $actor, $scope, $person, $event, null, PublicId::generate(),
			array( 'name' => 'CI member', 'contact' => 'member-ci@example.invalid' ), $now, CorrelationId::generate() );
	$allocation = new CapacityAllocationService(
		new CapacityRepository( $db, $prefix ), $registrations, new RegistrationStateMachine(),
		$policy, $tx, $audit, $outbox, $eligibility
	);
	// Verified contact is mandatory before allocation for events configured
	// with require_email_verification. The M4 issue+verify service supplies it.
	$verifier = new UOP\Application\Registration\EmailVerificationService( $registrations, $policy, $tx, $audit, $outbox );
	$challenge = $verifier->issue( $actor, $scope, $member, $now, CorrelationId::generate() );
	if ( ! $verifier->verify( $scope, $member, $challenge['token'], $now, CorrelationId::generate() ) ) {
		throw new RuntimeException( 'Could not verify synthetic member.' );
	}
	$bucket = $allocation->create_general_bucket( $actor, $scope, $event, 1, $now, CorrelationId::generate() );
	if ( 'accepted' !== $allocation->decide( $actor, $scope, $member, $bucket, PublicId::generate(), $now, CorrelationId::generate() )
		|| 'waitlisted' !== $allocation->decide( $actor, $scope, $guest, $bucket, PublicId::generate(), $now, CorrelationId::generate() ) ) {
		throw new RuntimeException( 'Expected first place accepted and guest waiting.' );
	}
	$lifecycle = new CapacityLifecycleService(
		new WaitlistRepository( $db, $prefix ), new CapacityRepository( $db, $prefix ),
		new RegistrationStateMachine(), $policy, $tx, $audit, $outbox, $eligibility
	);
	$catalog = new EmailTemplateCatalog();
	$lifecycle->set_offer_delivery( new WaitlistOfferDeliveryService(
		new EmailMessageRepository( $db, $prefix ), $catalog, new EmailTemplateRules( $catalog ), $tx
	) );
	$lifecycle->cancel( $actor, $scope, $member, PublicId::generate(), $now, CorrelationId::generate() );
	$offer = $lifecycle->offer_next( $actor, $scope, $bucket, PublicId::generate(), $now, CorrelationId::generate() );
	if ( ! $offer || ! isset( $offer['public_id'] ) ) {
		throw new RuntimeException( 'Waitlist offer was not created.' );
	}
	// Do not expose secret token in process output, filenames or browser logs.
	echo "Capacity full, guest waitlisted, seat released, private offer created.\n";
	return;
}
if ( 'check-accepted' === $action ) {
	$rows = $db->rows(
		'SELECT status FROM %i WHERE organization_id = %d AND public_id = %s',
		array( $prefix . 'registrations', $scope->id, $guest->to_binary() )
	);
	if ( 'accepted' !== ( $rows[0]['status'] ?? null ) ) {
		throw new RuntimeException( 'Guest acceptance did not persist.' );
	}
	$count = $db->rows(
		'SELECT COUNT(*) AS n FROM %i WHERE organization_id = %d AND status = %s',
		array( $prefix . 'capacity_claims', $scope->id, 'confirmed' )
	);
	if ( 1 !== (int) ( $count[0]['n'] ?? 0 ) ) {
		throw new RuntimeException( 'Guest waitlist acceptance did not retain exactly one confirmed seat.' );
	}
	echo "Guest accepted with exactly one confirmed seat.\n";
	return;
}
throw new RuntimeException( 'Invalid disposable E2E action.' );
