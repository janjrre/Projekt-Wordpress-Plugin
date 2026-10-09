<?php
/**
 * M6-05 safe consent-document administration, reusing immutable M5 commands.
 *
 * @package UOP
 */

namespace UOP\Admin;

use UOP\Application\Consent\ConsentDefinitionService;
use UOP\Application\Policy\Actor;
use UOP\Application\Query\M6OperationsReadService;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\ConsentRepository;

/** The UI has no direct SQL or write permissions beyond existing M5 services. */
final class M6ConsentDocumentsScreen {
	/**
	 * Bind policy, scoped summaries and the audited M5 consent service.
	 *
	 * @param M6OperationsReadService  $policy      Central live privacy authorization.
	 * @param ConsentRepository        $definitions Scoped immutable document metadata.
	 * @param ConsentDefinitionService $commands    Audited create and publish commands.
	 */
	public function __construct(
		private M6OperationsReadService $policy,
		private ConsentRepository $definitions,
		private ConsentDefinitionService $commands
	) {}

	/** Expose one page for consent document editors only. */
	public function menu(): void {
		add_menu_page(
			__( 'UOP Consents', 'uop-core' ),
			__( 'UOP Consents', 'uop-core' ),
			'uop_manage_privacy', // phpcs:ignore -- Dedicated UOP privacy capability.
			'uop-consents',
			array( $this, 'render' ),
			'dashicons-media-text',
			33
		);
	}

	/**
	 * Load matching form styles only for authorized consent editors.
	 *
	 * @param string $hook Active admin page hook.
	 */
	public function assets( string $hook ): void {
		if ( 'toplevel_page_uop-consents' !== $hook || ! $this->authorized() ) {
			return;
		}
		$url     = plugin_dir_url( dirname( __DIR__, 2 ) . '/uop-core.php' );
		$version = defined( 'UOP_CORE_VERSION' ) ? (string) constant( 'UOP_CORE_VERSION' ) : '0.1.0-alpha.2';
		wp_enqueue_style( 'uop-m6-console', $url . 'assets/m6-console.css', array(), $version );
	}

	/** Render tenant-owned definitions, a new draft and immutable publish form. */
	public function render(): void {
		if ( ! $this->authorized() ) {
			wp_die( esc_html__( 'You cannot manage consent documents.', 'uop-core' ) );
		}
		$scope = $this->scope();
		if ( null === $scope ) {
			wp_die( esc_html__( 'UOP organization unavailable.', 'uop-core' ) );
		}
		$message = $this->submit( $scope );
		echo '<div class="wrap uop-m6-console"><h1>' . esc_html__( 'Consent documents', 'uop-core' ) . '</h1>';
		echo '<p>' . esc_html__( 'Each published consent is an immutable plain-text version. Editing never changes a past signed decision. Only the current version is linked to newly published forms.', 'uop-core' ) . '</p>';
		if ( '' !== $message ) {
			echo '<div class="notice notice-info" role="status"><p>' . esc_html( $message ) . '</p></div>';
		}
		echo '<div class="uop-m6-console__grid">';
		echo '<section class="uop-m6-console__panel"><h2>' . esc_html__( 'Create draft definition', 'uop-core' ) . '</h2>';
		echo '<form method="post">';
		wp_nonce_field( 'uop_m6_consents' );
		echo '<input type="hidden" name="uop_consent_action" value="create" />';
		echo '<p><label for="uop-consent-key">' . esc_html__( 'Stable key', 'uop-core' ) . '</label>';
		echo '<input class="regular-text" id="uop-consent-key" name="uop_consent_key" type="text" maxlength="100" pattern="[a-z][a-z0-9_]*" required autocomplete="off" /></p>';
		echo '<p><label for="uop-consent-title">' . esc_html__( 'Participant-facing title', 'uop-core' ) . '</label>';
		echo '<input class="regular-text" id="uop-consent-title" name="uop_consent_title" type="text" maxlength="191" required /></p>';
		submit_button( __( 'Create draft', 'uop-core' ), 'primary', 'submit', false );
		echo '</form></section>';

		$rows = $this->definitions->summaries( $scope );
		echo '<section class="uop-m6-console__panel"><h2>' . esc_html__( 'Publish a new version', 'uop-core' ) . '</h2>';
		if ( ! $rows ) {
			echo '<p role="status">' . esc_html__( 'Create a draft before publishing a document.', 'uop-core' ) . '</p>';
		} else {
			echo '<form method="post">';
			wp_nonce_field( 'uop_m6_consents' );
			echo '<input type="hidden" name="uop_consent_action" value="publish" />';
			echo '<p><label for="uop-consent-definition">' . esc_html__( 'Definition', 'uop-core' ) . '</label>';
			echo '<select id="uop-consent-definition" name="uop_consent_definition" required>';
			foreach ( $rows as $row ) {
				$id = PublicId::from_binary( (string) $row['public_id'] )->to_string();
				echo '<option value="' . esc_attr( $id ) . '">' . esc_html( $row['title'] . ' (' . $row['consent_key'] . ')' ) . '</option>';
			}
			echo '</select></p>';
			echo '<p><label for="uop-consent-body">' . esc_html__( 'Consent document plain text', 'uop-core' ) . '</label>';
			echo '<textarea class="large-text" id="uop-consent-body" name="uop_consent_content" maxlength="100000" minlength="20" rows="8" required></textarea></p>';
			echo '<p>' . esc_html__( 'A new immutable version is created. Previously recorded consent evidence remains linked to its original version.', 'uop-core' ) . '</p>';
			submit_button( __( 'Publish new version', 'uop-core' ), 'primary', 'submit', false );
			echo '</form>';
		}
		echo '</section></div>';

		echo '<h2>' . esc_html__( 'Definitions and current versions', 'uop-core' ) . '</h2>';
		if ( ! $rows ) {
			echo '<p role="status">' . esc_html__( 'No consent definitions yet.', 'uop-core' ) . '</p>';
		} else {
			echo '<div class="uop-m6-console__table"><table class="widefat striped"><thead><tr>';
			foreach ( array( __( 'Key', 'uop-core' ), __( 'Title', 'uop-core' ), __( 'State', 'uop-core' ), __( 'Version', 'uop-core' ), __( 'Document ID', 'uop-core' ) ) as $header ) {
				echo '<th scope="col">' . esc_html( $header ) . '</th>';
			}
			echo '</tr></thead><tbody>';
			foreach ( $rows as $row ) {
				$version = null === $row['version'] ? __( 'Not published', 'uop-core' ) : (string) $row['version'];
				$uuid    = null === $row['version_public_id'] ? '' : PublicId::from_binary( (string) $row['version_public_id'] )->to_string();
				echo '<tr><td><code>' . esc_html( $row['consent_key'] ) . '</code></td><td>' . esc_html( $row['title'] ) . '</td><td>' . esc_html( $row['status'] ) . '</td><td>' . esc_html( $version ) . '</td><td><code>' . esc_html( $uuid ) . '</code></td></tr>';
			}
			echo '</tbody></table></div>';
		}
		echo '</div>';
	}

