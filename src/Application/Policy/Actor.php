<?php
/**
 * Authenticated WordPress actor identity.
 *
 * @package UOP
 */
namespace UOP\Application\Policy;

/** No role names and no cached permissions are stored here. */
final readonly class Actor {
	public function __construct( public int $user_id ) {}
}
