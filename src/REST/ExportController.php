<?php
/**
 * Narrow, permission-gated export endpoints and private CSV transport.
 *
 * @package UOP
 */

namespace UOP\REST;

use InvalidArgumentException;
use RuntimeException;
use UOP\Application\Export\ExportJobService;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyObject;
use UOP\Application\Policy\PolicyService;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/** No export path, tenant ID, raw SQL filter or arbitrary column name is accepted. */
final class ExportController extends BaseController {
	/**
	 * Authorize and serve only reviewed export methods.
	 *
	 * @param ExportJobService $exports Live export application.
	 * @param PolicyService    $policy Same central object authorization as admin.
	 */
	public function __construct( private ExportJobService $exports, private PolicyService $policy ) {}

	/** Register export DTO endpoints and protected byte-stream transport. */
	public function register(): void {
		$this->register_endpoint( '/exports', 'POST', array( $this, 'create' ), array( $this, 'can_export' ) );
		$this->register_endpoint( '/exports/(?P<uuid>[0-9a-f-]{36})', 'GET', array( $this, 'status' ), array( $this, 'can_export' ), $this->uuid_argument() );
		$this->register_endpoint( '/exports/(?P<uuid>[0-9a-f-]{36})/download', 'GET', array( $this, 'download' ), array( $this, 'can_export' ), $this->uuid_argument() );
		add_filter( 'rest_pre_serve_request', array( $this, 'serve_download' ), 10, 3 );
	}

	/**
	 * Enforce current WordPress and organization capabilities on every route.
	 *
	 * @return bool|WP_Error
	 */
	public function can_export(): bool|WP_Error {
		$scope = $this->scope();
		return null !== $scope && $this->policy->can( new Actor( get_current_user_id() ), 'export.create', new PolicyObject( $scope->id, 'organization', $scope->id ) )->allowed ? true : $this->denied();
	}

	/**
	 * Submit only a strict idempotent person CSV request envelope.
	 *
	 * @param WP_REST_Request $request API request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$body = $this->strict_json_object(
				$request,
				array(
					'command_id' => array( 'type' => 'string' ),
					'columns'    => array(
						'type'  => 'array',
						'items' => array( 'type' => 'string' ),
					),
					'status'     => array( 'type' => 'string' ),
				),
				array( 'command_id', 'columns', 'status' )
			);
			$uuid = $this->exports->request(
				new Actor( get_current_user_id() ),
				$this->required_scope(),
				PublicId::from_string( $body['command_id'] ),
				$body['columns'],
				$body['status'],
				gmdate( 'Y-m-d H:i:s' ),
				CorrelationId::generate()
			);
			return new WP_REST_Response(
				array(
					'public_id' => $uuid->to_string(),
					'status'    => 'queued',
				),
				202
			);
		} catch ( InvalidArgumentException ) {
			return RestError::for_kind( 'invalid_schema' );
		} catch ( RuntimeException ) {
			return RestError::for_kind( 'conflict' );
		}
	}

	/**
	 * Return only the owner's sanitized status and counts, never storage keys.
	 *
	 * @param WP_REST_Request $request API request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function status( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			return new WP_REST_Response(
				$this->exports->status( new Actor( get_current_user_id() ), $this->required_scope(), PublicId::from_string( (string) $request['uuid'] ), gmdate( 'Y-m-d H:i:s' ) ),
				200
			);
		} catch ( InvalidArgumentException | RuntimeException ) {
			return RestError::for_kind( 'not_found' );
		}
	}

	/**
	 * Request a byte-stream only after current owner and expiry checks.
	 *
	 * @param WP_REST_Request $request API request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function download( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$id       = PublicId::from_string( (string) $request['uuid'] );
			$contents = $this->exports->download( new Actor( get_current_user_id() ), $this->required_scope(), $id, gmdate( 'Y-m-d H:i:s' ) );
			$response = new WP_REST_Response( array( 'uop_private_csv' => $contents ), 200 );
			$response->header( 'Content-Type', 'text/csv; charset=utf-8' );
			$response->header( 'Content-Disposition', 'attachment; filename="uop-export-' . $id->to_string() . '.csv"' );
			$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
			$response->header( 'X-Content-Type-Options', 'nosniff' );
			return $response;
		} catch ( InvalidArgumentException | RuntimeException ) {
			return RestError::for_kind( 'not_found' );
		}
	}

	/**
	 * Serve only a previously permission-checked export response as raw CSV.
	 *
	 * @param bool             $served Whether another handler already served.
	 * @param WP_REST_Response $response Current HTTP response.
	 * @param WP_REST_Request  $request  Current REST request.
	 * @return bool True only when this controller served the private CSV.
	 */
	public function serve_download( bool $served, WP_REST_Response $response, WP_REST_Request $request ): bool {
		if ( $served || 'GET' !== $request->get_method()
			|| ! preg_match( '#^/uop/v1/exports/[0-9a-f-]{36}/download$#D', $request->get_route() )
			|| 200 !== $response->get_status() ) {
			return $served;
		}
		$data = $response->get_data();
		if ( ! is_array( $data ) || ! is_string( $data['uop_private_csv'] ?? null ) ) {
			return $served;
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Verified UTF-8 CSV bytes must be delivered unchanged as attachment with nosniff.
		echo $data['uop_private_csv'];
		return true;
	}

	/**
	 * Derive the single active installation organization from trusted options.
	 *
	 * @return OrgScope|null
	 */
	private function scope(): ?OrgScope {
		$id = (int) get_option( 'uop_default_organization_id', 0 );
		return $id > 0 ? new OrgScope( $id ) : null;
	}

	/**
	 * Require configured organization for a previously authorized operation.
	 *
	 * @return OrgScope
	 * @throws RuntimeException When the organization is unavailable.
	 */
	private function required_scope(): OrgScope {
		$scope = $this->scope();
		if ( null === $scope ) {
			throw new RuntimeException( 'Export organization unavailable.' );
		}
		return $scope;
	}
}
