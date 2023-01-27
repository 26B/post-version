<?php

namespace TwentySixB\WP\Plugin\PostVersion;

use TwentySixB\WP\Plugin\PostVersion\Hooks\Status;
use TwentySixB\WP\Plugin\PostVersion\Version;
use WP_Post;

// TODO: capabilities for actions.

/**
 * Interface for methods related to post versions.
 *
 * @since 0.0.0
 */
class VersionInterface {

	/**
	 * Get a post's version.
	 *
	 * @since 0.0.0
	 *
	 * @param int        $post_id
	 * @param int|string $version
	 * @return ?WP_Post WP_Post if the version exists and is published, null otherwise.
	 */
	public static function get_version( int $post_id, int|string $version ) : ?WP_Post {

		// Get all of the post's published versions.
		$post_versions = self::get_versions( $post_id );

		// If the version requested is the version number, check the keys of the versions array.
		if ( is_int( $version ) ) {
			return $post_versions[ $version ] ?? null;
		}

		// If the version requestes is the version label, check every post version's label to find it.
		foreach ( $post_versions as $post_version ) {
			if ( $post_version->post_version->label() === $version ) {
				return $post_version;
			}
		}

		// If the version label is not found, return null.
		return null;
	}

	/**
	 * Create a new post's version.
	 *
	 * @since 0.0.0
	 *
	 * @param int $post_id
	 * @return bool True if new version was created, false otherwise.
	 */
	public static function create_new_version( int $post_id ) : bool {

		// Check if the current post is versioned.
		$post = get_post( $post_id );
		if ( ! Options::is_post_type_versioned( $post->post_type ) ) {
			return false;
		}

		// Get the current version. If none exist, then do not proceed.
		// TODO: How to handle versioning pre-existing content?
		$current_version = Version::get( $post_id );
		if ( ! $current_version instanceof Version ) {
			return false;
		}

		// Make new version information.
		$new_version_number = $current_version->version() + 1;
		$new_version_label  = apply_filters( 'post_version_new_version_label', $new_version_number, $post_id, $current_version );

		// Delete old post version.
		delete_post_meta( $post_id, sprintf( 'post_version_%s', $current_version->version() ), $current_version->label() );

		// Add new post version.
		add_post_meta( $post_id, sprintf( 'post_version_%s', $new_version_number ), $new_version_label, true );

		// Stop duplication of meta's on revisions.
		add_filter( 'post_version_duplicate_meta_terms', '__return_false' );

		// Update the post status of the real post to unreleased status.
		remove_action( 'pre_post_update', 'wp_save_post_revision' );
		$status = wp_update_post( [ 'ID' => $post_id, 'post_status' => Status::UNRELEASED ], true );
		add_action( 'pre_post_update', 'wp_save_post_revision' );
		if ( is_wp_error( $status ) ) {
			// TODO:
			return false;
		}

		// Get the latest post revision and set its status to published.
		$post_revisions = wp_get_post_revisions( $post_id );

		/**
		 * TODO: if the latest revision is a versioned revision, or there are no revisions, we need
		 * to throw an error or create a new revision before the post meta is updated above.
		 */
		if ( ! empty( $post_revisions ) ) {
			$latest_revision = reset( $post_revisions );

			// Publish revision.
			remove_action( 'pre_post_update', 'wp_save_post_revision' );
			$status = wp_update_post( [ 'ID' => $latest_revision->ID, 'post_status' => 'publish' ], true );
			add_action( 'pre_post_update', 'wp_save_post_revision' );

			if ( is_wp_error( $status ) ) {
				// TODO:
				return false;
			}
		}

		remove_filter( 'post_version_duplicate_meta_terms', '__return_false' );

		return true;
	}

	/**
	 * Get published versions of a post.
	 *
	 * @since 0.0.0
	 *
	 * @param int $post_id
	 * @return array Array of versioned WP_Post's, indexed by their version number.
	 */
	public static function get_versions( int $post_id ) : array {
		$versions_found = [];

		// Check if the post's type is versioned.
		$post = get_post( $post_id );
		if ( ! Options::is_post_type_versioned( $post->post_type ) ) {
			return $versions_found;
		}

		// If the post is published, add it to the list as the latest version.
		if ( $post->post_status === 'publish' ) {
			$version = Version::get( $post_id );

			if (
				$version instanceof Version
				&& ! isset( $versions_found[ $version->version() ] ) // Shouldn't happen.
			) {
				$post->post_version                    = $version;
				$versions_found[ $version->version() ] = $post;
			}
		}

		/**
		 * Filters whether hidden (draft) post versions should be returned.
		 *
		 * @since 0.0.0
		 *
		 * @param bool $show_hidden_versions
		 * @param int  $post_id
		 * @return void
		 */
		$show_hidden_versions = apply_filters( 'post_version_show_hidden_versions', false, $post_id );

		// Get post revisions.
		$show_hidden_versions && add_filter( 'post_version_show_hidden_versions_query', '__return_true' );
		$revisions = wp_get_post_revisions( $post_id );
		$show_hidden_versions && remove_filter( 'post_version_show_hidden_versions_query', '__return_true' );

		// Filter to the wanted verions (published or published/draft).
		$revisions = array_filter(
			$revisions,
			fn ( $revision ) => in_array( $revision->post_status, $show_hidden_versions ? [ 'publish', 'draft' ] : [ 'publish' ], true )
		);

		// Check that each revision has a version and do not add duplicates (which shouldn't happen).
		foreach ( $revisions as $revision ) {
			$version = Version::get( $revision->ID );
			if ( ! $version instanceof Version || isset( $versions_found[ $version->version() ] ) ) {
				continue;
			}

			// Add post_version and fix post_name to the main post.
			$revision->post_version                = $version;
			$revision->post_name                   = $post->post_name;
			$versions_found[ $version->version() ] = $revision;
		}

		return $versions_found;
	}

