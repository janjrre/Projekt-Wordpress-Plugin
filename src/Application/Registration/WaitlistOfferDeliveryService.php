<?php
/**
 * Private, transactional waitlist-offer mail handoff.
 *
 * @package UOP
 */

namespace UOP\Application\Registration;

use RuntimeException;
use UOP\Application\Communication\EmailTemplateCatalog;
use UOP\Application\Communication\EmailTemplateRules;
use UOP\Core\PublicId;
use UOP\Core\TransactionManager;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\EmailMessageRepository;

/**
 * A held seat and a recipient-specific immutable mail must commit together.
 * The bearer secret goes into neither queue arguments nor domain events.
 */
final class WaitlistOfferDeliveryService {
	/**
	 * @param EmailMessageRepository $messages Stored private mail envelopes.
	 * @param EmailTemplateCatalog   $catalog  Frozen templates.
	 * @param EmailTemplateRules     $rules    Restricted render variables.
	 * @param TransactionManager     $tx       The active capacity transaction.
	 */
	public function __construct(
		private EmailMessageRepository $messages,
		private EmailTemplateCatalog $catalog,
		private EmailTemplateRules $rules,
		private TransactionManager $tx
	) {}

	/** An independent operator switch prevents untested live offers. */
	public function ready(): bool {
		return (bool) apply_filters( 'uop_waitlist_offer_enabled', false )
			&& function_exists( 'as_enqueue_async_action' )
			&& str_starts_with( home_url( '/' ), 'https://' );
	}

	/**
	 * Call only *inside* the bucket-locked capacity transaction.
	 *
	 * @param OrgScope            $scope   Trusted organization.
	 * @param array<string,mixed> $row     Locked FIFO registration.
	 * @param PublicId            $offer   Newly created offer identity.
	 * @param string              $token   Fresh 256-bit one-time bearer.
	 * @param string              $expires Offer expiry in UTC.
	 * @param string              $now     Trusted UTC instant.
	 * @throws RuntimeException If delivery cannot be safely prepared.
	 */
	public function queue_inside( OrgScope $scope, array $row, PublicId $offer, string $token, string $expires, string $now ): void {
		if ( ! $this->ready() ) {
			return;
		}
		if ( null === ( $row['email_verified_at'] ?? null ) || ! is_string( $row['contact_email'] ?? null )
			|| ! is_email( $row['contact_email'] ) ) {
			throw new RuntimeException( 'No verified recipient for held offer.' );
		}
		$key = hash( 'sha256', 'uop:waitlist-offer:' . $scope->id . ':' . $offer->to_string(), true );
		if ( $this->messages->by_command( $scope, $key ) ) {
			throw new RuntimeException( 'Offer mail already queued.' );
		}
		$post = get_post( (int) $row['event_post_id'] );
		if ( ! $post || 'uop_event' !== $post->post_type || 'publish' !== $post->post_status
			|| ! empty( $post->post_password ) ) {
			throw new RuntimeException( 'Offer event is no longer public.' );
		}
		$link     = home_url( '/?uop-offer=1' ) . '#offer_id=' . rawurlencode( $offer->to_string() ) . '&token=' . $token;
		$locale   = 'de_DE';
		$defaults = $this->catalog->defaults( 'waitlist_offer', $locale );
		$title    = mb_substr( (string) get_the_title( (int) $row['event_post_id'] ), 0, 250 );
		$rendered = $this->rules->render(
			'waitlist_offer',
			$locale,
			$defaults['subject'],
			$defaults['body_text'],
			$defaults['body_html'],
			array(
				'participant_name' => __( 'Guest', 'uop-core' ),
				'event_title'      => $title,
				'action_url'       => $link,
				'expires_at'       => $expires . ' UTC',
			)
		);
		$digest  = $this->rules->validate( 'waitlist_offer', $locale, $defaults['subject'], $defaults['body_text'], $defaults['body_html'] );
		$message = PublicId::generate();
		$this->messages->enqueue( $scope, $message, $key, (int) $row['id'], (string) $row['contact_email'], 'waitlist_offer', $locale, 1, $digest, $rendered, $now );
		$this->tx->after_commit(
			static function () use ( $scope, $message ): void {
				if ( function_exists( 'as_enqueue_async_action' ) ) {
					as_enqueue_async_action( 'uop_mail_deliver', array( $scope->id, $message->to_string() ), 'uop', true );
				}
			}
		);
	}
}
