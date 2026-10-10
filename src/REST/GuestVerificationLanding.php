<?php
/**
 * HTTPS-only, no-referrer guest email verification landing.
 *
 * @package UOP
 */

namespace UOP\REST;

/** Email URL fragment is read locally; a button explicitly POSTs to REST. */
final class GuestVerificationLanding {
	/** Render the dedicated public landing only for the exact safe query. */
	public function maybe_render(): void {
		if ( ! isset( $_GET['uop-verify'] ) || '1' !== $_GET['uop-verify'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only landing.
			return;
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow, noarchive' );
		header( 'Referrer-Policy: no-referrer' );
		header( 'Cache-Control: private, no-store, max-age=0' );
		$url = plugin_dir_url( dirname( __DIR__, 2 ) . '/uop-core.php' );
		$version = defined( 'UOP_CORE_VERSION' ) ? (string) constant( 'UOP_CORE_VERSION' ) : '0.1.0-alpha.2';
		wp_enqueue_script( 'uop-guest-verification', $url . 'assets/m6-guest-verification.js', array(), $version, true );
		?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="referrer" content="no-referrer">
<title><?php esc_html_e( 'Email verification', 'uop-core' ); ?></title>
<style>body{font-family:system-ui,sans-serif;max-width:42rem;margin:4rem auto;padding:1.5rem;line-height:1.6}button{min-height:44px;padding:.65rem 1rem;font:inherit;border-radius:.3rem}button:focus-visible{outline:3px solid currentColor;outline-offset:3px}@media(max-width:480px){body{margin:1rem auto}}</style>
</head>
<body>
<main id="uop-guest-verification"
	data-endpoint="<?php echo esc_url( rest_url( 'uop/v1/registration-verifications' ) ); ?>"
	data-invalid="<?php echo esc_attr__( 'This verification link is incomplete. Request a new link from the organizer.', 'uop-core' ); ?>"
	data-processing="<?php echo esc_attr__( 'Checking your confirmation…', 'uop-core' ); ?>"
	data-received="<?php echo esc_attr__( 'Your confirmation request has been received. If the link was valid and unused, your address is now confirmed.', 'uop-core' ); ?>">
<h1><?php esc_html_e( 'Confirm email address', 'uop-core' ); ?></h1>
<p><?php esc_html_e( 'To confirm that you control the address used for your registration, press the button below. This does not create a WordPress account.', 'uop-core' ); ?></p>
<button type="button"><?php esc_html_e( 'Confirm email', 'uop-core' ); ?></button>
<p role="status" aria-live="polite"></p>
</main>
<?php wp_print_scripts( 'uop-guest-verification' ); ?>
</body>
</html>
		<?php
		exit;
	}
}
