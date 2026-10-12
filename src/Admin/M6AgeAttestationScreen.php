<?php
/**
 * Native WordPress screen for independent, event-scoped age review.
 *
 * @package UOP
 */

namespace UOP\Admin;

use InvalidArgumentException;
use RuntimeException;
use UOP\Application\Identity\AgeAttestationService;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyObject;
use UOP\Application\Policy\PolicyService;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\AgeAttestationRepository;
use UOP\Infrastructure\Database\Connection;
use UOP\Infrastructure\Database\PersonRepository;

/** Does not collect a DOB, ID number, copy of an identity document or consent. */
final class M6AgeAttestationScreen {
	/**
	 * Compose the only authoritative reviewer and read-only attestation lookup.
	 *
	 * @param AgeAttestationService    $reviews  Server-side reviewer permission.
	 * @param AgeAttestationRepository $evidence Verified age class reads.
	 * @param PersonRepository         $people   Scoped subject resolution.
	 * @param PolicyService            $policy   Live event-scoped authorization.
	 * @param Connection               $db       Private published-event list.
	 * @param string                   $prefix   Tenant-specific database prefix.
	 */
	public function __construct(
		private AgeAttestationService $reviews,
		private AgeAttestationRepository $evidence,
		private PersonRepository $people,
		private PolicyService $policy,
		private Connection $db,
		private string $prefix
	) {}

	/** Register a guarded administrative page with native accessible controls. */
	public function menu(): void {
		add_menu_page(
			__( 'Age attestations', 'uop-core' ),
			__( 'Age attestations', 'uop-core' ),
			'uop_manage_organization', // phpcs:ignore -- Registered UOP primitive capability.
			'uop-age',
			array( $this, 'render' ),
			'dashicons-shield',
			32
		);
	}

