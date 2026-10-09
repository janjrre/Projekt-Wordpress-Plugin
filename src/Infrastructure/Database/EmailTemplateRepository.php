<?php
/**
 * Per-organization email template overrides with optimistic revisions.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use RuntimeException;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;

/** Code defaults are not rows; only administrator overrides are persisted. */
final class EmailTemplateRepository {
	/**
	 * Bind trusted database connection.
	 *
	 * @param Connection $db     Database adapter.
	 * @param string     $prefix Trusted site's table prefix.
	 */
	public function __construct( private Connection $db, private string $prefix ) {}

	/**
	 * Read one organization-owned override, never falling back cross-tenant.
	 *
	 * @param OrgScope $scope  Owning organization.
	 * @param string   $key    Built-in key validated by service.
	 * @param string   $locale Validated locale.
	 * @return array<string,mixed>|null
	 */
	public function find( OrgScope $scope, string $key, string $locale ): ?array {
		$rows = $this->db->rows(
			'SELECT id, public_id, revision, subject, body_text, body_html, content_hash, status FROM %i WHERE organization_id = %d AND template_key = %s AND locale = %s LIMIT 1',
			array( $this->prefix . 'email_templates', $scope->id, $key, $locale )
		);
		return $rows[0] ?? null;
	}

	/**
	 * Serialize updates of an existing record.
	 *
	 * @param OrgScope $scope  Owning organization.
	 * @param string   $key    Built-in template key.
	 * @param string   $locale Locale key.
	 * @return array<string,mixed>|null
	 */
	public function lock( OrgScope $scope, string $key, string $locale ): ?array {
		$rows = $this->db->rows(
			'SELECT id, public_id, revision, content_hash, status FROM %i WHERE organization_id = %d AND template_key = %s AND locale = %s LIMIT 1 FOR UPDATE',
			array( $this->prefix . 'email_templates', $scope->id, $key, $locale )
		);
		return $rows[0] ?? null;
	}

	/**
	 * Create or update a template under a live transaction.
	 *
	 * @param OrgScope                 $scope    Owning organization.
	 * @param string                   $key      Allowlisted template key.
	 * @param string                   $locale   Allowlisted locale.
	 * @param array<string,mixed>|null $old      Locked previous version.
	 * @param int                      $expected Optimistic expected revision (0 to insert).
	 * @param PublicId                 $uuid     New public ID on insert.
	 * @param string                   $subject  Validated subject.
	 * @param string                   $text     Validated text.
	 * @param string|null              $html     Validated optional HTML.
	 * @param string                   $digest   Binary content hash.
	 * @param int                      $actor_id Verified editor.
	 * @param string                   $now      Trusted UTC timestamp.
	 * @return array{id:int,public_id:string,revision:int}
	 * @throws RuntimeException When a concurrent edit or identical content is detected.
	 */
	public function save( OrgScope $scope, string $key, string $locale, ?array $old, int $expected, PublicId $uuid, string $subject, string $text, ?string $html, string $digest, int $actor_id, string $now ): array {
		if ( null === $old ) {
			if ( 0 !== $expected ) {
				throw new RuntimeException( 'Stale email template revision.' );
			}
			$this->db->execute(
				'INSERT INTO %i (public_id, organization_id, template_key, locale, revision, subject, body_html, body_text, content_hash, status, created_by_user_id, updated_by_user_id, created_at, updated_at) VALUES (%s,%d,%s,%s,%d,%s,NULLIF(%s,%s),%s,%s,%s,%d,%d,%s,%s)',
				array( $this->prefix . 'email_templates', $uuid->to_binary(), $scope->id, $key, $locale, 1, $subject, $html ?? '', '', $text, $digest, 'active', $actor_id, $actor_id, $now, $now )
			);
			$new = $this->find( $scope, $key, $locale );
			if ( ! $new || ! hash_equals( $new['public_id'], $uuid->to_binary() ) ) {
				throw new RuntimeException( 'Email override creation conflict.' );
			}
			return array(
				'id'        => (int) $new['id'],
				'public_id' => $uuid->to_string(),
				'revision'  => 1,
			);
		}
		if ( (int) $old['revision'] !== $expected || 'active' !== $old['status'] ) {
			throw new RuntimeException( 'Stale email template revision.' );
		}
		if ( hash_equals( (string) $old['content_hash'], $digest ) ) {
			throw new RuntimeException( 'Email template content unchanged.' );
		}
		$next = $expected + 1;
		if ( 1 !== $this->db->execute(
			"UPDATE %i SET revision = %d, subject = %s, body_html = NULLIF(%s,%s), body_text = %s, content_hash = %s, updated_by_user_id = %d, updated_at = %s WHERE organization_id = %d AND id = %d AND revision = %d AND status = 'active'",
			array( $this->prefix . 'email_templates', $next, $subject, $html ?? '', '', $text, $digest, $actor_id, $now, $scope->id, (int) $old['id'], $expected )
		) ) {
			throw new RuntimeException( 'Concurrent email template update.' );
		}
		return array(
			'id'        => (int) $old['id'],
			'public_id' => PublicId::from_binary( (string) $old['public_id'] )->to_string(),
			'revision'  => $next,
		);
	}
}
