<?php
/**
 * Durable migration checkpoints in WordPress options.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

/** Reads state from the database under the runner lock, never from a stale option cache. */
final class MigrationState {
	/**
	 * Bind site options.
	 *
	 * @param Connection $db Connection.
	 * @param string     $options Site options table.
	 */
	public function __construct( private Connection $db, private string $options ) {}

	/**
	 * Read durable state.
	 *
	 * @param string $key Internal option name.
	 * @param mixed  $fallback Default.
	 * @return mixed
	 */
	public function read( string $key, mixed $fallback = null ): mixed {
		$rows = $this->db->rows( 'SELECT option_value FROM %i WHERE option_name = %s', array( $this->options, $key ) );
		return $rows ? maybe_unserialize( $rows[0]['option_value'] ) : $fallback;
	}

	/**
	 * Persist state on the same database connection as the migration.
	 *
	 * @param string $key Internal option name.
	 * @param mixed  $value State value.
	 */
	public function write( string $key, mixed $value ): void {
		$this->db->execute( "INSERT INTO %i (option_name, option_value, autoload) VALUES (%s, %s, 'off') ON DUPLICATE KEY UPDATE option_value = VALUES(option_value), autoload = 'off'", array( $this->options, $key, (string) maybe_serialize( $value ) ) );
		wp_cache_delete( $key, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}
}
