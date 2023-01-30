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

		/**
		 * Filters the PostVersion's options array.
		 *
		 * If an array is returned, it will override the option values in the WordPress options
		 * table.
		 *
		 * @param null $options
		 */
		$options = \apply_filters( 'post_version_options', null );
		if ( is_array( $options ) ) {
			return self::validate_options( array_merge( self::DEFAULT, $options ) );
		}

		$options = \get_option( 'post_version_options' );
		if ( $options ) {
			return self::validate_options( $options );
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

	/**
	 * Validate option values.
	 *
	 * @since 0.0.0
	 *
	 * @param array $options
	 * @return array
	 */
	private static function validate_options( array $options ) : array {

		// Validate and fix post types in options.
		$post_types = [];
		foreach ( $options['post_types'] as $post_type ) {

			// Prevent non strings and revisions from being versioned.
			if (
				! is_string( $post_type )
				|| $post_type === 'revision'
			) {
				continue;
			}

			// Ignore base WordPress post types that support revisions by default.
			if ( $post_type !== 'post' && $post_type !== 'page' ) {

				// Prevent post types without support for revisions from being versioned.
				if ( ! post_type_supports( $post_type, 'revisions' ) ) {
					continue;
				}
			}

			$post_types[] = $post_type;
		}
		$options['post_types'] = $post_types;

		return $options;
	}
}
