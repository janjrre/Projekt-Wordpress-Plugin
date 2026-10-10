<?php
/**
 * Permission-gated registration creation, review, cancellation and verification.
 *
 * @package UOP
 */

namespace UOP\REST;

use InvalidArgumentException;
use RuntimeException;
use UOP\Application\Query\M6ReadService;
use UOP\Application\Registration\CapacityLifecycleService;
use UOP\Application\Registration\EmailVerificationService;
use UOP\Application\Registration\GuestVerificationDeliveryService;
use UOP\Application\Registration\RegistrationService;
use UOP\Application\Registration\RegistrationTransitionService;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/** Every command delegates to the existing auditable application services. */
final class RegistrationController extends BaseController {
	/** Initialize the existing domain command services.
	 *
	 * @param M6ReadService                 $reads        Safe projected registration read models.
	 * @param RegistrationService           $registrations Idempotent submission writer.
	 * @param RegistrationTransitionService $transitions  Non-capacity state transitions.
	 * @param CapacityLifecycleService      $capacity     Seat-safe cancellation.
	 * @param EmailVerificationService      $verification Hashed, one-time email token verifier.
	 */
	public function __construct(
		private M6ReadService $reads,
		private RegistrationService $registrations,
		private RegistrationTransitionService $transitions,
		private CapacityLifecycleService $capacity,
		private EmailVerificationService $verification,
		private ?GuestVerificationDeliveryService $guest_delivery = null
	) {}

	/** Register M6-02 public and authenticated routes with explicit permissions. */
	public function register(): void {
		$this->register_endpoint( '/registrations', 'GET', array( $this, 'index' ), array( $this, 'authenticated' ) );
		$this->register_endpoint( '/registrations', 'POST', array( $this, 'create' ), '__return_true' );
		$this->register_endpoint( '/registrations/guest', 'POST', array( $this, 'create_guest' ), '__return_true' );
		$this->register_endpoint( '/registration-verifications', 'POST', array( $this, 'verify_email' ), '__return_true' );
		$this->register_endpoint( '/registrations/(?P<uuid>[0-9a-f-]{36})', 'GET', array( $this, 'show' ), array( $this, 'can_view' ), $this->uuid_argument() );
		$this->register_endpoint( '/registrations/(?P<uuid>[0-9a-f-]{36})/cancel', 'POST', array( $this, 'cancel' ), array( $this, 'can_view' ), $this->uuid_argument() );
		$this->register_endpoint( '/registrations/(?P<uuid>[0-9a-f-]{36})/transitions', 'POST', array( $this, 'transition' ), array( $this, 'can_view' ), $this->uuid_argument() );
	}

	/** Check authentication for protected registration queries.
	 *
	 * @return bool|WP_Error
	 */
	public function authenticated(): bool|WP_Error {
		return get_current_user_id() > 0 ? true : $this->denied();
	}

	/** Check access to a specific requested registration.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return bool|WP_Error
	 */
	public function can_view( WP_REST_Request $request ): bool|WP_Error {
		$scope = $this->organization_scope();
		$id    = $this->request_public_id( $request );
		return $scope && $id && null !== $this->reads->registration( $this->current_actor(), $scope, $id ) ? true : $this->denied( true );
	}

	/**
	 * List only the current person's registrations, or a deliberately selected subject.
	 * A profile-view delegation alone never authorizes registration access.
	 *
	 * @param WP_REST_Request $request GET query parameters.
	 * @return array<string, mixed>|WP_Error
	 */
	public function index( WP_REST_Request $request ): array|WP_Error {
		$scope = $this->organization_scope();
		if ( ! $scope ) {
			return RestError::for_kind( 'unavailable' );
		}
		try {
			$person = $request->get_param( 'person_id' );
			$after  = $request->get_param( 'after' );
			if ( ( null !== $person && ! is_string( $person ) ) || ( null !== $after && ! is_string( $after ) ) ) {
				return RestError::for_kind( 'invalid_schema' );
			}
			$result = $this->reads->registrations(
				$this->current_actor(),
				$scope,
				null === $person ? null : PublicId::from_string( $person ),
				null === $after ? null : PublicId::from_string( $after )
			);
			return $result ?? RestError::for_kind( 'not_found' );
		} catch ( InvalidArgumentException ) {
			return RestError::for_kind( 'invalid_schema' );
		}
	}

	/** Check access to a specific requested registration.
	 *
	 * @param WP_REST_Request $request Route request.
	 * @return array<string, mixed>|WP_Error
	 */
	public function show( WP_REST_Request $request ): array|WP_Error {
		$scope = $this->organization_scope();
		$id    = $this->request_public_id( $request );
		return $scope && $id ? ( $this->reads->registration( $this->current_actor(), $scope, $id ) ?? RestError::for_kind( 'not_found' ) ) : RestError::for_kind( 'not_found' );
	}

