<?php

namespace TwentySixB\WP\Plugin\PostVersion\Hooks;

use TwentySixB\WP\Plugin\PostVersion\Options;
use TwentySixB\WP\Plugin\PostVersion\VersionInterface;
use WP_Post;

/**
 * Hooks related to post versions.
 *
 * @since 0.0.1
 */
class Version {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.1
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
	 * @since 0.0.1
	 *
	 * @param int     $post_id
	 * @param WP_Post $post
	 * @param bool    $update
	 * @return void
	 */
	public function add_version_to_new_post( int $post_id, WP_Post $post, bool $update ) : void {
		if ( ! Options::is_post_type_versioned( $post->post_type ) ) {
			return;
		}

		// If its a new post being inserted, add version 1.
		if ( ! $update ) {
			add_post_meta( $post_id, 'post_version_1', '1', true );
			return;
		}


		/** If its an existing post, check if they have any post_version. If not, add version 1 or
		 * the next incremental version if there are existing versions.
		 */

		$post_meta = get_post_meta( $post_id );
		foreach ( $post_meta as $meta_key => $meta_values ) {

			// If a post version is found with values, don't add the version meta.
			if ( preg_match( '/post_version_([0-9]+)/', $meta_key ) && ! empty( $meta_values ) ) {
				return;
			}
		}

		// Check for existing previous versions.
		add_filter( 'post_version_show_hidden_versions', '__return_true' );
		$versions     = VersionInterface::get_versions( $post_id );
		remove_filter( 'post_version_show_hidden_versions', '__return_true' );

		$next_version = 1;
		if ( ! empty( $versions ) ) {
			$next_version = 1 + (int) array_key_first( $versions );
		}

		add_post_meta( $post_id, "post_version_{$next_version}", "{$next_version}", true );
	}
}
