<?php
/**
 * WordPress event CPT lifecycle registration.
 *
 * @package UOP
 */

namespace UOP\Core;

use UOP\Extension\ModuleInterface;

/** CPT is editorial only; never add operational duplicate post meta. */
final class EventModule implements ModuleInterface {
	/**
	 * Return the registered module key.
	 *
	 * @return string
	 */
	public function key(): string {
		return 'events';
	}

	/**
	 * Register the event content type only after database/environment gates.
	 *
	 * @param ServiceContainer $container Kernel service container.
	 */
	public function register( ServiceContainer $container ): void {
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
}
