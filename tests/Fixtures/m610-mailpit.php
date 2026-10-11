<?php
/**
 * Disposable GitHub Actions only: route every WordPress email to Mailpit.
 * This MU plugin is NOT part of the UOP Core distribution.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
add_action(
	'phpmailer_init',
	static function ( $phpmailer ): void {
		$phpmailer->isSMTP();
		$phpmailer->Host       = '127.0.0.1';
		$phpmailer->Port       = 1025;
		$phpmailer->SMTPAuth   = false;
		$phpmailer->SMTPSecure = '';
		$phpmailer->SMTPAutoTLS = false;
	}
);

// Strictly disposable test-only opt-ins. No external SMTP or live participants.
add_filter( 'uop_guest_verification_enabled', static fn (): bool => '1' === getenv( 'UOP_M610_CI' ) );
add_filter( 'uop_waitlist_offer_enabled', static fn (): bool => '1' === getenv( 'UOP_M610_CI' ) );

/** CI-only, non-sensitive readiness flags for troubleshooting block gates. */
add_action(
	'send_headers',
	static function (): void {
		if ( '1' !== getenv( 'UOP_M610_CI' ) || headers_sent() ) {
			return;
		}
		header( 'X-UOP-CI-Flags: ' . implode( ',', array(
			is_ssl() ? 'https' : 'http',
			apply_filters( 'uop_guest_verification_enabled', false ) ? 'opt-in' : 'off',
			function_exists( 'as_enqueue_async_action' ) ? 'scheduler' : 'no-scheduler',
			str_starts_with( home_url( '/' ), 'https://' ) ? 'secure-origin' : 'insecure-origin',
		) ) );
	},
	0
);
