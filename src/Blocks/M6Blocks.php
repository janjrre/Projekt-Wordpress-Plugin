<?php
/**
 * Scoped, server-rendered Gutenberg blocks for the UOP V1 front end.
 *
 * @package UOP
 */

namespace UOP\Blocks;

use InvalidArgumentException;
use UOP\Application\Policy\Actor;
use UOP\Application\Query\M6ReadService;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\EventRepository;
use UOP\Infrastructure\Database\FormRepository;
use UOP\Infrastructure\Database\OccurrenceRepository;

/**
 * Render only public event information or policy-projected private summaries.
 * Form submission UX is kept disabled until M6-07 and protected guest mail are ready.
 */
final class M6Blocks {
	/**
	 * Reuse existing scoped storage and authorization projections.
	 *
	 * @param EventRepository      $events       Configured events.
	 * @param OccurrenceRepository $occurrences  Tenant-scoped schedules.
	 * @param FormRepository       $forms        Published-version form snapshot.
	 * @param M6ReadService        $reads        Existing self/delegation policy projection.
	 */
	public function __construct(
		private EventRepository $events,
		private OccurrenceRepository $occurrences,
		private FormRepository $forms,
		private M6ReadService $reads
	) {}

	/** Register blocks with metadata, shared assets and block supports. */
	public function register(): void {
		$version = defined( 'UOP_CORE_VERSION' ) ? (string) constant( 'UOP_CORE_VERSION' ) : '0.1.0-alpha.2';
		$url     = plugin_dir_url( dirname( __DIR__, 2 ) . '/uop-core.php' );
		wp_register_script(
			'uop-m6-block-editor',
			$url . 'assets/m6-blocks-editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
			$version,
			true
		);
		wp_register_style( 'uop-m6-blocks', $url . 'assets/m6-blocks.css', array(), $version );
		foreach ( array( 'event-list', 'event-details', 'registration-form', 'portal', 'my-registrations' ) as $slug ) {
			register_block_type(
				dirname( __DIR__, 2 ) . '/blocks/' . $slug,
				array(
					'render_callback' => fn ( array $attributes ): string => $this->render( $slug, $attributes ),
				)
			);
		}
	}

	/**
	 * Ensure participant fragments cannot be cached as shared guest HTML.
	 *
	 * @return void
	 */
	public function private_cache_guard(): void {
		$post = get_post();
		if ( $post && ( has_block( 'uop/portal', $post ) || has_block( 'uop/my-registrations', $post ) ) ) {
			$this->no_cache();
		}
	}

	/**
	 * Select public versus private projections by an allowlisted block slug.
	 *
	 * @param string              $slug       Registered UOP block name.
	 * @param array<string,mixed> $attributes Validated WordPress block attributes.
	 * @return string Safe HTML with Gutenberg-supported wrapper attributes.
	 */
	public function render( string $slug, array $attributes = array() ): string {
		if ( ! in_array( $slug, array( 'event-list', 'event-details', 'registration-form', 'portal', 'my-registrations' ), true ) ) {
			return '';
		}
		$scope = $this->scope();
		if ( ! $scope ) {
			return $this->wrap( $slug, '<p role="status">' . esc_html__( 'The organization is unavailable.', 'uop-core' ) . '</p>' );
		}
		if ( 'portal' === $slug || 'my-registrations' === $slug ) {
			$this->no_cache();
			if ( is_user_logged_in() ) {
				$this->portal_assets();
			}
		}
		$event_id = isset( $attributes['eventId'] ) && is_string( $attributes['eventId'] ) ? $attributes['eventId'] : '';
		$html     = match ( $slug ) {
			'event-list'        => $this->event_list( $scope, $attributes ),
			'event-details'     => $this->event_details( $scope, $event_id ),
			'registration-form' => $this->registration_form( $scope, $event_id ),
			'portal'            => $this->portal(),
			'my-registrations'  => $this->my_registrations( $scope ),
		};
		return $this->wrap( $slug, $html );
	}

	/**
	 * Server-owned organization, never an untrusted shortcode attribute.
	 *
	 * @return OrgScope|null
	 */
	private function scope(): ?OrgScope {
		$id = (int) get_option( 'uop_default_organization_id', 0 );
		return $id > 0 ? new OrgScope( $id ) : null;
	}

