<?php
/**
 * Database seam for transaction and migration testing.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

/** SQL is infrastructure-only, with prepared values and identifiers. */
interface Connection {
	/**
	 * Execute a statement.
	 *
	 * @param string $sql Prepared SQL template.
	 * @param array  $args Placeholder values.
	 * @phpstan-param list<int|float|string> $args
	 * @return int
	 */
	public function execute( string $sql, array $args = array() ): int;
	/**
	 * Read explicit columns.
	 *
	 * @param string $sql Prepared SQL template.
	 * @param array  $args Placeholder values.
	 * @phpstan-param list<int|float|string> $args
	 * @return list<array<string, mixed>>
	 */
	public function rows( string $sql, array $args = array() ): array;
}
