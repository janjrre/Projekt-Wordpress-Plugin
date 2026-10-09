<?php
/**
 * Privacy subject lookup, tenant-safe export pages and conservative redaction.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use RuntimeException;
use UOP\Core\PublicId;

/** An email is not a person key. Only explicit WordPress account links identify subjects. */
final class PrivacyAccountGateway {
	/**
	 * Bind the live site-scoped database.
	 *
	 * @param Connection $db     Active database adapter.
	 * @param string     $prefix Trusted site table prefix.
	 */
	public function __construct( private Connection $db, private string $prefix ) {}

	/**
	 * Resolve a verified WordPress privacy-request email without family data mixing.
	 *
	 * @param string $email WordPress request email.
	 * @return array{status:string,user_id:int} Resolution status and user ID only.
	 */
	public function resolve( string $email ): array {
		if ( ! is_email( $email ) ) {
			return array( 'status' => 'unmatched', 'user_id' => 0 );
		}
		$user   = get_user_by( 'email', $email );
		$userid = $user instanceof \WP_User ? (int) $user->ID : 0;
		$linked = 0;
		if ( $userid > 0 ) {
			$rows   = $this->db->rows(
				'SELECT id FROM %i WHERE wp_user_id = %d ORDER BY id ASC LIMIT 101',
				array( $this->prefix . 'persons', $userid )
			);
			$linked = count( $rows );
			if ( $linked > 100 ) {
				return array( 'status' => 'manual', 'user_id' => 0 );
			}
		}
		$other = $this->db->rows(
			'SELECT id FROM %i WHERE primary_email = %s AND (wp_user_id IS NULL OR wp_user_id <> %d) LIMIT 1',
			array( $this->prefix . 'persons', $email, $userid )
		);
		if ( $other ) {
			return array( 'status' => 'manual', 'user_id' => 0 );
		}
		$other_registration = $this->db->rows(
			'SELECT r.id FROM %i r INNER JOIN %i p ON p.id = r.person_id AND p.organization_id = r.organization_id WHERE r.contact_email = %s AND (p.wp_user_id IS NULL OR p.wp_user_id <> %d) LIMIT 1',
			array( $this->prefix . 'registrations', $this->prefix . 'persons', $email, $userid )
		);
		if ( $other_registration ) {
			return array( 'status' => 'manual', 'user_id' => 0 );
		}
		$other_mail = $this->db->rows(
			'SELECT m.id FROM %i m LEFT JOIN %i r ON r.id = m.registration_id AND r.organization_id = m.organization_id LEFT JOIN %i p ON p.id = r.person_id AND p.organization_id = r.organization_id WHERE m.recipient = %s AND (p.id IS NULL OR p.wp_user_id IS NULL OR p.wp_user_id <> %d) LIMIT 1',
			array( $this->prefix . 'email_messages', $this->prefix . 'registrations', $this->prefix . 'persons', $email, $userid )
		);
		if ( $other_mail ) {
			return array( 'status' => 'manual', 'user_id' => 0 );
		}
		return array( 'status' => $linked > 0 ? 'resolved' : 'unmatched', 'user_id' => $userid );
	}

