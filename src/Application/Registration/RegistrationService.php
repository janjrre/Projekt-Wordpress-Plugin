<?php
/**
 * Authorized immutable registration submission command.
 *
 * @package UOP
 */

namespace UOP\Application\Registration;

use InvalidArgumentException;
use RuntimeException;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyObject;
use UOP\Application\Policy\PolicyService;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Core\TransactionManager;
use UOP\Domain\Conditions\ConditionEngine;
use UOP\Domain\Organization\OrgScope;
use UOP\Domain\Profiles\FieldRules;
use UOP\Infrastructure\Database\AuditWriter;
use UOP\Infrastructure\Database\OutboxRepository;
use UOP\Infrastructure\Database\RegistrationRepository;

/**
 * Phase one of M4: authenticated/delegated submissions; guest verification,
 * capacity, consent and offer flows are separate subsequent commands.
 */
final class RegistrationService {
	/**
	 * Bind the M2 authorization, historical persistence and audit boundary.
	 *
	 * @param RegistrationRepository $registrations Scoped registration storage.
	 * @param PolicyService          $policy        Central authorization.
	 * @param TransactionManager     $tx            Atomic command transaction.
	 * @param AuditWriter            $audit         Durable minimal audit.
	 * @param OutboxRepository       $outbox        Transactional domain events.
	 */
	public function __construct(
		private RegistrationRepository $registrations,
		private PolicyService $policy,
		private TransactionManager $tx,
		private AuditWriter $audit,
		private OutboxRepository $outbox
	) {}

