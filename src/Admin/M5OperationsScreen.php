<?php
/**
 * Privacy-safe administrative operations page for M5 communication and data jobs.
 *
 * @package UOP
 */

namespace UOP\Admin;

use RuntimeException;
use UOP\Application\Communication\EmailMessageService;
use UOP\Application\Export\ExportJobService;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyObject;
use UOP\Application\Policy\PolicyService;
use UOP\Application\Privacy\RetentionService;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\M5OperationsRepository;
use UOP\Infrastructure\Database\PrivacyAccountGateway;

/** All actions are nonces plus re-checked live M2 policy; no private payloads reach HTML. */
final class M5OperationsScreen {
	/**
	 * Bind existing application services; no direct SQL or bypass commands in UI.
	 *
	 * @param PolicyService           $policy Shared live authorization.
	 * @param M5OperationsRepository  $operations Safe bounded read model.
	 * @param EmailMessageService     $mail Authorized retry command.
	 * @param RetentionService        $retention Authorized preview and processing.
	 * @param ExportJobService        $exports Private file expiry cleanup.
	 * @param PrivacyAccountGateway   $privacy Conservative email identity check.
	 */
	public function __construct(
		private PolicyService $policy,
		private M5OperationsRepository $operations,
		private EmailMessageService $mail,
		private RetentionService $retention,
		private ExportJobService $exports,
		private PrivacyAccountGateway $privacy
	) {}

	/** Advertise a restricted, server-rendered admin page. */
	public function menu(): void {
		add_menu_page(
			__( 'UOP Operations', 'uop-core' ),
			__( 'UOP Operations', 'uop-core' ),
			'uop_manage_settings', // phpcs:ignore -- Custom administrator capability installed by UOP.
			'uop-operations',
			array( $this, 'render' ),
			'dashicons-shield',
			29
		);
	}

	/** Render only the subsections that the current administrator can view. */
	public function render(): void {
		$scope = $this->scope();
		if ( null === $scope ) {
			wp_die( esc_html__( 'UOP organization is not available.', 'uop-core' ) );
		}
		$actor = new Actor( get_current_user_id() );
		$can_mail = $this->allowed( $actor, $scope, 'communication.send' );
		$can_privacy = $this->allowed( $actor, $scope, 'privacy.manage' );
		$can_export = $this->allowed( $actor, $scope, 'export.create' );
		if ( ! current_user_can( 'uop_manage_settings' ) || ! ( $can_mail || $can_privacy || $can_export ) ) { // phpcs:ignore -- Custom organization capability.
			wp_die( esc_html__( 'You cannot view UOP operations.', 'uop-core' ) );
		}
		$message = $this->submit( $actor, $scope, $can_mail, $can_privacy, $can_export );
		echo '<div class="wrap"><h1>' . esc_html__( 'UOP Operations', 'uop-core' ) . '</h1>';
		echo '<p>' . esc_html__( 'Only diagnostic codes, state counts and public IDs are shown. Message bodies, recipients, tokens and private files are never displayed.', 'uop-core' ) . '</p>';
		if ( '' !== $message ) {
			echo '<div class="notice notice-info"><p>' . esc_html( $message ) . '</p></div>';
		}
		if ( $can_mail ) {
			$this->render_mail( $scope );
		}
		if ( $can_export ) {
			$this->render_exports( $scope );
		}
		if ( $can_privacy ) {
			$this->render_privacy( $scope );
		}
		echo '</div>';
	}

