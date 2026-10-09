<?php
/**
 * Fixed allowlist for the V1 person CSV projection.
 *
 * @package UOP
 */

namespace UOP\Application\Export;

use InvalidArgumentException;

/** No arbitrary profile field name, dynamic SQL column or personal history. */
final class CsvExportSchema {
	/**
	 * Map published CSV names to reviewed internal projection fields.
	 *
	 * @var list<string>
	 */
	private const ALLOWED = array( 'public_id', 'display_name', 'status', 'primary_email' );

	/**
	 * Validate a client or durable-job column selection identically.
	 *
	 * @param array $columns Candidate column names.
	 * @phpstan-param array<mixed> $columns
	 * @return list<string>
	 * @throws InvalidArgumentException When selection is unsafe or unsupported.
	 */
	public static function columns( array $columns ): array {
		if ( ! array_is_list( $columns ) || ! $columns || count( $columns ) > count( self::ALLOWED ) || count( array_unique( $columns ) ) !== count( $columns ) ) {
			throw new InvalidArgumentException( 'Unsupported CSV column selection.' );
		}
		foreach ( $columns as $column ) {
			if ( ! is_string( $column ) || ! in_array( $column, self::ALLOWED, true ) ) {
				throw new InvalidArgumentException( 'Unsupported CSV column selection.' );
			}
		}
		return $columns;
	}
}
