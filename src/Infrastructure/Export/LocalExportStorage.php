<?php
/**
 * ADR 308 private local temporary storage implementation.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Export;

use RuntimeException;

/** Refuses web-readable paths and symbolic links instead of trusting uploads protection. */
final class LocalExportStorage implements ExportStorageInterface {
	/**
	 * Make an installation-specific private directory under system temp.
	 *
	 * @throws RuntimeException When a safe private directory is unavailable.
	 */
	private function directory(): string {
		$base = realpath( sys_get_temp_dir() );
		if ( false === $base ) {
			throw new RuntimeException( 'Private export storage is unavailable.' );
		}
		$base = rtrim( $base, DIRECTORY_SEPARATOR );
		foreach ( array( ABSPATH, WP_CONTENT_DIR ) as $webroot ) {
			$real = realpath( $webroot );
			if ( false !== $real && ( $base === $real || str_starts_with( $base . DIRECTORY_SEPARATOR, rtrim( $real, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR ) ) ) {
				throw new RuntimeException( 'Private export storage is inside a public directory.' );
			}
		}
		$hash = substr( hash( 'sha256', wp_salt( 'auth' ) . home_url( '/' ) ), 0, 24 );
		$path = $base . DIRECTORY_SEPARATOR . 'uop-private-' . $hash;
		if ( is_link( $path ) ) {
			throw new RuntimeException( 'Private export storage path is a symlink.' );
		}
		if ( ! is_dir( $path ) && ! mkdir( $path, 0700 ) && ! is_dir( $path ) ) {
			throw new RuntimeException( 'Private export directory cannot be created.' );
		}
		$real = realpath( $path );
		if ( false === $real || ! is_dir( $real ) || ! is_writable( $real ) || ( fileperms( $real ) & 0077 ) !== 0 ) {
			throw new RuntimeException( 'Private export directory is not secure.' );
		}
		return $real;
	}

	/**
	 * Validate an opaque file identity and prevent symlink substitution.
	 *
	 * @param string $key Server-generated key, never a client supplied path.
	 * @return string Absolute private file path.
	 * @throws RuntimeException For malicious keys or symbolic links.
	 */
	private function file( string $key ): string {
		if ( ! preg_match( '/^[0-9a-f]{32}\.csv$/D', $key ) ) {
			throw new RuntimeException( 'Invalid private export key.' );
		}
		$path = $this->directory() . DIRECTORY_SEPARATOR . $key;
		if ( is_link( $path ) ) {
			throw new RuntimeException( 'Private export file is a symlink.' );
		}
		return $path;
	}

	/**
	 * Atomically place a restricted file outside public WordPress storage.
	 *
	 * @param string $contents CSV bytes; never a public URL.
	 * @return string Generated relative key.
	 * @throws RuntimeException If writing or hardening fails.
	 */
	public function write( string $contents ): string {
		if ( strlen( $contents ) > 5242880 ) {
			throw new RuntimeException( 'Export is too large for the bounded local writer.' );
		}
		$key  = bin2hex( random_bytes( 16 ) ) . '.csv';
		$path = $this->file( $key );
		$temp = $this->directory() . DIRECTORY_SEPARATOR . bin2hex( random_bytes( 16 ) ) . '.tmp';
		$handle = fopen( $temp, 'x+b' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Strict local private temporary file, not public uploads.
		if ( false === $handle ) {
			throw new RuntimeException( 'Cannot create private export file.' );
		}
		try {
			if ( ! chmod( $temp, 0600 ) || strlen( $contents ) !== fwrite( $handle, $contents ) || ! fflush( $handle ) ) {
				throw new RuntimeException( 'Private export write failed.' );
			}
		} finally {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close protected local file.
		}
		if ( ! rename( $temp, $path ) ) {
			unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Remove failed private temp artifact.
			throw new RuntimeException( 'Private export finalization failed.' );
		}
		return $key;
	}

	/**
	 * Load a pre-authorized private file with strict size and access checks.
	 *
	 * @param string $key Opaque storage reference from a scoped job.
	 * @return string Exact bytes.
	 * @throws RuntimeException If missing, insecure or larger than the V1 bound.
	 */
	public function read( string $key ): string {
		$path = $this->file( $key );
		if ( ! is_file( $path ) || ( fileperms( $path ) & 0077 ) !== 0 || filesize( $path ) > 5242880 ) {
			throw new RuntimeException( 'Private export file unavailable.' );
		}
		$bytes = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read protected local private file, never HTTP.
		if ( false === $bytes ) {
			throw new RuntimeException( 'Private export file read failed.' );
		}
		return $bytes;
	}

	/**
	 * Remove one private file, returning safely when already gone.
	 *
	 * @param string $key Opaque storage reference.
	 * @throws RuntimeException On failed cleanup.
	 */
	public function delete( string $key ): void {
		$path = $this->file( $key );
		if ( file_exists( $path ) && ! unlink( $path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Strict temporary protected export file.
			throw new RuntimeException( 'Private export cleanup failed.' );
		}
	}
}