	/**
	 * Fail closed on nonces and invalid operations without revealing payloads.
	 *
	 * @param Actor    $actor Live WordPress actor.
	 * @param OrgScope $scope Organization.
	 * @param bool     $can_mail Authorized communications section.
	 * @param bool     $can_privacy Authorized privacy section.
	 * @param bool     $can_export Authorized exports section.
	 * @return string Generic action outcome, no personally identifying content.
	 */
	private function submit( Actor $actor, OrgScope $scope, bool $can_mail, bool $can_privacy, bool $can_export ): string {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! isset( $_POST['uop_operation'] ) ) {
			return '';
		}
		$nonce = isset( $_POST['_wpnonce'] ) && is_string( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified below.
		if ( ! wp_verify_nonce( $nonce, 'uop_m5_operations' ) ) {
			return __( 'The request was rejected. Reload this page.', 'uop-core' );
		}
		$operation = is_string( $_POST['uop_operation'] ) ? sanitize_key( wp_unslash( $_POST['uop_operation'] ) ) : '';
		$identity = isset( $_POST['uop_identifier'] ) && is_string( $_POST['uop_identifier'] ) ? sanitize_text_field( wp_unslash( $_POST['uop_identifier'] ) ) : '';
		try {
			if ( 'mail_retry' === $operation && $can_mail ) {
				$retried = $this->mail->retry_failed( $actor, $scope, PublicId::from_string( $identity ), CorrelationId::generate() );
				return $retried ? __( 'Failed delivery has been requeued.', 'uop-core' ) : __( 'This delivery is not retryable. Check its state.', 'uop-core' );
			}
			if ( 'export_cleanup' === $operation && $can_export ) {
				$count = $this->exports->cleanup( $scope, gmdate( 'Y-m-d H:i:s' ) );
				return sprintf( __( '%d expired export jobs have been processed.', 'uop-core' ), $count );
			}
			if ( 'privacy_lookup' === $operation && $can_privacy ) {
				$resolution = $this->privacy->resolve( $identity );
				return match ( $resolution['status'] ) {
					'resolved' => __( 'This address maps to an explicitly linked WordPress subject. Use WordPress Privacy Tools for the verified request.', 'uop-core' ),
					'manual' => __( 'Manual identity resolution is required. Do not export or erase data automatically.', 'uop-core' ),
					default => __( 'No unambiguous linked subject was found. Do not associate people by email alone.', 'uop-core' ),
				};
			}
			if ( 'retention_preview' === $operation && $can_privacy ) {
				$preview = $this->retention->dry_run( $actor, $scope, $identity, 0, gmdate( 'Y-m-d H:i:s' ) );
				return sprintf( __( 'Preview: %1$d eligible, %2$d protected, %3$d examined. No records were changed.', 'uop-core' ), $preview['eligible'], $preview['held'], $preview['examined'] );
			}
		} catch ( \Throwable ) {
			return __( 'The operation was not completed. Review configuration and permissions.', 'uop-core' );
		}
		return __( 'The operation was not permitted.', 'uop-core' );
	}

