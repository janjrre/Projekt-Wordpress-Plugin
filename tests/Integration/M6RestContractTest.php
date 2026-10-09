<?php
/**
 * M6 contract tests for the shared REST transport boundary.
 *
 * @package UOP
 */

namespace UOP\Tests\Integration;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use UOP\REST\BaseController;
use UOP\REST\RestError;
use WP_REST_Request;

/** The same object-schema and error rules apply to future M6 controllers. */
final class M6RestContractTest extends TestCase {
	/** Verify that every status has one stable, public error code. */
	public function test_error_contract_maps_all_documented_statuses(): void {
		$expected = array(
			'invalid_schema'  => 400,
			'unauthenticated' => 401,
			'forbidden'       => 403,
			'not_found'       => 404,
			'conflict'        => 409,
			'validation'      => 422,
			'rate_limited'    => 429,
			'internal'        => 500,
			'unavailable'     => 503,
		);
		foreach ( $expected as $kind => $status ) {
			$error = RestError::for_kind( $kind );
			self::assertSame( 'uop_' . $kind, $error->get_error_code() );
			self::assertSame( $status, $error->get_error_data()['status'] );
			self::assertStringNotContainsString( '/tmp/', $error->get_error_message() );
		}
	}

	/** Ensure JSON object allowlists apply before commands or data writes. */
	public function test_transport_rejects_mass_assignment_and_wrong_types(): void {
		$controller = new class() extends BaseController {
			/**
			 * Test the protected transport validator.
			 *
			 * @param WP_REST_Request $request Request.
			 * @return array<string, mixed> DTO.
			 */
			public function validated( WP_REST_Request $request ): array {
				return $this->strict_json_object(
					$request,
					array(
						'name'     => array( 'type' => 'string' ),
						'revision' => array( 'type' => 'integer' ),
					),
					array( 'name', 'revision' )
				);
			}
		};
		$request = new WP_REST_Request( 'POST', '/uop/v1/test-contract' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( '{"name":"Someone","revision":1}' );
		self::assertSame( array( 'name' => 'Someone', 'revision' => 1 ), $controller->validated( $request ) );
		foreach ( array( '{"name":"Someone","revision":1,"role":"administrator"}', '{"name":"Someone","revision":"1"}', '{"name":"Someone"}', '[1,2]', '{"name":false,"revision":1}' ) as $invalid ) {
			$request->set_body( $invalid );
			try {
				$controller->validated( $request );
				self::fail( 'Unsafe REST body was accepted.' );
			} catch ( InvalidArgumentException ) {
				self::assertTrue( true );
			}
		}
	}

	/** Register route with authorization callback and stable REST error. */
	public function test_denied_rest_request_uses_explicit_error_contract(): void {
		$controller = new class() extends BaseController {
			/** Register a guarded endpoint. */
			public function register(): void {
				$this->register_endpoint(
					'/m6-contract-check',
					'GET',
					static fn () => array( 'ok' => true ),
					static fn () => RestError::for_kind( 'unauthenticated' )
				);
			}
		};
		$controller->register();
		$result = rest_do_request( new WP_REST_Request( 'GET', '/uop/v1/m6-contract-check' ) );
		self::assertSame( 401, $result->get_status() );
		self::assertSame( 'uop_unauthenticated', $result->get_data()['code'] );
	}
}
