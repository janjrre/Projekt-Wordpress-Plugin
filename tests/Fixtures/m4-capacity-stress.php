<?php
/**
 * One free seat, fifty simultaneous PHP processes, independent InnoDB sessions.
 *
 * Requires UOP_TEST_ALLOW_DATABASE=1 and a disposable WP_ROOT; not a production command.
 */
if ( '1' !== getenv( 'UOP_TEST_ALLOW_DATABASE' ) || ! getenv( 'WP_ROOT' ) ) {
	throw new RuntimeException( 'M4 concurrency suite must use a disposable test database.' );
}
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';
require getenv( 'WP_ROOT' ) . '/wp-load.php';

use UOP\Application\Event\EventService;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyService;
use UOP\Application\Registration\CapacityAllocationService;
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
use UOP\Infrastructure\Database\Installer;
use UOP\Infrastructure\Database\OccurrenceRepository;
use UOP\Infrastructure\Database\OutboxRepository;
use UOP\Infrastructure\Database\PersonRepository;
use UOP\Infrastructure\Database\RegistrationRepository;
use UOP\Infrastructure\Database\WpdbConnection;

global $wpdb;
Installer::runner()->run();
$prefix = $wpdb->prefix . 'uop_';
$db     = new WpdbConnection( $wpdb );
$scope  = new OrgScope( (int) get_option( 'uop_default_organization_id' ) );
$admin  = get_user_by( 'login', 'uop_test_admin' );
if ( ! $admin ) {
	throw new RuntimeException( 'Disposable WordPress admin is missing.' );
}
wp_set_current_user( $admin->ID );
$actor  = new Actor( (int) $admin->ID );
$people = new PersonRepository( $db, $prefix );
$policy = new PolicyService(
	$people,
	new DelegationRepository( $db, $prefix ),
	new AssignmentRepository( $db, $prefix ),
	static fn ( int $user, string $cap ): bool => $user === (int) $admin->ID
);
$tx     = new TransactionManager( $db, static function ( int $delay ): void { usleep( $delay ); }, static function ( Throwable $error ): void {} );
$audit  = new AuditWriter( $db, $prefix );
$outbox = new OutboxRepository( $db, $prefix );
$now    = '2030-02-01 09:00:00';
if ( ! post_type_exists( 'uop_event' ) ) {
	register_post_type( 'uop_event', array( 'public' => true ) );
}
$post = wp_insert_post( array( 'post_type' => 'uop_event', 'post_title' => 'M4 concurrent allocation', 'post_status' => 'publish' ), true );
if ( ! is_int( $post ) ) {
	throw new RuntimeException( 'Stress test event could not be created.' );
}
$event_service = new EventService( new EventRepository( $db, $prefix ), new OccurrenceRepository( $db, $prefix ), $policy, $tx, $audit, $outbox );
$event         = $event_service->configure( $actor, $scope, $post, 'Europe/Berlin', $now, CorrelationId::generate() );
$capacity      = new CapacityAllocationService( new CapacityRepository( $db, $prefix ), new RegistrationRepository( $db, $prefix ), new RegistrationStateMachine(), $policy, $tx, $audit, $outbox );
$bucket        = $capacity->create_general_bucket( $actor, $scope, $event, 1, $now, CorrelationId::generate() );

