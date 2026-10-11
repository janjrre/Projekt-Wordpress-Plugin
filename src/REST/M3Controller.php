<?php
/**
 * M3 read-only DTO endpoints and draft authoring routes.
 *
 * @package UOP
 */

namespace UOP\REST;

use InvalidArgumentException;
use RuntimeException;
use UOP\Application\Form\FormService;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyObject;
use UOP\Application\Policy\PolicyService;
use UOP\Application\Query\M3ReadService;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;
use WP_Error;
use WP_REST_Request;

/** REST controller holds no repositories and no direct SQL. */
final class M3Controller extends BaseController {
	/**
	 * Compose authorization, reads, and commands via application services.
	 *
	 * @param M3ReadService $reads  Projected safe read DTOs.
	 * @param FormService   $forms  Authorized form draft/publish commands.
	 * @param PolicyService $policy Central permissions for route guards.
	 */
	public function __construct( private M3ReadService $reads, private FormService $forms, private PolicyService $policy ) {}

	/** Register only known DTO routes through the uniform permission boundary. */
	public function register(): void {
		$this->register_endpoint( '/forms', 'GET', array( $this, 'forms' ), array( $this, 'can_manage_forms' ) );
		$this->register_endpoint( '/forms', 'POST', array( $this, 'create_form' ), array( $this, 'can_manage_forms' ) );
		$this->register_endpoint( '/forms/(?P<uuid>[0-9a-f-]{36})', 'GET', array( $this, 'form' ), array( $this, 'can_view_form' ), $this->uuid_argument() );
		$this->register_endpoint( '/forms/(?P<uuid>[0-9a-f-]{36})/draft', 'PUT', array( $this, 'save_form' ), array( $this, 'can_view_form' ), $this->uuid_argument() );
		$this->register_endpoint( '/forms/(?P<uuid>[0-9a-f-]{36})/publish', 'POST', array( $this, 'publish_form' ), array( $this, 'can_view_form' ), $this->uuid_argument() );
		$this->register_endpoint( '/events', 'GET', array( $this, 'events' ), array( $this, 'can_manage_events' ) );
		$this->register_endpoint( '/events/(?P<uuid>[0-9a-f-]{36})', 'GET', array( $this, 'event' ), array( $this, 'can_view_event' ), $this->uuid_argument() );
		$this->register_endpoint( '/people/(?P<uuid>[0-9a-f-]{36})/profile', 'GET', array( $this, 'profile' ), array( $this, 'can_view_profile' ), $this->uuid_argument() );
	}

	/**
	 * Check organization-wide form authoring scope for form collection.
	 *
	 * @return bool|WP_Error
	 */
	public function can_manage_forms(): bool|WP_Error {
		$scope = $this->scope();
		return null !== $scope && $this->policy->can( $this->actor(), 'form.manage', new PolicyObject( $scope->id, 'organization', $scope->id ) )->allowed ? true : $this->denied();
	}

	/**
	 * Verify scope and object before disclosing or modifying a form.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return bool|WP_Error
	 */
	public function can_view_form( WP_REST_Request $request ): bool|WP_Error {
		$scope = $this->scope();
		$uuid  = $this->uuid( $request );
		return null !== $scope && null !== $uuid && null !== $this->reads->form( $this->actor(), $scope, $uuid ) ? true : $this->denied( true );
	}

	/**
	 * Ensure event collection read authorization.
	 *
	 * @return bool|WP_Error
	 */
	public function can_manage_events(): bool|WP_Error {
		$scope = $this->scope();
		return null !== $scope && $this->policy->can( $this->actor(), 'event.manage', new PolicyObject( $scope->id, 'organization', $scope->id ) )->allowed ? true : $this->denied();
	}

	/**
	 * Ensure object-level event permission.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return bool|WP_Error
	 */
	public function can_view_event( WP_REST_Request $request ): bool|WP_Error {
		$scope = $this->scope();
		$uuid  = $this->uuid( $request );
		return null !== $scope && null !== $uuid && null !== $this->reads->event( $this->actor(), $scope, $uuid ) ? true : $this->denied( true );
	}

	/**
	 * Hide inaccessible profile objects and fields from API clients.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return bool|WP_Error
	 */
	public function can_view_profile( WP_REST_Request $request ): bool|WP_Error {
		$scope = $this->scope();
		$uuid  = $this->uuid( $request );
		return null !== $scope && null !== $uuid && null !== $this->reads->profile( $this->actor(), $scope, $uuid ) ? true : $this->denied( true );
	}

	/**
	 * Project a bounded organization form list.
	 *
	 * @return array<string, mixed>
	 */
	public function forms(): array {
		return array( 'items' => $this->reads->forms( $this->actor(), $this->required_scope() ) );
	}

	/**
	 * Return one sanitized, scope-checked form editor DTO.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return array<string, mixed>
	 */
	public function form( WP_REST_Request $request ): array {
		return (array) $this->reads->form( $this->actor(), $this->required_scope(), $this->required_uuid( $request ) );
	}

