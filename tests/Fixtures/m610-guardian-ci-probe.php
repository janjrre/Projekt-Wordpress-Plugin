<?php
/**
 * Read-only synthetic guardian claim proof for ephemeral HTTPS browser tests.
 *
 * No real identifiers, names or contact information are emitted.
 */
if ( '1' !== getenv( 'UOP_M610_CI' ) || 'cli' !== PHP_SAPI || 'https://127.0.0.1:8443' !== home_url() ) {
	throw new RuntimeException( 'Refusing guardian probe outside disposable CI.' );
}
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
$rate   = $user ? (int) get_transient( 'uop_guardian_claim_' . hash( 'sha256', $scope->id . ':' . $user->ID ) ) : -1;
$count  = $db->rows(
	"SELECT COUNT(*) AS n FROM %i WHERE organization_id = %d AND type_key = 'guardian_of' AND status = 'pending'",
	array( $wpdb->prefix . 'uop_relationships', $scope->id )
);
echo wp_json_encode(
	array(
		'account_linked' => (bool) $linked,
		'self_active'    => 'active' === ( $linked['status'] ?? '' ),
		'child_found'    => (bool) $child,
		'child_active'   => 'active' === ( $child['status'] ?? '' ),
		'pending_count'  => (int) ( $count[0]['n'] ?? 0 ),
		'claim_attempts' => $rate,
	)
);
