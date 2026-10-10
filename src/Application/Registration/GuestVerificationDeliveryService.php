<?php
/**
 * Private guest verification mail handoff, consuming only committed events.
 *
 * @package UOP
 */

namespace UOP\Application\Registration;

use InvalidArgumentException;
use UOP\Application\Communication\EmailTemplateCatalog;
use UOP\Application\Communication\EmailTemplateRules;
use UOP\Application\Event\DomainEventDto;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyObject;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Core\TransactionManager;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\AuditWriter;
use UOP\Infrastructure\Database\EmailMessageRepository;
use UOP\Infrastructure\Database\OutboxRepository;
use UOP\Infrastructure\Database\RegistrationRepository;

/**
 * Queue the immutable action mail and its hashed challenge in one transaction.
 * No token is emitted through REST, action queue arguments, outbox, or audit.
 */
final class GuestVerificationDeliveryService {
	/**
	 * Construct the trusted post-commit guest verification dispatcher.
	 *
	 * @param RegistrationRepository $registrations Scoped registration rows.
	 * @param EmailMessageRepository $messages      Durable mail snapshot storage.
	 * @param EmailTemplateCatalog   $catalog       Internal allowlisted template.
	 * @param EmailTemplateRules     $rules         Strict interpolation and URL encoding.
	 * @param TransactionManager     $tx            Atomic challenge and mail enqueue.
	 * @param AuditWriter            $audit         Minimal proof of issuance.
	 * @param OutboxRepository       $outbox        Token-free events.
	 */
	public function __construct(
		private RegistrationRepository $registrations,
		private EmailMessageRepository $messages,
		private EmailTemplateCatalog $catalog,
		private EmailTemplateRules $rules,
		private TransactionManager $tx,
		private AuditWriter $audit,
		private OutboxRepository $outbox
	) {}

	/** Only explicitly enabled, HTTPS-capable, queue-capable sites accept guest writes. */
	public function ready(): bool {
		return (bool) apply_filters( 'uop_guest_verification_enabled', false )
			&& function_exists( 'as_enqueue_async_action' )
			&& str_starts_with( home_url( '/' ), 'https://' );
	}

	/**
	 * Route committed guest events by non-sensitive UUID, never raw input.
	 *
	 * @param DomainEventDto $event Post-commit outbox DTO.
	 */
	public function on_event( DomainEventDto $event ): void {
		if ( 'registration.email_verification_required' !== $event->event_name || ! $this->ready() ) {
			return;
		}
		try {
			$public_id = PublicId::from_string( $event->public_id );
		} catch ( InvalidArgumentException ) {
			return;
		}
		$org_id = (int) get_option( 'uop_default_organization_id', 0 );
		if ( $org_id > 0 ) {
			$this->queue( new OrgScope( $org_id ), $public_id, gmdate( 'Y-m-d H:i:s' ) );
		}
	}

	/**
	 * At-least-once consumer: idempotent per registration, despite retries.
	 *
	 * @param OrgScope $scope               Trusted site organization.
	 * @param PublicId $registration Committed registration UUID.
	 * @param string   $now          Trusted UTC timestamp.
	 * @return PublicId|null Queue message UUID, if pending; no secret returned.
	 */
	public function queue( OrgScope $scope, PublicId $registration, string $now ): ?PublicId {
		if ( ! $this->ready() ) {
			return null;
		}
		return $this->tx->run(
			function () use ( $scope, $registration, $now ): ?PublicId {
				$row = $this->registrations->lock_registration( $scope, $registration );
				if ( ! $row || 'guest' !== $row['source'] || null !== $row['email_verified_at']
					|| ! in_array( $row['status'], array( 'submitted', 'review' ), true )
					|| ! is_string( $row['contact_email'] ) || ! is_email( $row['contact_email'] ) ) {
					return null;
				}
				$key      = hash( 'sha256', 'uop:guest-verification:' . $scope->id . ':' . $registration->to_string(), true );
				$previous = $this->messages->by_command( $scope, $key );
				if ( $previous ) {
					return PublicId::from_binary( (string) $previous['public_id'] );
				}

				$token   = bin2hex( random_bytes( 32 ) );
				$expires = gmdate( 'Y-m-d H:i:s', strtotime( $now . ' UTC +24 hours' ) );
				$changed = $this->registrations->challenge( $scope, (int) $row['id'], hash( 'sha256', $token, true ), $expires, $now );
				if ( ! $changed ) {
					throw new \RuntimeException( 'Unable to issue guest challenge.' );
				}
				$base     = home_url( '/?uop-verify=1' );
				$link     = $base . '#registration_id=' . rawurlencode( $registration->to_string() ) . '&token=' . $token;
				$post     = get_post( (int) $row['event_post_id'] );
				$name     = $post ? (string) get_the_title( $post ) : __( 'Event', 'uop-core' );
				$locale   = 'de_DE';
				$defaults = $this->catalog->defaults( 'email_verification', $locale );
				$rendered = $this->rules->render(
					'email_verification',
					$locale,
					$defaults['subject'],
					$defaults['body_text'],
					$defaults['body_html'],
					array(
						'participant_name' => __( 'Guest', 'uop-core' ),
						'event_title'      => mb_substr( $name, 0, 250 ),
						'action_url'       => $link,
						'expires_at'       => $expires . ' UTC',
					)
				);
				$digest   = $this->rules->validate( 'email_verification', $locale, $defaults['subject'], $defaults['body_text'], $defaults['body_html'] );
				$message  = PublicId::generate();
				$this->messages->enqueue( $scope, $message, $key, (int) $row['id'], (string) $row['contact_email'], 'email_verification', $locale, 1, $digest, $rendered, $now );
				$object = new PolicyObject( $scope->id, 'registration', (int) $row['id'], (int) $row['person_id'], (int) $row['event_post_id'] );
				$event  = PublicId::generate();
				$trace  = CorrelationId::generate();
				$this->audit->append( $scope, new Actor( 0 ), 'registration.verification_issued', $object, 'success', $trace, $event );
				$this->outbox->append( $scope, $event, 'registration', (int) $row['id'], 'registration.verification_issued', $trace, array( 'public_id' => $registration->to_string() ) );
				$this->tx->after_commit(
					static function () use ( $scope, $message ): void {
						if ( function_exists( 'as_enqueue_async_action' ) ) {
							as_enqueue_async_action( 'uop_mail_deliver', array( $scope->id, $message->to_string() ), 'uop', true );
						}
					}
				);
				return $message;
			}
		);
	}
}