	/**
	 * Accept only explicitly public active events with published CPT posts.
	 *
	 * @param OrgScope $scope Current organization.
	 * @param PublicId $id    Public event UUID.
	 * @return array<string,mixed>|null
	 */
	private function public_event( OrgScope $scope, PublicId $id ): ?array {
		$row = $this->events->by_public( $scope, $id );
		if ( ! $row || 'active' !== $row['status'] || 'public' !== $row['visibility'] ) {
			return null;
		}
		$post = get_post( (int) $row['event_post_id'] );
		if ( ! $post || 'uop_event' !== $post->post_type || 'publish' !== $post->post_status || ! empty( $post->post_password ) ) {
			return null;
		}
		return $row;
	}

	/**
	 * Parse and verify an event block selector without exposing hidden events.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param string   $uuid  Editor-supplied event identity.
	 * @return array<string,mixed>|null
	 */
	private function resolve_event( OrgScope $scope, string $uuid ): ?array {
		try {
			return $this->public_event( $scope, PublicId::from_string( $uuid ) );
		} catch ( InvalidArgumentException ) {
			return null;
		}
	}

	/**
	 * Bounded event listing excluding private, draft and passworded posts.
	 *
	 * @param OrgScope            $scope      Current organization.
	 * @param array<string,mixed> $attributes Block settings.
	 * @return string
	 */
	private function event_list( OrgScope $scope, array $attributes ): string {
		$limit = isset( $attributes['limit'] ) && is_int( $attributes['limit'] ) ? max( 1, min( 12, $attributes['limit'] ) ) : 6;
		$items = array();
		foreach ( $this->events->for_organization( $scope ) as $row ) {
			$id = PublicId::from_binary( (string) $row['public_id'] );
			if ( ! $this->public_event( $scope, $id ) ) {
				continue;
			}
			$post_id = (int) $row['event_post_id'];
			$title   = get_the_title( $post_id );
			$url     = get_permalink( $post_id );
			$items[] = '<li><a href="' . esc_url( (string) $url ) . '">' . esc_html( $title ) . '</a></li>';
			if ( count( $items ) >= $limit ) {
				break;
			}
		}
		if ( ! $items ) {
			return '<p role="status">' . esc_html__( 'No public events are available.', 'uop-core' ) . '</p>';
		}
		return '<ul class="uop-m6-blocks__events">' . implode( '', $items ) . '</ul>';
	}

	/**
	 * Public event title and live, manually scheduled occurrence windows.
	 *
	 * @param OrgScope $scope Tenant.
	 * @param string   $uuid  Public event UUID.
	 * @return string
	 */
	private function event_details( OrgScope $scope, string $uuid ): string {
		$event = $this->resolve_event( $scope, $uuid );
		if ( ! $event ) {
			return '<p role="status">' . esc_html__( 'This event is not publicly available.', 'uop-core' ) . '</p>';
		}
		$post_id = (int) $event['event_post_id'];
		$html    = '<h2>' . esc_html( get_the_title( $post_id ) ) . '</h2>';
		$items   = array();
		foreach ( $this->occurrences->for_event( $scope, $post_id ) as $row ) {
			if ( 'scheduled' !== $row['status'] ) {
				continue;
			}
			$start = strtotime( (string) $row['start_at'] . ' UTC' );
			$end   = strtotime( (string) $row['end_at'] . ' UTC' );
			$zone  = (string) $row['timezone'];
			if ( false === $start || false === $end ) {
				continue;
			}
			$items[] = '<li><time datetime="' . esc_attr( gmdate( 'c', $start ) ) . '">' . esc_html( wp_date( 'd.m.Y H:i', $start, new \DateTimeZone( $zone ) ) ) . '</time>–<time datetime="' . esc_attr( gmdate( 'c', $end ) ) . '">' . esc_html( wp_date( 'd.m.Y H:i', $end, new \DateTimeZone( $zone ) ) ) . '</time> ' . esc_html( $zone ) . '</li>';
		}
		$html .= $items ? '<ul class="uop-m6-blocks__dates">' . implode( '', $items ) . '</ul>' : '<p>' . esc_html__( 'No upcoming event times have been published.', 'uop-core' ) . '</p>';
		return $html;
	}

	/**
	 * Show immutable published form field requirements without activating guest writes.
	 *
	 * @param OrgScope $scope Tenant boundary.
	 * @param string   $uuid  Public event UUID.
	 * @return string
	 */
	private function registration_form( OrgScope $scope, string $uuid ): string {
		$event = $this->resolve_event( $scope, $uuid );
		if ( ! $event ) {
			return '<p role="status">' . esc_html__( 'Registration is not available for this event.', 'uop-core' ) . '</p>';
		}
		$schema = $this->forms->published_for_event( $scope, (int) $event['event_post_id'] );
		if ( ! $schema ) {
			return '<p role="status">' . esc_html__( 'No published registration form is available.', 'uop-core' ) . '</p>';
		}
		$items = array();
		foreach ( $schema['fields'] as $field ) {
			$label   = (string) $field['label'];
			$note    = ! empty( $field['required'] ) ? __( 'required', 'uop-core' ) : __( 'optional', 'uop-core' );
			$items[] = '<li>' . esc_html( $label ) . ' <span class="uop-m6-blocks__subtle">(' . esc_html( $note ) . ')</span></li>';
		}
		return '<h2>' . esc_html__( 'Registration form', 'uop-core' ) . '</h2><p role="status">' . esc_html__( 'The registration fields are listed below. Submission through this block will be enabled with the secure participant portal.', 'uop-core' ) . '</p><ul>' . implode( '', $items ) . '</ul>';
	}

