<?php
/**
 * Minimal synthetic-only guardian diagnostic for the ephemeral HTTPS test.
 *
 * No real personal data, tokens, names or identifiers are printed.
 */
if ( '1' !== getenv( 'UOP_M610_CI' ) || 'cli' !== PHP_SAPI || 'https://127.0.0.1:8443' !== home_url() ) {
	throw new RuntimeException( 'Refusing guardian probe outside disposable CI.' );
}
use UOP\Application\Identity\GuardianReviewService;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyService;
use UOP\Core\CorrelationId;
use UOP\Core\TransactionManager;
use UOP\Infrastructure\Database\AssignmentRepository;
use UOP\Infrastructure\Database\AuditWriter;
use UOP\Infrastructure\Database\DelegationRepository;
use UOP\Infrastructure\Database\OutboxRepository;
use UOP\Infrastructure\Database\RelationshipRepository;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\PersonRepository;
use UOP\Infrastructure\Database\WpdbConnection;

global $wpdb;
$scope  = new OrgScope( (int) get_option( 'uop_default_organization_id' ) );
$db     = new WpdbConnection( $wpdb );
$people = new PersonRepository( $db, $wpdb->prefix . 'uop_' );
$user   = get_user_by( 'login', 'uop_ci_review_parent' );
$linked = $user ? $people->by_user( $scope, (int) $user->ID ) : null;
$raw    = (string) get_option( 'uop_m610_ci_review_child', '' );
$child  = $raw ? $people->find( $scope, PublicId::from_string( $raw ) ) : null;
$rate  = $user ? (int) get_transient( 'uop_guardian_claim_' . hash( 'sha256', $scope->id . ':' . $user->ID ) ) : -1;
$count  = $db->rows(
	"SELECT COUNT(*) AS n FROM %i WHERE organization_id = %d AND type_key = 'guardian_of' AND status = 'pending'",
	array( $wpdb->prefix . 'uop_relationships', $scope->id )
);
$result = 'not_attempted';
$error  = '';
$code   = 0;
if ( $user && $child && 0 === (int) ( $count[0]['n'] ?? 0 ) ) {
	// Diagnostic-only direct service call on this exclusively synthetic site,
	// AFTER the browser claim was already attempted. No private content logged.
	$delegations = new DelegationRepository( $db, $wpdb->prefix . 'uop_' );
	$reviews     = new GuardianReviewService(
		$people,
		new RelationshipRepository( $db, $wpdb->prefix . 'uop_' ),
		$delegations,
		$db,
		new PolicyService( $people, $delegations, new AssignmentRepository( $db, $wpdb->prefix . 'uop_' ), static fn ( int $id, string $cap ): bool => user_can( $id, $cap ) ),
		new TransactionManager( $db, static function ( int $us ): void {}, static function ( Throwable $e ): void {} ),
		new AuditWriter( $db, $wpdb->prefix . 'uop_' ),
		new OutboxRepository( $db, $wpdb->prefix . 'uop_' ),
		$wpdb->prefix . 'uop_'
	);
	try {
		$result = $reviews->request( new Actor( (int) $user->ID ), $scope, PublicId::from_string( $raw ), gmdate( 'Y-m-d H:i:s' ), CorrelationId::generate() ) ? 'stored' : 'ignored';
	} catch ( Throwable $e ) {
		$result = 'thrown';
		$error  = get_class( $e );
		$code   = $e->getCode();
	}
}
echo wp_json_encode(
	array(
		'account_linked' => (bool) $linked,
		'self_active'    => 'active' === ( $linked['status'] ?? '' ),
		'child_found'    => (bool) $child,
		'child_active'   => 'active' === ( $child['status'] ?? '' ),
		'pending_count'  => (int) ( $count[0]['n'] ?? 0 ),
		'claim_attempts' => $rate,
		'direct_result'  => $result,
		'direct_class'   => $error,
		'direct_code'    => $code,
	)
);
