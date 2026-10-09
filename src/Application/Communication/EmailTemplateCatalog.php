<?php
/**
 * Built-in, non-editable defaults and placeholder contracts for mail templates.
 *
 * @package UOP
 */

namespace UOP\Application\Communication;

use InvalidArgumentException;

/** A fixed registry prevents arbitrary keys and uncontrolled mail variables. */
final class EmailTemplateCatalog {
	/**
	 * Allowed placeholders by message type.
	 *
	 * @var array<string, list<string>>
	 */
	private const VARIABLES = array(
		'registration_received' => array( 'participant_name', 'event_title' ),
		'email_verification'    => array( 'participant_name', 'event_title', 'action_url', 'expires_at' ),
		'waitlist_offer'        => array( 'participant_name', 'event_title', 'action_url', 'expires_at' ),
		'registration_cancelled' => array( 'participant_name', 'event_title' ),
		'event_cancelled'       => array( 'participant_name', 'event_title' ),
		'consent_withdrawn'     => array( 'participant_name', 'event_title' ),
	);

	/**
	 * Allowlisted variables for exactly one registered key.
	 *
	 * @param string $key Built-in message key.
	 * @return list<string>
	 */
	public function variables( string $key ): array {
		if ( ! isset( self::VARIABLES[ $key ] ) ) {
			throw new InvalidArgumentException( 'Unknown email template key.' );
		}
		return self::VARIABLES[ $key ];
	}

	/**
	 * Confirm supported locale strings before using a database key.
	 *
	 * @param string $locale WordPress-style locale.
	 */
	public function validate_locale( string $locale ): void {
		if ( ! preg_match( '/^[a-z]{2}_[A-Z]{2}$/D', $locale ) || ! in_array( $locale, array( 'de_DE', 'en_US' ), true ) ) {
			throw new InvalidArgumentException( 'Unsupported template locale.' );
		}
	}

	/**
	 * Stable default text. Action links are generated externally; none are logged.
	 *
	 * @param string $key    Registered template key.
	 * @param string $locale Supported locale.
	 * @return array{subject:string,body_text:string,body_html:string}
	 */
	public function defaults( string $key, string $locale ): array {
		$this->variables( $key );
		$this->validate_locale( $locale );
		$de = array(
			'registration_received' => array( 'Anmeldung eingegangen: {{event_title}}', 'Hallo {{participant_name}},' . "\n\n" . 'deine Anmeldung für {{event_title}} ist eingegangen.', '<p>Hallo {{participant_name}},</p><p>Deine Anmeldung für <strong>{{event_title}}</strong> ist eingegangen.</p>' ),
			'email_verification' => array( 'E-Mail bestätigen: {{event_title}}', 'Hallo {{participant_name}},' . "\n\n" . 'bestätige deine E-Mail-Adresse für {{event_title}} bis {{expires_at}}:' . "\n" . '{{action_url}}', '<p>Hallo {{participant_name}},</p><p>Bitte bestätige deine E-Mail-Adresse für {{event_title}} bis {{expires_at}}.</p><p><a href="{{action_url}}">E-Mail bestätigen</a></p>' ),
			'waitlist_offer' => array( 'Platzangebot: {{event_title}}', 'Hallo {{participant_name}},' . "\n\n" . 'für {{event_title}} ist ein Platz verfügbar. Bitte bestätige bis {{expires_at}}:' . "\n" . '{{action_url}}', '<p>Hallo {{participant_name}},</p><p>Für {{event_title}} ist ein Platz verfügbar. Bestätige bis {{expires_at}}.</p><p><a href="{{action_url}}">Platz annehmen</a></p>' ),
			'registration_cancelled' => array( 'Anmeldung storniert: {{event_title}}', 'Hallo {{participant_name}},' . "\n\n" . 'deine Anmeldung für {{event_title}} wurde storniert.', '<p>Hallo {{participant_name}},</p><p>Deine Anmeldung für {{event_title}} wurde storniert.</p>' ),
			'event_cancelled' => array( 'Veranstaltung abgesagt: {{event_title}}', 'Hallo {{participant_name}},' . "\n\n" . 'die Veranstaltung {{event_title}} wurde abgesagt.', '<p>Hallo {{participant_name}},</p><p>Die Veranstaltung {{event_title}} wurde abgesagt.</p>' ),
			'consent_withdrawn' => array( 'Einwilligung widerrufen: {{event_title}}', 'Hallo {{participant_name}},' . "\n\n" . 'dein Widerruf für {{event_title}} wurde gespeichert.', '<p>Hallo {{participant_name}},</p><p>Dein Widerruf für {{event_title}} wurde gespeichert.</p>' ),
		);
		$en = array(
			'registration_received' => array( 'Registration received: {{event_title}}', 'Hello {{participant_name}},' . "\n\n" . 'We received your registration for {{event_title}}.', '<p>Hello {{participant_name}},</p><p>Your registration for <strong>{{event_title}}</strong> was received.</p>' ),
			'email_verification' => array( 'Verify email: {{event_title}}', 'Hello {{participant_name}},' . "\n\n" . 'Verify your email for {{event_title}} before {{expires_at}}:' . "\n" . '{{action_url}}', '<p>Hello {{participant_name}},</p><p>Verify your email for {{event_title}} before {{expires_at}}.</p><p><a href="{{action_url}}">Verify email</a></p>' ),
			'waitlist_offer' => array( 'Seat offer: {{event_title}}', 'Hello {{participant_name}},' . "\n\n" . 'A seat is available for {{event_title}}. Confirm before {{expires_at}}:' . "\n" . '{{action_url}}', '<p>Hello {{participant_name}},</p><p>A seat is available for {{event_title}} until {{expires_at}}.</p><p><a href="{{action_url}}">Accept seat</a></p>' ),
			'registration_cancelled' => array( 'Registration cancelled: {{event_title}}', 'Hello {{participant_name}},' . "\n\n" . 'Your registration for {{event_title}} was cancelled.', '<p>Hello {{participant_name}},</p><p>Your registration for {{event_title}} was cancelled.</p>' ),
			'event_cancelled' => array( 'Event cancelled: {{event_title}}', 'Hello {{participant_name}},' . "\n\n" . 'The event {{event_title}} was cancelled.', '<p>Hello {{participant_name}},</p><p>The event {{event_title}} was cancelled.</p>' ),
			'consent_withdrawn' => array( 'Consent withdrawn: {{event_title}}', 'Hello {{participant_name}},' . "\n\n" . 'Your withdrawal for {{event_title}} was recorded.', '<p>Hello {{participant_name}},</p><p>Your withdrawal for {{event_title}} was recorded.</p>' ),
		);
		$parts = ( 'de_DE' === $locale ? $de : $en )[ $key ];
		return array(
			'subject'   => $parts[0],
			'body_text' => $parts[1],
			'body_html' => $parts[2],
		);
	}
}
