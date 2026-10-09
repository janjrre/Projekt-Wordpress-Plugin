<?php
/**
 * WordPress Core privacy exporter and eraser integration.
 *
 * @package UOP
 */

namespace UOP\Application\Privacy;

use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyObject;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Core\TransactionManager;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\AuditWriter;
use UOP\Infrastructure\Database\OutboxRepository;
use UOP\Infrastructure\Database\PrivacyRepository;

/** The WordPress-confirmed email request cannot silently claim a shared family identity. */
final class WordPressPrivacyAdapter {
	/**
	 * Compose callbacks with the normal transaction and audit boundary.
	 *
	 * @param PrivacyRepository  $privacy Scoped resolver and datastore.
	 * @param TransactionManager $tx      Atomic erasure.
	 * @param AuditWriter        $audit   Minimal audit events.
	 * @param OutboxRepository   $outbox  Durable domain events.
	 */
	public function __construct(
		private PrivacyRepository $privacy,
		private TransactionManager $tx,
		private AuditWriter $audit,
		private OutboxRepository $outbox
	) {}

	/** Register first-party WordPress privacy integrations. */
	public function register_hooks(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	/**
	 * Register the exporter for WordPress's confirmed requests.
	 *
	 * @param array $exporters Other active exporters.
	 * @return array All exporters.
	 */
	public function register_exporter( array $exporters ): array {
		$exporters['uop-core'] = array(
			'exporter_friendly_name' => __( 'UOP Core', 'uop-core' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Register the eraser for WordPress's confirmed requests.
	 *
	 * @param array $erasers Other active erasers.
	 * @return array All erasers.
	 */
	public function register_eraser( array $erasers ): array {
		$erasers['uop-core'] = array(
			'eraser_friendly_name' => __( 'UOP Core', 'uop-core' ),
			'callback'              => array( $this, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Export only account-linked subjects; return a generic manual-review item on ambiguity.
	 *
	 * @param string $email Verified WordPress privacy request address.
	 * @param int    $page  One-based privacy page.
	 * @return array|\WP_Error Core exporter data or a safe error.
	 */
	public function export( string $email, int $page = 1 ): array|\WP_Error {
		if ( ! current_user_can( 'export_others_personal_data' ) ) {
			return new \WP_Error( 'uop_privacy_denied', __( 'UOP privacy exporter authorization failed.', 'uop-core' ) );
		}
		if ( $page < 1 ) {
			return new \WP_Error( 'uop_privacy_page', __( 'Invalid privacy export page.', 'uop-core' ) );
		}
		try {
			$resolution = $this->privacy->resolve( $email );
			if ( 'manual' === $resolution['status'] ) {
				return array(
					'data' => 1 === $page ? array( self::manual_item() ) : array(),
					'done' => true,
				);
			}
			if ( 'resolved' !== $resolution['status'] ) {
				return array( 'data' => array(), 'done' => true );
			}
			return $this->privacy->export_page( $resolution['user_id'], $page );
		} catch ( \Throwable ) {
			return new \WP_Error( 'uop_privacy_unavailable', __( 'UOP privacy data could not be verified. Please contact the site administrator.', 'uop-core' ) );
		}
	}

	/**
	 * Redact only unheld profile/contact data and declare historic records retained.
	 *
	 * @param string $email WordPress privacy request address.
	 * @param int    $page  One-based privacy page.
	 * @return array{items_removed:bool,items_retained:bool,messages:list<string>,done:bool}
	 */
	public function erase( string $email, int $page = 1 ): array {
		$base = array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		if ( ! current_user_can( 'erase_others_personal_data' ) || $page < 1 ) {
			$base['items_retained'] = true;
			$base['messages'][]     = __( 'UOP privacy erasure authorization failed.', 'uop-core' );
			return $base;
		}
		if ( $page > 1 ) {
			return $base;
		}
		try {
			return $this->tx->run(
				function () use ( $email, $base ): array {
					$resolution = $this->privacy->resolve( $email );
					if ( 'manual' === $resolution['status'] ) {
						$base['items_retained'] = true;
						$base['messages'][]     = __( 'UOP requires manual identity verification because this address may be shared. No data was erased.', 'uop-core' );
						return $base;
					}
					if ( 'resolved' !== $resolution['status'] ) {
						return $base;
					}
					$altered = $this->privacy->erase_contact_profile( $resolution['user_id'] );
					foreach ( $altered as $person ) {
						$scope  = new OrgScope( $person['organization_id'] );
						$object = new PolicyObject( $scope->id, 'person', $person['id'], $person['id'] );
						$event  = PublicId::generate();
						$trace  = CorrelationId::generate();
						$this->audit->append( $scope, new Actor( 0 ), 'person.privacy_profile_redacted', $object, 'success', $trace, $event );
						$this->outbox->append( $scope, $event, 'person', $person['id'], 'person.privacy_profile_redacted', $trace, array( 'reason_code' => 'privacy_request' ) );
					}
					$base['items_removed']  = count( $altered ) > 0;
					$base['items_retained'] = true;
					$base['messages'][]    = __( 'UOP retains registration, consent, communication and audit histories for review under configured retention rules. Legal holds may also prevent profile erasure. This request requires additional privacy review.', 'uop-core' );
					return $base;
				}
			);
		} catch ( \Throwable ) {
			$base['items_retained'] = true;
			$base['messages'][]     = __( 'UOP could not safely process this privacy request. No erasure was confirmed; manual review is required.', 'uop-core' );
			return $base;
		}
	}

	/**
	 * Return a non-identifying manual-resolution privacy item.
	 *
	 * @return array<string,mixed> WordPress personal data exporter item.
	 */
	private static function manual_item(): array {
		return array(
			'group_id'    => 'uop-identity',
			'group_label' => 'UOP identity resolution',
			'item_id'     => 'manual-resolution',
			'data'        => array(
				array(
					'name'  => 'Notice',
					'value' => __( 'Manual identity verification is required before UOP can release data associated with this email address. No family member data was automatically included.', 'uop-core' ),
				),
			),
		);
	}
}