	/**
	 * Get the current/latest version of a post.
	 *
	 * TODO: Handle revision post id's being passed.
	 *
	 * @since 0.0.0
	 *
	 * @param int $post_id
	 * @return WP_Post The current/latest version.
	 */
	public static function get_current_version( int $post_id ) : WP_Post {
		$post = get_post( $post_id );

		// If the post's type is not versioned, return the main post.
		if ( ! Options::is_post_type_versioned( $post->post_type ) ) {
			return $post;
		}

		// If the post is published, return the main post.
		if ( $post->post_status === 'publish' ) {
			$version = Version::get( $post_id );
			if ( $version instanceof Version ) {
				$post->post_version = $version;
			}
			return $post;
		}

		// Get the versions and return the latest one.
		// TODO: more optimal way to get latest published revision.
		$versions = self::get_versions( $post_id );
		if ( empty( $versions ) ) {
			// TODO: if nothing is published, return $post or return null?
			return $post;
		}

		return reset( $versions );
	}

	/**
	 * Delete a post's version.
	 *
	 * Deletes the published revision for the request post's version.
	 *
	 * @since 0.0.0
	 *
	 * @param int        $post_id
	 * @param int|string $version
	 * @return bool True if the version was delete, false otherwise.
	 */
	public static function delete_version( int $post_id, int|string $version ) : bool {

		// Get the requested version.
		$version = self::get_version( $post_id, $version );
		if ( $version === null ) {
			return false;
		}

		// If it's the latest version, don't delete it.
		if ( empty( $version->post_parent ) ) {
			return false;
		}

		// Delete the post revision.
		add_filter( 'post_version_delete_revision', '__return_null' );
		$status = (bool) wp_delete_post_revision( $version );
		remove_filter( 'post_version_delete_revision', '__return_null' );
		return $status;
	}

	/**
	 * Hide a post's version.
	 *
	 * Sets a revision version's post_status to 'draft'.
	 *
	 * @since 0.0.0
	 *
	 * @param int $post_id
	 * @param int|string $version
	 * @return bool True if the version was hidden, false otherwise.
	 */
	public static function hide_version( int $post_id, int|string $version ) : bool {

		// Get the requested version.
		$version = self::get_version( $post_id, $version );
		if ( $version === null ) {
			return false;
		}

		// If the main post is the latest version, it cannot be hidden this way.
		if ( empty( $version->post_parent ) ) {
			return false;
		}

		// If the status is already draft, don't do anything else.
		if ( $version->post_status === 'draft' ) {
			return true;
		}

		// Update the revision's post status to draft.
		remove_action( 'pre_post_update', 'wp_save_post_revision' );
		$status = wp_update_post( [ 'ID' => $version->ID, 'post_status' => 'draft' ], true );
		add_action( 'pre_post_update', 'wp_save_post_revision' );
		return ! is_wp_error( $status );
	}

	/**
	 * Unhide a post's version.
	 *
	 * Sets a revision version's post_status to 'publish'.
	 *
	 * @since 0.0.0
	 *
	 * @param int        $post_id
	 * @param int|string $version
	 * @return bool True if the version was unhidden, false otherwise.
	 */
	public static function unhide_version( int $post_id, int|string $version ) : bool {

		// Get the hidden post version.
		add_filter( 'post_version_show_hidden_versions', '__return_true' );
		$version = self::get_version( $post_id, $version );
		remove_filter( 'post_version_show_hidden_versions', '__return_true' );
		if ( $version === null ) {
			return false;
		}

		// If the main post is the latest version, ignore the action.
		if ( empty( $version->post_parent ) ) {
			return false;
		}

		// If the status is already publish, don't do anything else.
		if ( $version->post_status === 'publish' ) {
			return true;
		}

		// Set the revision to publish.
		remove_action( 'pre_post_update', 'wp_save_post_revision' );
		$status = wp_update_post( [ 'ID' => $version->ID, 'post_status' => 'publish' ], true );
		add_action( 'pre_post_update', 'wp_save_post_revision' );
		return ! is_wp_error( $status );
	}
}
