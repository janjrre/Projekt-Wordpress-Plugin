<?php
/**
 * M6-10 public and authenticated registration presentation.
 *
 * @package UOP
 */

namespace UOP\Blocks;

use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\ConsentRepository;

/**
 * Presentation only: posted data always goes through RegistrationController and
 * the unchanged M4 policy, form-version, consent and capacity services.
 */
final class M6SubmissionForm {
	/** @param ConsentRepository $documents Trusted immutable consent versions. */
	public function __construct( private ConsentRepository $documents ) {}

	/**
	 * Render a JS-enhanced form, with no useful network action until initialized.
	 *
	 * @param OrgScope            $scope Organization from WordPress settings.
	 * @param string              $event Public event identity from scoped lookup.
	 * @param array<string,mixed> $schema Current published, checksum-verified snapshot.
	 * @param bool                $guest Whether this is a protected guest intake.
	 * @param list<array<string,mixed>> $occurrences Published scheduled occurrences.
	 * @return string Escaped registration controls, or a fail-closed message.
	 */
	public function render( OrgScope $scope, string $event, array $schema, bool $guest, array $occurrences ): string {
		foreach ( $schema['fields'] as $field ) {
			if ( isset( $field['visible_when'] ) && $this->has_private_condition( $field['visible_when'] ) ) {
				return '<p role="status">' . esc_html__( 'This form requires a private eligibility check. Please contact the organizer to complete your registration.', 'uop-core' ) . '</p>';
			}
		}
		$documents = array();
		foreach ( $schema['fields'] as $field ) {
			if ( 'consent' !== $field['type'] ) {
				continue;
			}
			try {
				$id = PublicId::from_string( (string) ( $field['consent_version_public_id'] ?? '' ) );
				$doc = $this->documents->version( $scope, $id );
			} catch ( \InvalidArgumentException | \RuntimeException ) {
				$doc = null;
			}
			if ( ! $doc ) {
				return '<p role="status">' . esc_html__( 'The required consent documents are unavailable. Registration has been paused.', 'uop-core' ) . '</p>';
			}
			$documents[ $field['key'] ] = (string) $doc['content'];
		}

		$url     = plugin_dir_url( dirname( __DIR__, 2 ) . '/uop-core.php' );
		$version = defined( 'UOP_CORE_VERSION' ) ? (string) constant( 'UOP_CORE_VERSION' ) : '0.1.0-alpha.2';
		wp_enqueue_script( 'uop-m6-registration', $url . 'assets/m6-registration.js', array(), $version, true );
		if ( ! $guest && ! wp_script_is( 'uop-m6-registration-config', 'enqueued' ) ) {
			// Never embed a REST nonce into a shared guest page.
			wp_add_inline_script(
				'uop-m6-registration',
				'window.uopM6Registration = ' . wp_json_encode( array( 'nonce' => wp_create_nonce( 'wp_rest' ) ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';',
				'before'
			);
		}

		$html = '<form class="uop-m6-registration" method="post" data-uop-registration-form data-uop-event="' . esc_attr( $event ) . '" data-uop-guest="' . ( $guest ? '1' : '0' ) . '" data-uop-rest="' . esc_url( rest_url( 'uop/v1/' ) ) . '">';
		$html .= '<p>' . esc_html__( 'Complete the published form. Your information is submitted securely and checked again by the server.', 'uop-core' ) . '</p>';
		if ( ! $guest ) {
			$html .= '<div class="uop-m6-registration__subject"><label for="' . esc_attr( wp_unique_id( 'uop-subject-' ) ) . '">' . esc_html__( 'Person to register', 'uop-core' ) . '</label>';
			$html .= '<select data-uop-subject required disabled><option value="">' . esc_html__( 'Loading authorized persons…', 'uop-core' ) . '</option></select></div>';
		}
		$times = array_values( array_filter( $occurrences, static fn ( array $item ): bool => 'scheduled' === $item['status'] ) );
		if ( $times ) {
			$id = wp_unique_id( 'uop-occurrence-' );
			$html .= '<div><label for="' . esc_attr( $id ) . '">' . esc_html__( 'Event date', 'uop-core' ) . '</label><select id="' . esc_attr( $id ) . '" data-uop-occurrence required>';
			$html .= '<option value="">' . esc_html__( 'Choose a date', 'uop-core' ) . '</option>';
			foreach ( $times as $time ) {
				$html .= '<option value="' . esc_attr( PublicId::from_binary( (string) $time['public_id'] )->to_string() ) . '">' . esc_html( (string) $time['start_at'] . ' UTC' ) . '</option>';
			}
			$html .= '</select></div>';
		}
		$html .= '<fieldset><legend>' . esc_html__( 'Registration details', 'uop-core' ) . '</legend>';
		foreach ( $schema['fields'] as $field ) {
			$key       = (string) $field['key'];
			$type      = (string) $field['type'];
			$required  = ! empty( $field['required'] );
			$id        = wp_unique_id( 'uop-registration-' );
			$condition = isset( $field['visible_when'] ) ? ' data-uop-condition="' . esc_attr( (string) wp_json_encode( $field['visible_when'] ) ) . '"' : '';
			$html     .= '<div class="uop-m6-registration__field" data-uop-field-group="' . esc_attr( $key ) . '"' . $condition . '>';
			if ( 'radio' === $type ) {
				$html .= '<fieldset><legend>' . esc_html( $field['label'] ) . '</legend>';
			} else {
				$html .= '<label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label>';
			}
			$html .= ' <span class="uop-m6-blocks__subtle">' . esc_html( $required ? __( '(required)', 'uop-core' ) : __( '(optional)', 'uop-core' ) ) . '</span>';
			$attrs = ' data-uop-field="' . esc_attr( $key ) . '" data-uop-required="' . ( $required ? '1' : '0' ) . '"' . ( $required ? ' required' : '' );
			if ( 'consent' === $type ) {
				$html .= '<pre class="uop-m6-registration__document">' . esc_html( $documents[ $key ] ) . '</pre>';
				$html .= '<input type="checkbox" id="' . esc_attr( $id ) . '"' . $attrs . ' /> ' . esc_html__( 'I agree to this consent document.', 'uop-core' );
			} elseif ( 'textarea' === $type ) {
				$html .= '<textarea id="' . esc_attr( $id ) . '"' . $attrs . ' rows="3" maxlength="10000"></textarea>';
			} elseif ( 'radio' === $type ) {
				foreach ( $field['options'] as $i => $option ) {
					$choice = $id . '-' . $i;
					$html .= '<label for="' . esc_attr( $choice ) . '"><input id="' . esc_attr( $choice ) . '" type="radio" name="' . esc_attr( 'uop-choice-' . $id ) . '" value="' . esc_attr( $option ) . '"' . $attrs . ' />' . esc_html( $option ) . '</label>';
				}
				$html .= '</fieldset>';
			} elseif ( in_array( $type, array( 'select', 'multiselect' ), true ) ) {
				$html .= '<select id="' . esc_attr( $id ) . '"' . $attrs . ( 'multiselect' === $type ? ' multiple' : '' ) . '>';
				if ( 'select' === $type ) {
					$html .= '<option value="">' . esc_html__( 'Choose an option', 'uop-core' ) . '</option>';
				}
				foreach ( $field['options'] as $option ) {
					$html .= '<option value="' . esc_attr( $option ) . '">' . esc_html( $option ) . '</option>';
				}
				$html .= '</select>';
			} else {
				$input_type = match ( $type ) {
					'checkbox' => 'checkbox',
					'number'   => 'number',
					'email'    => 'email',
					'phone'    => 'tel',
					'date'     => 'date',
					default    => 'text',
				};
				$html .= '<input id="' . esc_attr( $id ) . '" type="' . esc_attr( $input_type ) . '"' . $attrs . ( 'number' === $type ? ' step="any"' : '' ) . ' />';
			}
			$html .= '</div>';
		}
		$html .= '</fieldset><button type="submit" data-uop-submit disabled>' . esc_html__( 'Submit registration', 'uop-core' ) . '</button>';
		$html .= '<p role="status" aria-live="polite" data-uop-result></p>';
		$html .= '<noscript><p role="status">' . esc_html__( 'JavaScript is required to submit this registration securely.', 'uop-core' ) . '</p></noscript></form>';
		return $html;
	}

	/** @param array<string,mixed> $node Published condition AST. */
	private function has_private_condition( array $node ): bool {
		if ( 'profile' === ( $node['source'] ?? null ) || in_array( $node['operator'] ?? '', array( 'age_lt_at', 'age_gte_at' ), true ) ) {
			return true;
		}
		foreach ( array( 'all', 'any' ) as $group ) {
			foreach ( $node[ $group ] ?? array() as $child ) {
				if ( $this->has_private_condition( $child ) ) {
					return true;
				}
			}
		}
		return isset( $node['not'] ) && $this->has_private_condition( $node['not'] );
	}
}
