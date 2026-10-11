<?php
/**
 * Guardian claim and reviewed approval REST contract.
 *
 * @package UOP
 */

namespace UOP\REST;

use InvalidArgumentException;
use RuntimeException;
use UOP\Application\Identity\GuardianReviewService;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Infrastructure\Database\DatabaseException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/** A claimant cannot list or approve pending relationship requests. */
final class GuardianReviewController extends BaseController {
	/**
	 * Bind a single policy-enforced command service.
	 *
	 * @param GuardianReviewService $service Scoped reviewer workflow.
	 */
	public function __construct( private GuardianReviewService $service ) {}

	/** Register explicit and independently guarded transport boundaries. */
	public function register(): void {
		$this->register_endpoint( '/me/guardian-claims', 'POST', array( $this, 'claim' ), array( $this, 'authenticated' ) );
		$this->register_endpoint( '/admin/guardian-claims', 'GET', array( $this, 'pending' ), array( $this, 'reviewer' ) );
		$this->register_endpoint( '/admin/guardian-claims/(?P<uuid>[0-9a-f-]{36})/review', 'POST', array( $this, 'review' ), array( $this, 'reviewer' ), $this->uuid_argument() );
	}

	/** Deny anonymous claims before interpreting their content.
	 *
	 * @return bool|WP_Error
	 */
	public function authenticated(): bool|WP_Error {
		return get_current_user_id() > 0 ? true : $this->denied();
	}

	/** Only delegation managers may enumerate or act on claims.
	 *
	 * @return bool|WP_Error
	 */
	public function reviewer(): bool|WP_Error {
		$scope = $this->organization_scope();
		if ( ! $scope ) {
			return $this->denied();
		}
		try {
			$this->service->pending( $this->current_actor(), $scope );
			return true;
		} catch ( RuntimeException ) {
			return $this->denied();
		}
	}

	/**
	 * Return a uniform receipt even for unknown subject IDs; no child lookup leaks.
	 *
	 * @param WP_REST_Request $request Incoming claim JSON.
	 * @return WP_REST_Response|WP_Error
	 */
	public function claim( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$body  = $this->strict_json_object(
				$request,
				array(
					'subject_id' => array(
						'type'      => 'string',
						'maxLength' => 36,
					),
				),
				array( 'subject_id' )
			);
			$scope = $this->organization_scope();
			if ( ! $scope ) {
				return RestError::for_kind( 'unavailable' );
			}
			try {
				$uuid = PublicId::from_string( $body['subject_id'] );
				$this->service->request( $this->current_actor(), $scope, $uuid, gmdate( 'Y-m-d H:i:s' ), CorrelationId::generate() );
			} catch ( InvalidArgumentException ) {
				// A syntactically invalid ID must not disclose subject existence.
				return new WP_REST_Response( array( 'status' => 'received' ), 202 );
			}
			return new WP_REST_Response( array( 'status' => 'received' ), 202 );
		} catch ( InvalidArgumentException ) {
			return RestError::for_kind( 'invalid_schema' );
		} catch ( DatabaseException ) {
			// Do not report a receipt if the transaction was not committed.
			// The error body never identifies the subject or claim existence.
			return RestError::for_kind( 'unavailable' );
		}
	}

	/** Return only the manager's limited, audited work queue.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function pending(): array|WP_Error {
		$scope = $this->organization_scope();
		return $scope ? array( 'items' => $this->service->pending( $this->current_actor(), $scope ) ) : RestError::for_kind( 'unavailable' );
	}

	/**
	 * Reviewer must explicitly attest an offline evidence check.
	 *
	 * @param WP_REST_Request $request Incoming review JSON.
	 * @return WP_REST_Response|WP_Error
	 */
	public function review( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$body  = $this->strict_json_object(
				$request,
				array(
					'decision'         => array(
						'type' => 'string',
						'enum' => array( 'approve', 'reject' ),
					),
					'evidence_checked' => array( 'type' => 'boolean' ),
					'event_post_id'    => array(
						'type'    => 'integer',
						'minimum' => 0,
					),
				),
				array( 'decision', 'evidence_checked', 'event_post_id' )
			);
			$scope = $this->organization_scope();
			$uuid  = $this->request_public_id( $request );
			if ( ! $scope || ! $uuid ) {
				return RestError::for_kind( 'not_found' );
			}
			$grant = $this->service->review(
				$this->current_actor(),
				$scope,
				$uuid,
				$body['decision'],
				$body['evidence_checked'],
				$body['event_post_id'],
				gmdate( 'Y-m-d H:i:s' ),
				CorrelationId::generate()
			);
			return new WP_REST_Response(
				array(
					'status'   => 'approve' === $body['decision'] ? 'approved' : 'rejected',
					'grant_id' => $grant?->to_string(),
				),
				200
			);
		} catch ( InvalidArgumentException ) {
			return RestError::for_kind( 'invalid_schema' );
		} catch ( RuntimeException ) {
			return RestError::for_kind( 'conflict' );
		}
	}
}