	/**
	 * Validate a bounded nonce-authenticated form and delegate all mutations.
	 *
	 * @param OrgScope $scope Trusted tenant.
	 * @return string Safe status line; no document content echoed.
	 */
	private function submit( OrgScope $scope ): string {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'POST' !== $method || ! isset( $_POST['uop_consent_action'] ) ) {
			return '';
		}
		$nonce = isset( $_POST['_wpnonce'] ) && is_string( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified below.
		if ( ! wp_verify_nonce( $nonce, 'uop_m6_consents' ) ) {
			return __( 'Request rejected. Reload this page.', 'uop-core' );
		}
		$action = is_string( $_POST['uop_consent_action'] ) ? sanitize_key( wp_unslash( $_POST['uop_consent_action'] ) ) : '';
		try {
			$actor = new Actor( get_current_user_id() );
			$now   = gmdate( 'Y-m-d H:i:s' );
			if ( 'create' === $action ) {
				$key   = isset( $_POST['uop_consent_key'] ) && is_string( $_POST['uop_consent_key'] ) ? sanitize_key( wp_unslash( $_POST['uop_consent_key'] ) ) : '';
				$title = isset( $_POST['uop_consent_title'] ) && is_string( $_POST['uop_consent_title'] ) ? sanitize_text_field( wp_unslash( $_POST['uop_consent_title'] ) ) : '';
				$this->commands->create( $actor, $scope, $key, $title, $now, CorrelationId::generate() );
				return __( 'Consent definition draft created.', 'uop-core' );
			}
			if ( 'publish' === $action ) {
				$uuid = isset( $_POST['uop_consent_definition'] ) && is_string( $_POST['uop_consent_definition'] ) ? sanitize_text_field( wp_unslash( $_POST['uop_consent_definition'] ) ) : '';
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Preserve exact legal text for its SHA-256: ConsentDefinitionService validates UTF-8, control bytes, HTML delimiters and length before storage.
				$body = isset( $_POST['uop_consent_content'] ) && is_string( $_POST['uop_consent_content'] ) ? wp_unslash( $_POST['uop_consent_content'] ) : '';
				$this->commands->publish( $actor, $scope, PublicId::from_string( $uuid ), $body, $now, CorrelationId::generate() );
				return __( 'New immutable consent version published.', 'uop-core' );
			}
		} catch ( \Throwable ) {
			return __( 'Document not saved. Check the input, your permissions, and any existing version.', 'uop-core' );
		}
		return __( 'Action not permitted.', 'uop-core' );
	}

	/**
	 * Require the precise M2 organization privacy-management permission.
	 *
	 * @return bool
	 */
	private function authorized(): bool {
		$scope = $this->scope();
		return null !== $scope && current_user_can( 'uop_manage_privacy' ) // phpcs:ignore -- Registered UOP capability.
			&& $this->policy->allowed( new Actor( get_current_user_id() ), $scope, 'privacy.manage' );
	}

	/**
	 * Use only the configured server-owned organization.
	 *
	 * @return OrgScope|null
	 */
	private function scope(): ?OrgScope {
		$id = (int) get_option( 'uop_default_organization_id', 0 );
		return $id > 0 ? new OrgScope( $id ) : null;
	}
}
