<?php
/**
 * Authorized immutable registration submission command.
 *
 * @package UOP
 */

namespace UOP\Application\Registration;

use InvalidArgumentException;
use RuntimeException;
use UOP\Application\Consent\ConsentRecordService;
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
use UOP\Infrastructure\Database\PersonRepository;

/**
 * Shared validation for authenticated, delegated and anonymous guest requests.
 * Consent records are atomic with snapshots; protected mail delivery is separate.
 */
final class RegistrationService {
	/**
	 * Bind the M2 authorization, historical persistence and audit boundary.
	 *
	 * @param RegistrationRepository    $registrations Scoped registration storage.
	 * @param PolicyService             $policy        Central authorization.
	 * @param TransactionManager        $tx            Atomic command transaction.
	 * @param AuditWriter               $audit         Durable minimal audit.
	 * @param OutboxRepository          $outbox        Transactional domain events.
	 * @param RegistrationFactsService  $facts         Scoped and policy-filtered eligibility facts.
	 * @param PersonRepository          $people        Scoped guest person persistence.
	 * @param ConsentRecordService|null $consents      Optional immutable consent evidence writer.
	 */
	public function __construct(
		private RegistrationRepository $registrations,
		private PolicyService $policy,
		private TransactionManager $tx,
		private AuditWriter $audit,
		private OutboxRepository $outbox,
		private RegistrationFactsService $facts,
		private PersonRepository $people,
		private ?ConsentRecordService $consents = null
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
			fn (): PublicId => $this->submit_inside( $actor, $scope, $person_id, $event_id, $occurrence, $command_id, $input, $utc_now, $correlation, false )
		);
	}

	/**
	 * Submit an anonymous public-event guest without linking any account.
	 *
	 * Creates a fresh Person in the same transaction and requires a verified
	 * email gate. Only the domain outbox asks M5 to deliver a private challenge.
	 *
	 * @param OrgScope      $scope       Trusted organization.
	 * @param PublicId      $event_id    Public event UUID.
	 * @param PublicId|null $occurrence  Optional scheduled occurrence.
	 * @param PublicId      $command_id  Client idempotency UUID.
	 * @param array         $input       Published form values, including email.
	 * @phpstan-param array<string, mixed> $input
	 * @param string        $utc_now     Trusted UTC time.
	 * @param CorrelationId $correlation Request correlation.
	 * @return PublicId Registration public UUID.
	 * @throws RuntimeException For private events or unauthorized submissions.
	 */
	public function submit_guest( OrgScope $scope, PublicId $event_id, ?PublicId $occurrence, PublicId $command_id, array $input, string $utc_now, CorrelationId $correlation ): PublicId {
		return $this->tx->run(
			function () use ( $scope, $event_id, $occurrence, $command_id, $input, $utc_now, $correlation ): PublicId {
				$form = $this->registrations->active_event_form( $scope, $event_id );
				if ( ! $form || 'public' !== $form['visibility'] || 1 !== (int) $form['require_email_verification'] ) {
					throw new RuntimeException( 'Public guest registration requires verified contact.' );
				}
				$post_id = (int) $form['event_post_id'];
				$post    = get_post( $post_id );
				if ( ! $post || 'uop_event' !== $post->post_type || 'publish' !== $post->post_status ) {
					throw new RuntimeException( 'Guest event is unavailable.' );
				}
				$occurrence_id = $this->registrations->occurrence_id( $scope, $post_id, $occurrence );
				$prior         = $this->registrations->by_submission_key( $scope, $command_id );
				if ( $prior ) {
					if ( 'guest' !== $prior['source'] || (int) $prior['event_post_id'] !== $post_id
						|| (int) $prior['occurrence_id'] !== $occurrence_id
						|| (int) $prior['form_version_id'] !== (int) $form['form_version_id'] ) {
						throw new RuntimeException( 'Guest command belongs to another submission.' );
					}
					$original = self::replay_fields( json_decode( (string) $prior['payload_json'], true, 64, JSON_THROW_ON_ERROR )['fields'] );
					$incoming = $input;
					ksort( $original );
					ksort( $incoming );
					if ( $original !== $incoming ) {
						throw new RuntimeException( 'Guest command payload was modified.' );
					}
					return PublicId::from_binary( $prior['public_id'] );
				}
				$guest_id = PublicId::generate();
				$this->people->create( $scope, $guest_id, 'Guest', null, $utc_now );
				return $this->submit_inside( new Actor( 0 ), $scope, $guest_id, $event_id, $occurrence, $command_id, $input, $utc_now, $correlation, true );
			}
		);
	}

	/**
	 * Persist authenticated and strictly internal guest submissions identically.
	 *
	 * @param Actor         $actor       Account actor or a new anonymous guest.
	 * @param OrgScope      $scope       Trusted tenant.
	 * @param PublicId      $person_id   Existing subject or internally created guest.
	 * @param PublicId      $event_id    Published event UUID.
	 * @param PublicId|null $occurrence  Selected occurrence.
	 * @param PublicId      $command_id  Idempotent operation UUID.
	 * @param array         $input       Form field values.
	 * @phpstan-param array<string, mixed> $input
	 * @param string        $utc_now     Trusted time.
	 * @param CorrelationId $correlation Trace identity.
	 * @param bool          $guest       Internal anonymous guest flag.
	 * @return PublicId Stored registration identity.
	 * @throws RuntimeException When business or policy checks fail.
	 * @throws InvalidArgumentException When form values are invalid.
	 */
	private function submit_inside( Actor $actor, OrgScope $scope, PublicId $person_id, PublicId $event_id, ?PublicId $occurrence, PublicId $command_id, array $input, string $utc_now, CorrelationId $correlation, bool $guest ): PublicId {
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
		if ( $guest && ( 'public' !== $form['visibility'] || 1 !== (int) $form['require_email_verification'] ) ) {
			throw new RuntimeException( 'Guest admission requires a public, verified-contact event.' );
		}
		if ( ( null !== $form['registration_open_at'] && $utc_now < $form['registration_open_at'] )
			|| ( null !== $form['registration_close_at'] && $utc_now > $form['registration_close_at'] ) ) {
			throw new RuntimeException( 'Registration window is closed.' );
		}
		$occurrence_id = $this->registrations->occurrence_id( $scope, $event_post_id, $occurrence );
		$resource      = new PolicyObject( $scope->id, 'registration', (int) $person['id'], (int) $person['id'], $event_post_id );
		if ( ! $guest && ! $this->policy->can( $actor, 'registration.create', $resource )->allowed ) {
			throw new RuntimeException( 'Registration not permitted.' );
		}
		$prior = $this->registrations->by_submission_key( $scope, $command_id );
		if ( $prior ) {
			if ( (int) $prior['person_id'] !== (int) $person['id'] || (int) $prior['event_post_id'] !== $event_post_id
				|| (int) $prior['occurrence_id'] !== $occurrence_id || (int) $prior['form_version_id'] !== (int) $form['form_version_id'] ) {
				throw new RuntimeException( 'Idempotency key is bound to another submission.' );
			}
			$original   = json_decode( (string) $prior['payload_json'], true, 64, JSON_THROW_ON_ERROR );
			$old_fields = self::replay_fields( $original['fields'] );
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
		$schema            = json_decode( $schema_json, true, 64, JSON_THROW_ON_ERROR );
		$conditions        = array();
		$event_eligibility = null;
		if ( null !== $form['eligibility_json'] && '' !== $form['eligibility_json'] ) {
			$event_eligibility = json_decode( (string) $form['eligibility_json'], true, 64, JSON_THROW_ON_ERROR );
			if ( ! is_array( $event_eligibility ) ) {
				throw new RuntimeException( 'Event eligibility is malformed.' );
			}
			$conditions[] = $event_eligibility;
		}
		$values  = array();
		$types   = array();
		$stashed = array();
		foreach ( $schema['fields'] as $field ) {
			if ( isset( $field['visible_when'] ) ) {
				$conditions[] = $field['visible_when'];
			}
			$key             = $field['key'];
			$types[ $key ]   = $field['type'];
			$stashed[ $key ] = array_key_exists( $key, $input ) ? $input[ $key ] : null;
		}
		if ( array_diff( array_keys( $input ), array_keys( $types ) ) ) {
			throw new InvalidArgumentException( 'Unknown form field.' );
		}
		$trusted          = $this->facts->load( $actor, $scope, (int) $person['id'], $event_post_id, $occurrence_id, $conditions );
		$engine           = new ConditionEngine();
		$stored           = array();
		$pending_consents = array();
		foreach ( $schema['fields'] as $field ) {
			$key     = $field['key'];
			$visible = true;
			if ( isset( $field['visible_when'] ) ) {
				$visible = $engine->evaluate(
					$field['visible_when'],
					array(
						'profile'      => $trusted['profile'],
						'registration' => $stashed,
					),
					$trusted['contexts']
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
			if ( 'consent' === $field['type'] ) {
				if ( null === $value && ! $field['required'] ) {
					continue;
				}
				if ( ! is_bool( $value ) ) {
					throw new InvalidArgumentException( 'Consent requires an explicit boolean decision.' );
				}
				if ( $field['required'] && ! $value ) {
					throw new InvalidArgumentException( 'Required consent must be granted.' );
				}
				if ( ! $this->consents ) {
					throw new RuntimeException( 'Consent evidence service unavailable.' );
				}
				$pending_consents[ $key ] = array(
					'version'  => PublicId::from_string( $field['consent_version_public_id'] ),
					'decision' => $value,
					'required' => $field['required'],
				);
				continue;
			}
			$normalized     = FieldRules::normalize( $field['type'], $value, $field['options'] ?? array() );
			$stored[ $key ] = $value;
			$values[ $key ] = $normalized;
		}

		if ( null !== $event_eligibility && ! $engine->evaluate(
			$event_eligibility,
			array(
				'profile'      => $trusted['profile'],
				'registration' => $stored,
			),
			$trusted['contexts']
		) ) {
			throw new RuntimeException( 'Event eligibility requirements were not met.' );
		}
		$contact_email = null;
		foreach ( $schema['fields'] as $field ) {
			if ( 'email' === $field['type'] && isset( $stored[ $field['key'] ] ) ) {
				if ( ! is_string( $stored[ $field['key'] ] ) || ! is_email( $stored[ $field['key'] ] ) ) {
					throw new InvalidArgumentException( 'Invalid contact email.' );
				}
				if ( null !== $contact_email && $contact_email !== $stored[ $field['key'] ] ) {
					throw new InvalidArgumentException( 'One verified contact email is required.' );
				}
				$contact_email = $stored[ $field['key'] ];
			}
		}
		if ( 1 === (int) $form['require_email_verification'] && null === $contact_email ) {
			throw new InvalidArgumentException( 'Contact email is required for verification.' );
		}
		$uuid            = PublicId::generate();
		$version_id      = PublicId::from_binary( $form['form_version_public_id'] );
		$evidence_writer = null;
		if ( $pending_consents ) {
			$evidence_writer = function ( int $registration_id ) use ( $actor, $scope, $person, $event_post_id, $pending_consents, $stored, $values, $utc_now, $correlation, $guest ): array {
				$historical_fields = $stored;
				$reference_values  = $values;
				foreach ( $pending_consents as $key => $consent ) {
					if ( ! $this->consents ) {
						throw new RuntimeException( 'Consent evidence service unavailable.' );
					}
					$record = $this->consents->record_submission(
						$actor,
						$scope,
						(int) $person['id'],
						$event_post_id,
						$registration_id,
						$consent['version'],
						$consent['decision'],
						$consent['required'],
						$guest,
						$utc_now,
						$correlation
					);
					$historical_fields[ $key ] = $record['evidence'];
					$reference_values[ $key ]  = array(
						array(
							'slot'    => 'value_reference',
							'value'   => $record['reference_id'],
							'ordinal' => 0,
						),
					);
				}
				return array(
					'fields' => $historical_fields,
					'values' => $reference_values,
				);
			};
		}
		$id         = $this->registrations->insert( $scope, $uuid, $command_id, (int) $person['id'], $actor->user_id, $event_post_id, $occurrence_id, (int) $form['form_version_id'], $version_id, $stored, $values, $types, $contact_email, $utc_now, $correlation, $guest ? 'guest' : 'portal', $evidence_writer );
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
		if ( $guest ) {
			$this->outbox->append(
				$scope,
				PublicId::generate(),
				'registration',
				$id,
				'registration.email_verification_required',
				$correlation,
				array( 'public_id' => $uuid->to_string() )
			);
		}
		return $uuid;
	}

	/**
	 * Restore only consent decisions for safe idempotent comparison against
	 * original form input, without exposing a parallel stored boolean truth.
	 *
	 * @param array<string, mixed> $fields Historical registration snapshot.
	 * @return array<string, mixed>
	 */
	private static function replay_fields( array $fields ): array {
		foreach ( $fields as $key => $value ) {
			if ( is_array( $value ) && isset( $value['record_public_id'], $value['definition_key'], $value['version_public_id'], $value['decision'] )
				&& in_array( $value['decision'], array( 'granted', 'denied' ), true ) ) {
				$fields[ $key ] = 'granted' === $value['decision'];
			}
		}
		return $fields;
	}
}
