<?php
/**
 * WordPress event CPT lifecycle registration.
 *
 * @package UOP
 */

namespace UOP\Core;

use UOP\Extension\ModuleInterface;

/** CPT stores editorial content only; operations are organization-owned rows. */
final class EventModule implements ModuleInterface {
	/**
	 * Stable module key.
	 *
	 * @return string
	 */
	public function key(): string {
		return 'events';
	}

	/**
	 * Register editorial CPT on the proper WordPress init lifecycle.
	 *
	 * @param ServiceContainer $container Kernel composition.
	 */
	public function register( ServiceContainer $container ): void {
		add_action(
			'init',
			static function (): void {
				register_post_type(
					'uop_event',
					array(
						'labels'          => array(
							'name'          => __( 'Events', 'uop-core' ),
							'singular_name' => __( 'Event', 'uop-core' ),
						),
						'public'          => true,
						'show_in_rest'    => true,
						'has_archive'     => false,
						'supports'        => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ),
						'capability_type' => 'post',
						'map_meta_cap'    => true,
					)
				);
			}
		);
	}
}