	/**
	 * Create a form using an explicit allowlist for mutable author properties.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create_form( WP_REST_Request $request ): array|WP_Error {
		try {
			$body = $this->body( $request, array( 'key', 'title', 'context', 'draft' ) );
			if ( ! is_string( $body['key'] ) || ! is_string( $body['title'] ) || ! is_string( $body['context'] ) || ! is_array( $body['draft'] ) ) {
				return RestError::for_kind( 'invalid_schema' );
			}
			$uuid = $this->forms->create( $this->actor(), $this->required_scope(), $body['key'], $body['title'], $body['context'], $body['draft'], gmdate( 'Y-m-d H:i:s' ), CorrelationId::generate() );
			return (array) $this->reads->form( $this->actor(), $this->required_scope(), $uuid );
		} catch ( InvalidArgumentException $error ) {
			return RestError::for_kind( 'invalid_schema' );
		} catch ( RuntimeException $error ) {
			return RestError::for_kind( 'validation' );
		}
	}

	/**
	 * Save only draft schema when its observed revision is current.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return array<string, mixed>|WP_Error
	 */
	public function save_form( WP_REST_Request $request ): array|WP_Error {
		try {
			$body = $this->body( $request, array( 'revision', 'draft' ) );
			if ( ! is_int( $body['revision'] ) || ! is_array( $body['draft'] ) ) {
				return RestError::for_kind( 'invalid_schema' );
			}
			$this->forms->save_draft( $this->actor(), $this->required_scope(), $this->required_uuid( $request ), $body['revision'], $body['draft'], gmdate( 'Y-m-d H:i:s' ), CorrelationId::generate() );
			return $this->form( $request );
		} catch ( RuntimeException $error ) {
			return RestError::for_kind( 'conflict' );
		} catch ( InvalidArgumentException $error ) {
			return RestError::for_kind( 'invalid_schema' );
		}
	}

	/**
	 * Publish a new immutable snapshot without accepting client-owned metadata.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return array<string, mixed>|WP_Error
	 */
	public function publish_form( WP_REST_Request $request ): array|WP_Error {
		try {
			$body = $this->body( $request, array( 'revision' ) );
			if ( ! is_int( $body['revision'] ) ) {
				return RestError::for_kind( 'invalid_schema' );
			}
			$this->forms->publish( $this->actor(), $this->required_scope(), $this->required_uuid( $request ), $body['revision'], gmdate( 'Y-m-d H:i:s' ), CorrelationId::generate() );
			return $this->form( $request );
		} catch ( InvalidArgumentException $error ) {
			return RestError::for_kind( 'invalid_schema' );
		} catch ( RuntimeException $error ) {
			return RestError::for_kind( 'conflict' );
		}
	}

	/**
	 * Return authorized event summaries.
	 *
	 * @return array<string, mixed>
	 */
	public function events(): array {
		return array( 'items' => $this->reads->events( $this->actor(), $this->required_scope() ) );
	}

	/**
	 * Return authorized event and occurrence read model.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return array<string, mixed>
	 */
	public function event( WP_REST_Request $request ): array {
		return (array) $this->reads->event( $this->actor(), $this->required_scope(), $this->required_uuid( $request ) );
	}

	/**
	 * Return the single centrally projected profile read model.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return array<string, mixed>
	 */
	public function profile( WP_REST_Request $request ): array {
		return (array) $this->reads->profile( $this->actor(), $this->required_scope(), $this->required_uuid( $request ) );
	}

	/**
	 * Parse a JSON object, rejecting unknown server-owned properties.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @param array           $keys Exact required keys.
	 * @phpstan-param list<string> $keys
	 * @return array<string, mixed>
	 * @throws InvalidArgumentException For invalid transport shape.
	 */
	private function body( WP_REST_Request $request, array $keys ): array {
		$definitions = array(
			'key'      => array( 'type' => 'string' ),
			'title'    => array( 'type' => 'string' ),
			'context'  => array( 'type' => 'string' ),
			'draft'    => array( 'type' => 'object' ),
			'revision' => array( 'type' => 'integer' ),
		);
		return $this->strict_json_object( $request, array_intersect_key( $definitions, array_flip( $keys ) ), $keys );
	}

	/**
	 * Read the current WP actor without trusting client-provided identities.
	 *
	 * @return Actor
	 */
	private function actor(): Actor {
		return new Actor( get_current_user_id() );
	}

	/**
	 * Scope routes to the active site organization, never a request field.
	 *
	 * @return OrgScope|null
	 */
	private function scope(): ?OrgScope {
		$id = (int) get_option( 'uop_default_organization_id', 0 );
		return $id > 0 ? new OrgScope( $id ) : null;
	}

	/**
	 * Enforce that the installation has a valid owner organization.
	 *
	 * @return OrgScope
	 * @throws RuntimeException For unconfigured installation.
	 */
	private function required_scope(): OrgScope {
		$scope = $this->scope();
		if ( ! $scope ) {
			throw new RuntimeException( 'Missing organization scope.' );
		}
		return $scope;
	}

	/**
	 * Decode a client UUID without leaking existence on malformed IDs.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return PublicId|null
	 */
	private function uuid( WP_REST_Request $request ): ?PublicId {
		try {
			return PublicId::from_string( (string) $request['uuid'] );
		} catch ( InvalidArgumentException $error ) {
			return null;
		}
	}

	/**
	 * Require a validated UUID after the permission callback.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return PublicId
	 * @throws RuntimeException If input lacks valid UUID.
	 */
	private function required_uuid( WP_REST_Request $request ): PublicId {
		$uuid = $this->uuid( $request );
		if ( null === $uuid ) {
			throw new RuntimeException( 'Invalid resource.' );
		}
		return $uuid;
	}
}
