<?php
/**
 * Policy-guarded mail-template fallback, preview and override commands.
 *
 * @package UOP
 */

namespace UOP\Application\Communication;

use RuntimeException;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyObject;
use UOP\Application\Policy\PolicyService;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Core\TransactionManager;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\AuditWriter;
use UOP\Infrastructure\Database\EmailTemplateRepository;
use UOP\Infrastructure\Database\OutboxRepository;

/**
 * Templates are not emails: no delivery occurs in this service.
 * Rendered immutable messages and their worker belong to M5-04.
 */
final class EmailTemplateService {
	/**
	 * Depend on the single policy, validator and durable audit pipeline.
	 *
	 * @param EmailTemplateCatalog    $catalog Fixed defaults and variable allowlist.
	 * @param EmailTemplateRules      $rules   Strict HTML, variable and header validation.
	 * @param EmailTemplateRepository $repo    Tenant override persistence.
	 * @param PolicyService           $policy  Organization-level permission.
	 * @param TransactionManager      $tx      Atomic writes.
	 * @param AuditWriter             $audit   Privacy-safe evidence.
	 * @param OutboxRepository        $outbox  Durable side-effect handoff.
	 */
	public function __construct(
		private EmailTemplateCatalog $catalog,
		private EmailTemplateRules $rules,
		private EmailTemplateRepository $repo,
		private PolicyService $policy,
		private TransactionManager $tx,
		private AuditWriter $audit,
		private OutboxRepository $outbox
	) {}

	/**
	 * Load a configured override or its read-only code fallback.
	 *
	 * @param Actor    $actor  Administrator or communication manager.
	 * @param OrgScope $scope  Trusted owning organization.
	 * @param string   $key    Registered template name.
	 * @param string   $locale Supported locale.
	 * @return array{public_id:string|null,revision:int,source:string,subject:string,body_text:string,body_html:string|null,hash:string}
	 */
	public function get( Actor $actor, OrgScope $scope, string $key, string $locale ): array {
		$this->authorize( $actor, $scope );
		return $this->resolved( $scope, $key, $locale );
	}

	/**
	 * Strictly render a saved template or an unsaved editor preview.
	 *
	 * @param Actor                    $actor     Authorized communication editor.
	 * @param OrgScope                 $scope     Trusted owning organization.
	 * @param string                   $key       Registered key.
	 * @param string                   $locale    Supported locale.
	 * @param array<string,string>     $variables Sample values; never persisted.
	 * @param array<string,mixed>|null $candidate Optional unsaved body fields.
	 * @return array{subject:string,body_text:string,body_html:string|null}
	 * @throws RuntimeException When caller is unauthorized.
	 * @throws \InvalidArgumentException For malformed preview candidate fields.
	 */
	public function preview( Actor $actor, OrgScope $scope, string $key, string $locale, array $variables, ?array $candidate = null ): array {
		$this->authorize( $actor, $scope );
		if ( null === $candidate ) {
			$template = $this->resolved( $scope, $key, $locale );
		} else {
			if ( array_diff( array_keys( $candidate ), array( 'subject', 'body_text', 'body_html' ) )
				|| ! is_string( $candidate['subject'] ?? null ) || ! is_string( $candidate['body_text'] ?? null )
				|| ( isset( $candidate['body_html'] ) && ! is_string( $candidate['body_html'] ) ) ) {
				throw new \InvalidArgumentException( 'Unsupported template preview fields.' );
			}
			$template = $candidate;
		}
		return $this->rules->render( $key, $locale, $template['subject'], $template['body_text'], $template['body_html'] ?? null, $variables );
	}

