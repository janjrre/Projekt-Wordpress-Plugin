<?php
/**
 * Accessible native WordPress review queue for untrusted guardian claims.
 *
 * @package UOP
 */

namespace UOP\Admin;

use InvalidArgumentException;
use RuntimeException;
use UOP\Application\Identity\GuardianReviewService;
use UOP\Application\Policy\Actor;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\Connection;

/** Does not make a mere guardian claim into an automatic family permission. */
final class M6GuardianReviewScreen {
	/**
	 * Bind live policy service and tenant-scoped event selection.
	 *
	 * @param GuardianReviewService $reviews Reviewed claims and grants.
	 * @param Connection            $db      Event scope lookup adapter.
	 * @param string                $prefix  Trusted tenant table prefix.
	 */
	public function __construct(
		private GuardianReviewService $reviews,
		private Connection $db,
		private string $prefix
	) {}

	/** Register private staff work queue and nonce-protected review actions. */
	public function menu(): void {
		add_menu_page(
			__( 'Guardian requests', 'uop-core' ),
			__( 'Guardian requests', 'uop-core' ),
			'uop_manage_delegations', // phpcs:ignore -- Registered UOP primitive capability.
			'uop-guardians',
			array( $this, 'render' ),
			'dashicons-groups',
			31
		);
	}

	/** Display requests without exposing emails, birthdates or proof documents. */
	public function render(): void {
		$scope = $this->scope();
		// phpcs:ignore -- Registered UOP primitive capability.
		if ( ! $scope || ! current_user_can( 'uop_manage_delegations' ) ) {
			wp_die( esc_html__( 'Guardian review not permitted.', 'uop-core' ) );
		}
		try {
			$items = $this->reviews->pending( new Actor( get_current_user_id() ), $scope );
		} catch ( RuntimeException ) {
			wp_die( esc_html__( 'Guardian review not permitted.', 'uop-core' ) );
		}
		$events = $this->db->rows(
			'SELECT event_post_id FROM %i WHERE organization_id = %d ORDER BY event_post_id ASC LIMIT 100',
			array( $this->prefix . 'event_settings', $scope->id )
		);
		echo '<div class="wrap"><h1>' . esc_html__( 'Guardian requests', 'uop-core' ) . '</h1>';
		echo '<p>' . esc_html__( 'A submitted relationship is only a claim. Check authority separately before granting time-limited access for one event. Neither a shared email nor an age statement is proof.', 'uop-core' ) . '</p>';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice.
		if ( isset( $_GET['result'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This GET value controls only a cosmetic result notice.
			$status = sanitize_key( wp_unslash( (string) $_GET['result'] ) );
			if ( in_array( $status, array( 'updated', 'error' ), true ) ) {
				echo '<p role="status" class="notice notice-' . esc_attr( 'updated' === $status ? 'success' : 'error' ) . '"><span>' . esc_html( 'updated' === $status ? __( 'Request reviewed.', 'uop-core' ) : __( 'Review rejected; no permission was granted.', 'uop-core' ) ) . '</span></p>';
			}
		}
		if ( ! $items ) {
			echo '<p>' . esc_html__( 'No pending guardian requests.', 'uop-core' ) . '</p></div>';
			return;
		}
		echo '<ul style="list-style:none;padding:0;max-width:900px">';
		foreach ( $items as $item ) {
			$uuid = (string) $item['public_id'];
			echo '<li style="border:1px solid #c3c4c7;padding:16px;margin-bottom:16px;background:#fff">';
			echo '<h2>' . esc_html( (string) $item['claimant'] ) . ' → ' . esc_html( (string) $item['subject'] ) . '</h2>';
			echo '<p>' . esc_html__( 'Claim created (UTC):', 'uop-core' ) . ' ' . esc_html( (string) $item['created_at'] ) . '</p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'uop_guardian_review' );
			echo '<input type="hidden" name="action" value="uop_guardian_review">';
			echo '<input type="hidden" name="claim_id" value="' . esc_attr( $uuid ) . '">';
			echo '<label for="uop-event-' . esc_attr( $uuid ) . '">' . esc_html__( 'Limit authorization to event', 'uop-core' ) . '</label> ';
			echo '<select required style="min-height:44px" id="uop-event-' . esc_attr( $uuid ) . '" name="event_post_id">';
			echo '<option value="">' . esc_html__( 'Choose an event', 'uop-core' ) . '</option>';
			foreach ( $events as $event ) {
				$post = get_post( (int) $event['event_post_id'] );
				if ( $post && 'uop_event' === $post->post_type && 'publish' === $post->post_status ) {
					echo '<option value="' . esc_attr( (string) $post->ID ) . '">' . esc_html( get_the_title( $post ) ) . '</option>';
				}
			}
			echo '</select><p><label><input type="checkbox" name="evidence_checked" value="1"> ' . esc_html__( 'I independently checked the representative authority outside this form.', 'uop-core' ) . '</label></p>';
			echo '<button class="button button-primary" style="min-height:44px" type="submit" name="decision" value="approve">' . esc_html__( 'Approve for 90 days', 'uop-core' ) . '</button> ';
			echo '<button class="button" style="min-height:44px" type="submit" name="decision" value="reject" formnovalidate>' . esc_html__( 'Reject claim', 'uop-core' ) . '</button>';
			echo '</form></li>';
		}
		echo '</ul></div>';
	}

	/**
	 * Require WordPress nonce and server-owned policy before any mutation.
	 *
	 * @throws \RuntimeException When an invalid review is requested.
	 */
	public function submit(): void {
		// phpcs:ignore -- Registered UOP primitive capability.
		if ( ! current_user_can( 'uop_manage_delegations' ) ) {
			wp_die( esc_html__( 'Guardian review not permitted.', 'uop-core' ) );
		}
		check_admin_referer( 'uop_guardian_review' );
		$result = 'updated';
		try {
			$scope = $this->scope();
			if ( ! $scope ) {
				throw new RuntimeException( 'Organization not configured.' );
			}
			$claim    = PublicId::from_string( sanitize_text_field( wp_unslash( (string) ( $_POST['claim_id'] ?? '' ) ) ) );
			$decision = sanitize_key( wp_unslash( (string) ( $_POST['decision'] ?? '' ) ) );
			$event    = absint( $_POST['event_post_id'] ?? 0 );
			$checked  = '1' === sanitize_text_field( wp_unslash( (string) ( $_POST['evidence_checked'] ?? '' ) ) );
			$this->reviews->review( new Actor( get_current_user_id() ), $scope, $claim, $decision, $checked, $event, gmdate( 'Y-m-d H:i:s' ), CorrelationId::generate() );
		} catch ( InvalidArgumentException | RuntimeException ) {
			$result = 'error';
		}
		wp_safe_redirect( admin_url( 'admin.php?page=uop-guardians&result=' . $result ) );
		exit;
	}

	/** Resolve current tenant only through WordPress server configuration. */
	private function scope(): ?OrgScope {
		$id = (int) get_option( 'uop_default_organization_id', 0 );
		return $id > 0 ? new OrgScope( $id ) : null;
	}
}
