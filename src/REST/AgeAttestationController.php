<?php
/**
 * Explicit, manager-only age verification administration via WordPress REST.
 *
 * @package UOP
 */

namespace UOP\REST;

use InvalidArgumentException;
use RuntimeException;
use UOP\Application\Identity\AgeAttestationService;
use UOP\Application\Policy\PolicyObject;
use UOP\Application\Policy\PolicyService;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Infrastructure\Database\AgeAttestationRepository;
use UOP\Infrastructure\Database\PersonRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/** Never accepts personal identifiers, DOBs, uploaded documents or arbitrary methods. */
final class AgeAttestationController extends BaseController {
	/**
	 * @param AgeAttestationService $reviews Review and revoke workflow.
	 * @param AgeAttestationRepository $repository Verified event evidence.
	 * @param PersonRepository $people Scoped person resolution.
	 * @param PolicyService $policy Live permission decisions.
	 */
	public function __construct(
		private AgeAttestationService $reviews,
		private AgeAttestationRepository $repository,
		private PersonRepository $people,
		private PolicyService $policy
	) {}

	/** Register strictly scoped age review routes. */
	public function register(): void {
		$this->register_endpoint( '/admin/age-attestations', 'GET', array( $this, 'current' ), array( $this, 'authorized' ) );
		$this->register_endpoint( '/admin/age-attestations', 'POST', array( $this, 'record' ), array( $this, 'authorized' ) );
	}

	/** @return bool|WP_Error Only tenant-specific administrators with live permission. */
	public function authorized(): bool|WP_Error {
		$scope = $this->organization_scope();
		return $scope && get_current_user_id() > 0 && $this->policy->can(
			$this->current_actor(), 'organization.manage', new PolicyObject( $scope->id, 'organization', $scope->id )
		)->allowed ? true : $this->denied();
	}

	/**
	 * Return only the current result, not a DOB, scanned proof or unbounded history.
	 *
	 * @param WP_REST_Request $request Scoped query.
	 * @return array<string,mixed>|WP_Error
	 */
	public function current( WP_REST_Request $request ): array|WP_Error {
		$scope = $this->organization_scope();
		$uuid = $this->request_public_id( $request, 'person_id' );
		$event = (int) $request['event_post_id'];
		$threshold = (int) $request['threshold_age'];
		if ( ! $scope || ! $uuid || $event < 1 || $threshold < 1 || $threshold > 120 ) {
			return RestError::for_kind( 'invalid_schema' );
		}
		$person = $this->people->find( $scope, $uuid );
		if ( ! $person || 'active' !== $person['status'] ) {
			return RestError::for_kind( 'not_found' );
		}
		$row = $this->repository->latest( $scope, (int) $person['id'], $event, $threshold );
		if ( ! $row ) {
			return array( 'status' => 'unknown' );
		}
		$status = (string) $row['status'];
		if ( 'verified' === $status && (string) $row['valid_until'] < gmdate( 'Y-m-d H:i:s' ) ) {
			$status = 'expired';
		}
		return array(
			'status' => $status,
			'outcome' => (string) $row['outcome'],
			'valid_until' => (string) $row['valid_until'],
			'method' => (string) $row['method_key'],
		);
	}

	/**
	 * Review or revoke one attestation. A confirmation flag records staff
	 * declaration, never independently proves the offline verification.
	 *
	 * @param WP_REST_Request $request Strict JSON.
	 * @return WP_REST_Response|WP_Error
	 */
	public function record( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$body = $this->strict_json_object( $request, array(
				'person_id' => array( 'type' => 'string', 'maxLength' => 36 ),
				'event_post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
				'threshold_age' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 120 ),
				'outcome' => array( 'type' => 'string', 'enum' => array( 'gte', 'lt' ) ),
				'method' => array( 'type' => 'string', 'enum' => array( 'in_person_review', 'trusted_register', 'authorized_offline_review', 'revoked' ) ),
				'valid_until' => array( 'type' => 'string', 'maxLength' => 19 ),
				'evidence_checked' => array( 'type' => 'boolean' ),
				'revoke' => array( 'type' => 'boolean' ),
			), array( 'person_id', 'event_post_id', 'threshold_age', 'outcome', 'method', 'valid_until', 'evidence_checked', 'revoke' ) );
			$scope = $this->organization_scope();
			if ( ! $scope ) {
				return RestError::for_kind( 'unavailable' );
			}
			$now = gmdate( 'Y-m-d H:i:s' );
			$id = $this->reviews->decide(
				$this->current_actor(), $scope, PublicId::from_string( $body['person_id'] ),
				$body['event_post_id'], $body['threshold_age'], $body['outcome'], $body['method'],
				$body['valid_until'], $body['evidence_checked'], $body['revoke'], $now, CorrelationId::generate()
			);
			$response = new WP_REST_Response( array( 'public_id' => $id->to_string(), 'status' => $body['revoke'] ? 'revoked' : 'verified' ), 201 );
			$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
			return $response;
		} catch ( InvalidArgumentException ) {
			return RestError::for_kind( 'invalid_schema' );
		} catch ( RuntimeException ) {
			return RestError::for_kind( 'conflict' );
		}
	}
}