	/**
	 * Submit an authenticated subject's published form only via the M4 service.
	 * Guest writes remain closed until M5 provides protected, tested challenge
	 * delivery; accepting guest writes without a usable link would strand users.
	 *
	 * @param WP_REST_Request $request JSON submission.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( 0 === get_current_user_id() ) {
			return RestError::for_kind( 'unavailable' );
		}
		try {
			$body    = $this->strict_json_object(
				$request,
				array(
					'person_id'     => array( 'type' => 'string' ),
					'event_id'      => array( 'type' => 'string' ),
					'occurrence_id' => array( 'type' => 'string' ),
					'command_id'    => array( 'type' => 'string' ),
					'fields'        => array( 'type' => 'object' ),
				),
				array( 'person_id', 'event_id', 'command_id', 'fields' )
			);
			$person  = PublicId::from_string( $body['person_id'] );
			$event   = PublicId::from_string( $body['event_id'] );
			$command = PublicId::from_string( $body['command_id'] );
			$when    = isset( $body['occurrence_id'] ) ? PublicId::from_string( $body['occurrence_id'] ) : null;
		} catch ( InvalidArgumentException ) {
			return RestError::for_kind( 'invalid_schema' );
		}
		$scope = $this->organization_scope();
		if ( ! $scope ) {
			return RestError::for_kind( 'unavailable' );
		}
		try {
			$id = $this->registrations->submit( $this->current_actor(), $scope, $person, $event, $when, $command, $body['fields'], gmdate( 'Y-m-d H:i:s' ), CorrelationId::generate() );
			return new WP_REST_Response(
				array(
					'public_id' => $id->to_string(),
					'status'    => 'submitted',
				),
				201
			);
		} catch ( InvalidArgumentException ) {
			return RestError::for_kind( 'validation' );
		} catch ( RuntimeException ) {
			return RestError::for_kind( 'conflict' );
		}
	}

	/**
	 * Public guest intake is disabled unless explicitly opted in, on HTTPS, with
	 * working Action Scheduler. The async outbox consumer alone issues a secret.
	 * REST never emits contact information or a registration identifier.
	 *
	 * @param WP_REST_Request $request Submitted published form values.
	 * @return WP_REST_Response|WP_Error Generic receipt or stable error.
	 */
	public function create_guest( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! $this->guest_delivery || ! $this->guest_delivery->ready() ) {
			return RestError::for_kind( 'unavailable' );
		}
		if ( strlen( $request->get_body() ) > 32768 ) {
			return RestError::for_kind( 'invalid_schema' );
		}
		try {
			$body = $this->strict_json_object(
				$request,
				array(
					'event_id'      => array( 'type' => 'string' ),
					'occurrence_id' => array( 'type' => 'string' ),
					'command_id'    => array( 'type' => 'string' ),
					'fields'        => array( 'type' => 'object' ),
				),
				array( 'event_id', 'command_id', 'fields' )
			);
			$event   = PublicId::from_string( $body['event_id'] );
			$command = PublicId::from_string( $body['command_id'] );
			$when    = isset( $body['occurrence_id'] ) ? PublicId::from_string( $body['occurrence_id'] ) : null;
		} catch ( InvalidArgumentException ) {
			return RestError::for_kind( 'invalid_schema' );
		}
		$scope = $this->organization_scope();
		if ( ! $scope ) {
			return RestError::for_kind( 'unavailable' );
		}
		// Use only the server observed peer; ignore forgeable proxy IP headers.
		$peer = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
		if ( false === filter_var( $peer, FILTER_VALIDATE_IP ) ) {
			$peer = 'unknown';
		}
		$limit_key = 'uop_guest_' . hash( 'sha256', $scope->id . ':' . $event->to_string() . ':' . $peer );
		$attempts  = (int) get_transient( $limit_key );
		if ( $attempts >= 6 ) {
			return RestError::for_kind( 'rate_limited' );
		}
		set_transient( $limit_key, $attempts + 1, 15 * MINUTE_IN_SECONDS );
		try {
			$this->registrations->submit_guest(
				$scope,
				$event,
				$when,
				$command,
				$body['fields'],
				gmdate( 'Y-m-d H:i:s' ),
				CorrelationId::generate()
			);
		} catch ( InvalidArgumentException ) {
			return RestError::for_kind( 'validation' );
		} catch ( RuntimeException ) {
			return RestError::for_kind( 'conflict' );
		}
		$response = new WP_REST_Response( array( 'status' => 'received' ), 202 );
		$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
		return $response;
	}

	/**
	 * Cancel using the M4 bucket-locking lifecycle when a seat is allocated.
	 *
	 * @param WP_REST_Request $request JSON idempotency command.
	 * @return WP_REST_Response|WP_Error
	 */
	public function cancel( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$body    = $this->strict_json_object(
				$request,
				array( 'command_id' => array( 'type' => 'string' ) ),
				array( 'command_id' )
			);
			$command = PublicId::from_string( $body['command_id'] );
		} catch ( InvalidArgumentException ) {
			return RestError::for_kind( 'invalid_schema' );
		}
		$scope = $this->organization_scope();
		$id    = $this->request_public_id( $request );
		if ( ! $scope || ! $id ) {
			return RestError::for_kind( 'not_found' );
		}
		$registration = $this->reads->registration( $this->current_actor(), $scope, $id );
		if ( ! $registration ) {
			return RestError::for_kind( 'not_found' );
		}
		try {
			$status = $registration['status'];
			$now    = gmdate( 'Y-m-d H:i:s' );
			$trace  = CorrelationId::generate();
			if ( in_array( $status, array( 'accepted', 'waitlisted', 'offered' ), true ) ) {
				$this->capacity->cancel( $this->current_actor(), $scope, $id, $command, $now, $trace );
			} elseif ( in_array( $status, array( 'submitted', 'review' ), true ) ) {
				$this->transitions->transition( $this->current_actor(), $scope, $id, 'cancelled', $command, $now, $trace );
			} elseif ( 'cancelled' !== $status ) {
				return RestError::for_kind( 'conflict' );
			}
			return new WP_REST_Response(
				array(
					'public_id' => $id->to_string(),
					'status'    => 'cancelled',
				),
				200
			);
		} catch ( InvalidArgumentException | RuntimeException ) {
			return RestError::for_kind( 'conflict' );
		}
	}

	/**
	 * Only review and rejection use this non-capacity transition endpoint.
	 *
	 * @param WP_REST_Request $request JSON transition command.
	 * @return WP_REST_Response|WP_Error
	 */
	public function transition( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$body    = $this->strict_json_object(
				$request,
				array(
					'command_id' => array( 'type' => 'string' ),
					'target'     => array(
						'type' => 'string',
						'enum' => array( 'review', 'rejected' ),
					),
				),
				array( 'command_id', 'target' )
			);
			$command = PublicId::from_string( $body['command_id'] );
		} catch ( InvalidArgumentException ) {
			return RestError::for_kind( 'invalid_schema' );
		}
		$scope = $this->organization_scope();
		$id    = $this->request_public_id( $request );
		if ( ! $scope || ! $id ) {
			return RestError::for_kind( 'not_found' );
		}
		try {
			$this->transitions->transition( $this->current_actor(), $scope, $id, $body['target'], $command, gmdate( 'Y-m-d H:i:s' ), CorrelationId::generate() );
			return new WP_REST_Response(
				array(
					'public_id' => $id->to_string(),
					'status'    => $body['target'],
				),
				200
			);
		} catch ( InvalidArgumentException | RuntimeException ) {
			return RestError::for_kind( 'conflict' );
		}
	}

	/**
	 * Consume only a private high-entropy secret; never return subject data.
	 * The same generic response is used for valid, expired and absent tokens.
	 *
	 * @param WP_REST_Request $request Public verification challenge.
	 * @return WP_REST_Response|WP_Error
	 */
	public function verify_email( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$body = $this->strict_json_object(
				$request,
				array(
					'registration_id' => array( 'type' => 'string' ),
					'token'           => array(
						'type'    => 'string',
						'pattern' => '^[0-9a-f]{64}$',
					),
				),
				array( 'registration_id', 'token' )
			);
			$id   = PublicId::from_string( $body['registration_id'] );
		} catch ( InvalidArgumentException ) {
			return RestError::for_kind( 'invalid_schema' );
		}
		$scope = $this->organization_scope();
		if ( ! $scope ) {
			return RestError::for_kind( 'unavailable' );
		}
		$key   = 'uop_verify_' . hash( 'sha256', $scope->id . ':' . $id->to_string() );
		$tries = (int) get_transient( $key );
		if ( $tries >= 10 ) {
			return RestError::for_kind( 'rate_limited' );
		}
		// WordPress transients are a secondary abuse guard; bearer secrets remain 256-bit.
		set_transient( $key, $tries + 1, 15 * MINUTE_IN_SECONDS );
		try {
			$this->verification->verify( $scope, $id, $body['token'], gmdate( 'Y-m-d H:i:s' ), CorrelationId::generate() );
		} catch ( InvalidArgumentException | RuntimeException ) {
			return RestError::for_kind( 'unavailable' );
		}
		$response = new WP_REST_Response( array( 'status' => 'received' ), 202 );
		$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
		return $response;
	}
}
