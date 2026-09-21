<?php
/**
 * Prepared WordPress database adapter.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use wpdb;
/** No cache: transactions, migration locks and scoped reads require current data. */
final class WpdbConnection implements Connection {
	/**
	 * Original session identity; reconnects invalidate transactions and locks.
	 *
	 * @var int
	 */
	private int $session_id;
	/**
	 * Bind a WordPress connection.
	 *
	 * @param wpdb $wpdb WordPress database.
	 */
	public function __construct( private wpdb $wpdb ) {
		$handle           = $wpdb->__get( 'dbh' );
		$this->session_id = $handle instanceof \mysqli ? $handle->thread_id : 0;
	}

	/**
	 * Execute prepared SQL without leaking database diagnostics.
	 *
	 * @param string $sql Infrastructure SQL.
	 * @param array  $args Bound values and identifiers.
	 * @phpstan-param list<int|float|string> $args
	 * @return int
	 * @throws DatabaseException If execution fails.
	 */
	public function execute( string $sql, array $args = array() ): int {
		$query     = $this->prepare( $sql, $args );
		$prior     = $this->wpdb->suppress_errors( true );
		$retries   = $this->wpdb->__get( 'reconnect_retries' );
		$reporting = error_reporting(); // phpcs:ignore WordPress.PHP.DevelopmentFunctions,WordPress.PHP.DiscouragedPHPFunctions -- Read caller configuration to restore after WordPress's connection check.
		$this->wpdb->__set( 'reconnect_retries', 0 );
		try {
			$handle = $this->wpdb->__get( 'dbh' );
			if ( ! $handle instanceof \mysqli || 0 === $this->session_id || $handle->thread_id !== $this->session_id ) {
				throw new DatabaseException( 'Database connection changed.', 2006 );
			}
			// Reconnecting would lose transaction and advisory-lock ownership. Fail closed.
			if ( ! $this->wpdb->check_connection( false ) ) {
				throw new DatabaseException( 'Database connection lost.', 2006 );
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Prepared by the single adapter method below; uncached infrastructure query.
			$result = $this->wpdb->query( $query );
			if ( false === $result ) {
				$handle = $this->wpdb->__get( 'dbh' );
				$errno  = $handle instanceof \mysqli ? $handle->errno : 0;
				throw new DatabaseException( 'Database operation failed.', $errno );
			}
			return (int) $result;
		} finally {
			// WordPress check_connection can lower reporting when WP_DEBUG is on; restore caller state.
			error_reporting( $reporting ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions,WordPress.PHP.DiscouragedPHPFunctions -- Restores the previous value, never disables diagnostics.
			$this->wpdb->__set( 'reconnect_retries', $retries );
			$this->wpdb->suppress_errors( $prior );
		}
	}

	/**
	 * Read rows using the same error boundary.
	 *
	 * @param string $sql Infrastructure SQL.
	 * @param array  $args Bound values and identifiers.
	 * @phpstan-param list<int|float|string> $args
	 * @return list<array<string, mixed>>
	 */
	public function rows( string $sql, array $args = array() ): array {
		$this->execute( $sql, $args );
		return array_map( static fn ( object $row ): array => (array) $row, $this->wpdb->last_result );
	}

	/**
	 * Centralized placeholder expansion.
	 *
	 * @param string $sql Infrastructure SQL only.
	 * @param array  $args Bound values.
	 * @phpstan-param list<int|float|string> $args
	 * @return string
	 */
	private function prepare( string $sql, array $args ): string {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL templates are private infrastructure contracts; all variable values are arguments.
		return $args ? (string) $this->wpdb->prepare( $sql, $args ) : $sql;
	}
}
