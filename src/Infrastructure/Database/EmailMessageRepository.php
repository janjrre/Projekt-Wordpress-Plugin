<?php
/**
 * Durable, organization-scoped immutable email envelopes and delivery claims.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use RuntimeException;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;

/** No transport calls occur inside a database transaction. */
final class EmailMessageRepository {
	/**
	 * Bind one physical database and trusted site prefix.
	 *
	 * @param Connection $db     Database.
	 * @param string     $prefix Trusted table prefix.
	 */
	public function __construct( private Connection $db, private string $prefix ) {}

	/**
	 * Resolve only an organization-owned registration.
	 *
	 * @param OrgScope $scope Tenant.
	 * @param PublicId $id    Registration public identity.
	 * @return int|null Internal key.
	 */
	public function registration_id( OrgScope $scope, PublicId $id ): ?int {
		$rows = $this->db->rows(
			'SELECT id FROM %i WHERE organization_id = %d AND public_id = %s LIMIT 1',
			array( $this->prefix . 'registrations', $scope->id, $id->to_binary() )
		);
		return $rows ? (int) $rows[0]['id'] : null;
	}

	/**
	 * Look up one idempotent send request, never a mutable draft.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param string   $key   Binary SHA-256 identity.
	 * @return array<string,mixed>|null Stored envelope.
	 */
	public function by_command( OrgScope $scope, string $key ): ?array {
		$rows = $this->db->rows(
			'SELECT id, public_id, recipient, template_key, locale, registration_id, status FROM %i WHERE organization_id = %d AND idempotency_key = %s LIMIT 1',
			array( $this->prefix . 'email_messages', $scope->id, $key )
		);
		return $rows[0] ?? null;
	}

	/**
	 * Insert the final rendered snapshot, never the variable map or plaintext action secrets in outbox.
	 *
	 * @param OrgScope                        $scope    Tenant.
	 * @param PublicId                        $uuid     Public email identity.
	 * @param string                          $key      SHA-256 idempotency key.
	 * @param int|null                        $registration Owned internal registration, if known.
	 * @param string                          $recipient Validated recipient.
	 * @param string                          $template  Allowlisted template name.
	 * @param string                          $locale    Supported locale.
	 * @param int                             $revision Frozen template revision.
	 * @param string                          $digest   Binary content digest.
	 * @param array<string,string|null>       $rendered Subject and full final bodies.
	 * @param string                          $now      UTC timestamp.
	 * @throws RuntimeException If a database insert cannot be reloaded.
	 */
	public function enqueue( OrgScope $scope, PublicId $uuid, string $key, ?int $registration, string $recipient, string $template, string $locale, int $revision, string $digest, array $rendered, string $now ): void {
		$this->db->execute(
			'INSERT INTO %i (public_id, organization_id, idempotency_key, registration_id, recipient, template_key, subject, body_html, body_text, status, attempts, queued_at, created_at, updated_at, template_revision, template_hash, locale) VALUES (%s,%d,%s,NULLIF(%d,0),%s,%s,%s,NULLIF(%s,%s),%s,%s,%d,%s,%s,%s,%d,%s,%s)',
			array(
				$this->prefix . 'email_messages',
				$uuid->to_binary(),
				$scope->id,
				$key,
				$registration ?? 0,
				$recipient,
				$template,
				$rendered['subject'],
				$rendered['body_html'] ?? '',
				'',
				$rendered['body_text'],
				'queued',
				0,
				$now,
				$now,
				$now,
				$revision,
				$digest,
				$locale,
			)
		);
	}

