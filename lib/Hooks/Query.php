<?php

namespace TwentySixB\WP\Plugin\PostVersion\Hooks;

use TwentySixB\WP\Plugin\PostVersion\Options;
use TwentySixB\WP\Plugin\PostVersion\VersionInterface;
use WP_Post;
use WP_Query;

class Query {

	public function register() : void {
		add_filter( 'the_posts', [ $this, 'map_results_to_latest_version' ], 10, 2 );
	}

	public function map_results_to_latest_version( array $posts, WP_Query $query ) : array {
		if ( ! apply_filters( 'post_version_hide_unreleased', true, $posts, $query ) ) {
			return $posts;
		}

		$new_posts = [];
		foreach ( $posts as $index => $post ) {
			if ( ! Options::is_post_type_versioned( $post->post_type ) ) {
				$new_posts[ $index ] = $post;
				continue;
			}

			$current_version = VersionInterface::get_current_version( $post->ID );
			if ( ! $current_version instanceof WP_Post ) {
				continue;
			}

			$new_posts[ $index ] = $current_version;
		}

		return $new_posts;
	}
}