	/**
	 * Submit once with a caller-generated v4 idempotency UUID.
	 *
	 * @param Actor         $actor       Current WordPress actor.
	 * @param OrgScope      $scope       Trusted organization.
	 * @param PublicId      $person_id   Linked or delegated subject.
	 * @param PublicId      $event_id    Active published event.
	 * @param PublicId|null $occurrence  Event occurrence or event-wide.
	 * @param PublicId      $command_id  Stable idempotency UUID.
	 * @param array         $input       Only published form field keys.
	 * @phpstan-param array<string, mixed> $input
	 * @param string        $utc_now     Trusted UTC instant.
	 * @param CorrelationId $correlation Request correlation.
	 * @return PublicId Canonical persisted registration UUID.
	 * @throws RuntimeException When state, policy or required fields prohibit submission.
	 * @throws InvalidArgumentException For malformed input.
	 */
	public function submit( Actor $actor, OrgScope $scope, PublicId $person_id, PublicId $event_id, ?PublicId $occurrence, PublicId $command_id, array $input, string $utc_now, CorrelationId $correlation ): PublicId {
		return $this->tx->run(
			function () use ( $actor, $scope, $person_id, $event_id, $occurrence, $command_id, $input, $utc_now, $correlation ): PublicId {
				$person = $this->registrations->lock_person( $scope, $person_id );
				if ( ! $person ) {
					throw new RuntimeException( 'Registration subject unavailable.' );
				}
				$form = $this->registrations->active_event_form( $scope, $event_id );
				if ( ! $form ) {
					throw new RuntimeException( 'No active published registration form.' );
				}
				$event_post_id = (int) $form['event_post_id'];
				$post          = get_post( $event_post_id );
				if ( ! $post || 'uop_event' !== $post->post_type || 'publish' !== $post->post_status ) {
					throw new RuntimeException( 'Event is not published.' );
				}
				if ( ( null !== $form['registration_open_at'] && $utc_now < $form['registration_open_at'] )
					|| ( null !== $form['registration_close_at'] && $utc_now > $form['registration_close_at'] ) ) {
					throw new RuntimeException( 'Registration window is closed.' );
				}
				$occurrence_id = $this->registrations->occurrence_id( $scope, $event_post_id, $occurrence );
				$resource      = new PolicyObject( $scope->id, 'registration', (int) $person['id'], (int) $person['id'], $event_post_id );
				if ( ! $this->policy->can( $actor, 'registration.create', $resource )->allowed ) {
					throw new RuntimeException( 'Registration not permitted.' );
				}
				$prior = $this->registrations->by_submission_key( $scope, $command_id );
				if ( $prior ) {
					if ( (int) $prior['person_id'] !== (int) $person['id'] || (int) $prior['event_post_id'] !== $event_post_id
						|| (int) $prior['occurrence_id'] !== $occurrence_id || (int) $prior['form_version_id'] !== (int) $form['form_version_id'] ) {
						throw new RuntimeException( 'Idempotency key is bound to another submission.' );
					}
					$original   = json_decode( (string) $prior['payload_json'], true, 64, JSON_THROW_ON_ERROR );
					$old_fields = $original['fields'];
					$new_fields = $input;
					ksort( $old_fields );
					ksort( $new_fields );
					if ( $old_fields !== $new_fields ) {
						throw new RuntimeException( 'Idempotency key was reused with different field values.' );
					}
					return PublicId::from_binary( $prior['public_id'] );
				}
				if ( $this->registrations->already_registered( $scope, (int) $person['id'], $event_post_id, $occurrence_id ) ) {
					throw new RuntimeException( 'Subject already has an active registration.' );
				}
				$schema_json = (string) $form['schema_json'];
				if ( ! hash_equals( $form['checksum'], hash( 'sha256', $schema_json, true ) ) ) {
					throw new RuntimeException( 'Published form integrity check failed.' );
				}
				$schema  = json_decode( $schema_json, true, 64, JSON_THROW_ON_ERROR );
				$values  = array();
				$types   = array();
				$stashed = array();
				foreach ( $schema['fields'] as $field ) {
					if ( 'consent' === $field['type'] ) {
						// Consent records must exist in the same transaction (M5).
						throw new RuntimeException( 'Consent processing is not yet available.' );
					}
					if ( isset( $field['visible_when'] ) && $this->uses_profile_condition( $field['visible_when'] ) ) {
						throw new RuntimeException( 'Server-side profile conditions require authoritative facts.' );
					}
					$key             = $field['key'];
					$types[ $key ]   = $field['type'];
					$stashed[ $key ] = array_key_exists( $key, $input ) ? $input[ $key ] : null;
				}
				if ( array_diff( array_keys( $input ), array_keys( $types ) ) ) {
					throw new InvalidArgumentException( 'Unknown form field.' );
				}
				$engine = new ConditionEngine();
				$stored = array();
				foreach ( $schema['fields'] as $field ) {
					$key     = $field['key'];
					$visible = true;
					if ( isset( $field['visible_when'] ) ) {
						$visible = $engine->evaluate(
							$field['visible_when'],
							array(
								'profile'      => array(),
								'registration' => $stashed,
							)
						);
					}
					if ( ! $visible ) {
						if ( array_key_exists( $key, $input ) ) {
							throw new InvalidArgumentException( 'A hidden field cannot be submitted.' );
						}
						continue;
					}
					$value = $stashed[ $key ];
					if ( $field['required'] && ( null === $value || '' === $value || array() === $value || false === $value ) ) {
						throw new InvalidArgumentException( 'Required field is missing.' );
					}
					$normalized     = FieldRules::normalize( $field['type'], $value, $field['options'] ?? array() );
					$stored[ $key ] = $value;
					$values[ $key ] = $normalized;
				}
				$uuid       = PublicId::generate();
				$version_id = PublicId::from_binary( $form['form_version_public_id'] );
				$id         = $this->registrations->insert( $scope, $uuid, $command_id, (int) $person['id'], $actor->user_id, $event_post_id, $occurrence_id, (int) $form['form_version_id'], $version_id, $stored, $values, $types, $utc_now, $correlation );
				$event      = PublicId::generate();
				$registered = new PolicyObject( $scope->id, 'registration', $id, (int) $person['id'], $event_post_id );
				$this->audit->append( $scope, $actor, 'registration.submitted', $registered, 'success', $correlation, $event );
				$this->outbox->append(
					$scope,
					$event,
					'registration',
					$id,
					'registration.submitted',
					$correlation,
					array(
						'public_id' => $uuid->to_string(),
						'status'    => 'submitted',
					)
				);
				return $uuid;
			}
		);
	}

	/**
	 * Prevent profile-based rules from evaluating against untrusted/missing data.
	 *
	 * @param array<string, mixed> $node Condition AST node.
	 * @return bool True when authoritative profile facts are required.
	 */
	private function uses_profile_condition( array $node ): bool {
		foreach ( array( 'all', 'any' ) as $group ) {
			if ( isset( $node[ $group ] ) ) {
				foreach ( $node[ $group ] as $child ) {
					if ( $this->uses_profile_condition( $child ) ) {
						return true;
					}
				}
				return false;
			}
		}
		if ( isset( $node['not'] ) ) {
			return $this->uses_profile_condition( $node['not'] );
		}
		return 'profile' === ( $node['source'] ?? '' );
	}
}
