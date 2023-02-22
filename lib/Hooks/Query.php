<?php

namespace TwentySixB\WP\Plugin\PostVersion\Hooks;

use TwentySixB\WP\Plugin\PostVersion\Options;
use TwentySixB\WP\Plugin\PostVersion\VersionInterface;
use WP_Post;
use WP_Query;

/**
 * Hooks for queries of WordPress.
 *
 * @since 0.0.1
 */
class Query {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function register() : void {

		// Add `version` as a public query var.
		add_filter( 'query_vars', [ $this, 'add_version_to_query_vars' ] );

		// Map the single query post result to their requested version via query args.
		add_filter( 'the_posts', [ $this, 'map_results_to_requested_version' ], 9, 2 );

		// Map the query post results to their latest versions.
		add_filter( 'the_posts', [ $this, 'map_results_to_latest_version' ], 10, 2 );
	}

	/**
	 * Add version as a public query var.
	 *
	 * @since 0.0.3
	 *
	 * @param array $public_query_vars
	 * @return array
	 */
	public function add_version_to_query_vars( array $public_query_vars ) : array {
		$public_query_vars[] = 'version';
		return $public_query_vars;
	}

	/**
	 * Map the single query post result to its requested version, if it exists.
	 *
	 * If the version does not exist, then the it will force a 404.
	 *
	 * @since 0.0.3
	 *
	 * @param  array    $posts
	 * @param  WP_Query $query
	 * @return array
	 */
	public function map_results_to_requested_version( array $posts, WP_Query $query ) : array {

		// Get version from the query var.
		$requested_version = (int) get_query_var( 'version', '' );
		if ( empty( $requested_version ) ) {
			return $posts;
		}

		// Ignore queries in the backoffice.
		if ( is_admin() ) {
			return $posts;
		}

		if ( ! $query->is_main_query() ) {
			return $posts;
		}

		if ( ! $query->is_single() && ! $query->is_singular() && ! $query->is_page() ) {
			return $posts;
		}

		if ( count( $posts ) !== 1 ) {
			return $posts;
		}

		$post = $posts[0];
		if ( ! Options::is_post_type_versioned( $post->post_type ) ) {
			return $posts;
		}

		$requested_post_version = VersionInterface::get_version( $post->ID, $requested_version );

		if ( $requested_post_version === null ) {
			global $wp_query;
			\status_header( 404 );
			\nocache_headers();
			$wp_query->set_404();
			return [];
		}


		return [ $requested_post_version ];
	}

	/**
	 * Map the query post results to the latest versions.
	 *
	 * @since 0.0.1
	 *
	 * @param  array    $posts
	 * @param  WP_Query $query
	 * @return array
	 */
	public function map_results_to_latest_version( array $posts, WP_Query $query ) : array {

		// Ignore queries in the backoffice.
		if ( is_admin() ) {
			return $posts;
		}

		/**
		 * Filter whether to show unreleased posts.
		 *
		 * Skip mapping of unreleased WP_Post's to their latest versions.
		 *
		 * @since 0.0.1
		 *
		 * @param bool     $show
		 * @param array    $posts
		 * @param WP_Query $query
		 */
		if ( apply_filters( 'post_version_show_unreleased', false, $posts, $query ) ) {
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
