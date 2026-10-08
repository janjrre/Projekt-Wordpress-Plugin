<?php
/**
 * Bind a published event form without storing competing WordPress post meta.
 *
 * @package UOP
 */

namespace UOP\Application\Registration;

use RuntimeException;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyObject;
use UOP\Application\Policy\PolicyService;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Core\TransactionManager;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\AuditWriter;
use UOP\Infrastructure\Database\Connection;
use UOP\Infrastructure\Database\OutboxRepository;

/** Explicit configuration command protects the event and form organizations. */
final class RegistrationConfigurationService {
	/**
	 * Compose scoped persistence and authoritative permissions.
	 *
	 * @param Connection         $db     Scoped database adapter.
	 * @param string             $prefix Trusted site table prefix.
	 * @param PolicyService      $policy Current object permission service.
	 * @param TransactionManager $tx     Atomic transaction.
	 * @param AuditWriter        $audit  Audit writer.
	 * @param OutboxRepository   $outbox Transactional events.
	 */
	public function __construct( private Connection $db, private string $prefix, private PolicyService $policy, private TransactionManager $tx, private AuditWriter $audit, private OutboxRepository $outbox ) {}

	/**
	 * Select the immutable published-form lineage for a managed event.
	 *
	 * @param Actor         $actor       WordPress actor.
	 * @param OrgScope      $scope       Trusted organization.
	 * @param PublicId      $event_id    Active event public ID.
	 * @param PublicId      $form_id     Published form public ID.
	 * @param string        $utc_now     UTC timestamp.
	 * @param CorrelationId $correlation Command trace.
	 * @throws RuntimeException For denied or cross-org associations.
	 */
	public function bind( Actor $actor, OrgScope $scope, PublicId $event_id, PublicId $form_id, string $utc_now, CorrelationId $correlation ): void {
		$this->tx->run(
			function () use ( $actor, $scope, $event_id, $form_id, $utc_now, $correlation ): void {
				$events = $this->db->rows(
					"SELECT event_post_id FROM %i WHERE organization_id = %d AND public_id = %s AND status = 'active' LIMIT 1 FOR UPDATE",
					array( $this->prefix . 'event_settings', $scope->id, $event_id->to_binary() )
				);
				$forms  = $this->db->rows(
					"SELECT id FROM %i WHERE organization_id = %d AND public_id = %s AND context = 'event' AND status = 'published' AND current_version_id IS NOT NULL LIMIT 1",
					array( $this->prefix . 'forms', $scope->id, $form_id->to_binary() )
				);
				if ( ! $events || ! $forms ) {
					throw new RuntimeException( 'Event and form must be active in one organization.' );
				}
				$post_id  = (int) $events[0]['event_post_id'];
				$resource = new PolicyObject( $scope->id, 'event', $post_id, null, $post_id );
				$form     = new PolicyObject( $scope->id, 'form', (int) $forms[0]['id'] );
				if ( ! user_can( $actor->user_id, 'edit_post', $post_id )
					|| ! $this->policy->can( $actor, 'event.manage', $resource )->allowed
					|| ! $this->policy->can( $actor, 'form.manage', $form )->allowed ) {
					throw new RuntimeException( 'Cannot bind a form to the requested event.' );
				}
				$this->db->execute(
					'UPDATE %i SET default_form_id = %d, version = version + 1, updated_at = %s WHERE organization_id = %d AND event_post_id = %d',
					array( $this->prefix . 'event_settings', (int) $forms[0]['id'], $utc_now, $scope->id, $post_id )
				);
				$event = PublicId::generate();
				$this->audit->append( $scope, $actor, 'event.form_bound', $resource, 'success', $correlation, $event );
				$this->outbox->append( $scope, $event, 'event', $post_id, 'event.form_bound', $correlation, array( 'public_id' => $event_id->to_string() ) );
			}
		);
	}
}
