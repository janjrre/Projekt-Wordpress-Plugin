<?php
namespace UOP\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use UOP\Application\Export\CsvExportSchema;
use UOP\Application\Export\CsvStreamEncoder;

final class M5CsvStreamTest extends TestCase {
	public function test_exact_allowlist_and_duplicate_columns(): void {
		self::assertSame( array( 'public_id', 'display_name', 'primary_email' ), CsvExportSchema::columns( array( 'public_id', 'display_name', 'primary_email' ) ) );
		foreach ( array( array(), array( 'display_name', 'display_name' ), array( 'email' ), array( 'public_id', 'unknown' ), array( 0 => 'public_id', 2 => 'status' ) ) as $columns ) {
			try {
				CsvExportSchema::columns( $columns );
				self::fail( 'Invalid CSV columns were accepted.' );
			} catch ( InvalidArgumentException ) {
				self::assertTrue( true );
			}
		}
	}

	public function test_spreadsheet_formulas_and_invisible_prefixes_are_escaped(): void {
		foreach ( array(
			'=SUM(1,2)',
			'+SUM(1,2)',
			'-12+3',
			'@SUM(1,2)',
			"  =cmd|'/C calc'!A0",
			"\t=SUM(1,2)",
			"\n+SUM(1,2)",
			"\r@SUM(1,2)",
			"\u{FEFF}=SUM(1,2)",
			"\u{200B}=SUM(1,2)",
			"\u{00A0}=SUM(1,2)",
		) as $danger ) {
			self::assertSame( "'" . $danger, CsvStreamEncoder::cell( $danger ) );
		}
		foreach ( array( "Émilie 日本語 €", 'Some = text', "'=not a formula", 'one,two', '"quoted"', 'leading text' ) as $normal ) {
			self::assertSame( $normal, CsvStreamEncoder::cell( $normal ) );
		}
	}

	public function test_utf8_csv_delimiters_newlines_and_row_counts(): void {
		$csv = new CsvStreamEncoder( array( 'display_name', 'primary_email' ) );
		try {
			$csv->row( array( "Léa, Müller", 'contact@example.invalid' ) );
			$csv->row( array( "Japanese 日本語\nSecond line", '"quoted"@example.invalid' ) );
			$csv->row( array( '=2+2', '' ) );
			$finished = $csv->finish();
			self::assertSame( 3, $finished['count'] );
			self::assertSame( "display_name,primary_email\r\n", substr( $finished['body'], 0, 28 ) );
			self::assertStringContainsString( '"Léa, Müller"', $finished['body'] );
			self::assertStringContainsString( "Japanese 日本語\nSecond line", $finished['body'] );
			self::assertStringContainsString( '"quoted"', $finished['body'] );
			self::assertStringContainsString( "'=2+2", $finished['body'] );
			self::assertTrue( mb_check_encoding( $finished['body'], 'UTF-8' ) );
		} finally {
			$csv->close();
		}
	}

	public function test_invalid_utf8_and_nul_are_rejected(): void {
		foreach ( array( "\xC3\x28", "secret\x00value" ) as $invalid ) {
			try {
				CsvStreamEncoder::cell( $invalid );
				self::fail( 'Invalid text was accepted.' );
			} catch ( RuntimeException ) {
				self::assertTrue( true );
			}
		}
	}

	public function test_row_and_byte_limits_fail_without_returning_partial_csv(): void {
		$csv = new CsvStreamEncoder( array( 'display_name' ) );
		try {
			$csv->row( array( str_repeat( 'X', 5242880 ) ) );
			self::fail( 'Oversized CSV row was accepted.' );
		} catch ( RuntimeException ) {
			self::assertTrue( true );
		} finally {
			$csv->close();
		}
		$csv = new CsvStreamEncoder( array( 'display_name' ) );
		try {
			for ( $i = 0; $i < 5000; ++$i ) {
				$csv->row( array( 'example' ) );
			}
			self::assertSame( 5000, $csv->finish()['count'] );
			try {
				$csv->row( array( 'too many' ) );
				self::fail( 'CSV row limit was not enforced.' );
			} catch ( RuntimeException ) {
				self::assertTrue( true );
			}
		} finally {
			$csv->close();
		}
	}
}
