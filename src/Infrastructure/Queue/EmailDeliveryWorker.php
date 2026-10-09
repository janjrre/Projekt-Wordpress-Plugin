<?php
/**
 * Single-claim WordPress mail transport and queued-message recovery.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Queue;

use Throwable;
use UOP\Core\PublicId;
use UOP\Core\TransactionManager;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\Connection;
use UOP\Infrastructure\Database\EmailMessageRepository;

/**
 * Accepted means wp_mail returned true, NOT mailbox delivery.
 * Crash after committing 'sending' is ambiguous and needs manual resolution.
 */
final class EmailDeliveryWorker {
	/**
	 * Bind transport to frozen, scoped message storage.
	 *
	 * @param Connection             $db       Database connection.
	 * @param string                 $prefix   Trusted site prefix.
	 * @param EmailMessageRepository $messages Durable message envelopes.
	 * @param TransactionManager     $tx       DB transaction boundary.
	 */
	public function __construct( private Connection $db, private string $prefix, private EmailMessageRepository $messages, private TransactionManager $tx ) {}

	/** Register private queue jobs and periodic recovery, not a public endpoint. */
	public function register_hooks(): void {
		add_action( 'uop_mail_deliver', array( $this, 'deliver' ), 10, 2 );
		add_action( 'uop_mail_sweep', array( $this, 'sweep' ) );
		add_action(
			'init',
			static function (): void {
				if ( function_exists( 'as_schedule_recurring_action' ) && function_exists( 'as_next_scheduled_action' )
					&& false === as_next_scheduled_action( 'uop_mail_sweep', array(), 'uop' ) ) {
					as_schedule_recurring_action( time() + 60, 300, 'uop_mail_sweep', array(), 'uop', true );
				}
			},
			25
		);
	}

	/** Requeue a bounded set of known queued messages, never ambiguous sending rows. */
	public function sweep(): void {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return;
		}
		$organizations = $this->db->rows(
			"SELECT id FROM %i WHERE status = 'active' ORDER BY id ASC LIMIT 100",
			array( $this->prefix . 'organizations' )
		);
		foreach ( $organizations as $organization ) {
			$scope = new OrgScope( (int) $organization['id'] );
			foreach ( $this->messages->pending( $scope ) as $binary_id ) {
				as_enqueue_async_action( 'uop_mail_deliver', array( $scope->id, PublicId::from_binary( $binary_id )->to_string() ), 'uop', true );
			}
		}
	}

	/**
	 * Reserve and send once without holding a transaction across wp_mail.
	 *
	 * @param int    $organization_id Scoped queue argument.
	 * @param string $uuid            Public mail UUID, never content or URL.
	 */
	public function deliver( int $organization_id, string $uuid ): void {
		if ( $organization_id < 1 ) {
			return;
		}
		$scope   = new OrgScope( $organization_id );
		$message = PublicId::from_string( $uuid );
		$claim   = $this->tx->run( fn (): ?array => $this->messages->claim( $scope, $message ) );
		if ( null === $claim ) {
			return;
		}
		$headers = array( null === $claim['body_html'] ? 'Content-Type: text/plain; charset=UTF-8' : 'Content-Type: text/html; charset=UTF-8' );
		$body    = null === $claim['body_html'] ? (string) $claim['body_text'] : (string) $claim['body_html'];
		try {
			$accepted = wp_mail( (string) $claim['recipient'], (string) $claim['subject'], $body, $headers );
		} catch ( Throwable ) {
			$accepted = false;
		}
		// The status transition is deliberately fail-closed: if persistence
		// fails here, the message stays 'sending', never re-sent automatically.
		$this->tx->run( fn (): void => $this->messages->complete( $scope, $message, (bool) $accepted ) );
	}
}
