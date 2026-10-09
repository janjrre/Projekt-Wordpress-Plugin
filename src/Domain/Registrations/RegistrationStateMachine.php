<?php
/**
 * Frozen V1 registration lifecycle.
 *
 * @package UOP
 */

namespace UOP\Domain\Registrations;

use InvalidArgumentException;

/** Every transition is an explicit domain decision, never a direct status PATCH. */
final class RegistrationStateMachine {
	/**
	 * Fixed business state transition map.
	 *
	 * @var array<string, list<string>>
	 */
	private const TRANSITIONS = array(
		'submitted'  => array( 'review', 'accepted', 'waitlisted', 'rejected', 'cancelled' ),
		'review'     => array( 'accepted', 'waitlisted', 'rejected', 'cancelled' ),
		'accepted'   => array( 'cancelled' ),
		'waitlisted' => array( 'offered', 'cancelled', 'rejected' ),
		'offered'    => array( 'accepted', 'waitlisted', 'cancelled' ),
		'rejected'   => array(),
		'cancelled'  => array(),
	);

	/**
	 * Reject transitions outside the complete seven-state lifecycle.
	 *
	 * @param string $from Existing state.
	 * @param string $to   Target state.
	 * @throws InvalidArgumentException When the transition is not permitted.
	 */
	public function assert_transition( string $from, string $to ): void {
		if ( ! in_array( $to, self::TRANSITIONS[ $from ] ?? array(), true ) ) {
			throw new InvalidArgumentException( 'Invalid registration state transition.' );
		}
	}

	/**
	 * Enumerate the fixed business states for validation and documentation.
	 *
	 * @return list<string>
	 */
	public function states(): array {
		return array_keys( self::TRANSITIONS );
	}
}
