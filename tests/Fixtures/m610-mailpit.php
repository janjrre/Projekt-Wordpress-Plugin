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