	/**
	 * Read bounded, account-linked privacy data without selecting delegated subjects.
	 *
	 * @param int $user_id Trusted WordPress account.
	 * @param int $page    One-based WordPress privacy page.
	 * @return array{data:list<array<string,mixed>>,done:bool}
	 * @throws RuntimeException If stored historic evidence fails integrity validation.
	 */
	public function export_page( int $user_id, int $page ): array {
		if ( $user_id < 1 || $page < 1 || $page > 100000 ) {
			return array( 'data' => array(), 'done' => true );
		}
		$limit = 25;
		$after = ( $page - 1 ) * $limit;
		$data  = array();
		$done  = true;
		$rows  = $this->db->rows(
			'SELECT id, public_id, organization_id, display_name, primary_email, status, created_at FROM %i WHERE wp_user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d',
			array( $this->prefix . 'persons', $user_id, $limit, $after )
		);
		$done  = $done && count( $rows ) < $limit;
		foreach ( $rows as $row ) {
			$data[] = $this->item(
				'uop-person', 'UOP person',
				PublicId::from_binary( $row['public_id'] )->to_string(),
				array(
					array( 'name' => 'Organization', 'value' => (string) $row['organization_id'] ),
					array( 'name' => 'Display name', 'value' => (string) $row['display_name'] ),
					array( 'name' => 'Contact email', 'value' => (string) ( $row['primary_email'] ?? '' ) ),
					array( 'name' => 'Status', 'value' => (string) $row['status'] ),
					array( 'name' => 'Created', 'value' => (string) $row['created_at'] ),
				)
			);
		}

		$values = $this->db->rows(
			'SELECT v.id, v.ordinal, v.value_string, v.value_text, v.value_integer, v.value_decimal, v.value_date, v.value_datetime, v.value_boolean, f.label, p.public_id FROM %i v INNER JOIN %i p ON p.id = v.person_id INNER JOIN %i f ON f.id = v.field_id AND f.organization_id = p.organization_id WHERE p.wp_user_id = %d ORDER BY v.id ASC LIMIT %d OFFSET %d',
			array( $this->prefix . 'profile_values', $this->prefix . 'persons', $this->prefix . 'profile_fields', $user_id, $limit, $after )
		);
		$done = $done && count( $values ) < $limit;
		foreach ( $values as $row ) {
			$value = '';
			foreach ( array( 'value_string', 'value_text', 'value_integer', 'value_decimal', 'value_date', 'value_datetime', 'value_boolean' ) as $slot ) {
				if ( null !== $row[ $slot ] ) {
					$value = (string) $row[ $slot ];
					break;
				}
			}
			$data[] = $this->item(
				'uop-profile', 'UOP profile values', (string) $row['id'],
				array(
					array( 'name' => 'Person', 'value' => PublicId::from_binary( $row['public_id'] )->to_string() ),
					array( 'name' => 'Field', 'value' => (string) $row['label'] ),
					array( 'name' => 'Value', 'value' => $value ),
				)
			);
		}

		$snapshots = $this->db->rows(
			'SELECT s.id, s.payload_json, s.payload_hash, s.redacted_at, s.revision, r.public_id AS registration_public_id, r.status, r.contact_email, r.event_post_id FROM %i s INNER JOIN %i r ON r.id = s.registration_id INNER JOIN %i p ON p.id = r.person_id AND p.organization_id = r.organization_id WHERE p.wp_user_id = %d ORDER BY s.id ASC LIMIT %d OFFSET %d',
			array( $this->prefix . 'registration_snapshots', $this->prefix . 'registrations', $this->prefix . 'persons', $user_id, $limit, $after )
		);
		$done = $done && count( $snapshots ) < $limit;
		foreach ( $snapshots as $row ) {
			if ( null === $row['redacted_at'] && ! hash_equals( (string) $row['payload_hash'], hash( 'sha256', (string) $row['payload_json'], true ) ) ) {
				throw new RuntimeException( 'Stored registration history integrity violation.' );
			}
			$data[] = $this->item(
				'uop-registration', 'UOP registrations', (string) $row['id'],
				array(
					array( 'name' => 'Registration', 'value' => PublicId::from_binary( $row['registration_public_id'] )->to_string() ),
					array( 'name' => 'Revision', 'value' => (string) $row['revision'] ),
					array( 'name' => 'Status', 'value' => (string) $row['status'] ),
					array( 'name' => 'Event', 'value' => (string) $row['event_post_id'] ),
					array( 'name' => 'Contact email', 'value' => (string) ( $row['contact_email'] ?? '' ) ),
					array( 'name' => 'Answers', 'value' => null === $row['redacted_at'] ? (string) $row['payload_json'] : '[redacted]' ),
				)
			);
		}

		$consents = $this->db->rows(
			'SELECT c.id, c.public_id, c.decision, c.auth_context, c.decided_at, v.version, d.consent_key FROM %i c INNER JOIN %i p ON p.id = c.subject_person_id AND p.organization_id = c.organization_id INNER JOIN %i v ON v.id = c.definition_version_id INNER JOIN %i d ON d.id = v.definition_id AND d.organization_id = c.organization_id WHERE p.wp_user_id = %d ORDER BY c.id ASC LIMIT %d OFFSET %d',
			array( $this->prefix . 'consent_records', $this->prefix . 'persons', $this->prefix . 'consent_versions', $this->prefix . 'consent_definitions', $user_id, $limit, $after )
		);
		$done = $done && count( $consents ) < $limit;
		foreach ( $consents as $row ) {
			$data[] = $this->item(
				'uop-consent', 'UOP consent evidence',
				PublicId::from_binary( $row['public_id'] )->to_string(),
				array(
					array( 'name' => 'Definition', 'value' => (string) $row['consent_key'] ),
					array( 'name' => 'Version', 'value' => (string) $row['version'] ),
					array( 'name' => 'Decision', 'value' => (string) $row['decision'] ),
					array( 'name' => 'Acting context', 'value' => (string) $row['auth_context'] ),
					array( 'name' => 'Decided', 'value' => (string) $row['decided_at'] ),
				)
			);
		}

		$messages = $this->db->rows(
			'SELECT m.id, m.public_id, m.template_key, m.status, m.queued_at, m.recipient FROM %i m INNER JOIN %i r ON r.id = m.registration_id AND r.organization_id = m.organization_id INNER JOIN %i p ON p.id = r.person_id AND p.organization_id = r.organization_id WHERE p.wp_user_id = %d ORDER BY m.id ASC LIMIT %d OFFSET %d',
			array( $this->prefix . 'email_messages', $this->prefix . 'registrations', $this->prefix . 'persons', $user_id, $limit, $after )
		);
		$done = $done && count( $messages ) < $limit;
		foreach ( $messages as $row ) {
			$data[] = $this->item(
				'uop-mail', 'UOP communications', PublicId::from_binary( $row['public_id'] )->to_string(),
				array(
					array( 'name' => 'Template', 'value' => (string) $row['template_key'] ),
					array( 'name' => 'Recipient', 'value' => (string) $row['recipient'] ),
					array( 'name' => 'Status', 'value' => (string) $row['status'] ),
					array( 'name' => 'Queued', 'value' => (string) $row['queued_at'] ),
				)
			);
		}
		return array( 'data' => $data, 'done' => $done );
	}

