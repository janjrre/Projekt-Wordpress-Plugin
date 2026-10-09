<?php
/**
 * Policy-projected, bounded people CSV export.
 *
 * @package UOP
 */

namespace UOP\Application\Export;

use InvalidArgumentException;
use RuntimeException;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\FieldDefinition;
use UOP\Application\Policy\PolicyObject;
use UOP\Application\Policy\PolicyService;
use UOP\Application\Policy\ProjectionService;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\PageRequest;
use UOP\Infrastructure\Database\PersonRepository;

/** Uses the M2 authoritative object+field policy, not a separate CSV access rule. */
final class PersonExportGenerator {
	/**
	 * Bind tenant-scoped people and the same policy as every read channel.
	 *
	 * @param PersonRepository $people Scoped people source.
	 * @param PolicyService    $policy Live authorization.
	 */
	public function __construct( private PersonRepository $people, private PolicyService $policy ) {}

	/**
	 * Generate a reviewed UTF-8 CSV one bounded keyset page at a time.
	 *
	 * @param Actor    $actor Original exporting account.
	 * @param OrgScope $scope Tenant.
	 * @param array    $columns Allowlisted export names.
	 * @param string   $status Optional fixed equality filter.
	 * @phpstan-param list<string> $columns
	 * @return array{body:string,count:int} Private CSV bytes and count.
	 * @throws InvalidArgumentException|RuntimeException For unsupported columns, encoding or denied fields.
	 */
	public function generate( Actor $actor, OrgScope $scope, array $columns, string $status ): array {
		CsvExportSchema::columns( $columns );
		$csv         = new CsvStreamEncoder( $columns );
		$projector   = new ProjectionService( $this->policy );
		$definitions = array(
			'display_name'  => new FieldDefinition( 'display_name', 'personal', true, false, true, false ),
			'status'        => new FieldDefinition( 'status', 'internal', false, false, false, false ),
			'primary_email' => new FieldDefinition( 'primary_email', 'personal', false, false, false, false ),
		);
		try {
			$after = 0;
			do {
				$rows      = $this->people->page( $scope, new PageRequest( 100, $after ), '' === $status ? array() : array( 'status' => $status ) );
				$page_size = count( $rows );
				foreach ( $rows as $row ) {
					$after     = (int) $row['id'];
					$object    = new PolicyObject( $scope->id, 'person', $after, null, null, null !== $row['archived_at'] );
					$id        = PublicId::from_binary( $row['public_id'] );
					$projected = $projector->project(
						$actor,
						'person.view',
						$object,
						$id,
						$definitions,
						array(
							'display_name'  => (string) $row['display_name'],
							'status'        => (string) $row['status'],
							'primary_email' => (string) ( $row['primary_email'] ?? '' ),
						)
					);
					if ( null === $projected ) {
						continue;
					}
					$available = array_merge( array( 'public_id' => $projected['public_id'] ), $projected['fields'] );
					$values    = array();
					foreach ( $columns as $column ) {
						if ( ! array_key_exists( $column, $available ) ) {
							throw new RuntimeException( 'CSV field-level authorization denied.' );
						}
						$values[] = (string) $available[ $column ];
					}
					$csv->row( $values );
				}
			} while ( 100 === $page_size );
			return $csv->finish();
		} finally {
			$csv->close();
		}
	}
}
