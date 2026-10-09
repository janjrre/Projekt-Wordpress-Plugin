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
use WP_REST_Server;

/** No export path, tenant ID, raw SQL filter or arbitrary column name is accepted. */
final class ExportController {
	/**
	 * Authorize and serve only reviewed export methods.
	 *
	 * @param ExportJobService $exports Live export application.
	 * @param PolicyService    $policy Same central object authorization as admin.
	 */
	public function __construct( private ExportJobService $exports, private PolicyService $policy ) {}

	/** Register the three narrow M5 routes and a no-JSON private download adapter. */
	public function register(): void {
		register_rest_route(
			'uop/v1',
			'/exports',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create' ),
				'permission_callback' => array( $this, 'can_export' ),
			)
		);
		register_rest_route(
			'uop/v1',
			'/exports/(?P<uuid>[0-9a-f-]{36})',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'status' ),
				'permission_callback' => array( $this, 'can_export' ),
			)
		);
		register_rest_route(
			'uop/v1',
			'/exports/(?P<uuid>[0-9a-f-]{36})/download',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'download' ),
				'permission_callback' => array( $this, 'can_export' ),
			)
		);
		add_filter( 'rest_pre_serve_request', array( $this, 'serve_download' ), 10, 4 );
	}

	/**
	 * Enforce current WordPress and organization capabilities on every route.
	 *
	 * @return bool
	 */
	public function can_export(): bool {
		$scope = $this->scope();
		return null !== $scope && $this->policy->can( new Actor( get_current_user_id() ), 'export.create', new PolicyObject( $scope->id, 'organization', $scope->id ) )->allowed;
	}

	/**
	 * Submit only a strict idempotent person CSV request envelope.
	 *
	 * @param WP_REST_Request $request API request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$body = $request->get_json_params();
			if ( ! is_array( $body ) || count( $body ) !== 3 || array_diff( array_keys( $body ), array( 'command_id', 'columns', 'status' ) )
				|| ! is_string( $body['command_id'] ?? null ) || ! is_array( $body['columns'] ?? null ) || ! is_string( $body['status'] ?? null ) ) {
				return new WP_Error( 'uop_invalid_export', 'Invalid export request.', array( 'status' => 400 ) );
			}
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
			return new WP_Error( 'uop_invalid_export', 'Invalid export request.', array( 'status' => 400 ) );
		} catch ( RuntimeException ) {
			return new WP_Error( 'uop_export_conflict', 'Export request is unavailable.', array( 'status' => 409 ) );
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
			return new WP_Error( 'uop_export_unavailable', 'Export is not available.', array( 'status' => 404 ) );
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
			return new WP_Error( 'uop_export_unavailable', 'Export is not available.', array( 'status' => 404 ) );
		}
	}

	/**
	 * Serve only a previously permission-checked export response as raw CSV.
	 *
	 * @param bool             $served Whether another handler already served.
	 * @param WP_REST_Response $response Current HTTP response.
	 * @param WP_REST_Request  $request  Current REST request.
	 * @param WP_REST_Server   $server   WordPress REST server.
	 * @return bool True only when this controller served the private CSV.
	 */
	public function serve_download( bool $served, $response, WP_REST_Request $request, $server ): bool {
		if ( $served || ! $server instanceof WP_REST_Server || 'GET' !== $request->get_method()
			|| ! preg_match( '#^/uop/v1/exports/[0-9a-f-]{36}/download$#D', $request->get_route() )
			|| ! $response instanceof WP_REST_Response || 200 !== $response->get_status() ) {
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