	/**
	 * Redact active profile and person contact values, preserving historical evidence.
	 *
	 * @param int $user_id Explicitly resolved WordPress account.
	 * @return list<array{id:int,organization_id:int}> Subjects with altered values.
	 */
	public function erase_contact_profile( int $user_id ): array {
		$rows = $this->db->rows(
			'SELECT id, organization_id FROM %i WHERE wp_user_id = %d ORDER BY id LIMIT 101 FOR UPDATE',
			array( $this->prefix . 'persons', $user_id )
		);
		if ( ! $rows || count( $rows ) > 100 ) {
			return array();
		}
		$removed = array();
		foreach ( $rows as $person ) {
			$id  = (int) $person['id'];
			$org = (int) $person['organization_id'];
			$hold = $this->db->rows(
				'SELECT id FROM %i WHERE id = %d AND organization_id = %d AND retention_hold_until > UTC_TIMESTAMP() LIMIT 1',
				array( $this->prefix . 'persons', $id, $org )
			);
			$registration_hold = $this->db->rows(
				'SELECT id FROM %i WHERE person_id = %d AND organization_id = %d AND retention_hold_until > UTC_TIMESTAMP() LIMIT 1',
				array( $this->prefix . 'registrations', $id, $org )
			);
			if ( $hold || $registration_hold ) {
				continue;
			}
			$deleted = $this->db->execute(
				'DELETE v FROM %i v INNER JOIN %i p ON p.id = v.person_id WHERE p.organization_id = %d AND p.id = %d AND p.wp_user_id = %d',
				array( $this->prefix . 'profile_values', $this->prefix . 'persons', $org, $id, $user_id )
			);
			$updated = $this->db->execute(
				'UPDATE %i SET display_name = %s, primary_email = NULL, version = version + 1, updated_at = UTC_TIMESTAMP() WHERE organization_id = %d AND id = %d AND wp_user_id = %d AND (display_name <> %s OR primary_email IS NOT NULL)',
				array( $this->prefix . 'persons', 'Erased person', $org, $id, $user_id, 'Erased person' )
			);
			if ( $deleted > 0 || $updated > 0 ) {
				$removed[] = array( 'id' => $id, 'organization_id' => $org );
			}
		}
		return $removed;
	}

	/**
	 * Format one WordPress privacy exporter datum without HTML injection.
	 *
	 * @param string $group Group slug.
	 * @param string $label Group title.
	 * @param string $id    Stable item ID.
	 * @param array  $data  Named text values.
	 * @return array<string, mixed> WordPress privacy item.
	 */
	private function item( string $group, string $label, string $id, array $data ): array {
		return array(
			'group_id'    => $group,
			'group_label' => $label,
			'item_id'     => $id,
			'data'        => $data,
		);
	}
}
