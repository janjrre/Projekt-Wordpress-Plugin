<?php
/**
 * Single projection boundary for all response channels.
 *
 * @package UOP
 */
namespace UOP\Application\Policy;

use UOP\Core\PublicId;

/** Reject object access before any value is serialized or rendered. */
final class ProjectionService {
	public function __construct( private PolicyService $policy ) {}

	/**
	 * @param array<string, FieldDefinition> $definitions Trusted published field metadata.
	 * @param array<string, mixed> $values Raw internal values, never returned wholesale.
	 * @return array<string, mixed>|null Null is a hidden object.
	 */
	public function project( Actor $actor, string $action, Resource $resource, PublicId $public_id, array $definitions, array $values ): ?array {
		if ( ! $this->policy->can( $actor, $action, $resource )->allowed ) {
			return null;
		}
		$visible = array();
		foreach ( $definitions as $key => $definition ) {
			if ( ! $definition instanceof FieldDefinition || $key !== $definition->key || ! array_key_exists( $key, $values ) ) {
				continue;
			}
			if ( $this->policy->can( $actor, $action, $resource, $definition )->allowed ) {
				$visible[ $key ] = $values[ $key ];
			}
		}
		return array( 'public_id' => $public_id->to_string(), 'fields' => $visible );
	}
}
