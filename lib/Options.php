<?php

namespace TwentySixB\WP\Plugin\PostVersion;

/**
 * Handler for the `post_version_options` option.
 *
 * @since 0.0.0
 */
class Options {

	/**
	 * Default values for options.
	 *
	 * @since 0.0.0
	 *
	 * @var array
	 */
	const DEFAULT = [
		'post_types' => [],
	];

	/**
	 * Returns Post Version's options.
	 *
	 * @since 0.0.0
	 *
	 * @return array
	 */
	public static function get() : array {

		//TODO: Clean the post types in the options that cannot have revisions. Check their registry object.

		/**
		 */
		$options = \apply_filters( 'post_version_options', null );
		if ( is_array( $options ) ) {
			return array_merge( self::DEFAULT, $options );
		}

		$options = \get_option( 'post_version_options' );
		if ( $options ) {
			return $options;
		}

		return self::DEFAULT;
	}

	/**
	 * Returns whether a post type is versioned.
	 *
	 * @since 0.0.0
	 *
	 * @param string $post_type
	 * @return bool
	 */
	public static function is_post_type_versioned( string $post_type ) : bool {
		$options = self::get();
		return in_array( $post_type, $options['post_types'], true );
	}
}
