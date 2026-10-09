<?php
/**
 * Tenant-scoped definitions with immutable consent document versions.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use InvalidArgumentException;
use RuntimeException;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;

/** A definition root is mutable, while every published document is insert-only. */
final class ConsentRepository {
	/**
	 * Bind the scoped database.
	 *
	 * @param Connection $db     Transaction-owned connection.
	 * @param string     $prefix Trusted WordPress site prefix.
	 */
	public function __construct( private Connection $db, private string $prefix ) {}

	/**
	 * Create an unpublished definition.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param PublicId $uuid  Definition public identity.
	 * @param string   $key   Stable machine key.
	 * @param string   $title Public-facing title.
	 * @param string   $now   UTC timestamp.
	 * @throws InvalidArgumentException For invalid author data.
	 */
	public function create( OrgScope $scope, PublicId $uuid, string $key, string $title, string $now ): void {
		if ( ! preg_match( '/^[a-z][a-z0-9_]{0,99}$/D', $key )
			|| '' === trim( $title ) || mb_strlen( $title ) > 191 ) {
			throw new InvalidArgumentException( 'Invalid consent definition attributes.' );
		}
		$this->db->execute(
			'INSERT INTO %i (public_id, organization_id, consent_key, title, status, created_at, updated_at) VALUES (%s,%d,%s,%s,%s,%s,%s)',
			array( $this->prefix . 'consent_definitions', $uuid->to_binary(), $scope->id, $key, $title, 'draft', $now, $now )
		);
	}

	/**
	 * Lock a single organization-owned definition to serialize publishing.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param PublicId $uuid  Consent definition identity.
	 * @return array<string, mixed>|null
	 */
	public function lock( OrgScope $scope, PublicId $uuid ): ?array {
		$rows = $this->db->rows(
			'SELECT id, consent_key, status, current_version_id FROM %i WHERE organization_id = %d AND public_id = %s LIMIT 1 FOR UPDATE',
			array( $this->prefix . 'consent_definitions', $scope->id, $uuid->to_binary() )
		);
		return $rows[0] ?? null;
	}

	/**
	 * Append an immutable text version and move only the current pointer.
	 *
	 * @param OrgScope             $scope    Trusted organization.
	 * @param array<string, mixed> $root     Locked definition.
	 * @param PublicId             $uuid     New immutable document public ID.
	 * @param string               $content Plain-text informed-consent document.
	 * @param int                  $actor_id WordPress editor.
	 * @param string               $now      UTC timestamp.
	 * @return int New version number.
	 * @throws RuntimeException For archived definitions or repeated content.
	 */
	public function publish( OrgScope $scope, array $root, PublicId $uuid, string $content, int $actor_id, string $now ): int {
		if ( ! in_array( $root['status'], array( 'draft', 'active' ), true ) ) {
			throw new RuntimeException( 'Consent definition cannot be published.' );
		}
		$last = $this->db->rows(
			'SELECT version, document_hash FROM %i WHERE definition_id = %d ORDER BY version DESC LIMIT 1',
			array( $this->prefix . 'consent_versions', (int) $root['id'] )
		);
		if ( $last && hash_equals( (string) $last[0]['document_hash'], hash( 'sha256', $content, true ) ) ) {
			throw new RuntimeException( 'Consent document content has not changed.' );
		}
		$version = $last ? (int) $last[0]['version'] + 1 : 1;
		$this->db->execute(
			'INSERT INTO %i (public_id, definition_id, version, content, document_hash, published_by_user_id, published_at) VALUES (%s,%d,%d,%s,%s,%d,%s)',
			array( $this->prefix . 'consent_versions', $uuid->to_binary(), (int) $root['id'], $version, $content, hash( 'sha256', $content, true ), $actor_id, $now )
		);
		$new = $this->db->rows(
			'SELECT id FROM %i WHERE public_id = %s AND definition_id = %d LIMIT 1',
			array( $this->prefix . 'consent_versions', $uuid->to_binary(), (int) $root['id'] )
		);
		if ( ! $new ) {
			throw new RuntimeException( 'Published consent document could not be reloaded.' );
		}
		$updated = $this->db->execute(
			"UPDATE %i SET status = 'active', current_version_id = %d, updated_at = %s WHERE organization_id = %d AND id = %d AND status IN ('active','draft')",
			array( $this->prefix . 'consent_definitions', (int) $new[0]['id'], $now, $scope->id, (int) $root['id'] )
		);
		if ( 1 !== $updated ) {
			throw new RuntimeException( 'Consent definition status changed during publishing.' );
		}
		return $version;
	}

	/**
	 * Read a historical immutable consent document through its definition owner.
	 *
	 * @param OrgScope $scope   Trusted organization.
	 * @param PublicId $version Immutable version UUID.
	 * @return array<string, mixed>|null Document with stable definition key.
	 * @throws RuntimeException When stored document differs from its hash.
	 */
	public function version( OrgScope $scope, PublicId $version ): ?array {
		$rows = $this->db->rows(
			'SELECT v.id, v.public_id, v.definition_id, v.version, v.content, v.document_hash, d.consent_key FROM %i v INNER JOIN %i d ON d.id = v.definition_id WHERE d.organization_id = %d AND v.public_id = %s LIMIT 1',
			array( $this->prefix . 'consent_versions', $this->prefix . 'consent_definitions', $scope->id, $version->to_binary() )
		);
		if ( ! $rows ) {
			return null;
		}
		if ( ! hash_equals( (string) $rows[0]['document_hash'], hash( 'sha256', (string) $rows[0]['content'], true ) ) ) {
			throw new RuntimeException( 'Stored consent version integrity violation.' );
		}
		return $rows[0];
	}
}
