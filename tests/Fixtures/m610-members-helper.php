<?php
/**
 * Synthetic parent delegation controls and persistence assertions for CI only.
 * No dynamic user-supplied identity, web endpoint or secrets printed.
 */
if ( '1' !== getenv( 'UOP_M610_CI' ) || 'cli' !== PHP_SAPI || 'https://127.0.0.1:8443' !== home_url() ) {
	throw new RuntimeException( 'Refusing member assertion outside isolated GitHub CI.' );
}
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\DelegationRepository;
use UOP\Infrastructure\Database\PersonRepository;
use UOP\Infrastructure\Database\RelationshipRepository;
use UOP\Infrastructure\Database\WpdbConnection;
global $wpdb;
$db = new WpdbConnection( $wpdb );
$prefix = $wpdb->prefix . 'uop_';
$scope = new OrgScope( (int) get_option( 'uop_default_organization_id' ) );
$minor = PublicId::from_string( (string) get_option( 'uop_m610_ci_minor' ) );
$parent = get_user_by( 'login', 'uop_ci_parent' );
$adult = PublicId::from_string( (string) get_option( 'uop_m610_ci_adult_person' ) );
$event = PublicId::from_string( (string) get_option( 'uop_m610_ci_member_event' ) );
if ( ! $parent || ! $scope->id ) {
	throw new RuntimeException( 'Missing synthetic caregiver or organization.' );
}
$people = new PersonRepository( $db, $prefix );
$delegations = new DelegationRepository( $db, $prefix );
$now = gmdate( 'Y-m-d H:i:s' );
$action = getenv( 'UOP_M610_MEMBER_ACTION' );
if ( 'grant-child' === $action ) {
	$row = $people->find( $scope, $minor );
	if ( ! $row ) {
		throw new RuntimeException( 'Synthetic minor missing.' );
	}
	$parent_person = $people->by_user( $scope, (int) $parent->ID );
	if ( ! $parent_person ) {
		throw new RuntimeException( 'Synthetic caregiver person missing.' );
	}
	$relationships = new RelationshipRepository( $db, $prefix );
	$relationship  = PublicId::generate();
	$relationships->create( $scope, $relationship, (int) $parent_person['id'], (int) $row['id'], 'guardian_of', $now );
	$grant = PublicId::generate();
	$delegations->grant( $scope, $grant, (int) $parent->ID, (int) $row['id'], 'registration_manage', 'organization', 0, (int) $relationships->find( $scope, $relationship )['id'], $now );
	update_option( 'uop_m610_ci_child_grant', $grant->to_string(), false );
	update_option( 'uop_m610_ci_child_relationship', $relationship->to_string(), false );
	echo "Only the synthetic minor registration delegation with guardian evidence was granted.\n";
	return;
}
if ( 'revoke-relationship' === $action || 'reactivate-relationship' === $action ) {
	$relationship = PublicId::from_string( (string) get_option( 'uop_m610_ci_child_relationship' ) );
	$state = 'revoke-relationship' === $action ? 'revoked' : 'active';
	$updated = $db->execute(
		'UPDATE %i SET status = %s, updated_at = %s WHERE organization_id = %d AND public_id = %s',
		array( $prefix . 'relationships', $state, $now, $scope->id, $relationship->to_binary() )
	);
	if ( 1 !== $updated ) {
		throw new RuntimeException( 'Cannot change synthetic guardian evidence.' );
	}
	echo "Synthetic guardian evidence changed; separate delegation remains unchanged.\n";
	return;
}
if ( 'revoke-child' === $action ) {
	$grant = PublicId::from_string( (string) get_option( 'uop_m610_ci_child_grant' ) );
	if ( ! $delegations->revoke( $scope, $grant, $now ) ) {
		throw new RuntimeException( 'Cannot revoke minor registration delegation.' );
	}
	echo "Minor's synthetic registration delegation revoked.\n";
	return;
}
if ( 'verify-states' === $action ) {
	foreach ( array(
		$adult->to_string() => 'accepted',
		$minor->to_string() => 'rejected',
	) as $person => $expected ) {
		$rows = $db->rows(
			'SELECT r.status FROM %i r JOIN %i p ON p.id = r.person_id AND p.organization_id = r.organization_id WHERE r.organization_id = %d AND p.public_id = %s AND r.event_post_id = (SELECT event_post_id FROM %i WHERE organization_id = %d AND public_id = %s) LIMIT 2',
			array( $prefix . 'registrations', $prefix . 'persons', $scope->id, PublicId::from_string( $person )->to_binary(), $prefix . 'event_settings', $scope->id, $event->to_binary() )
		);
		if ( 1 !== count( $rows ) || $expected !== $rows[0]['status'] ) {
			throw new RuntimeException( 'Incorrect persisted registration state for synthetic actor.' );
		}
	}
	echo "Real UOP database holds adult accepted and minor rejected; no cross-account mutation.\n";
	return;
}
throw new RuntimeException( 'Invalid member CI action.' );
