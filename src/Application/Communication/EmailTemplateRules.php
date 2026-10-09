<?php
/**
 * Pure template validation and safe preview/message rendering.
 *
 * @package UOP
 */

namespace UOP\Application\Communication;

use InvalidArgumentException;

/** Validate both templates and substitutions before queuing or previewing mail. */
final class EmailTemplateRules {
	/**
	 * Inject the fixed list of usable templates and variables.
	 *
	 * @param EmailTemplateCatalog $catalog Fixed template catalog.
	 */
	public function __construct( private EmailTemplateCatalog $catalog ) {}

	/**
	 * Validate a complete template and return a deterministic content digest.
	 *
	 * @param string      $key     Built-in template identity.
	 * @param string      $locale  WordPress locale.
	 * @param string      $subject Mail subject.
	 * @param string      $text    Plain-text alternative.
	 * @param string|null $html    Optional restricted HTML.
	 * @return string SHA-256 binary digest, not an email token.
	 * @throws InvalidArgumentException For any unapproved data or markup.
	 */
	public function validate( string $key, string $locale, string $subject, string $text, ?string $html ): string {
		$allowed = $this->catalog->variables( $key );
		$this->catalog->validate_locale( $locale );
		if ( '' === trim( $subject ) || strlen( $subject ) > 255 || preg_match( '/[\r\n\x00-\x1F\x7F]/', $subject )
			|| '' === trim( $text ) || strlen( $text ) > 50000 || preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $text )
			|| ! mb_check_encoding( $subject . $text, 'UTF-8' ) ) {
			throw new InvalidArgumentException( 'Unsafe email subject or text.' );
		}
		$this->check_placeholders( $subject, $allowed );
		$this->check_placeholders( $text, $allowed );
		if ( null !== $html ) {
			if ( '' === trim( $html ) || strlen( $html ) > 50000 || ! mb_check_encoding( $html, 'UTF-8' ) ) {
				throw new InvalidArgumentException( 'Unsafe HTML template length or encoding.' );
			}
			$this->check_placeholders( $html, $allowed );
			// Action URLs are the only permitted interpolations inside HTML attributes.
			if ( preg_match_all( '/\bhref\s*=\s*["\']([^"\']*)["\']/i', $html, $matches ) ) {
				foreach ( $matches[1] as $href ) {
					if ( '{{action_url}}' !== $href || ! in_array( 'action_url', $allowed, true ) ) {
						throw new InvalidArgumentException( 'Only action_url links are permitted.' );
					}
				}
			}
			$allowed_tags = array(
				'p'      => array(),
				'br'     => array(),
				'strong' => array(),
				'em'     => array(),
				'ul'     => array(),
				'li'     => array(),
				'a'      => array( 'href' => true ),
			);
			// Use a known safe URL for policy evaluation; the actual link is validated
			// and escaped independently during rendering.
			$sample = str_replace( '{{action_url}}', 'https://example.invalid/action', $html );
			if ( wp_kses( $sample, $allowed_tags ) !== $sample ) {
				throw new InvalidArgumentException( 'HTML policy rejected template markup.' );
			}
		}
		$canonical = wp_json_encode(
			array(
				'key'       => $key,
				'locale'    => $locale,
				'subject'   => $subject,
				'body_text' => $text,
				'body_html' => $html,
			),
			JSON_THROW_ON_ERROR
		);
		return hash( 'sha256', (string) $canonical, true );
	}

	/**
	 * Render a preview or an immutable queued message from verified variables.
	 *
	 * @param string              $key       Template key.
	 * @param string              $locale    Template locale.
	 * @param string              $subject   Template subject.
	 * @param string              $text      Plain-text source.
	 * @param string|null         $html      HTML source.
	 * @param array<string,mixed> $variables Trusted and bounded render data.
	 * @return array{subject:string,body_text:string,body_html:string|null}
	 * @throws InvalidArgumentException When any required value is absent.
	 */
	public function render( string $key, string $locale, string $subject, string $text, ?string $html, array $variables ): array {
		$this->validate( $key, $locale, $subject, $text, $html );
		$allowed = $this->catalog->variables( $key );
		$safe    = array();
		foreach ( $variables as $name => $value ) {
			if ( ! in_array( $name, $allowed, true ) || ! is_string( $value ) || strlen( $value ) > 1000
				|| ! mb_check_encoding( $value, 'UTF-8' ) || preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value ) ) {
				throw new InvalidArgumentException( 'Unsafe email render variable.' );
			}
			if ( 'action_url' === $name && ( ! preg_match( '/^https:\/\/[^\s<>]+$/D', $value ) || '' === esc_url_raw( $value, array( 'https' ) ) ) ) {
				throw new InvalidArgumentException( 'Action URL must use HTTPS.' );
			}
			$safe[ $name ] = $value;
		}
		$render           = static function ( string $source, bool $escape ) use ( $safe ): string {
			return (string) preg_replace_callback(
				'/\{\{([a-z][a-z0-9_]*)\}\}/',
				static function ( array $found ) use ( $safe, $escape ): string {
					if ( ! array_key_exists( $found[1], $safe ) ) {
						throw new InvalidArgumentException( 'Missing email template render variable.' );
					}
					$value = $safe[ $found[1] ];
					return $escape ? ( 'action_url' === $found[1] ? esc_url( $value ) : esc_html( $value ) ) : $value;
				},
				$source
			);
		};
		$rendered_subject = $render( $subject, false );
		if ( preg_match( '/[\r\n]/', $rendered_subject ) || strlen( $rendered_subject ) > 255 ) {
			throw new InvalidArgumentException( 'Rendered mail header unsafe.' );
		}
		return array(
			'subject'   => $rendered_subject,
			'body_text' => $render( $text, false ),
			'body_html' => null === $html ? null : $render( $html, true ),
		);
	}

	/**
	 * Reject unknown placeholders, malformed delimiters and unlisted tokens.
	 *
	 * @param string $source Source content.
	 * @param array  $allowed Valid names for this message.
	 * @phpstan-param list<string> $allowed
	 * @throws InvalidArgumentException On unknown or malformed substitutions.
	 */
	private function check_placeholders( string $source, array $allowed ): void {
		preg_match_all( '/\{\{([a-z][a-z0-9_]*)\}\}/', $source, $matches );
		foreach ( $matches[1] as $name ) {
			if ( ! in_array( $name, $allowed, true ) ) {
				throw new InvalidArgumentException( 'Unknown email template variable.' );
			}
		}
		$clean = str_replace( $matches[0], '', $source );
		if ( str_contains( $clean, '{{' ) || str_contains( $clean, '}}' ) ) {
			throw new InvalidArgumentException( 'Malformed template placeholder.' );
		}
	}
}
