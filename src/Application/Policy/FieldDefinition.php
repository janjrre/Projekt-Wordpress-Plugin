<?php
/**
 * Immutable field-policy input.
 *
 * @package UOP
 */
namespace UOP\Application\Policy;

use InvalidArgumentException;

final readonly class FieldDefinition {
	public function __construct(
		public string $key,
		public string $sensitivity,
		public bool $subject_view,
		public bool $subject_edit,
		public bool $delegate_view,
		public bool $delegate_edit
	) {
		if ( '' === $key || ! preg_match( '/^[a-z][a-z0-9_]*$/D', $key ) ) {
			throw new InvalidArgumentException( 'Invalid field key.' );
		}
	}
}
