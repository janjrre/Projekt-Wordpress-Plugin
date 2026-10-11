<?php
/**
 * Read and authorize server-owned profile and event eligibility facts.
 *
 * @package UOP
 */

namespace UOP\Application\Registration;

use RuntimeException;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\FieldDefinition;
use UOP\Application\Policy\PolicyObject;
use UOP\Application\Policy\PolicyService;
use UOP\Domain\Conditions\ConditionEngine;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\RegistrationFactsRepository;

/** Never uses profile claims supplied by an applicant's request payload. */
final class RegistrationFactsService {
	/**
	 * Compose scoped reads and live field visibility policy.
	 *
	 * @param RegistrationFactsRepository $repository Scoped source data.
	 * @param PolicyService               $policy     Field authorization.
	 */
	public function __construct( private RegistrationFactsRepository $repository, private PolicyService $policy ) {}

	/**
	 * Gather all referenced server fields from validated condition ASTs.
	 *
	 * @param Actor                      $actor      Subject, delegate or manager.
	 * @param OrgScope                   $scope      Trusted organization.
	 * @param int                        $person_id  Scoped subject ID.
	 * @param int                        $event_post Trusted event CPT ID.
	 * @param int                        $occurrence Zero or scoped occurrence.
	 * @param list<array<string, mixed>> $conditions Conditional visibility and admission rules.
	 * @return array{profile:array<string, mixed>,contexts:array<string,string>}
	 * @throws RuntimeException When a fact, policy or date context is unreliable.
	 */
	public function load( Actor $actor, OrgScope $scope, int $person_id, int $event_post, int $occurrence, array $conditions ): array {
		/**
		 * Profile fact fields referenced by the eligibility tree.
		 *
		 * @var array<string, bool> $keys
		 */
		$keys     = array();
		$age_keys = array();
		$engine    = new ConditionEngine();
		foreach ( $conditions as $condition ) {
			$engine->validate( $condition );
			$this->references( $condition, $keys, $age_keys );
		}
		$context = array();
		if ( $age_keys ) {
			$start = $this->repository->event_start( $scope, $event_post, $occurrence );
			if ( null === $start ) {
				throw new RuntimeException( 'Age requirements need a unique scheduled event start.' );
			}
			$context['event.start'] = $start;
		}
		$profile = array();
		foreach ( array_keys( $keys ) as $key ) {
			$rows = $this->repository->profile_value( $scope, $person_id, $key );
			if ( ! $rows || count( $rows ) > 100 ) {
				throw new RuntimeException( 'Required authoritative profile field is unavailable.' );
			}
			$field      = $rows[0];
			$definition = new FieldDefinition(
				(string) $field['field_key'],
				(string) $field['sensitivity'],
				(bool) $field['subject_view'],
				(bool) $field['subject_edit'],
				(bool) $field['delegate_view'],
				(bool) $field['delegate_edit']
			);
			$object     = new PolicyObject( $scope->id, 'person', $person_id );
			if ( ! $this->policy->can( $actor, 'person.view', $object, $definition )->allowed ) {
				throw new RuntimeException( 'Profile eligibility field is not authorized for this actor.' );
			}
			$type = (string) $field['data_type'];
			if ( isset( $age_keys[ $key ] ) && ( 'date' !== $type || (bool) $field['subject_edit'] || (bool) $field['delegate_edit'] ) ) {
				throw new RuntimeException( 'Age eligibility requires an organization-controlled date of birth.' );
			}
			$value = 'multiselect' === $type ? array() : null;
			foreach ( $rows as $row ) {
				if ( null === $row['ordinal'] ) {
					continue;
				}
				$actual = match ( $type ) {
					'checkbox' => 1 === (int) $row['value_boolean'],
					'number' => (float) $row['value_decimal'],
					'date' => (string) $row['value_date'],
					'textarea' => (string) $row['value_text'],
					default => $row['value_string'],
				};
				if ( 'multiselect' === $type ) {
					$value[] = $actual;
				} else {
					$value = $actual;
				}
			}
			if ( isset( $age_keys[ $key ] ) ) {
				$birth = is_string( $value ) ? \DateTimeImmutable::createFromFormat( '!Y-m-d', $value, new \DateTimeZone( 'UTC' ) ) : false;
				if ( false === $birth || $birth->format( 'Y-m-d' ) !== $value || $value > gmdate( 'Y-m-d' ) ) {
					throw new RuntimeException( 'A valid, organization-controlled date of birth is required for age eligibility.' );
				}
				$event_day = substr( $context['event.start'], 0, 10 );
				if ( $value > $event_day ) {
					throw new RuntimeException( 'Date of birth must precede the scheduled event.' );
				}
			}
			$profile[ $key ] = $value;
		}
		return array(
			'profile'  => $profile,
			'contexts' => $context,
		);
	}

	/**
	 * Recursively collect references, rejecting request-owned age assertions.
	 *
	 * @param array<string, mixed> $node      Validated AST.
	 * @param array<string, bool>  $keys      Mutable distinct profile keys.
	 * @param-out array<string, bool> $keys
	 * @param array<string, bool>  $age_keys  Server-controlled birth fields used by age conditions.
	 * @param-out array<string, bool> $age_keys
	 * @throws RuntimeException When age is asserted through request-supplied data.
	 */
	private function references( array $node, array &$keys, array &$age_keys ): void {
		foreach ( array( 'all', 'any' ) as $group ) {
			if ( isset( $node[ $group ] ) ) {
				foreach ( $node[ $group ] as $child ) {
					$this->references( $child, $keys, $age_keys );
				}
				return;
			}
		}
		if ( isset( $node['not'] ) ) {
			$this->references( $node['not'], $keys, $age_keys );
			return;
		}
		if ( ! isset( $node['source'] ) ) {
			return;
		}
		if ( in_array( $node['operator'], array( 'age_lt_at', 'age_gte_at' ), true ) ) {
			if ( 'profile' !== $node['source'] || 'date_of_birth' !== $node['field'] ) {
				throw new RuntimeException( 'Age checks require the organization-controlled date_of_birth profile field.' );
			}
			$age_keys['date_of_birth'] = true;
		}
		if ( 'profile' === $node['source'] ) {
			$keys[ (string) $node['field'] ] = true;
		}
	}
}
