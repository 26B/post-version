<?php

namespace TwentySixB\WP\Plugin\PostVersion\CLI;

use WP_CLI;
use WP_CLI_Command;
use WP_Post;
use TwentySixB\WP\Plugin\PostVersion\Options;
use TwentySixB\WP\Plugin\PostVersion\Version as PostVersion;
use TwentySixB\WP\Plugin\PostVersion\VersionInterface;

/**
 * CLI commands related to post version control.
 *
 * @since 0.0.3
 */
class Version extends WP_CLI_Command {

	/**
	 * Logs information about the post's versions
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : ID of the post.
	 *
	 * [--v]
	 * : (Optional) Log more information on each version.
	 *
	 * @since 0.0.3
	 * @param array $args
	 * @param array $assoc_args
	 * @return void
	 */
	public function versions( array $args, array $assoc_args ) : void {
		$post = $this->validate_post_id( $args[0] );

		$verbose = $assoc_args['v'] ?? false;

		add_filter( 'post_version_show_hidden_versions', '__return_true' );
		$post_versions = VersionInterface::get_versions( $post->ID );
		remove_filter( 'post_version_show_hidden_versions', '__return_true' );

		if ( $post->post_status === 'unreleased' ) {
			array_unshift( $post_versions, $post );
		}

		WP_CLI::log( __( 'Versions:', 'post-version' ) );
		$indent = str_repeat( ' ', 4 );
		foreach ( $post_versions as $post_version ) {
			$version = PostVersion::get( $post_version->ID );
			WP_CLI::log( sprintf( '- %s (%s) : %s', $version->label(), $version->version(), $version->status() ) );
			if ( ! $verbose ) {
				continue;
			}

			WP_CLI::log( $indent . sprintf( '%s: %s', __( 'Post ID', 'post-version' ), $post_version->ID ) );
			WP_CLI::log( $indent . sprintf( '%s: %s', __( 'URL', 'post-version' ), VersionInterface::get_version_permalink( $post->ID, $version->version() ) ) );
			WP_CLI::log( $indent . sprintf( '%s: %s', __( 'Last modified on', 'post-version' ), $post_version->post_modified ) );
		}
	}

	/**
	 * Tries to hide a post's version.
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : ID of the post.
	 *
	 * <version>
	 * : Number of the version.
	 *
	 * @since 0.0.3
	 * @param array $args
	 * @return void
	 */
	public function hide( array $args ) : void {
		$post           = $this->validate_post_id( $args[0] );
		$version_number = $args[1];
		if ( ! is_numeric( $version_number ) ) {
			WP_CLI::error( __( 'Version value must be numeric.', 'post-version' ) );
		}

		add_filter( 'post_version_show_hidden_versions', '__return_true' );
		$version = VersionInterface::get_version( $post->ID, $version_number );
		remove_filter( 'post_version_show_hidden_versions', '__return_true' );
		if ( $version === null ) {
			WP_CLI::error( __( 'Version does not exist.', 'post-version' ) );
		}

		if ( $version->post_status === 'draft' ) {
			WP_CLI::error( __( 'Version is already hidden.', 'post-version' ) );
		}

		$status = VersionInterface::hide_version( $post->ID, $version_number );

		if ( ! $status ) {
			/* translators: %s: Version number */
			WP_CLI::error( sprintf( __( 'Version %s failed to be hidden.', 'post-version' ), $version_number ) );
		}

		/* translators: %s: Version number */
		WP_CLI::success( sprintf( __( 'Version %s hidden.', 'post-version' ), $version_number ) );
	}

	/**
	 * Tries to unhide a post's version.
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : ID of the post.
	 *
	 * <version>
	 * : Number of the version.
	 *
	 * @since 0.0.3
	 * @param array $args
	 * @return void
	 */
	public function unhide( array $args ) : void {
		$post           = $this->validate_post_id( $args[0] );
		$version_number = $args[1];
		if ( ! is_numeric( $version_number ) ) {
			WP_CLI::error( __( 'Version value must be numeric.', 'post-version' ) );
		}

		add_filter( 'post_version_show_hidden_versions', '__return_true' );
		$version = VersionInterface::get_version( $post->ID, $version_number );
		remove_filter( 'post_version_show_hidden_versions', '__return_true' );
		if ( $version === null ) {
			WP_CLI::error( __( 'Version does not exist.', 'post-version' ) );
		}

		if ( $version->post_status === 'publish' ) {
			WP_CLI::error( __( 'Version is already live.', 'post-version' ) );
		}

		$status = VersionInterface::unhide_version( $post->ID, $version_number );

		if ( ! $status ) {
			/* translators: %s: Version number */
			WP_CLI::error( sprintf( __( 'Version %s failed to be unhidden.', 'post-version' ), $version_number ) );
		}

		/* translators: %s: Version number */
		WP_CLI::success( sprintf( __( 'Version %s unhidden.', 'post-version' ), $version_number ) );
	}

	/**
	 * Tries to delete a post's version.
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : ID of the post.
	 *
	 * <version>
	 * : Number of the version.
	 *
	 * @since 0.0.3
	 * @param array $args
	 * @return void
	 */
	public function delete( array $args ) : void {
		$post           = $this->validate_post_id( $args[0] );
		$version_number = $args[1];
		if ( ! is_numeric( $version_number ) ) {
			WP_CLI::error( __( 'Version value must be numeric.', 'post-version' ) );
		}

		add_filter( 'post_version_show_hidden_versions', '__return_true' );
		$version = VersionInterface::get_version( $post->ID, $version_number );
		remove_filter( 'post_version_show_hidden_versions', '__return_true' );
		if ( $version === null ) {
			WP_CLI::error( __( 'Version does not exist.', 'post-version' ) );
		}

		WP_CLI::confirm(
			WP_CLI::colorize(
				sprintf(
					/* translators: 1: Version number, 2: Version label */
					__( 'Are you sure you want to delete version %1$s with the label \'%2$s\'? This action cannot be undone.' ),
					"%G{$version_number}%N",
					"%G{$version->post_version->label()}%N"
				),
			)
		);

		$status = VersionInterface::delete_version( $post->ID, $version_number );

		if ( ! $status ) {
			/* translators: %s: Version number */
			WP_CLI::error( sprintf( __( 'Version %s failed to be deleted.', 'post-version' ), $version_number ) );
		}

		/* translators: %s: Version number */
		WP_CLI::success( sprintf( __( 'Version %s deleted.', 'post-version' ), $version_number ) );
	}

	/**
	 * Validates post id argument.
	 *
	 * If the post id is valid for CLI operations, its WP_Post is returned. Otherwise, the
	 * command will error.
	 *
	 * @since 0.0.3
	 *
	 * @param mixed $post_id
	 * @return WP_Post
	 */
	private function validate_post_id( $post_id ) : WP_Post {
		if ( ! is_numeric( $post_id ) ) {
			WP_CLI::error( __( 'Post ID must be numeric.', 'post-version' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			WP_CLI::error( __( 'Post does not exist.', 'post-version' ) );
		}

		if ( ! Options::is_post_type_versioned( $post->post_type ) ) {
			/* translators: %s: Post type */
			WP_CLI::error( sprintf( __( 'Post type \'%s\' is not versionable.', 'post-version' ), $post->post_type ) );
		}

		return $post;
	}
}
