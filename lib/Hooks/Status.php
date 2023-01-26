<?php

namespace TwentySixB\WP\Plugin\PostVersion\Hooks;

use TwentySixB\WP\Plugin\PostVersion\Options;
use WP_Post;

class Status {

	const UNRELEASED = 'unreleased';

	public function register() : void {
		add_action( 'init', [ $this, 'register_unreleased' ] );
		add_filter( 'wp_insert_post_data', [ $this, 'prevent_unreleased_change' ], 10, 4 );
	}

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

	public function prevent_unreleased_change( array $data, array $postarr, array $unsanitized_postarr, bool $update ) : array {
		if ( ! $update ) {
			return $data;
		}

		$post = get_post( $postarr['ID'] );
		if (
			! $post instanceof WP_Post
			|| ! Options::is_post_type_versioned( $post->post_type )
			|| $post->post_status !== Status::UNRELEASED
		) {
			return $data;
		}

		// TODO: add hook to override this.

		// Post status is unreleased and something is trying to change it.
		if ( $data['post_status'] !== 'publish' && $data['post_status'] !== Status::UNRELEASED ) {
			$data['post_status'] = Status::UNRELEASED;
		}

		return $data;
	}
}
