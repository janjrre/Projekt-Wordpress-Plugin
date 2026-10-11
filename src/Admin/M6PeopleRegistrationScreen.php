<?php
/**
 * Accessible M6-04 People and Registrations administration hosts.
 *
 * @package UOP
 */

namespace UOP\Admin;

use UOP\Application\Policy\Actor;
use UOP\Application\Query\M6AdminReadService;
use UOP\Domain\Organization\OrgScope;

/** Both screens render only authorized hosts; REST rechecks every operation. */
final class M6PeopleRegistrationScreen {
	/**
	 * Use the same live organization authorization as the administrative API.
	 *
	 * @param M6AdminReadService $reads Single managerial access policy.
	 */
	public function __construct( private M6AdminReadService $reads ) {}

	/** Register separate pages so each resource has distinct visibility. */
	public function menu(): void {
		add_menu_page(
			__( 'UOP People', 'uop-core' ),
			__( 'UOP People', 'uop-core' ),
			'uop_view_people', // phpcs:ignore -- UOP custom capability managed by CapabilityRegistry.
			'uop-people',
			array( $this, 'render_people' ),
			'dashicons-groups',
			30
		);
		add_menu_page(
			__( 'UOP Registrations', 'uop-core' ),
			__( 'UOP Registrations', 'uop-core' ),
			'uop_view_registrations', // phpcs:ignore -- UOP custom capability managed by CapabilityRegistry.
			'uop-registrations',
			array( $this, 'render_registrations' ),
			'dashicons-clipboard',
			31
		);
	}

	/** Render the guarded person administration mount. */
	public function render_people(): void {
		$this->render( 'people' );
	}

	/** Render the guarded registration administration mount. */
	public function render_registrations(): void {
		$this->render( 'registrations' );
	}

	/**
	 * Keep a readable explanation if JavaScript fails to initialize.
	 *
	 * @param string $kind Allowed administration resource.
	 */
	private function render( string $kind ): void {
		if ( ! $this->allowed( $kind ) ) {
			wp_die( esc_html__( 'You cannot access this administration screen.', 'uop-core' ) );
		}
		$title = 'people' === $kind ? __( 'People', 'uop-core' ) : __( 'Registrations', 'uop-core' );
		echo '<div class="wrap uop-m6-admin"><h1>' . esc_html( $title ) . '</h1>';
		echo '<p>' . esc_html__( 'Only records authorized for your organization are displayed. Changes are audited.', 'uop-core' ) . '</p>';
		echo '<div id="uop-m6-admin-root" data-resource="' . esc_attr( $kind ) . '">';
		echo '<p role="status">' . esc_html__( 'Loading administration interface…', 'uop-core' ) . '</p>';
		echo '</div></div>';
	}

	/**
	 * Enqueue one keyboard-accessible component only on the two UOP pages.
	 *
	 * @param string $hook WordPress admin page hook.
	 */
	public function assets( string $hook ): void {
		$kind = match ( $hook ) {
			'toplevel_page_uop-people'        => 'people',
			'toplevel_page_uop-registrations' => 'registrations',
			default                           => '',
		};
		if ( '' === $kind || ! $this->allowed( $kind ) ) {
			return;
		}
		$url     = plugin_dir_url( dirname( __DIR__, 2 ) . '/uop-core.php' );
		$version = defined( 'UOP_CORE_VERSION' ) ? (string) constant( 'UOP_CORE_VERSION' ) : '0.1.0-alpha.2';
		wp_enqueue_style( 'uop-m6-admin', $url . 'assets/m6-admin.css', array(), $version );
		wp_enqueue_script(
			'uop-m6-admin',
			$url . 'assets/m6-admin.js',
			array( 'wp-element', 'wp-api-fetch', 'wp-i18n' ),
			$version,
			true
		);
		wp_add_inline_script(
			'uop-m6-admin',
			'window.uopM6Admin = ' . wp_json_encode(
				array(
					'nonce' => wp_create_nonce( 'wp_rest' ),
				),
				JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
			) . ';',
			'before'
		);
	}

	/**
	 * Verify both the WordPress capability and organization-wide assignment.
	 *
	 * @param string $kind Resource being administered.
	 * @return bool
	 */
	private function allowed( string $kind ): bool {
		$cap = 'people' === $kind ? 'uop_view_people' : 'uop_view_registrations';
		if ( ! in_array( $kind, array( 'people', 'registrations' ), true ) || ! current_user_can( $cap ) ) { // phpcs:ignore -- UOP custom capabilities.
			return false;
		}
		$id = (int) get_option( 'uop_default_organization_id', 0 );
		if ( $id < 1 ) {
			return false;
		}
		return $this->reads->can_list( new Actor( get_current_user_id() ), new OrgScope( $id ), $kind );
	}
}