	/**
	 * Save a scoped override with an expected revision and one audit/outbox pair.
	 *
	 * @param Actor         $actor       Authorized communication editor.
	 * @param OrgScope      $scope       Owning organization.
	 * @param string        $key         Registered key.
	 * @param string        $locale      Supported locale.
	 * @param int           $expected    0 for first override, previous revision later.
	 * @param string        $subject     Submitted subject.
	 * @param string        $text        Submitted plain text.
	 * @param string|null   $html        Restricted HTML or null.
	 * @param string        $now         Trusted UTC timestamp.
	 * @param CorrelationId $correlation Stable command correlation.
	 * @return array{public_id:string,revision:int}
	 * @throws RuntimeException When caller is unauthorized or revision has changed.
	 * @throws \InvalidArgumentException For an invalid revision.
	 */
	public function save( Actor $actor, OrgScope $scope, string $key, string $locale, int $expected, string $subject, string $text, ?string $html, string $now, CorrelationId $correlation ): array {
		$digest = $this->rules->validate( $key, $locale, $subject, $text, $html );
		if ( $expected < 0 ) {
			throw new \InvalidArgumentException( 'Invalid expected mail revision.' );
		}
		return $this->tx->run(
			function () use ( $actor, $scope, $key, $locale, $expected, $subject, $text, $html, $now, $correlation, $digest ): array {
				$this->authorize( $actor, $scope );
				$old      = $this->repo->lock( $scope, $key, $locale );
				$override = $this->repo->save( $scope, $key, $locale, $old, $expected, PublicId::generate(), $subject, $text, $html, $digest, $actor->user_id, $now );
				$event    = PublicId::generate();
				$object   = new PolicyObject( $scope->id, 'organization', $scope->id );
				$this->audit->append( $scope, $actor, 'email_template.saved', $object, 'success', $correlation, $event );
				$this->outbox->append( $scope, $event, 'email_template', $override['id'], 'mail.template_saved', $correlation, array( 'public_id' => $override['public_id'] ) );
				return array(
					'public_id' => $override['public_id'],
					'revision'  => $override['revision'],
				);
			}
		);
	}

	/**
	 * Resolve a template only after policy and input validation.
	 *
	 * @param OrgScope $scope  Tenant boundary.
	 * @param string   $key    Registry key.
	 * @param string   $locale Supported locale.
	 * @return array{public_id:string|null,revision:int,source:string,subject:string,body_text:string,body_html:string|null,hash:string}
	 * @throws RuntimeException On corrupted stored content.
	 */
	private function resolved( OrgScope $scope, string $key, string $locale ): array {
		$this->catalog->variables( $key );
		$this->catalog->validate_locale( $locale );
		$override = $this->repo->find( $scope, $key, $locale );
		if ( $override && 'active' === $override['status'] ) {
			$digest = $this->rules->validate( $key, $locale, (string) $override['subject'], (string) $override['body_text'], $override['body_html'] );
			if ( ! hash_equals( (string) $override['content_hash'], $digest ) ) {
				throw new RuntimeException( 'Stored email template integrity violation.' );
			}
			return array(
				'public_id' => PublicId::from_binary( (string) $override['public_id'] )->to_string(),
				'revision'  => (int) $override['revision'],
				'source'    => 'override',
				'subject'   => (string) $override['subject'],
				'body_text' => (string) $override['body_text'],
				'body_html' => $override['body_html'],
				'hash'      => bin2hex( $digest ),
			);
		}
		$default = $this->catalog->defaults( $key, $locale );
		$digest  = $this->rules->validate( $key, $locale, $default['subject'], $default['body_text'], $default['body_html'] );
		return array(
			'public_id' => null,
			'revision'  => 0,
			'source'    => 'builtin',
			'subject'   => $default['subject'],
			'body_text' => $default['body_text'],
			'body_html' => $default['body_html'],
			'hash'      => bin2hex( $digest ),
		);
	}

	/**
	 * Require live organization-specific send/communication management.
	 *
	 * @param Actor    $actor Current authenticated editor.
	 * @param OrgScope $scope Organization.
	 * @throws RuntimeException When caller is unauthorized.
	 */
	private function authorize( Actor $actor, OrgScope $scope ): void {
		if ( ! $this->policy->can( $actor, 'communication.send', new PolicyObject( $scope->id, 'organization', $scope->id ) )->allowed ) {
			throw new RuntimeException( 'Email template access denied.' );
		}
	}
}
