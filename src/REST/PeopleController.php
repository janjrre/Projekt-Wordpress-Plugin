<?php
/**
 * Authenticated person and self/delegation REST facade.
 *
 * @package UOP
 */

namespace UOP\REST;

use InvalidArgumentException;
use RuntimeException;
use UOP\Application\Identity\PersonService;
use UOP\Application\Query\M6ReadService;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use WP_Error;
use WP_REST_Request;

/** Auth never follows email addresses; only linked or live delegated people. */
final class PeopleController extends BaseController {
	/**
	 * Shared API contract or operation.
	 *
	 * @param M6ReadService $reads  Field-policy filtered DTOs.
	 * @param PersonService $people Audited, optimistic edit commands.
	 */
	public function __construct( private M6ReadService $reads, private PersonService $people ) {}

	/** Register narrowly scoped person routes. */
	public function register(): void {
		$this->register_endpoint( '/me/persons', 'GET', array( $this, 'my_people' ), array( $this, 'authenticated' ) );
		$this->register_endpoint( '/people/(?P<uuid>[0-9a-f-]{36})', 'GET', array( $this, 'person' ), array( $this, 'can_view' ), $this->uuid_argument() );
		$this->register_endpoint( '/people/(?P<uuid>[0-9a-f-]{36})', 'PATCH', array( $this, 'patch' ), array( $this, 'can_view' ), $this->uuid_argument() );
	}

	/** @return bool|WP_Error */
	public function authenticated(): bool|WP_Error {
		return get_current_user_id() > 0 ? true : $this->denied();
	}

	/**
	 * Shared API contract or operation.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return bool|WP_Error
	 */
	public function can_view( WP_REST_Request $request ): bool|WP_Error {
		$scope = $this->organization_scope();
		$id    = $this->request_public_id( $request );
		return $scope && $id && null !== $this->reads->person( $this->current_actor(), $scope, $id ) ? true : $this->denied( true );
	}

	/** @return array<string, mixed>|WP_Error */
	public function my_people(): array|WP_Error {
		$scope = $this->organization_scope();
		return $scope ? array( 'items' => $this->reads->my_people( $this->current_actor(), $scope ) ) : RestError::for_kind( 'unavailable' );
	}

	/**
	 * Shared API contract or operation.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return array<string, mixed>|WP_Error
	 */
	public function person( WP_REST_Request $request ): array|WP_Error {
		$scope = $this->organization_scope();
		$id    = $this->request_public_id( $request );
		return $scope && $id ? ( $this->reads->person( $this->current_actor(), $scope, $id ) ?? RestError::for_kind( 'not_found' ) ) : RestError::for_kind( 'not_found' );
	}

	/**
	 * Only a versioned display-name change; never a user link, role or email edit.
	 *
	 * @param WP_REST_Request $request Incoming JSON.
	 * @return array<string, mixed>|WP_Error
	 */
	public function patch( WP_REST_Request $request ): array|WP_Error {
		try {
			$body = $this->strict_json_object(
				$request,
				array(
					'display_name' => array(
						'type'      => 'string',
						'minLength' => 1,
						'maxLength' => 191,
					),
					'version'      => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
				array( 'display_name', 'version' )
			);
			$scope = $this->organization_scope();
			$id    = $this->request_public_id( $request );
			if ( ! $scope || ! $id ) {
				return RestError::for_kind( 'not_found' );
			}
			$this->people->rename( $this->current_actor(), $scope, $id, $body['version'], $body['display_name'], gmdate( 'Y-m-d H:i:s' ), CorrelationId::generate() );
			return $this->person( $request );
		} catch ( InvalidArgumentException ) {
			return RestError::for_kind( 'invalid_schema' );
		} catch ( RuntimeException ) {
			return RestError::for_kind( 'conflict' );
		}
	}
}
