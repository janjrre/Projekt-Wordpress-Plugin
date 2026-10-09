<?php
/**
 * Authorized creation and publishing of verifiable consent documents.
 *
 * @package UOP
 */

namespace UOP\Application\Consent;

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
use UOP\Infrastructure\Database\ConsentRepository;
use UOP\Infrastructure\Database\OutboxRepository;

/** A version is an immutable text document, not a client-owned HTML blob. */
final class ConsentDefinitionService {
	/**
	 * Bind the single live policy, persistence and evidence boundary.
	 *
	 * @param ConsentRepository  $consents Tenant-scoped documents.
	 * @param PolicyService      $policy   Live authorization checks.
	 * @param TransactionManager $tx       Atomic business transaction.
	 * @param AuditWriter        $audit    Append-only audit.
	 * @param OutboxRepository   $outbox   Durable event queue.
	 */
	public function __construct(
		private ConsentRepository $consents,
		private PolicyService $policy,
		private TransactionManager $tx,
		private AuditWriter $audit,
		private OutboxRepository $outbox
	) {}

	/**
	 * Create a draft definition under the current organization.
	 *
	 * @param Actor         $actor       Authenticated administrator.
	 * @param OrgScope      $scope       Trusted organization.
	 * @param string        $key         Stable machine key.
	 * @param string        $title       Participant-facing title.
	 * @param string        $now         Trusted UTC timestamp.
	 * @param CorrelationId $correlation Request trace.
	 * @return PublicId New definition identity.
	 * @throws RuntimeException When administrator is unauthorized.
	 */
	public function create( Actor $actor, OrgScope $scope, string $key, string $title, string $now, CorrelationId $correlation ): PublicId {
		$uuid = PublicId::generate();
		$this->tx->run(
			function () use ( $actor, $scope, $key, $title, $now, $correlation, $uuid ): void {
				$this->authorize( $actor, $scope );
				$this->consents->create( $scope, $uuid, $key, $title, $now );
				$root = $this->consents->lock( $scope, $uuid );
				if ( ! $root ) {
					throw new RuntimeException( 'New consent definition was not persisted.' );
				}
				$event  = PublicId::generate();
				$object = new PolicyObject( $scope->id, 'organization', $scope->id );
				$this->audit->append( $scope, $actor, 'consent.definition_created', $object, 'success', $correlation, $event );
				$this->outbox->append( $scope, $event, 'consent', (int) $root['id'], 'consent.definition_created', $correlation, array( 'public_id' => $uuid->to_string() ) );
			}
		);
		return $uuid;
	}

	/**
	 * Publish text with a SHA-256 content fingerprint; preserve past versions.
	 *
	 * @param Actor         $actor       Current authorized editor.
	 * @param OrgScope      $scope       Trusted organization.
	 * @param PublicId      $definition  Scoped root public identity.
	 * @param string        $content     Verbatim plain-text informed consent.
	 * @param string        $now         Trusted UTC timestamp.
	 * @param CorrelationId $correlation Request trace.
	 * @return PublicId Newly published immutable version identity.
	 * @throws InvalidArgumentException When content is invalid.
	 */
	public function publish( Actor $actor, OrgScope $scope, PublicId $definition, string $content, string $now, CorrelationId $correlation ): PublicId {
		if ( strlen( $content ) < 20 || strlen( $content ) > 100000 || trim( $content ) !== $content
			|| preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F<>]/', $content ) ) {
			throw new InvalidArgumentException( 'Consent documents must be bounded UTF-8 plain text.' );
		}
		if ( ! mb_check_encoding( $content, 'UTF-8' ) ) {
			throw new InvalidArgumentException( 'Consent document encoding invalid.' );
		}
		return $this->tx->run(
			function () use ( $actor, $scope, $definition, $content, $now, $correlation ): PublicId {
				$root = $this->consents->lock( $scope, $definition );
				if ( ! $root ) {
					throw new RuntimeException( 'Consent definition unavailable.' );
				}
				$this->authorize( $actor, $scope );
				$uuid = PublicId::generate();
				$this->consents->publish( $scope, $root, $uuid, $content, $actor->user_id, $now );
				$event  = PublicId::generate();
				$object = new PolicyObject( $scope->id, 'organization', $scope->id );
				$this->audit->append( $scope, $actor, 'consent.version_published', $object, 'success', $correlation, $event );
				$this->outbox->append( $scope, $event, 'consent', (int) $root['id'], 'consent.version_published', $correlation, array( 'public_id' => $uuid->to_string() ) );
				return $uuid;
			}
		);
	}

	/**
	 * Require live organization privacy management.
	 *
	 * @param Actor    $actor Current trusted actor.
	 * @param OrgScope $scope Organization boundary.
	 * @throws RuntimeException When the actor lacks this organization permission.
	 */
	private function authorize( Actor $actor, OrgScope $scope ): void {
		if ( ! $this->policy->can( $actor, 'privacy.manage', new PolicyObject( $scope->id, 'organization', $scope->id ) )->allowed ) {
			throw new RuntimeException( 'Consent definition operation is not permitted.' );
		}
	}
}
