<?php
/**
 * HTTPS reverse-proxy request marker for DISPOSABLE local CI server only.
 * Not bundled with, or installed into, the WordPress plugin.
 */
if ( 'https' === ( $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '' ) &&
	'127.0.0.1' === ( $_SERVER['REMOTE_ADDR'] ?? '' ) ) {
	$_SERVER['HTTPS']      = 'on';
	$_SERVER['SERVER_PORT'] = '443';
}
return false;
