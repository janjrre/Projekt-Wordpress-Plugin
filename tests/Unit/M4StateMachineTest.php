<?php
namespace UOP\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use UOP\Domain\Registrations\RegistrationStateMachine;

final class M4StateMachineTest extends TestCase {
	public function test_only_seven_frozen_states_and_explicit_transitions(): void {
		$machine = new RegistrationStateMachine();
		self::assertSame( array( 'submitted', 'review', 'accepted', 'waitlisted', 'offered', 'rejected', 'cancelled' ), $machine->states() );
		foreach ( array(
			array( 'submitted', 'review' ),
			array( 'review', 'waitlisted' ),
			array( 'waitlisted', 'offered' ),
			array( 'offered', 'accepted' ),
			array( 'offered', 'waitlisted' ),
			array( 'accepted', 'cancelled' ),
		) as $transition ) {
			$machine->assert_transition( $transition[0], $transition[1] );
		}
		foreach ( array(
			array( 'rejected', 'accepted' ),
			array( 'cancelled', 'accepted' ),
			array( 'submitted', 'offered' ),
			array( 'accepted', 'waitlisted' ),
			array( 'unknown', 'accepted' ),
			array( 'submitted', 'submitted' ),
		) as $transition ) {
			try {
				$machine->assert_transition( $transition[0], $transition[1] );
				self::fail( 'Invalid state transition accepted.' );
			} catch ( InvalidArgumentException ) {
				self::assertTrue( true );
			}
		}
	}
}
