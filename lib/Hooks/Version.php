<?php

namespace TwentySixB\WP\Plugin\PostVersion\Hooks;

use TwentySixB\WP\Plugin\PostVersion\Options;
use WP_Post;

/**
 * Hooks related to post versions.
 *
 * @since 0.0.0
 */
class Version {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.0
	 *
	 * @return void
	 */
	public function register() : void {

		// Add initial post_version when a new post is saved.
		add_action( 'wp_insert_post', [ $this, 'add_version_to_new_post' ], 10, 3 );
	}

	/**
	 * Add initial post_version when a new post is saved.
	 *
	 * @since 0.0.0
	 *
	 * @param int     $post_id
	 * @param WP_Post $post
	 * @param bool    $update
	 * @return void
	 */
	public function add_version_to_new_post( int $post_id, WP_Post $post, bool $update ) : void {
		if ( $update || ! Options::is_post_type_versioned( $post->post_type ) ) {
			return;
		}

		add_post_meta( $post_id, 'post_version_1', '1', true );
	}
}