	/**
	 * Render a clearly bounded offline review with current evidence state.
	 *
	 * @throws RuntimeException When the selected person is unavailable.
	 */
	public function render(): void {
		$scope = $this->scope();
		// phpcs:ignore -- Registered UOP primitive capability.
		if ( ! $scope || ! current_user_can( 'uop_manage_organization' ) ) {
			wp_die( esc_html__( 'Age verification not permitted.', 'uop-core' ) );
		}
		$events = $this->db->rows(
			'SELECT event_post_id FROM %i WHERE organization_id = %d AND status = %s ORDER BY event_post_id ASC LIMIT 100',
			array( $this->prefix . 'event_settings', $scope->id, 'active' )
		);
		echo '<div class="wrap"><h1>' . esc_html__( 'Age verification', 'uop-core' ) . '</h1>';
		echo '<p>' . esc_html__( 'Verify the age category with a suitable external process before recording an outcome. No birth date or identity document is stored here. Age verification does not grant guardian access or consent.', 'uop-core' ) . '</p>';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Non-mutating notice only.
		if ( isset( $_GET['result'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Non-mutating notice only.
			$result = sanitize_key( wp_unslash( (string) $_GET['result'] ) );
			if ( in_array( $result, array( 'verified', 'revoked', 'error' ), true ) ) {
				echo '<p role="status" class="notice notice-' . esc_attr( 'error' === $result ? 'error' : 'success' ) . '"><span>' . esc_html( 'error' === $result ? __( 'Age review was rejected; no new authorization was recorded.', 'uop-core' ) : __( 'Age attestation decision was recorded.', 'uop-core' ) ) . '</span></p>';
			}
		}
		echo '<h2>' . esc_html__( 'Check current status', 'uop-core' ) . '</h2>';
		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="uop-age">';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only lookup, no mutation.
		$lookup_id = isset( $_GET['person_id'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['person_id'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only lookup, no mutation.
		$lookup_event = absint( $_GET['event_post_id'] ?? 0 );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only lookup, no mutation.
		$lookup_threshold = absint( $_GET['threshold_age'] ?? 18 );
		$current_outcome = 'gte';
		echo '<p><label for="uop-age-subject">' . esc_html__( 'Person UUID', 'uop-core' ) . '</label> ';
		echo '<input required type="text" style="min-height:44px;width:100%;max-width:320px" id="uop-age-subject" name="person_id" maxlength="36" value="' . esc_attr( $lookup_id ) . '"></p>';
		echo '<p><label for="uop-age-event">' . esc_html__( 'Event', 'uop-core' ) . '</label> ';
		echo '<select required style="min-height:44px" name="event_post_id" id="uop-age-event">';
		echo '<option value="">' . esc_html__( 'Choose published event', 'uop-core' ) . '</option>';
		foreach ( $events as $event ) {
			$post = get_post( (int) $event['event_post_id'] );
			if ( $post && $this->may_manage_event( $scope, (int) $post->ID ) ) {
				echo '<option value="' . esc_attr( (string) $post->ID ) . '" ' . selected( $lookup_event, (int) $post->ID, false ) . '>' . esc_html( get_the_title( $post ) ) . '</option>';
			}
		}
		echo '</select></p>';
		echo '<p><label for="uop-age-threshold">' . esc_html__( 'Age threshold', 'uop-core' ) . '</label> ';
		echo '<input required type="number" style="min-height:44px;width:80px" min="1" max="120" id="uop-age-threshold" name="threshold_age" value="' . esc_attr( (string) $lookup_threshold ) . '"></p>';
		echo '<button class="button" style="min-height:44px" type="submit">' . esc_html__( 'Check status', 'uop-core' ) . '</button></form>';
		if ( '' !== $lookup_id && $lookup_event > 0 && $lookup_threshold > 0 && $lookup_threshold <= 120 ) {
			try {
				$subject = PublicId::from_string( $lookup_id );
				$person  = $this->people->find( $scope, $subject );
				if ( ! $person || 'active' !== $person['status'] ) {
					throw new RuntimeException( 'Subject is unavailable.' );
				}
				if ( ! $this->may_manage_event( $scope, $lookup_event, (int) $person['id'] ) ) {
					throw new RuntimeException( 'Event review not permitted.' );
				}
				$record = $this->evidence->latest( $scope, (int) $person['id'], $lookup_event, $lookup_threshold );
				$current_outcome = $record && 'lt' === $record['outcome'] ? 'lt' : 'gte';
				$status = $record ? (string) $record['status'] : 'unknown';
				if ( $record && 'verified' === $status && (string) $record['valid_until'] < gmdate( 'Y-m-d H:i:s' ) ) {
					$status = 'expired';
				}
				$labels = array(
					'unknown'  => __( 'Not verified: independent review required.', 'uop-core' ),
					'verified' => __( 'Verified for this event and age threshold.', 'uop-core' ),
					'expired'  => __( 'Expired: new independent review required.', 'uop-core' ),
					'revoked'  => __( 'Revoked: new independent review required.', 'uop-core' ),
				);
				echo '<p role="status"><strong>' . esc_html__( 'Current age attestation:', 'uop-core' ) . '</strong> ' . esc_html( $labels[ $status ] ?? $labels['unknown'] ) . '</p>';
			} catch ( InvalidArgumentException | RuntimeException ) {
				echo '<p role="status">' . esc_html__( 'Person or event unavailable.', 'uop-core' ) . '</p>';
			}
		}
		echo '<h2>' . esc_html__( 'Record verification or revocation', 'uop-core' ) . '</h2>';
		echo '<p>' . esc_html__( 'The reviewer must be a different authorized account. The age threshold applies only to the event selected above.', 'uop-core' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'uop_age_review' );
		echo '<input type="hidden" name="action" value="uop_age_review">';
		echo '<p><label for="uop-age-review-person">' . esc_html__( 'Person UUID', 'uop-core' ) . '</label> ';
		echo '<input required id="uop-age-review-person" type="text" style="min-height:44px;width:100%;max-width:320px" name="person_id" maxlength="36" value="' . esc_attr( $lookup_id ) . '"></p>';
		echo '<p><label for="uop-age-review-event">' . esc_html__( 'Event', 'uop-core' ) . '</label> ';
		echo '<select required id="uop-age-review-event" name="event_post_id" style="min-height:44px">';
		echo '<option value="">' . esc_html__( 'Choose published event', 'uop-core' ) . '</option>';
		foreach ( $events as $event ) {
			$post = get_post( (int) $event['event_post_id'] );
			if ( $post && $this->may_manage_event( $scope, (int) $post->ID ) ) {
				echo '<option value="' . esc_attr( (string) $post->ID ) . '" ' . selected( $lookup_event, (int) $post->ID, false ) . '>' . esc_html( get_the_title( $post ) ) . '</option>';
			}
		}
		echo '</select></p>';
		echo '<p><label for="uop-age-review-threshold">' . esc_html__( 'Verified age threshold', 'uop-core' ) . '</label> ';
		echo '<input required type="number" min="1" max="120" id="uop-age-review-threshold" name="threshold_age" style="min-height:44px;width:80px" value="' . esc_attr( (string) $lookup_threshold ) . '"></p>';
		echo '<p><label for="uop-age-review-outcome">' . esc_html__( 'Verified category', 'uop-core' ) . '</label> ';
		echo '<select id="uop-age-review-outcome" name="outcome" style="min-height:44px"><option value="gte" ' . selected( $current_outcome, 'gte', false ) . '>' . esc_html__( 'At least the threshold', 'uop-core' ) . '</option><option value="lt" ' . selected( $current_outcome, 'lt', false ) . '>' . esc_html__( 'Below the threshold', 'uop-core' ) . '</option></select></p>';
		echo '<p><label for="uop-age-review-method">' . esc_html__( 'Offline review method', 'uop-core' ) . '</label> ';
		echo '<select id="uop-age-review-method" name="method" style="min-height:44px"><option value="in_person_review">' . esc_html__( 'In-person review', 'uop-core' ) . '</option><option value="trusted_register">' . esc_html__( 'Trusted register', 'uop-core' ) . '</option><option value="authorized_offline_review">' . esc_html__( 'Other authorized offline review', 'uop-core' ) . '</option></select></p>';
		echo '<p><label for="uop-age-review-until">' . esc_html__( 'Valid until (UTC)', 'uop-core' ) . '</label> ';
		echo '<input required id="uop-age-review-until" type="date" name="valid_until" style="min-height:44px" value="' . esc_attr( gmdate( 'Y-m-d', time() + 90 * DAY_IN_SECONDS ) ) . '"></p>';
		echo '<p><label><input type="checkbox" name="evidence_checked" value="1"> ' . esc_html__( 'I independently checked the age category with the specified method outside this plugin.', 'uop-core' ) . '</label></p>';
		echo '<button class="button button-primary" type="submit" name="decision" value="verify" style="min-height:44px">' . esc_html__( 'Record verified class', 'uop-core' ) . '</button> ';
		echo '<button class="button" type="submit" name="decision" value="revoke" formnovalidate style="min-height:44px">' . esc_html__( 'Revoke current class', 'uop-core' ) . '</button></form></div>';
	}

	/**
	 * Apply nonce-protected commands using the same live policy as REST.
	 *
	 * @throws RuntimeException When the organization is unavailable.
	 * @throws InvalidArgumentException When review details are malformed.
	 */
	public function submit(): void {
		// phpcs:ignore -- Registered UOP primitive capability.
		if ( ! current_user_can( 'uop_manage_organization' ) ) {
			wp_die( esc_html__( 'Age verification not permitted.', 'uop-core' ) );
		}
		check_admin_referer( 'uop_age_review' );
		$result = 'verified';
		try {
			$scope = $this->scope();
			if ( ! $scope ) {
				throw new RuntimeException( 'Organization not configured.' );
			}
			$subject   = PublicId::from_string( sanitize_text_field( wp_unslash( (string) ( $_POST['person_id'] ?? '' ) ) ) );
			$event     = absint( $_POST['event_post_id'] ?? 0 );
			$threshold = absint( $_POST['threshold_age'] ?? 0 );
			$outcome   = sanitize_key( wp_unslash( (string) ( $_POST['outcome'] ?? '' ) ) );
			$decision  = sanitize_key( wp_unslash( (string) ( $_POST['decision'] ?? '' ) ) );
			$revoke    = 'revoke' === $decision;
			if ( ! in_array( $decision, array( 'verify', 'revoke' ), true ) ) {
				throw new InvalidArgumentException( 'Unknown review action.' );
			}
			$method  = $revoke ? 'revoked' : sanitize_key( wp_unslash( (string) ( $_POST['method'] ?? '' ) ) );
			$checked = '1' === sanitize_text_field( wp_unslash( (string) ( $_POST['evidence_checked'] ?? '' ) ) );
			$until   = $revoke ? gmdate( 'Y-m-d H:i:s' ) : sanitize_text_field( wp_unslash( (string) ( $_POST['valid_until'] ?? '' ) ) ) . ' 23:59:59';
			$this->reviews->decide( new Actor( get_current_user_id() ), $scope, $subject, $event, $threshold, $outcome, $method, $until, $checked, $revoke, gmdate( 'Y-m-d H:i:s' ), CorrelationId::generate() );
			$result = $revoke ? 'revoked' : 'verified';
		} catch ( InvalidArgumentException | RuntimeException ) {
			$result = 'error';
		}
		wp_safe_redirect( admin_url( 'admin.php?page=uop-age&result=' . $result ) );
		exit;
	}

	/** Enforce live WordPress and tenant/object permissions for review screens. */
	private function may_manage_event( OrgScope $scope, int $event_post, int $person_id = 0 ): bool {
		$post = get_post( $event_post );
		$actor = new Actor( get_current_user_id() );
		return $post && 'uop_event' === $post->post_type && 'publish' === $post->post_status
			&& user_can( $actor->user_id, 'edit_post', $event_post )
			&& $this->policy->can( $actor, 'event.manage', new PolicyObject( $scope->id, 'event', $event_post, $person_id > 0 ? $person_id : null, $event_post ) )->allowed;
	}

	/** Resolve the single server-configured tenant. */
	private function scope(): ?OrgScope {
		$id = (int) get_option( 'uop_default_organization_id', 0 );
		return $id > 0 ? new OrgScope( $id ) : null;
	}
}
