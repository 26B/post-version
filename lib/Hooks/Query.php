<?php

namespace TwentySixB\WP\Plugin\PostVersion\Hooks;

use TwentySixB\WP\Plugin\PostVersion\Options;
use TwentySixB\WP\Plugin\PostVersion\VersionInterface;
use WP_Post;
use WP_Query;

/**
 * Hooks for queries of WordPress.
 *
 * @since 0.0.0
 */
class Query {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.0
	 *
	 * @return void
	 */
	public function register() : void {

		// Map the query post results to their latest versions.
		add_filter( 'the_posts', [ $this, 'map_results_to_latest_version' ], 10, 2 );
	}

	/**
	 * Map the query post results to the latest versions.
	 *
	 * @since 0.0.0
	 *
	 * @param  array    $posts
	 * @param  WP_Query $query
	 * @return array
	 */
	public function map_results_to_latest_version( array $posts, WP_Query $query ) : array {

		/**
		 * Filter whether to show unreleased posts.
		 *
		 * Skip mapping of unreleased WP_Post's to their latest versions.
		 *
		 * @since 0.0.0
		 *
		 * @param bool     $show
		 * @param array    $posts
		 * @param WP_Query $query
		 */
		if ( ! apply_filters( 'post_version_show_unreleased', false, $posts, $query ) ) {
			return $posts;
		}

		// Map each versioned post to its latest version.
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