$registrations = array();
for ( $i = 0; $i < 50; ++$i ) {
	$person = PublicId::generate();
	$people->create( $scope, $person, 'Stress person ' . $i, null, $now );
	$subject = $people->find( $scope, $person );
	if ( ! $subject ) {
		throw new RuntimeException( 'Stress person unavailable.' );
	}
	$id = PublicId::generate();
	$db->execute(
		"INSERT INTO %i (public_id, submission_key, organization_id, person_id, actor_user_id, event_post_id, occurrence_id, form_version_id, status, source, submitted_at, created_at, updated_at) VALUES (%s,%s,%d,%d,%d,%d,0,1,'submitted','portal',%s,%s,%s)",
		array( $prefix . 'registrations', $id->to_binary(), PublicId::generate()->to_binary(), $scope->id, (int) $subject['id'], $actor->user_id, $post, $now, $now, $now )
	);
	$registrations[] = $id->to_string();
}
$directory = sys_get_temp_dir() . '/uop-m4-stress-' . bin2hex( random_bytes( 8 ) );
if ( ! mkdir( $directory, 0700 ) ) {
	throw new RuntimeException( 'Cannot create stress run directory.' );
}
file_put_contents(
	$directory . '/inputs.json',
	wp_json_encode(
		array(
			'global'        => array(
				'user_id'         => $actor->user_id,
				'organization_id' => $scope->id,
				'bucket'          => $bucket->to_string(),
				'utc_now'         => $now,
			),
			'registrations' => $registrations,
		),
		JSON_THROW_ON_ERROR
	)
);
$processes = array();
try {
	for ( $i = 0; $i < 50; ++$i ) {
		$command = array( PHP_BINARY, __DIR__ . '/m4-capacity-worker.php', $directory, (string) $i );
		$log     = $directory . '/worker-' . $i . '.log';
		$process = proc_open( $command, array( 0 => array( 'file', '/dev/null', 'r' ), 1 => array( 'file', $log, 'w' ), 2 => array( 'file', $log, 'a' ) ), $pipes );
		if ( ! is_resource( $process ) ) {
			throw new RuntimeException( 'Could not launch worker ' . $i );
		}
		$processes[] = $process;
	}
	$started = microtime( true );
	while ( count( glob( $directory . '/ready-*' ) ) !== 50 && microtime( true ) - $started < 90 ) {
		usleep( 50000 );
	}
	if ( count( glob( $directory . '/ready-*' ) ) !== 50 ) {
		throw new RuntimeException( 'Not all fifty workers reached the concurrency barrier.' );
	}
	file_put_contents( $directory . '/go', '1' );
	$results = array();
	foreach ( $processes as $i => $process ) {
		$exit_code = proc_close( $process );
		$data      = is_file( $directory . '/result-' . $i . '.json' ) ? json_decode( (string) file_get_contents( $directory . '/result-' . $i . '.json' ), true ) : array( 'error' => 'missing-worker-result' );
		if ( 0 !== $exit_code || ! isset( $data['status'] ) ) {
			throw new RuntimeException( 'Worker ' . $i . ' failed: ' . wp_json_encode( $data ) . ' ' . file_get_contents( $directory . '/worker-' . $i . '.log' ) );
		}
		$results[] = $data['status'];
	}
	if ( 1 !== count( array_filter( $results, static fn ( string $status ): bool => 'accepted' === $status ) )
		|| 49 !== count( array_filter( $results, static fn ( string $status ): bool => 'waitlisted' === $status ) ) ) {
		throw new RuntimeException( 'Concurrent service decisions were not 1 accepted / 49 waitlisted.' );
	}
	$claim_rows = $db->rows(
		'SELECT c.status, COUNT(*) AS count FROM %i c INNER JOIN %i b ON b.id = c.bucket_id WHERE b.organization_id = %d AND b.public_id = %s GROUP BY c.status',
		array( $prefix . 'capacity_claims', $prefix . 'capacity_buckets', $scope->id, $bucket->to_binary() )
	);
	if ( 1 !== count( $claim_rows ) || 'confirmed' !== $claim_rows[0]['status'] || 1 !== (int) $claim_rows[0]['count'] ) {
		throw new RuntimeException( 'Capacity invariant failed: confirmed claim count is not exactly one.' );
	}
	$waiting = $db->rows(
		"SELECT COUNT(*) AS count FROM %i w INNER JOIN %i b ON b.id = w.bucket_id WHERE b.organization_id = %d AND b.public_id = %s AND w.status = 'waiting'",
		array( $prefix . 'waitlist_entries', $prefix . 'capacity_buckets', $scope->id, $bucket->to_binary() )
	);
	if ( 49 !== (int) $waiting[0]['count'] ) {
		throw new RuntimeException( 'Capacity invariant failed: waiting count is not 49.' );
	}
	$history = $db->rows(
		"SELECT COUNT(*) AS count, COUNT(DISTINCT h.command_id) AS distinct_commands FROM %i h INNER JOIN %i r ON r.id = h.registration_id WHERE r.organization_id = %d AND r.event_post_id = %d AND h.to_status IN ('accepted','waitlisted')",
		array( $prefix . 'registration_history', $prefix . 'registrations', $scope->id, $post )
	);
	if ( 50 !== (int) $history[0]['count'] || 50 !== (int) $history[0]['distinct_commands'] ) {
		throw new RuntimeException( 'Duplicate/missing decision history under contention.' );
	}
	$events = $db->rows(
		"SELECT COUNT(*) AS count, COUNT(DISTINCT d.event_uuid) AS distinct_events FROM %i d INNER JOIN %i r ON r.id = d.aggregate_id WHERE d.aggregate_type = 'registration' AND d.event_name = 'registration.capacity_decided' AND r.organization_id = %d AND r.event_post_id = %d",
		array( $prefix . 'domain_events', $prefix . 'registrations', $scope->id, $post )
	);
	if ( 50 !== (int) $events[0]['count'] || 50 !== (int) $events[0]['distinct_events'] ) {
		throw new RuntimeException( 'Duplicate/missing domain events under contention.' );
	}
	echo 'PASS: 50 real parallel PHP workers, exactly 1 confirmed claim, 49 waitlisted, 50 distinct decision events/history.' . PHP_EOL;
} finally {
	foreach ( $processes as $process ) {
		if ( is_resource( $process ) ) {
			proc_terminate( $process );
			proc_close( $process );
		}
	}
	foreach ( glob( $directory . '/*' ) ?: array() as $file ) {
		unlink( $file );
	}
	rmdir( $directory );
}
