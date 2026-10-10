<?php
/**
 * M6-08 progressive enhancements for already scoped public block projections.
 *
 * @package UOP
 */

namespace UOP\Blocks;

use UOP\Domain\Conditions\ConditionEngine;

/**
 * Only published, public presentation metadata enters the interactivity context.
 * This class never authorizes, validates a submitted form or persists answers.
 */
final class M6InteractivityAdapter {
	/**
	 * Link one browser-only store to the installed WordPress Interactivity API.
	 */
	public function enqueue(): void {
		wp_enqueue_script_module( 'uop-m6-interactivity' );
	}

	/**
	 * Render a filter with complete, safe server-side fallback links.
	 *
	 * @param list<array{title:string,url:string}> $events Public visible events only.
	 * @return string Escaped interactive HTML.
	 */
	public function events( array $events ): string {
		$this->enqueue();
		$id   = wp_unique_id( 'uop-event-filter-' );
		$html = '<div data-wp-interactive="uop/m6" ' . wp_interactivity_data_wp_context( array( 'filter' => '' ) ) . '>';
		$html .= '<label for="' . esc_attr( $id ) . '">' . esc_html__( 'Filter events', 'uop-core' ) . '</label> ';
		$html .= '<input id="' . esc_attr( $id ) . '" type="search" data-wp-on--input="actions.filterEvents" autocomplete="off" />';
		$html .= '<ul class="uop-m6-blocks__events">';
		foreach ( $events as $event ) {
			$html .= '<li ' . wp_interactivity_data_wp_context( array( 'title' => mb_strtolower( $event['title'] ) ) ) . ' data-wp-bind--hidden="state.eventHidden"><a href="' . esc_url( $event['url'] ) . '">' . esc_html( $event['title'] ) . '</a></li>';
		}
		$html .= '</ul><p data-wp-bind--hidden="state.eventsPresent" hidden role="status">' . esc_html__( 'No matching events.', 'uop-core' ) . '</p></div>';
		return $html;
	}

	/**
	 * Expand and collapse already published occurrence details.
	 *
	 * @param string $html Trusted, escaped occurrence list HTML.
	 * @return string Disclosure with keyboard-supporting native controls.
	 */
	public function disclosure( string $html ): string {
		$this->enqueue();
		$id      = wp_unique_id( 'uop-event-times-' );
		$context = array( 'open' => true );
		return '<div data-wp-interactive="uop/m6" ' . wp_interactivity_data_wp_context( $context ) . '>'
			. '<button type="button" aria-controls="' . esc_attr( $id ) . '" aria-expanded="true" data-wp-bind--aria-expanded="context.open" data-wp-on--click="actions.toggleDisclosure">'
			. esc_html__( 'Show or hide event times', 'uop-core' ) . '</button>'
			. '<div id="' . esc_attr( $id ) . '" data-wp-bind--hidden="state.disclosureClosed">' . $html . '</div></div>';
	}

	/**
	 * Show a keyboard-friendly preview of the published schema, never a form POST.
	 *
	 * Conditions based on private profile data are not shipped to the browser.
	 * No consent checkbox is offered without immutable legal-document display.
	 *
	 * @param array<string,mixed> $schema Published and checksum-verified form snapshot.
	 * @return string Purely local preview. No submission endpoint or name fields.
	 */
	public function fields( array $schema ): string {
		$this->enqueue();
		$values = array();
		foreach ( $schema['fields'] as $field ) {
			$values[ $field['key'] ] = null;
		}
		$html = '<div class="uop-m6-preview" data-wp-interactive="uop/m6" '
			. wp_interactivity_data_wp_context( array( 'values' => $values ) ) . '>';
		$html .= '<p class="uop-m6-preview__notice" role="status">' . esc_html__( 'Preview only: values are not saved or sent. Registration is not yet available.', 'uop-core' ) . '</p>';
		$html .= '<fieldset class="uop-m6-preview__fieldset"><legend>' . esc_html__( 'Published registration fields', 'uop-core' ) . '</legend>';
		$engine = new ConditionEngine();
		foreach ( $schema['fields'] as $field ) {
			if ( isset( $field['visible_when'] ) && $this->has_private_condition( $field['visible_when'] ) ) {
				continue;
			}
			$condition = $field['visible_when'] ?? null;
			$visible   = null === $condition || $engine->evaluate( $condition, array( 'registration' => $values, 'profile' => array() ) );
			$id        = wp_unique_id( 'uop-preview-field-' );
			$ctx       = array( 'condition' => $condition );
			$type      = (string) $field['type'];
			$key       = (string) $field['key'];
			$label     = (string) $field['label'];
			$required  = ! empty( $field['required'] );
			$html     .= '<div class="uop-m6-preview__field" '
				. wp_interactivity_data_wp_context( $ctx )
				. ' data-wp-bind--hidden="state.fieldHidden"' . ( $visible ? '' : ' hidden' ) . '>';
			$html .= '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
			$html .= '<span class="uop-m6-blocks__subtle"> (' . esc_html( $required ? __( 'required', 'uop-core' ) : __( 'optional', 'uop-core' ) ) . ')</span>';
			if ( 'consent' === $type ) {
				$html .= '<p>' . esc_html__( 'Consent text and acknowledgement will appear in the verified registration flow.', 'uop-core' ) . '</p>';
			} elseif ( 'textarea' === $type ) {
				$html .= '<textarea id="' . esc_attr( $id ) . '" data-uop-field="' . esc_attr( $key ) . '" data-wp-on--input="actions.changeField" rows="3" maxlength="10000"></textarea>';
			} elseif ( in_array( $type, array( 'select', 'radio', 'multiselect' ), true ) ) {
				$html .= '<select id="' . esc_attr( $id ) . '" data-uop-field="' . esc_attr( $key ) . '" data-wp-on--change="actions.changeField"' . ( 'multiselect' === $type ? ' multiple' : '' ) . '>';
				if ( 'multiselect' !== $type ) {
					$html .= '<option value="">' . esc_html__( 'Select an option', 'uop-core' ) . '</option>';
				}
				foreach ( $field['options'] as $option ) {
					$html .= '<option value="' . esc_attr( $option ) . '">' . esc_html( $option ) . '</option>';
				}
				$html .= '</select>';
			} else {
				$input_type = match ( $type ) {
					'checkbox' => 'checkbox',
					'number' => 'number',
					'email' => 'email',
					'phone' => 'tel',
					'date' => 'date',
					default => 'text',
				};
				$html .= '<input id="' . esc_attr( $id ) . '" type="' . esc_attr( $input_type ) . '" data-uop-field="' . esc_attr( $key ) . '" data-wp-on--input="actions.changeField"' . ( 'checkbox' === $type ? ' data-wp-on--change="actions.changeField"' : '' ) . ' />';
			}
			$html .= '</div>';
		}
		$html .= '</fieldset><p class="uop-m6-preview__notice">' . esc_html__( 'Changing these fields only affects this preview. Final eligibility, required fields, privacy consent and capacity are checked by the server.', 'uop-core' ) . '</p></div>';
		return $html;
	}

	/**
	 * Private or age predicates need authenticated facts, never client guesses.
	 *
	 * @param array<string,mixed> $node Validated V1 condition AST.
	 * @return bool True when condition cannot be safely evaluated in public HTML.
	 */
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
