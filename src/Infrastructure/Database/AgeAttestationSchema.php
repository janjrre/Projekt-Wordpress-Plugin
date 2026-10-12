<?php
/**
 * Reviewed additive V2 age-attestation table, separate from frozen V1 manifest.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use RuntimeException;

/** Minimal event-scoped age threshold evidence; never stores dates of birth or IDs. */
final class AgeAttestationSchema {
	/**
	 * Create one additive table, without changing the frozen V1 manifest.
	 *
	 * @param string $prefix Validated WordPress-specific prefix.
	 * @param string $charset WordPress charset and collation.
	 * @return string
	 */
	public static function ddl( string $prefix, string $charset ): string {
		if ( ! preg_match( '/^[a-zA-Z0-9_]+$/D', $prefix ) ) {
			throw new RuntimeException( 'Invalid age attestation table prefix.' );
		}
		return 'CREATE TABLE ' . $prefix . 'age_attestations (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
public_id BINARY(16) NOT NULL,
organization_id BIGINT UNSIGNED NOT NULL,
person_id BIGINT UNSIGNED NOT NULL,
event_post_id BIGINT UNSIGNED NOT NULL,
threshold_age SMALLINT UNSIGNED NOT NULL,
outcome VARCHAR(16) NOT NULL,
status VARCHAR(16) NOT NULL,
reviewer_user_id BIGINT UNSIGNED NOT NULL,
method_key VARCHAR(32) NOT NULL,
checked_at DATETIME NOT NULL,
valid_until DATETIME NOT NULL,
created_at DATETIME NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY uq_public_id (public_id),
KEY ix_current (organization_id, person_id, event_post_id, threshold_age, id)
) ENGINE=InnoDB ' . $charset . ';';
	}

	/**
	 * Confirm the new table and its required protection indexes are not drifted.
	 *
	 * @param Connection $db Scoped database connection.
	 * @param string     $prefix Safe site prefix.
	 * @param string     $collation Exact site collation.
	 */
	public static function verify( Connection $db, string $prefix, string $collation ): void {
		$name = $prefix . 'age_attestations';
		$meta = $db->rows( 'SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', array( $name ) );
		if ( 1 !== count( $meta ) || 'InnoDB' !== $meta[0]['ENGINE'] || $collation !== $meta[0]['TABLE_COLLATION'] ) {
			throw new RuntimeException( 'Age attestation storage is absent or has unsafe engine or collation.' );
		}
		$fields   = $db->rows( 'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s ORDER BY ORDINAL_POSITION', array( $name ) );
		$required = array( 'id', 'public_id', 'organization_id', 'person_id', 'event_post_id', 'threshold_age', 'outcome', 'status', 'reviewer_user_id', 'method_key', 'checked_at', 'valid_until', 'created_at' );
		if ( array_column( $fields, 'COLUMN_NAME' ) !== $required ) {
			throw new RuntimeException( 'Age attestation columns differ from V2.' );
		}
		foreach ( $fields as $field ) {
			if ( 'NO' !== $field['IS_NULLABLE'] ) {
				throw new RuntimeException( 'Age attestation column must not be nullable.' );
			}
		}
		$indexes = $db->rows( 'SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME, SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s ORDER BY INDEX_NAME, SEQ_IN_INDEX', array( $name ) );
		$actual  = array();
		foreach ( $indexes as $row ) {
			if ( null !== $row['SUB_PART'] ) {
				throw new RuntimeException( 'Age index must never use a prefix.' );
			}
			$key                         = (string) $row['INDEX_NAME'];
			$actual[ $key ]['unique']    = 0 === (int) $row['NON_UNIQUE'];
			$actual[ $key ]['columns'][] = (string) $row['COLUMN_NAME'];
		}
		$expected = array(
			'PRIMARY'      => array(
				'unique'  => true,
				'columns' => array( 'id' ),
			),
			'ix_current'   => array(
				'unique'  => false,
				'columns' => array( 'organization_id', 'person_id', 'event_post_id', 'threshold_age', 'id' ),
			),
			'uq_public_id' => array(
				'unique'  => true,
				'columns' => array( 'public_id' ),
			),
		);
		ksort( $actual );
		ksort( $expected );
		if ( $actual !== $expected ) {
			throw new RuntimeException( 'Age attestation scope indexes are incomplete or drifted.' );
		}
		$fks = $db->rows( 'SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = %s', array( $name ) );
		if ( $fks ) {
			throw new RuntimeException( 'Unexpected physical age evidence foreign key.' );
		}
	}
}
