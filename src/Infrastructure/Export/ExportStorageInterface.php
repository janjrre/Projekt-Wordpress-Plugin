<?php
/**
 * Strict private export storage contract.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Export;

/** Storage never returns a public URL, nor accepts a client-selected path. */
interface ExportStorageInterface {
	/**
	 * Write one complete bounded private file.
	 *
	 * @param string $contents Already projected export bytes.
	 * @return string Opaque relative storage key.
	 */
	public function write( string $contents ): string;

	/**
	 * Read one trusted opaque file, only after a fresh authorization check.
	 *
	 * @param string $key Trusted key read from the private export job.
	 * @return string File contents.
	 */
	public function read( string $key ): string;

	/**
	 * Delete a prior artifact, including after a failed job commit.
	 *
	 * @param string $key Opaque private storage key.
	 */
	public function delete( string $key ): void;
}
