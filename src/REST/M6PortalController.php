<?php
/**
 * M6-07 participant portal read-only transport.
 *
 * @package UOP
 */

namespace UOP\REST;

use InvalidArgumentException;
use UOP\Application\Query\M6PortalReadService;
use UOP\Core\PublicId;
use WP_Error;
use WP_REST_Request;

/** Reuse the existing write endpoints to enforce audit, locking and revocation. */
final class M6PortalController extends BaseController {
	/**
	 * Bind the live, object-checked portal read service.
	 *
	 * @param M6PortalReadService $reads Minimal per-person and per-registration DTOs.
	 */
	public function __construct( private M6PortalReadService $reads ) {}

	/** Register only authenticated portal reads. */
	public function register(): void {
		$this->register_endpoint( '/me/portal', 'GET', array( $this, 'subjects' ), array( $this, 'authenticated' ) );
		$this->register_endpoint( '/me/portal/registrations', 'GET', array( $this, 'registrations' ), array( $this, 'authenticated' ) );
	}

	/**
	 * A logged-in WordPress account is required, regardless of cached HTML.
	 *
	 * @return bool|WP_Error
	 */
	public function authenticated(): bool|WP_Error {
		return get_current_user_id() > 0 ? true : $this->denied();
	}

	/**
	 * List only the active account's authorized subjects.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function subjects(): array|WP_Error {
		$scope = $this->organization_scope();
		return $scope ? $this->reads->subjects( $this->current_actor(), $scope ) : RestError::for_kind( 'unavailable' );
	}

	/**
	 * Page one selected subject; invalid or revoked grants are indistinguishable.
	 *
	 * @param WP_REST_Request $request Validated GET query.
	 * @return array<string,mixed>|WP_Error
	 */
	public function registrations( WP_REST_Request $request ): array|WP_Error {
		try {
			$person = $request->get_param( 'person_id' );
			$after  = $request->get_param( 'after' );
			if ( ! is_string( $person ) || ( null !== $after && ! is_string( $after ) ) ) {
				return RestError::for_kind( 'invalid_schema' );
			}
			$scope = $this->organization_scope();
			if ( ! $scope ) {
				return RestError::for_kind( 'unavailable' );
			}
			$page = $this->reads->registrations(
				$this->current_actor(),
				$scope,
				PublicId::from_string( $person ),
				null === $after ? null : PublicId::from_string( $after )
			);
			return $page ?? RestError::for_kind( 'not_found' );
		} catch ( InvalidArgumentException ) {
			return RestError::for_kind( 'invalid_schema' );
		}
	}
}
