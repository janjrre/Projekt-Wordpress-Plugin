<?php
/**
 * Stable REST error-code and HTTP-status contract.
 *
 * @package UOP
 */

namespace UOP\REST;

use WP_Error;

/** Exposes safe client messages; exception details must never leave the server. */
final class RestError {
	/**
	 * Construct one of the versioned API's known errors.
	 *
	 * @param string $kind Semantic failure reason.
	 * @return WP_Error Standard WordPress REST error envelope.
	 */
	public static function for_kind( string $kind ): WP_Error {
		$errors = array(
			'invalid_schema' => array( 400, 'Request does not match the expected schema.' ),
			'unauthenticated' => array( 401, 'Authentication required.' ),
			'forbidden'      => array( 403, 'Not permitted.' ),
			'not_found'      => array( 404, 'Resource not found.' ),
			'conflict'       => array( 409, 'The resource has changed or the action conflicts with its current state.' ),
			'validation'     => array( 422, 'The request could not be accepted.' ),
			'rate_limited'   => array( 429, 'Too many requests.' ),
			'internal'       => array( 500, 'Unexpected server error.' ),
			'unavailable'    => array( 503, 'Required service is currently unavailable.' ),
		);
		if ( ! isset( $errors[ $kind ] ) ) {
			$kind = 'internal';
		}
		return new WP_Error(
			'uop_' . $kind,
			$errors[ $kind ][1],
			array( 'status' => $errors[ $kind ][0] )
		);
	}
}
