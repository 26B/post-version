<?php

namespace TwentySixB\WP\Plugin\PostVersion\Hooks;

use TwentySixB\WP\Plugin\PostVersion\Options;
use WP_Post;

/**
 * Hooks related to PostVersion's custom post statuses.
 *
 * @since 0.0.1
 */
class Status {

	/**
	 * Unreleased post status.
	 *
	 * Represents a post in a version that is not yet published, but has previously published
	 * versions.
	 */
	const UNRELEASED = 'unreleased';

	/**
	 * Register hooks.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function register() : void {

		// Register the unreleased post status.
		add_action( 'init', [ $this, 'register_unreleased' ] );

		// Prevent unreleased status from being changed.
		add_filter( 'wp_insert_post_data', [ $this, 'prevent_unreleased_change' ], 10, 4 );
	}

	/**
	 * Register the unreleased post status.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function register_unreleased() : void {
		register_post_status(
			self::UNRELEASED,
			[
				'label'                     => __( 'Unreleased', 'post_version' ),
				'label_count'               => _n_noop( 'Unreleased <span class="count">(%s)</span>', 'Unreleased <span class="count">(%s)</span>', 'post_version' ),
				'exclude_from_search'       => false,
				'public'                    => true,
				'internal'                  => false, // Needs to be false for permalinks to work when unreleased.
				'protected'                 => false,
				'private'                   => false,
				'publicly_queryable'        => true,
				'show_in_admin_status_list' => true,
				'show_in_admin_all_list'    => true,
			]
		);
	}

	/**
	 * Prevent unreleased status from being changed.
	 *
	 * TODO: This might need to be changed to allow the post to be put into draft, hiding the post
	 * and all its versions.
	 *
	 * @since 0.0.1
	 *
	 * @param array $data
	 * @param array $postarr
	 * @param array $unsanitized_postarr
	 * @param bool  $update
	 * @return void
	 */
	public function prevent_unreleased_change( array $data, array $postarr, array $unsanitized_postarr, bool $update ) : array {

		// Ignore new posts.
		if ( ! $update ) {
			return $data;
		}

		// Check if post exists, its post_type is versioned and it's post status is unreleased.
		$post = get_post( $postarr['ID'] );
		if (
			! $post instanceof WP_Post
			|| ! Options::is_post_type_versioned( $post->post_type )
			|| $post->post_status !== Status::UNRELEASED
		) {
			return $data;
		}

		/**
		 * Filters whether to prevent a post's status from being changed from unreleased, when not
		 * being published.
		 *
		 * TODO: Better name.
		 *
		 * @since 0.0.1
		 *
		 * @param bool    $prevent_change
		 * @param WP_Post $post
		 * @param array   $data
		 * @param array   $postarr
		 * @param array   $unsanitized_postarr
		 * @param bool    $update
		 */
		$prevent_change = apply_filters( 'post_version_prevent_unreleased_change', true, $post, $data, $postarr, $unsanitized_postarr, $update );
		if ( ! $prevent_change ) {
			return $data;
		}

		// Keep post unreleased if not being published.
		if ( $data['post_status'] !== 'publish' && $data['post_status'] !== Status::UNRELEASED ) {
			$data['post_status'] = Status::UNRELEASED;
		}

		return $data;
	}
}
