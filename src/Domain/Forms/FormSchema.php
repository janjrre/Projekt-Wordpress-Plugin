<?php
/**
 * Fixed V1 form draft schema and field validation.
 *
 * @package UOP
 */

namespace UOP\Domain\Forms;

use InvalidArgumentException;
use UOP\Core\PublicId;
use UOP\Domain\Conditions\ConditionEngine;
use UOP\Domain\Profiles\FieldRules;

/** A form schema is an allowlisted declarative value; never executable markup. */
final class FormSchema {
	/**
	 * Validate author-input draft without allowing server-owned binding fields.
	 *
	 * @param array<string, mixed> $schema Strict form draft.
	 * @throws InvalidArgumentException For unknown fields or malformed conditions.
	 */
	public function validate_draft( array $schema ): void {
		if ( array_keys( $schema ) !== array( 'schema_version', 'fields' ) || 1 !== $schema['schema_version']
			|| ! is_array( $schema['fields'] ) || ! array_is_list( $schema['fields'] )
			|| ! $schema['fields'] || count( $schema['fields'] ) > 100 ) {
			throw new InvalidArgumentException( 'Invalid V1 form draft envelope.' );
		}
		$keys = array();
		$engine = new ConditionEngine();
		foreach ( $schema['fields'] as $field ) {
			if ( ! is_array( $field ) || array_diff( array_keys( $field ), array( 'key', 'type', 'label', 'required', 'options', 'visible_when', 'consent_definition_public_id' ) )
				|| ! is_bool( $field['required'] ?? null ) ) {
				throw new InvalidArgumentException( 'Form field properties are not allowed.' );
			}
			$definition = array_intersect_key( $field, array_flip( array( 'key', 'type', 'label', 'options' ) ) );
			FieldRules::validate( $definition );
			$key = (string) $field['key'];
			if ( isset( $keys[ $key ] ) ) {
				throw new InvalidArgumentException( 'Duplicate field key.' );
			}
			$keys[ $key ] = true;
			if ( isset( $field['visible_when'] ) ) {
				if ( ! is_array( $field['visible_when'] ) ) {
					throw new InvalidArgumentException( 'Invalid conditional visibility AST.' );
				}
				$engine->validate( $field['visible_when'] );
			}
			if ( 'consent' === $field['type'] ) {
				if ( empty( $field['consent_definition_public_id'] ) || ! is_string( $field['consent_definition_public_id'] ) ) {
					throw new InvalidArgumentException( 'Consent field requires a stable definition reference.' );
				}
				PublicId::from_string( $field['consent_definition_public_id'] );
			} elseif ( isset( $field['consent_definition_public_id'] ) ) {
				throw new InvalidArgumentException( 'Only consent fields may reference consent definitions.' );
			}
		}
	}
}
