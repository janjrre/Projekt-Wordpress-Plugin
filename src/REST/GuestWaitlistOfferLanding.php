<?php
/**
 * No-cache guest waitlist-offer confirmation landing.
 *
 * @package UOP
 */

namespace UOP\REST;

/** Bearer fragments are never included in the page request or browser referrer. */
final class GuestWaitlistOfferLanding {
	/** Public read-only page; state change requires an explicit POST. */
	public function maybe_render(): void {
		if ( ! isset( $_GET['uop-offer'] ) || '1' !== $_GET['uop-offer'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only route.
			return;
		}
		if ( ! is_ssl() ) {
			nocache_headers();
			status_header( 403 );
			wp_die( esc_html__( 'HTTPS is required to accept a waitlist offer.', 'uop-core' ), '', array( 'response' => 403 ) );
		}
		nocache_headers();
		header( 'Referrer-Policy: no-referrer' );
		header( 'X-Robots-Tag: noindex, nofollow, noarchive' );
		header( 'Cache-Control: private, no-store, max-age=0' );
		$url     = plugin_dir_url( dirname( __DIR__, 2 ) . '/uop-core.php' );
		$version = defined( 'UOP_CORE_VERSION' ) ? (string) constant( 'UOP_CORE_VERSION' ) : '0.1.0-alpha.2';
		wp_enqueue_script( 'uop-waitlist-offer', $url . 'assets/m6-waitlist-offer.js', array(), $version, false );
		?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="referrer" content="no-referrer">
<title><?php esc_html_e( 'Waitlist offer', 'uop-core' ); ?></title>
<style>body{font-family:system-ui,sans-serif;max-width:42rem;margin:4rem auto;padding:1.5rem;line-height:1.6}button{min-height:44px;padding:.65rem 1rem;font:inherit;border-radius:.3rem}button:focus-visible{outline:3px solid currentColor;outline-offset:3px}@media(max-width:480px){body{margin:1rem auto}}</style>
</head>
<body>
<main id="uop-waitlist-offer"
	data-endpoint="<?php echo esc_url( set_url_scheme( rest_url( 'uop/v1/waitlist-offers/guest-accept' ), 'https' ) ); ?>"
	data-invalid="<?php echo esc_attr__( 'This offer link is incomplete. Contact the organizer.', 'uop-core' ); ?>"
	data-processing="<?php echo esc_attr__( 'Checking your offer…', 'uop-core' ); ?>"
	data-received="<?php echo esc_attr__( 'Your offer confirmation has been received. If the offer was valid and available, your place is now reserved.', 'uop-core' ); ?>">
<h1><?php esc_html_e( 'Accept your place', 'uop-core' ); ?></h1>
<p><?php esc_html_e( 'A place was held temporarily for your verified email address. Confirm below to accept it before the stated deadline.', 'uop-core' ); ?></p>
<button type="button"><?php esc_html_e( 'Accept place', 'uop-core' ); ?></button>
<p role="status" aria-live="polite"></p>
</main>
		<?php wp_print_scripts( 'uop-waitlist-offer' ); ?>
</body>
</html>
		<?php
		exit;
	}
}
