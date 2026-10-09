<?php
/**
 * Standalone process: one actual M4 capacity command on an independent wpdb session.
 *
 * Intentionally executed only by the disposable CI stress harness.
 */
if ( '1' !== getenv( 'UOP_TEST_ALLOW_DATABASE' ) || ! getenv( 'WP_ROOT' ) ) {
	throw new RuntimeException( 'Concurrent M4 worker requires disposable test database.' );
}
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';
require getenv( 'WP_ROOT' ) . '/wp-load.php';

use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyService;
use UOP\Application\Registration\CapacityAllocationService;
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
use UOP\Infrastructure\Database\OutboxRepository;
use UOP\Infrastructure\Database\PersonRepository;
use UOP\Infrastructure\Database\RegistrationRepository;
use UOP\Infrastructure\Database\RegistrationFactsRepository;
use UOP\Infrastructure\Database\WpdbConnection;

$directory = $argv[1] ?? '';
$index     = (int) ( $argv[2] ?? -1 );
if ( ! is_dir( $directory ) || $index < 0 ) {
	throw new RuntimeException( 'Invalid worker arguments.' );
}
$payload = json_decode( (string) file_get_contents( $directory . '/inputs.json' ), true, 16, JSON_THROW_ON_ERROR );
$global  = $payload['global'];
$uuid    = $payload['registrations'][ $index ];
$ready   = $directory . '/ready-' . $index;
file_put_contents( $ready, '1' );
$start = microtime( true );
while ( ! is_file( $directory . '/go' ) && microtime( true ) - $start < 90 ) {
	usleep( 10000 );
}
if ( ! is_file( $directory . '/go' ) ) {
	throw new RuntimeException( 'Concurrency barrier not released.' );
}
global $wpdb;
$db     = new WpdbConnection( $wpdb );
$prefix = $wpdb->prefix . 'uop_';
$actor  = new Actor( (int) $global['user_id'] );
$scope  = new OrgScope( (int) $global['organization_id'] );
$people = new PersonRepository( $db, $prefix );
$policy = new PolicyService(
	$people,
	new DelegationRepository( $db, $prefix ),
	new AssignmentRepository( $db, $prefix ),
	static fn ( int $user, string $cap ): bool => $user === (int) $global['user_id']
);
$tx = new TransactionManager( $db, static function ( int $delay ): void { usleep( $delay ); }, static function ( Throwable $error ): void {} );
$service = new CapacityAllocationService(
	new CapacityRepository( $db, $prefix ),
	new RegistrationRepository( $db, $prefix ),
	new RegistrationStateMachine(),
	$policy,
	$tx,
	new AuditWriter( $db, $prefix ),
	new OutboxRepository( $db, $prefix ),
	new RegistrationEligibilityService( new RegistrationRepository( $db, $prefix ), new RegistrationFactsService( new RegistrationFactsRepository( $db, $prefix ), $policy ) )
);
try {
	$status = $service->decide(
		$actor,
		$scope,
		PublicId::from_string( $uuid ),
		PublicId::from_string( $global['bucket'] ),
		PublicId::generate(),
		$global['utc_now'],
		CorrelationId::generate()
	);
	file_put_contents( $directory . '/result-' . $index . '.json', wp_json_encode( array( 'status' => $status ) ) );
	exit( 0 );
} catch ( Throwable $error ) {
	file_put_contents( $directory . '/result-' . $index . '.json', wp_json_encode( array( 'error' => get_class( $error ), 'message' => $error->getMessage() ) ) );
	exit( 1 );
}
