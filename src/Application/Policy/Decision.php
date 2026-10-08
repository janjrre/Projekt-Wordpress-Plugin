<?php
/**
 * Internal policy result.
 *
 * @package UOP
 */
namespace UOP\Application\Policy;

/** Reason codes must never disclose existence of a hidden object to the client. */
final readonly class Decision {
	public function __construct( public bool $allowed, public string $reason ) {}
	public static function allow( string $reason ): self {
		return new self( true, $reason );
	}
	public static function deny( string $reason ): self {
		return new self( false, $reason );
	}
}