	/**
	 * Claim one queued delivery under a row lock.
	 *
	 * Once 'sending' is committed, a crash is ambiguous and MUST NOT be
	 * retried automatically: the transport may have accepted the message.
	 *
	 * @param OrgScope $scope Tenant.
	 * @param PublicId $uuid  Message UUID.
	 * @return array<string,mixed>|null Full frozen delivery snapshot when claimed.
	 */
	public function claim( OrgScope $scope, PublicId $uuid ): ?array {
		$rows = $this->db->rows(
			'SELECT id, recipient, subject, body_html, body_text, status, attempts FROM %i WHERE organization_id = %d AND public_id = %s LIMIT 1 FOR UPDATE',
			array( $this->prefix . 'email_messages', $scope->id, $uuid->to_binary() )
		);
		if ( ! $rows || 'queued' !== $rows[0]['status'] || (int) $rows[0]['attempts'] >= 5 ) {
			return null;
		}
		if ( 1 !== $this->db->execute(
			"UPDATE %i SET status = 'sending', attempts = attempts + 1, updated_at = UTC_TIMESTAMP() WHERE organization_id = %d AND id = %d AND status = 'queued'",
			array( $this->prefix . 'email_messages', $scope->id, (int) $rows[0]['id'] )
		) ) {
			throw new RuntimeException( 'Mail claim state changed.' );
		}
		return $rows[0];
	}

	/**
	 * Complete a previously claimed attempt; success means wp_mail accepted, not delivery.
	 *
	 * @param OrgScope $scope   Tenant.
	 * @param PublicId $uuid    Message UUID.
	 * @param bool     $success WP mail transport result.
	 * @throws RuntimeException If the claim was lost.
	 */
	public function complete( OrgScope $scope, PublicId $uuid, bool $success ): void {
		$status = $success ? 'accepted' : 'failed';
		$sql    = $success
			? "UPDATE %i SET status = %s, accepted_at = UTC_TIMESTAMP(), last_error_code = NULL, last_error_message = NULL, updated_at = UTC_TIMESTAMP() WHERE organization_id = %d AND public_id = %s AND status = 'sending'"
			: "UPDATE %i SET status = %s, failed_at = UTC_TIMESTAMP(), last_error_code = 'wp_mail_failed', last_error_message = NULL, updated_at = UTC_TIMESTAMP() WHERE organization_id = %d AND public_id = %s AND status = 'sending'";
		if ( 1 !== $this->db->execute( $sql, array( $this->prefix . 'email_messages', $status, $scope->id, $uuid->to_binary() ) ) ) {
			throw new RuntimeException( 'Mail delivery outcome could not be persisted.' );
		}
	}

	/**
	 * Explicit operator retry on a known failure, preserving ALL rendered fields.
	 *
	 * @param OrgScope $scope Tenant.
	 * @param PublicId $uuid  Message UUID.
	 * @return bool True if requeued, false if not retryable.
	 */
	public function retry_failed( OrgScope $scope, PublicId $uuid ): bool {
		$rows = $this->db->rows(
			'SELECT id, status, attempts FROM %i WHERE organization_id = %d AND public_id = %s LIMIT 1 FOR UPDATE',
			array( $this->prefix . 'email_messages', $scope->id, $uuid->to_binary() )
		);
		if ( ! $rows || 'failed' !== $rows[0]['status'] || (int) $rows[0]['attempts'] >= 5 ) {
			return false;
		}
		return 1 === $this->db->execute(
			"UPDATE %i SET status = 'queued', updated_at = UTC_TIMESTAMP() WHERE organization_id = %d AND id = %d AND status = 'failed'",
			array( $this->prefix . 'email_messages', $scope->id, (int) $rows[0]['id'] )
		);
	}

	/**
	 * Recover only known 'queued' envelopes after a failed enqueue/scheduler outage.
	 *
	 * @param OrgScope $scope Tenant.
	 * @return list<string> Public UUID binaries.
	 */
	public function pending( OrgScope $scope ): array {
		$rows = $this->db->rows(
			"SELECT public_id FROM %i WHERE organization_id = %d AND status = 'queued' AND attempts < 5 ORDER BY queued_at ASC, id ASC LIMIT 50",
			array( $this->prefix . 'email_messages', $scope->id )
		);
		return array_map( static fn ( array $row ): string => (string) $row['public_id'], $rows );
	}
}
