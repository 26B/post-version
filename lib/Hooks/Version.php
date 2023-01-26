<?php

namespace TwentySixB\WP\Plugin\PostVersion\Hooks;

use TwentySixB\WP\Plugin\PostVersion\Options;
use WP_Post;

class Version {

	public function register() : void {
		add_action( 'wp_insert_post', [ $this, 'add_version_to_new_post' ], 10, 3 );
	}

	public function add_version_to_new_post( int $post_id, WP_Post $post, bool $update ) : void {
		if ( $update || ! Options::is_post_type_versioned( $post->post_type ) ) {
			return;
		}

		add_post_meta( $post_id, 'post_version_1', '1', true );
	}
}
