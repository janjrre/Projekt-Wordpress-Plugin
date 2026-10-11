<?php
/**
 * Staging-only mu-plugin. Install on uop-test.dreamloud.de, NOT on Dreamloud.
 *
 * @package UOP
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$uop_staging_hostname = 'uop-test.dreamloud.de';
$uop_staging_configured = (
	'staging' === wp_get_environment_type()
	&& defined( 'UOP_STAGING_SITE_HOST' )
	&& UOP_STAGING_SITE_HOST === $uop_staging_hostname
	&& wp_parse_url( home_url( '/' ), PHP_URL_HOST ) === $uop_staging_hostname
);
if ( ! $uop_staging_configured ) {
	add_action(
		'init',
		static function (): void {
			wp_die( 'UOP staging guard: the isolated host identity is missing.', 'Staging configuration', array( 'response' => 503 ) );
		},
		0
	);
	return;
}
add_filter( 'pre_option_blog_public', static fn (): string => '0' );
add_filter(
	'robots_txt',
	static fn ( string $output ): string => "User-agent: *\nDisallow: /\n"
);
add_action(
	'send_headers',
	static function () use ( $uop_staging_hostname ): void {
		if ( ! headers_sent() ) {
			header( 'X-UOP-Staging: ' . $uop_staging_hostname );
			header( 'X-Robots-Tag: noindex, nofollow, noarchive' );
			header( 'Cache-Control: private, no-store, max-age=0' );
			header( 'Referrer-Policy: no-referrer' );
		}
	},
	0
);
/**
 * Outbound mail defaults to blocked; only explicit test mailboxes allowed.
 * UOP_STAGING_ALLOWED_RECIPIENTS is an optional CSV in staging wp-config.php.
 */
add_filter(
	'pre_wp_mail',
	static function ( $pre, array $args ) {
		if ( null !== $pre ) {
			return $pre;
		}
		$raw = defined( 'UOP_STAGING_ALLOWED_RECIPIENTS' )
			? (string) UOP_STAGING_ALLOWED_RECIPIENTS
			: '';
		$allowed = array_filter( array_map( 'strtolower', array_map( 'trim', explode( ',', $raw ) ) ) );
		if ( ! $allowed ) {
			return false;
		}
		$to = $args['to'] ?? array();
		$recipients = is_array( $to ) ? $to : explode( ',', (string) $to );
		if ( ! $recipients ) {
			return false;
		}
		foreach ( $recipients as $recipient ) {
			$email = trim( (string) $recipient );
			if ( preg_match( '/<([^<>]+)>/', $email, $match ) ) {
				$email = $match[1];
			}
			if ( ! is_email( $email ) || ! in_array( strtolower( $email ), $allowed, true ) ) {
				return false;
			}
		}
		return null;
	},
	10,
	2
);