	/**
	 * Show only diagnostic state counts and restricted retry buttons.
	 *
	 * @param OrgScope $scope Organization.
	 */
	private function render_mail( OrgScope $scope ): void {
		echo '<h2>' . esc_html__( 'Email delivery', 'uop-core' ) . '</h2>';
		$this->counts( $this->operations->counts( $scope, 'email' ) );
		echo '<p>' . esc_html__( 'Sending means an ambiguous attempt. Never automatically resend it. Failed messages may be explicitly retried (up to five attempts).', 'uop-core' ) . '</p>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Message', 'uop-core' ) . '</th><th>' . esc_html__( 'State', 'uop-core' ) . '</th><th>' . esc_html__( 'Attempts', 'uop-core' ) . '</th><th>' . esc_html__( 'Failure code', 'uop-core' ) . '</th><th>' . esc_html__( 'Action', 'uop-core' ) . '</th></tr></thead><tbody>';
		foreach ( $this->operations->problem_messages( $scope ) as $row ) {
			$id = PublicId::from_binary( (string) $row['public_id'] )->to_string();
			echo '<tr><td><code>' . esc_html( $id ) . '</code></td><td>' . esc_html( (string) $row['status'] ) . '</td><td>' . esc_html( (string) $row['attempts'] ) . '</td><td>' . esc_html( (string) ( $row['last_error_code'] ?? '-' ) ) . '</td><td>';
			if ( 'failed' === $row['status'] && (int) $row['attempts'] < 5 ) {
				$this->form( 'mail_retry', $id, __( 'Retry known failure', 'uop-core' ) );
			} else {
				echo esc_html__( 'Manual review', 'uop-core' );
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Show safe export failures, expiry and restricted cleanup.
	 *
	 * @param OrgScope $scope Organization.
	 */
	private function render_exports( OrgScope $scope ): void {
		echo '<h2>' . esc_html__( 'Private export jobs', 'uop-core' ) . '</h2>';
		$this->counts( $this->operations->counts( $scope, 'export' ) );
		echo '<p>' . esc_html__( 'Expired private files are deleted by a background job. Cleanup can also be invoked explicitly.', 'uop-core' ) . '</p>';
		$this->form( 'export_cleanup', '', __( 'Clean up expired exports', 'uop-core' ) );
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Job', 'uop-core' ) . '</th><th>' . esc_html__( 'State', 'uop-core' ) . '</th><th>' . esc_html__( 'Failure code', 'uop-core' ) . '</th><th>' . esc_html__( 'Expires (UTC)', 'uop-core' ) . '</th></tr></thead><tbody>';
		foreach ( $this->operations->problem_exports( $scope, gmdate( 'Y-m-d H:i:s' ) ) as $row ) {
			echo '<tr><td><code>' . esc_html( PublicId::from_binary( (string) $row['public_id'] )->to_string() ) . '</code></td><td>' . esc_html( (string) $row['status'] ) . '</td><td>' . esc_html( (string) ( $row['error_code'] ?? '-' ) ) . '</td><td>' . esc_html( (string) $row['expires_at'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Provide verified lookup guidance, retention read-only previews and failure counts.
	 *
	 * @param OrgScope $scope Organization.
	 */
	private function render_privacy( OrgScope $scope ): void {
		echo '<h2>' . esc_html__( 'Privacy and retention', 'uop-core' ) . '</h2>';
		echo '<p>' . esc_html__( 'An email address is not a person ID. Shared family addresses require manual identity verification in WordPress Privacy Tools.', 'uop-core' ) . '</p>';
		echo '<form method="post">';
		wp_nonce_field( 'uop_m5_operations' );
		echo '<input type="hidden" name="uop_operation" value="privacy_lookup" />';
		echo '<label for="uop-privacy-lookup">' . esc_html__( 'Verified request email', 'uop-core' ) . '</label> ';
		echo '<input type="email" id="uop-privacy-lookup" name="uop_identifier" required autocomplete="off" /> ';
		submit_button( __( 'Check identity resolution', 'uop-core' ), 'secondary', 'submit', false );
		echo '</form>';
		echo '<p>' . esc_html__( 'Retention executions require explicit administrator configuration. Previews do not change any data.', 'uop-core' ) . '</p>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Rule', 'uop-core' ) . '</th><th>' . esc_html__( 'Class', 'uop-core' ) . '</th><th>' . esc_html__( 'Action', 'uop-core' ) . '</th><th>' . esc_html__( 'Delay (days)', 'uop-core' ) . '</th><th>' . esc_html__( 'State', 'uop-core' ) . '</th><th>' . esc_html__( 'Preview', 'uop-core' ) . '</th></tr></thead><tbody>';
		foreach ( $this->operations->retention_rules( $scope ) as $row ) {
			echo '<tr><td><code>' . esc_html( (string) $row['rule_key'] ) . '</code></td><td>' . esc_html( (string) $row['data_class'] ) . '</td><td>' . esc_html( (string) $row['action'] ) . '</td><td>' . esc_html( (string) $row['delay_days'] ) . '</td><td>' . esc_html( 1 === (int) $row['enabled'] ? __( 'Enabled', 'uop-core' ) : __( 'Disabled', 'uop-core' ) ) . '</td><td>';
			if ( 1 === (int) $row['enabled'] ) {
				$this->form( 'retention_preview', (string) $row['rule_key'], __( 'Dry run', 'uop-core' ) );
			} else {
				echo esc_html__( 'Not active', 'uop-core' );
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
		$failures = $this->operations->retention_failures( $scope );
		foreach ( $failures as $row ) {
			echo '<p>' . esc_html( sprintf( __( 'Retention outbox events requiring recovery: %1$d at %2$d attempts.', 'uop-core' ), (int) $row['total'], (int) $row['attempts'] ) ) . '</p>';
		}
	}

	/**
	 * Output aggregate state counts only.
	 *
	 * @param array<string,int> $counts Status names and numbers.
	 */
	private function counts( array $counts ): void {
		echo '<p>';
		foreach ( $counts as $status => $count ) {
			echo '<span style="margin-right:1.5em"><strong>' . esc_html( $status ) . '</strong>: ' . esc_html( (string) $count ) . '</span>';
		}
		echo '</p>';
	}

	/**
	 * Render a nonce-protected operation POST with a bounded identifier.
	 *
	 * @param string $action Server-side allowlisted action.
	 * @param string $identifier UUID or configured rule key.
	 * @param string $label Accessible action name.
	 */
	private function form( string $action, string $identifier, string $label ): void {
		echo '<form method="post">';
		wp_nonce_field( 'uop_m5_operations' );
		echo '<input type="hidden" name="uop_operation" value="' . esc_attr( $action ) . '" />';
		echo '<input type="hidden" name="uop_identifier" value="' . esc_attr( $identifier ) . '" />';
		submit_button( $label, 'secondary', 'submit', false );
		echo '</form>';
	}

	/**
	 * Trusted single-site organization scope.
	 *
	 * @return OrgScope|null
	 */
	private function scope(): ?OrgScope {
		$id = (int) get_option( 'uop_default_organization_id', 0 );
		return $id > 0 ? new OrgScope( $id ) : null;
	}

	/**
	 * Require current exact organization assignment for the requested section.
	 *
	 * @param Actor    $actor Current WordPress actor.
	 * @param OrgScope $scope Tenant boundary.
	 * @param string   $action Exact policy action.
	 * @return bool
	 */
	private function allowed( Actor $actor, OrgScope $scope, string $action ): bool {
		return $this->policy->can( $actor, $action, new PolicyObject( $scope->id, 'organization', $scope->id ) )->allowed;
	}
}
