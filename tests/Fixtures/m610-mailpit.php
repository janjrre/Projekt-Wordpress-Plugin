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