	/**
	 * Auth-aware but non-identifying portal gateway.
	 *
	 * @return string
	 */
	private function portal(): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Sign in to access your participant portal.', 'uop-core' ) . ' <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html__( 'Sign in', 'uop-core' ) . '</a></p>';
		}
		return '<h2>' . esc_html__( 'Participant portal', 'uop-core' ) . '</h2><div data-uop-portal-root="portal"><p role="status">' . esc_html__( 'Loading your authorized persons and registrations…', 'uop-core' ) . '</p></div><noscript><p>' . esc_html__( 'JavaScript is required to change profiles and manage registrations. You can still use the My Registrations block to read your own entries.', 'uop-core' ) . '</p></noscript>';
	}

	/**
	 * Only fetch self/delegated records through the existing object policy.
	 *
	 * @param OrgScope $scope Tenant boundary.
	 * @return string
	 */
	private function my_registrations( OrgScope $scope ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Sign in to view your registrations.', 'uop-core' ) . ' <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html__( 'Sign in', 'uop-core' ) . '</a></p>';
		}
		$actor = new Actor( get_current_user_id() );
		$rows  = array();
		foreach ( $this->reads->my_people( $actor, $scope ) as $person ) {
			try {
				$page = $this->reads->registrations( $actor, $scope, PublicId::from_string( (string) $person['public_id'] ), null );
			} catch ( InvalidArgumentException ) {
				continue;
			}
			foreach ( $page['items'] ?? array() as $registration ) {
				$rows[] = '<li>' . esc_html( (string) $registration['created_at'] ) . ': ' . esc_html( (string) $registration['status'] ) . ' <span class="uop-m6-blocks__subtle">(' . esc_html( (string) $person['display_name'] ) . ')</span></li>';
			}
		}
		$fallback = $rows ? '<ul>' . implode( '', $rows ) . '</ul>' : '<p role="status">' . esc_html__( 'No registrations available to your account.', 'uop-core' ) . '</p>';
		return '<h2>' . esc_html__( 'My registrations', 'uop-core' ) . '</h2><div data-uop-portal-root="registrations"><div data-uop-portal-fallback>' . $fallback . '</div></div>';
	}

	/**
	 * Load participant-only controller and its REST nonce after public block checks.
	 */
	private function portal_assets(): void {
		$url     = plugin_dir_url( dirname( __DIR__, 2 ) . '/uop-core.php' );
		$version = defined( 'UOP_CORE_VERSION' ) ? (string) constant( 'UOP_CORE_VERSION' ) : '0.1.0-alpha.2';
		wp_enqueue_style( 'uop-m6-portal', $url . 'assets/m6-portal.css', array( 'uop-m6-blocks' ), $version );
		if ( wp_script_is( 'uop-m6-portal', 'enqueued' ) ) {
			return;
		}
		wp_enqueue_script(
			'uop-m6-portal',
			$url . 'assets/m6-portal.js',
			array( 'wp-api-fetch', 'wp-i18n' ),
			$version,
			true
		);
		wp_add_inline_script(
			'uop-m6-portal',
			'window.uopM6Portal = ' . wp_json_encode(
				array( 'nonce' => wp_create_nonce( 'wp_rest' ) ),
				JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
			) . ';',
			'before'
		);
	}

	/** Disable shared caching for personalized server-rendered blocks. */
	private function no_cache(): void {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! headers_sent() ) {
			nocache_headers();
		}
	}

	/**
	 * Use Block Supports for spacing, alignment, colors and anchors.
	 *
	 * @param string $slug Registered block.
	 * @param string $html Escaped/structured inner markup.
	 * @return string
	 */
	private function wrap( string $slug, string $html ): string {
		$attributes = get_block_wrapper_attributes( array( 'class' => 'uop-m6-blocks uop-m6-blocks--' . $slug ) );
		return '<section ' . $attributes . '>' . $html . '</section>';
	}
}
