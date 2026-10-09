<?php
/**
 * M6-03 strictly scoped operational and immutable consent REST boundary.
 *
 * @package UOP
 */

namespace UOP\REST;

use InvalidArgumentException;
use RuntimeException;
use UOP\Application\Consent\ConsentDefinitionService;
use UOP\Application\Consent\ConsentRecordService;
use UOP\Application\Query\M6OperationsReadService;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/** Delegates every mutation to audited transactional M5 services. */
final class M6OperationsController extends BaseController {
	/**
	 * Bind shared projection and existing application commands.
	 *
	 * @param M6OperationsReadService $reads       Live, policy-projected views.
	 * @param ConsentDefinitionService $definitions Approved immutable documents.
	 * @param ConsentRecordService     $records     Authorized withdrawal service.
	 */
	public function __construct(
		private M6OperationsReadService $reads,
		private ConsentDefinitionService $definitions,
		private ConsentRecordService $records
	) {}

	/** Declare explicit REST permissions and public UUID request parameters. */
	public function register(): void {
		$this->register_endpoint( '/events/(?P<uuid>[0-9a-f-]{36})/capacity', 'GET', array( $this, 'capacity' ), array( $this, 'can_capacity' ), $this->uuid_argument() );
		$this->register_endpoint( '/registrations/(?P<uuid>[0-9a-f-]{36})/consents', 'GET', array( $this, 'consents' ), array( $this, 'can_consents' ), $this->uuid_argument() );
		$this->register_endpoint( '/consents/(?P<uuid>[0-9a-f-]{36})', 'GET', array( $this, 'consent' ), array( $this, 'can_consent' ), $this->uuid_argument() );
		$this->register_endpoint( '/consents/(?P<uuid>[0-9a-f-]{36})/withdrawals', 'POST', array( $this, 'withdraw' ), array( $this, 'can_withdraw' ), $this->uuid_argument() );
		$this->register_endpoint( '/consent-definitions', 'POST', array( $this, 'definition' ), array( $this, 'can_privacy' ) );
		$this->register_endpoint( '/consent-definitions/(?P<uuid>[0-9a-f-]{36})/versions', 'POST', array( $this, 'publish' ), array( $this, 'can_privacy' ), $this->uuid_argument() );
		$this->register_endpoint( '/consent-versions/(?P<uuid>[0-9a-f-]{36})', 'GET', array( $this, 'version' ), array( $this, 'can_privacy' ), $this->uuid_argument() );
		$this->register_endpoint( '/audit', 'GET', array( $this, 'audit' ), array( $this, 'can_audit' ) );
	}

	/**
	 * Check a capacity manager for one existing event.
	 *
	 * @param WP_REST_Request $request Requested event.
	 * @return bool|WP_Error
	 */
	public function can_capacity( WP_REST_Request $request ): bool|WP_Error {
		$scope = $this->organization_scope();
		$id    = $this->request_public_id( $request );
		return $scope && $id && null !== $this->reads->capacity( $this->current_actor(), $scope, $id ) ? true : $this->denied( true );
	}

	/**
	 * Check current registration-specific viewing rights.
	 *
	 * @param WP_REST_Request $request Requested registration.
	 * @return bool|WP_Error
	 */
	public function can_consents( WP_REST_Request $request ): bool|WP_Error {
		$scope = $this->organization_scope();
		$id    = $this->request_public_id( $request );
		return $scope && $id && null !== $this->reads->registration_consents( $this->current_actor(), $scope, $id ) ? true : $this->denied( true );
	}

	/**
	 * Check read access to the consent's actual registration.
	 *
	 * @param WP_REST_Request $request Requested consent.
	 * @return bool|WP_Error
	 */
	public function can_consent( WP_REST_Request $request ): bool|WP_Error {
		$scope = $this->organization_scope();
		$id    = $this->request_public_id( $request );
		return $scope && $id && null !== $this->reads->consent( $this->current_actor(), $scope, $id ) ? true : $this->denied( true );
	}

	/**
	 * Check withdrawal authority separately from mere viewing.
	 *
	 * @param WP_REST_Request $request Requested consent.
	 * @return bool|WP_Error
	 */
	public function can_withdraw( WP_REST_Request $request ): bool|WP_Error {
		$scope = $this->organization_scope();
		$id    = $this->request_public_id( $request );
		return $scope && $id && null !== $this->reads->consent( $this->current_actor(), $scope, $id, 'registration.cancel' ) ? true : $this->denied( true );
	}

	/**
	 * Check privacy document administrators without exposing document UUIDs.
	 *
	 * @return bool|WP_Error
	 */
	public function can_privacy(): bool|WP_Error {
		$scope = $this->organization_scope();
		return $scope && $this->reads->allowed( $this->current_actor(), $scope, 'privacy.manage' ) ? true : $this->denied();
	}

	/**
	 * Check organization-wide audit read authority.
	 *
	 * @return bool|WP_Error
	 */
	public function can_audit(): bool|WP_Error {
		$scope = $this->organization_scope();
		return $scope && $this->reads->allowed( $this->current_actor(), $scope, 'audit.view' ) ? true : $this->denied();
	}

	/**
	 * Return non-binding seat counts, never offer tokens or waitlist contacts.
	 *
	 * @param WP_REST_Request $request Event.
	 * @return array<string,mixed>|WP_Error
	 */
	public function capacity( WP_REST_Request $request ): array|WP_Error {
		$scope = $this->organization_scope();
		$id    = $this->request_public_id( $request );
		return $scope && $id ? ( $this->reads->capacity( $this->current_actor(), $scope, $id ) ?? RestError::for_kind( 'not_found' ) ) : RestError::for_kind( 'not_found' );
	}

