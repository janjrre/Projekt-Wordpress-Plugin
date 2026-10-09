<?php
/**
 * Bounded RFC-4180-style UTF-8 CSV spooling and spreadsheet-injection guard.
 *
 * @package UOP
 */

namespace UOP\Application\Export;

use RuntimeException;

/** Writes one line at a time; final output remains limited by private V1 storage. */
final class CsvStreamEncoder {
	/**
	 * Private spool with 1 MiB in-memory threshold.
	 *
	 * @var resource|false
	 */
	private $handle;

	/** Included data row count, excluding header. */
	private int $count = 0;

	/**
	 * Open an ephemeral private stream and write approved UTF-8 headers.
	 *
	 * @param array $columns Reviewed CSV column names.
	 * @phpstan-param list<string> $columns
	 * @throws RuntimeException If streaming setup fails.
	 */
	public function __construct( array $columns ) {
		$this->handle = fopen( 'php://temp/maxmemory:1048576', 'w+b' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Non-public bounded spool.
		if ( false === $this->handle ) {
			throw new RuntimeException( 'CSV spool is unavailable.' );
		}
		$this->write( $columns );
	}

	/**
	 * Append one row after strict UTF-8 and spreadsheet formula validation.
	 *
	 * @param array $values Raw field values in selected header order.
	 * @phpstan-param list<string> $values
	 * @throws RuntimeException On unsafe encodings or size limits.
	 */
	public function row( array $values ): void {
		if ( $this->count >= 5000 ) {
			throw new RuntimeException( 'CSV row limit exceeded.' );
		}
		$escaped = array_map( array( self::class, 'cell' ), $values );
		$this->write( $escaped );
		++$this->count;
	}

	/**
	 * Finalize as bounded bytes for the existing protected export storage.
	 *
	 * @return array{body:string,count:int} Frozen UTF-8 CSV.
	 * @throws RuntimeException If the spool cannot be read.
	 */
	public function finish(): array {
		if ( false === $this->handle || ! rewind( $this->handle ) ) {
			throw new RuntimeException( 'CSV spool is unavailable.' );
		}
		$body = stream_get_contents( $this->handle );
		if ( false === $body || strlen( $body ) > 5242880 ) {
			throw new RuntimeException( 'CSV private storage size limit exceeded.' );
		}
		return array(
			'body'  => $body,
			'count' => $this->count,
		);
	}

	/** Release the ephemeral stream when the export completes or fails. */
	public function close(): void {
		if ( false !== $this->handle ) {
			fclose( $this->handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Free ephemeral local spool.
			$this->handle = false;
		}
	}

	/** Do not retain unclosed spools in exceptional execution paths. */
	public function __destruct() {
		$this->close();
	}

	/**
	 * Escape dangerous spreadsheet prefixes without changing valid UTF-8 text.
	 *
	 * @param string $value Original text field.
	 * @return string Safe CSV cell content.
	 * @throws RuntimeException For invalid or binary text.
	 */
	public static function cell( string $value ): string {
		if ( ! mb_check_encoding( $value, 'UTF-8' ) || preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value ) ) {
			throw new RuntimeException( 'CSV field is not valid UTF-8 text.' );
		}
		// Office, LibreOffice and spreadsheet imports may ignore leading whitespace,
		// invisible formatting characters or BOMs before a formula introducer.
		if ( preg_match( '/^(?:[\s\p{Z}\p{Cf}]*[=+\-@]|[\t\r\n])/u', $value ) ) {
			return "'" . $value;
		}
		return $value;
	}

	/**
	 * RFC-style quotes, commas and CRLF; reject overlarge spool incrementally.
	 *
	 * @param array $values Safe one-line CSV fields.
	 * @phpstan-param list<string> $values
	 * @throws RuntimeException When encoding or bounded writing fails.
	 */
	private function write( array $values ): void {
		if ( false === $this->handle || false === fputcsv( $this->handle, $values, ',', '"', '', "\r\n" )
			|| false === ftell( $this->handle ) || ftell( $this->handle ) > 5242880 ) {
			throw new RuntimeException( 'CSV encoding or spool size limit exceeded.' );
		}
	}
}
