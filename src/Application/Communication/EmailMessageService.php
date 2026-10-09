<?php
/**
 * Authorized immutable rendering before durable message queueing.
 *
 * @package UOP
 */

namespace UOP\Application\Communication;

use InvalidArgumentException;
use RuntimeException;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyObject;
use UOP\Application\Policy\PolicyService;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Core\TransactionManager;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\AuditWriter;
use UOP\Infrastructure\Database\EmailMessageRepository;
use UOP\Infrastructure\Database\OutboxRepository;

/** No real email is sent within this application command. */
final class EmailMessageService {
	/**
	 * Bind authorized templates, immutability and transactional audit.
	 *
	 * @param EmailTemplateService   $templates Authorized template resolution.
	 * @param EmailTemplateRules     $rules     Safe rendering.
	 * @param EmailMessageRepository $messages Durable frozen messages.
	 * @param PolicyService          $policy    Live actor authorization.
	 * @param TransactionManager     $tx        Transaction manager.
	 * @param AuditWriter            $audit     Minimal audit.
	 * @param OutboxRepository       $outbox    Token-free domain handoff.
	 */
	public function __construct(
		private EmailTemplateService $templates,
		private EmailTemplateRules $rules,
		private EmailMessageRepository $messages,
		private PolicyService $policy,
		private TransactionManager $tx,
		private AuditWriter $audit,
		private OutboxRepository $outbox
	) {}

	/**
	 * Persist one final rendered email for a unique command identity.
	 *
	 * Caller must have communication.send; neither the caller nor the outbox
	 * receives private action links. Repeated command UUIDs return the prior
	 * message without changing body, template hash, recipient, or delivery state.
	 *
	 * @param Actor                $actor       Current authorized sender.
	 * @param OrgScope             $scope       Trusted organization.
	 * @param PublicId             $command     Stable original command UUID.
	 * @param string               $recipient   Recipient email.
	 * @param string               $template    Registered template key.
	 * @param string               $locale      Registered locale.
	 * @param array<string,string> $variables   Subject/body merge fields.
	 * @param PublicId|null        $registration Optional scoped registration.
	 * @param string               $now         UTC timestamp.
	 * @param CorrelationId        $correlation Request trace.
	 * @return PublicId Message public UUID.
	 * @throws InvalidArgumentException When the recipient is invalid.
	 */
	public function queue( Actor $actor, OrgScope $scope, PublicId $command, string $recipient, string $template, string $locale, array $variables, ?PublicId $registration, string $now, CorrelationId $correlation ): PublicId {
		if ( ! is_email( $recipient ) || strlen( $recipient ) > 254 || preg_match( '/[\r\n\x00-\x1F\x7F]/', $recipient ) ) {
			throw new InvalidArgumentException( 'Invalid mail recipient.' );
		}
		$key = hash( 'sha256', $scope->id . ':' . $command->to_string(), true );
		return $this->tx->run(
			function () use ( $actor, $scope, $key, $recipient, $template, $locale, $variables, $registration, $now, $correlation ): PublicId {
				$object = new PolicyObject( $scope->id, 'organization', $scope->id );
				if ( ! $this->policy->can( $actor, 'communication.send', $object )->allowed ) {
					throw new RuntimeException( 'Mail queue access denied.' );
				}
				$registration_id = null;
				if ( null !== $registration ) {
					$registration_id = $this->messages->registration_id( $scope, $registration );
					if ( null === $registration_id ) {
						throw new RuntimeException( 'Mail registration does not belong to organization.' );
					}
				}
				$previous = $this->messages->by_command( $scope, $key );
				if ( $previous ) {
					if ( $previous['recipient'] !== $recipient || $previous['template_key'] !== $template
						|| $previous['locale'] !== $locale || (int) ( $previous['registration_id'] ?? 0 ) !== ( $registration_id ?? 0 ) ) {
						throw new RuntimeException( 'Conflicting mail idempotency request.' );
					}
					return PublicId::from_binary( (string) $previous['public_id'] );
				}
				$template_data = $this->templates->get( $actor, $scope, $template, $locale );
				$rendered      = $this->rules->render(
					$template,
					$locale,
					$template_data['subject'],
					$template_data['body_text'],
					$template_data['body_html'],
					$variables
				);
				$uuid          = PublicId::generate();
				$hash          = hex2bin( $template_data['hash'] );
				if ( false === $hash ) {
					throw new RuntimeException( 'Mail template hash invalid.' );
				}
				$this->messages->enqueue( $scope, $uuid, $key, $registration_id, $recipient, $template, $locale, $template_data['revision'], $hash, $rendered, $now );
				$event = PublicId::generate();
				$this->audit->append( $scope, $actor, 'mail.queued', $object, 'success', $correlation, $event );
				$this->outbox->append( $scope, $event, 'email', $scope->id, 'mail.queued', $correlation, array( 'public_id' => $uuid->to_string() ) );
				$this->tx->after_commit(
					static function () use ( $scope, $uuid ): void {
						if ( function_exists( 'as_enqueue_async_action' ) ) {
							as_enqueue_async_action( 'uop_mail_deliver', array( $scope->id, $uuid->to_string() ), 'uop', true );
						}
					}
				);
				return $uuid;
			}
		);
	}

	/**
	 * Explicit retry only for a known failed transport outcome, never a sending
	 * or accepted message; frozen content and template revision remain unchanged.
	 *
	 * @param Actor         $actor       Current communication operator.
	 * @param OrgScope      $scope       Trusted tenant.
	 * @param PublicId      $message     Message public identity.
	 * @param CorrelationId $correlation Request trace.
	 * @return bool Whether the message was requeued.
	 */
	public function retry_failed( Actor $actor, OrgScope $scope, PublicId $message, CorrelationId $correlation ): bool {
		return $this->tx->run(
			function () use ( $actor, $scope, $message, $correlation ): bool {
				$object = new PolicyObject( $scope->id, 'organization', $scope->id );
				if ( ! $this->policy->can( $actor, 'communication.send', $object )->allowed ) {
					throw new RuntimeException( 'Mail retry access denied.' );
				}
				if ( ! $this->messages->retry_failed( $scope, $message ) ) {
					return false;
				}
				$event = PublicId::generate();
				$this->audit->append( $scope, $actor, 'mail.retry_requested', $object, 'success', $correlation, $event );
				$this->outbox->append( $scope, $event, 'email', $scope->id, 'mail.retry_requested', $correlation, array( 'public_id' => $message->to_string() ) );
				$this->tx->after_commit(
					static function () use ( $scope, $message ): void {
						if ( function_exists( 'as_enqueue_async_action' ) ) {
							as_enqueue_async_action( 'uop_mail_deliver', array( $scope->id, $message->to_string() ), 'uop', true );
						}
					}
				);
				return true;
			}
		);
	}
}
