<?php
/**
 * Shared REST transport boundary for the versioned UOP API.
 *
 * @package UOP
 */

namespace UOP\REST;

use InvalidArgumentException;
use JsonException;
use UOP\Application\Policy\Actor;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;
use WP_REST_Request;

/** No repository access or domain mutations belong in transport controllers. */
abstract class BaseController {
	/**
	 * Register a route only with an explicit permission callback.
	 *
	 * @param string               $path       Path under uop/v1.
	 * @param string               $method     HTTP method.
	 * @param callable             $callback   Handler using an application service.
	 * @param callable             $permission Per-request permission check.
	 * @param array<string, mixed> $args       WordPress request parameter schema.
	 */
	protected function register_endpoint( string $path, string $method, callable $callback, callable $permission, array $args = array() ): void {
		register_rest_route(
			'uop/v1',
			$path,
			array(
				'methods'             => $method,
				'callback'            => $callback,
				'permission_callback' => $permission,
				'args'                => $args,
			)
		);
	}

	/**
	 * Validate the entire JSON object, including rejecting unknown attributes.
	 * A WordPress param schema alone does not prevent mass assignment.
	 *
	 * @param WP_REST_Request      $request    Request body.
	 * @param array<string, mixed> $properties Explicit accepted DTO properties.
	 * @param array                $required   Required property keys.
	 * @phpstan-param list<string> $required
	 * @return array<string, mixed> Validated, still-untrusted DTO for application services.
	 * @throws InvalidArgumentException On malformed JSON, unexpected keys, or type errors.
	 */
	protected function strict_json_object( WP_REST_Request $request, array $properties, array $required ): array {
		$raw = $request->get_body();
		if ( ! str_starts_with( ltrim( $raw ), '{' ) ) {
			throw new InvalidArgumentException( 'Expected a JSON object.' );
		}
		try {
			$data = json_decode( $raw, true, 64, JSON_THROW_ON_ERROR );
		} catch ( JsonException ) {
			throw new InvalidArgumentException( 'Malformed JSON body.' );
		}
		if ( ! is_array( $data ) || array_diff( array_keys( $data ), array_keys( $properties ) ) || array_diff( $required, array_keys( $data ) ) ) {
			throw new InvalidArgumentException( 'Unknown or missing DTO attributes.' );
		}
		foreach ( $data as $key => $value ) {
			$type = $properties[ $key ]['type'] ?? '';
			$valid_type = match ( $type ) {
				'string'  => is_string( $value ),
				'integer' => is_int( $value ),
				'boolean' => is_bool( $value ),
				'array'   => is_array( $value ) && array_is_list( $value ),
				'object'  => is_array( $value ) && ( array() === $value || ! array_is_list( $value ) ),
				default   => false,
			};
			if ( ! $valid_type || is_wp_error( rest_validate_value_from_schema( $value, $properties[ $key ], $key ) ) ) {
				throw new InvalidArgumentException( 'JSON body did not match the request schema.' );
			}
		}
		return $data;
	}

	/**
	 * Return a stable denial without disclosing hidden object existence.
	 *
	 * @param bool $hidden Hide unauthorized resource as a 404.
	 * @return \WP_Error Safe API error.
	 */
	protected function denied( bool $hidden = false ): \WP_Error {
		return RestError::for_kind( 0 === get_current_user_id() ? 'unauthenticated' : ( $hidden ? 'not_found' : 'forbidden' ) );
	}

	/**
	 * Return server-authenticated identity, never a client-supplied user ID.
	 *
	 * @return Actor Actor for current request.
	 */
	protected function current_actor(): Actor {
		return new Actor( get_current_user_id() );
	}

	/**
	 * Derive the single tenant scope from server configuration.
	 *
	 * @return OrgScope|null Installed organization.
	 */
	protected function organization_scope(): ?OrgScope {
		$id = (int) get_option( 'uop_default_organization_id', 0 );
		return $id > 0 ? new OrgScope( $id ) : null;
	}

	/**
	 * Convert the public route identifier without exposing internal PKs.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @param string          $key     Path parameter name.
	 * @return PublicId|null Parsed UUID or null for invalid input.
	 */
	protected function request_public_id( WP_REST_Request $request, string $key = 'uuid' ): ?PublicId {
		try {
			return PublicId::from_string( (string) $request[ $key ] );
		} catch ( InvalidArgumentException ) {
			return null;
		}
	}

	/**
	 * Standard schema for URL identifiers; domain validation runs after permission.
	 *
	 * @return array<string, mixed> WordPress REST argument definition.
	 */
	protected function uuid_argument(): array {
		return array(
			'uuid' => array(
				'description' => 'Public resource UUID.',
				'type'        => 'string',
				'required'    => true,
			),
		);
	}
}
