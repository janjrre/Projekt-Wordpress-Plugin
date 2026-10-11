<?php
/**
 * Organization manager only M6-04 keyset listing REST transport.
 *
 * @package UOP
 */

namespace UOP\REST;

use InvalidArgumentException;
use UOP\Application\Query\M6AdminReadService;
use UOP\Core\PublicId;
use WP_Error;
use WP_REST_Request;

/** Administrative list routes cannot be reached via participant self/delegation. */
final class M6AdminController extends BaseController {
	/**
	 * Bind the trusted managerial read model.
	 *
	 * @param M6AdminReadService $reads Live authorization and DTO projection.
	 */
	public function __construct( private M6AdminReadService $reads ) {}

	/** Register only administrative read endpoints with separate live guards. */
	public function register(): void {
		$this->register_endpoint( '/admin/people', 'GET', array( $this, 'people' ), array( $this, 'can_people' ) );
		$this->register_endpoint( '/admin/registrations', 'GET', array( $this, 'registrations' ), array( $this, 'can_registrations' ) );
	}

	/**
	 * Guard organization-wide person enumeration.
	 *
	 * @return bool|WP_Error
	 */
	public function can_people(): bool|WP_Error {
		$scope = $this->organization_scope();
		return $scope && $this->reads->can_list( $this->current_actor(), $scope, 'people' ) ? true : $this->denied();
	}

	/**
	 * Guard organization-wide registration enumeration.
	 *
	 * @return bool|WP_Error
	 */
	public function can_registrations(): bool|WP_Error {
		$scope = $this->organization_scope();
		return $scope && $this->reads->can_list( $this->current_actor(), $scope, 'registrations' ) ? true : $this->denied();
	}

	/**
	 * Return staff-visible persons with non-enumerable foreign cursors.
	 *
	 * @param WP_REST_Request $request Optional opaque 'after' cursor.
	 * @return array<string,mixed>|WP_Error
	 */
	public function people( WP_REST_Request $request ): array|WP_Error {
		try {
			$after = $request->get_param( 'after' );
			if ( null !== $after && ! is_string( $after ) ) {
				return RestError::for_kind( 'invalid_schema' );
			}
			$scope = $this->organization_scope();
			if ( ! $scope ) {
				return RestError::for_kind( 'unavailable' );
			}
			return $this->reads->people( $this->current_actor(), $scope, null === $after ? null : PublicId::from_string( $after ) ) ?? RestError::for_kind( 'not_found' );
		} catch ( InvalidArgumentException ) {
			return RestError::for_kind( 'invalid_schema' );
		}
	}

	/**
	 * Return approved status-filtered registration summaries.
	 *
	 * @param WP_REST_Request $request Optional status and opaque cursor.
	 * @return array<string,mixed>|WP_Error
	 */
	public function registrations( WP_REST_Request $request ): array|WP_Error {
		try {
			$after  = $request->get_param( 'after' );
			$status = $request->get_param( 'status' );
			if ( ( null !== $after && ! is_string( $after ) ) || ( null !== $status && ! is_string( $status ) ) ) {
				return RestError::for_kind( 'invalid_schema' );
			}
			$scope = $this->organization_scope();
			if ( ! $scope ) {
				return RestError::for_kind( 'unavailable' );
			}
			return $this->reads->registrations( $this->current_actor(), $scope, null === $after ? null : PublicId::from_string( $after ), $status ?? '' ) ?? RestError::for_kind( 'not_found' );
		} catch ( InvalidArgumentException ) {
			return RestError::for_kind( 'invalid_schema' );
		}
	}
}
