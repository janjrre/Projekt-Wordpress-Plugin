<?php
/**
 * Fixed projection for the first safe private CSV export.
 *
 * @package UOP
 */

namespace UOP\Application\Export;

use RuntimeException;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyObject;
use UOP\Application\Policy\PolicyService;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\PageRequest;
use UOP\Infrastructure\Database\PersonRepository;

/** M5-07 baseline: no arbitrary SQL, private fields, or unbounded export. */
final class PersonExportGenerator {
	/**
	 * Bind the same row-level policy used by admin and REST.
	 *
	 * @param PersonRepository $people Scoped people projection.
	 * @param PolicyService    $policy Live object authorization.
	 */
	public function __construct( private PersonRepository $people, private PolicyService $policy ) {}

	/**
	 * Safely construct a bounded, UTF-8 CSV from allowed public metadata.
	 *
	 * @param Actor    $actor Original export owner.
	 * @param OrgScope $scope Tenant.
	 * @param array    $columns Strict selectable headers.
	 * @param string   $status Optional equality status filter.
	 * @phpstan-param list<string> $columns
	 * @return array{body:string,count:int} Private export bytes and included row count.
	 * @throws RuntimeException When projection exceeds V1 bound.
	 */
	public function generate( Actor $actor, OrgScope $scope, array $columns, string $status ): array {
		$handle = fopen( 'php://temp/maxmemory:5242880', 'w+b' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Memory-backed ephemeral CSV buffer.
		if ( false === $handle ) {
			throw new RuntimeException( 'CSV projection unavailable.' );
		}
		try {
			if ( false === fputcsv( $handle, $columns, ',', '"', '' ) ) {
				throw new RuntimeException( 'CSV header encoding failed.' );
			}
			$after = 0;
			$count = 0;
			do {
				$rows      = $this->people->page( $scope, new PageRequest( 100, $after ), '' === $status ? array() : array( 'status' => $status ) );
				$page_size = count( $rows );
				foreach ( $rows as $row ) {
					$after = (int) $row['id'];
					if ( ! $this->policy->can( $actor, 'person.view', new PolicyObject( $scope->id, 'person', $after, null, null, null !== $row['archived_at'] ) )->allowed ) {
						continue;
					}
					$available = array(
						'public_id'    => PublicId::from_binary( $row['public_id'] )->to_string(),
						'display_name' => (string) $row['display_name'],
						'status'       => (string) $row['status'],
					);
					$values = array();
					foreach ( $columns as $column ) {
						$value = $available[ $column ];
						$values[] = preg_match( '/^\s*[=+\-@\t\r]/u', $value ) ? "'" . $value : $value;
					}
					if ( false === fputcsv( $handle, $values, ',', '"', '' ) ) {
						throw new RuntimeException( 'CSV row encoding failed.' );
					}
					++$count;
					if ( $count > 5000 ) {
						throw new RuntimeException( 'CSV export exceeds the V1 row bound.' );
					}
				}
			} while ( 100 === $page_size );
			rewind( $handle );
			$body = stream_get_contents( $handle );
			if ( false === $body || strlen( $body ) > 5242880 ) {
				throw new RuntimeException( 'CSV export exceeds the private storage bound.' );
			}
			return array(
				'body'  => $body,
				'count' => $count,
			);
		} finally {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close ephemeral buffer.
		}
	}
}