	/**
	 * Return only version-pinned evidence for a visible registration.
	 *
	 * @param WP_REST_Request $request Registration.
	 * @return array<string,mixed>|WP_Error
	 */
	public function consents( WP_REST_Request $request ): array|WP_Error {
		$scope = $this->organization_scope();
		$id    = $this->request_public_id( $request );
		return $scope && $id ? ( $this->reads->registration_consents( $this->current_actor(), $scope, $id ) ?? RestError::for_kind( 'not_found' ) ) : RestError::for_kind( 'not_found' );
	}

	/**
	 * Return one authorized immutable decision.
	 *
	 * @param WP_REST_Request $request Evidence.
	 * @return array<string,mixed>|WP_Error
	 */
	public function consent( WP_REST_Request $request ): array|WP_Error {
		$scope = $this->organization_scope();
		$id    = $this->request_public_id( $request );
		return $scope && $id ? ( $this->reads->consent( $this->current_actor(), $scope, $id ) ?? RestError::for_kind( 'not_found' ) ) : RestError::for_kind( 'not_found' );
	}

	/**
	 * Append a new withdrawal through the M5 service without editing past evidence.
	 *
	 * @param WP_REST_Request $request One idempotent withdrawal command.
	 * @return WP_REST_Response|WP_Error
	 */
	public function withdraw( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$body = $this->strict_json_object(
				$request,
				array( 'command_id' => array( 'type' => 'string' ) ),
				array( 'command_id' )
			);
			$command = PublicId::from_string( $body['command_id'] );
			$scope   = $this->organization_scope();
			$id      = $this->request_public_id( $request );
			if ( ! $scope || ! $id ) {
				return RestError::for_kind( 'not_found' );
			}
			$uuid = $this->records->withdraw( $this->current_actor(), $scope, $id, $command, gmdate( 'Y-m-d H:i:s' ), CorrelationId::generate() );
			return new WP_REST_Response( array( 'public_id' => $uuid->to_string(), 'decision' => 'withdrawn' ), 201 );
		} catch ( InvalidArgumentException ) {
			return RestError::for_kind( 'invalid_schema' );
		} catch ( RuntimeException ) {
			return RestError::for_kind( 'conflict' );
		}
	}

	/**
	 * Create a draft consent definition through the M5 document authority.
	 *
	 * @param WP_REST_Request $request Draft metadata.
	 * @return WP_REST_Response|WP_Error
	 */
	public function definition( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$body = $this->strict_json_object(
				$request,
				array(
					'key'   => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 100 ),
					'title' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 191 ),
				),
				array( 'key', 'title' )
			);
			$scope = $this->organization_scope();
			if ( ! $scope ) {
				return RestError::for_kind( 'unavailable' );
			}
			$id = $this->definitions->create( $this->current_actor(), $scope, $body['key'], $body['title'], gmdate( 'Y-m-d H:i:s' ), CorrelationId::generate() );
			return new WP_REST_Response( array( 'public_id' => $id->to_string() ), 201 );
		} catch ( InvalidArgumentException ) {
			return RestError::for_kind( 'invalid_schema' );
		} catch ( RuntimeException ) {
			return RestError::for_kind( 'conflict' );
		}
	}

	/**
	 * Publish an immutable consent version with a content fingerprint.
	 *
	 * @param WP_REST_Request $request Definition and plain-text content.
	 * @return WP_REST_Response|WP_Error
	 */
	public function publish( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$body = $this->strict_json_object(
				$request,
				array( 'content' => array( 'type' => 'string', 'minLength' => 20, 'maxLength' => 100000 ) ),
				array( 'content' )
			);
			$scope = $this->organization_scope();
			$id    = $this->request_public_id( $request );
			if ( ! $scope || ! $id ) {
				return RestError::for_kind( 'not_found' );
			}
			$version = $this->definitions->publish( $this->current_actor(), $scope, $id, $body['content'], gmdate( 'Y-m-d H:i:s' ), CorrelationId::generate() );
			return new WP_REST_Response( array( 'public_id' => $version->to_string() ), 201 );
		} catch ( InvalidArgumentException ) {
			return RestError::for_kind( 'validation' );
		} catch ( RuntimeException ) {
			return RestError::for_kind( 'conflict' );
		}
	}

	/**
	 * Return the immutable published document only to privacy administrators.
	 *
	 * @param WP_REST_Request $request Version.
	 * @return array<string,mixed>|WP_Error
	 */
	public function version( WP_REST_Request $request ): array|WP_Error {
		$scope = $this->organization_scope();
		$id    = $this->request_public_id( $request );
		try {
			return $scope && $id ? ( $this->reads->consent_version( $this->current_actor(), $scope, $id ) ?? RestError::for_kind( 'not_found' ) ) : RestError::for_kind( 'not_found' );
		} catch ( RuntimeException ) {
			return RestError::for_kind( 'unavailable' );
		}
	}

	/**
	 * Return bounded audit metadata without internal identities or payloads.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function audit(): array|WP_Error {
		$scope = $this->organization_scope();
		return $scope ? ( $this->reads->audit( $this->current_actor(), $scope ) ?? RestError::for_kind( 'forbidden' ) ) : RestError::for_kind( 'unavailable' );
	}
}
