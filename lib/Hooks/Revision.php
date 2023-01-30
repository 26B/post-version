<?php

namespace TwentySixB\WP\Plugin\PostVersion\Hooks;

use TwentySixB\WP\Plugin\PostVersion\Options;
use TwentySixB\WP\Plugin\PostVersion\Version;
use WP_Post;
use WP_Query;

/**
 * Hooks for revisions related functionality of WordPress.
 *
 * @since 0.0.0
 */
class Revision {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.0
	 *
	 * @return void
	 */
	public function register() : void {

		// Stop revision's from being deleted when they are the last revision.
		add_action( 'pre_delete_post', [ $this, 'stop_revision_delete' ], PHP_INT_MAX, 3 );

		// Add filter to copy meta and terms to revision from original posts.
		add_action( 'wp_insert_post', [ $this, 'add_filter_copy_meta_terms' ], PHP_INT_MAX, 3 );

		// Check for changes in the post's meta or terms when its saved.
		add_action( 'wp_save_post_revision_check_for_changes', [ $this, 'wp_save_post_revision_check_for_changes' ], PHP_INT_MAX, 10 );

		// Allow for more post statues for revisions during queries.
		add_action( 'pre_get_posts', [ $this, 'allow_more_statuses_for_revisions' ],  PHP_INT_MAX );
	}

	/**
	 * Stop revision's from being deleted when they are the last revision.
	 *
	 * TODO: Handle $force_delete.
	 *
	 * @since 0.0.0
	 *
	 * @param WP_Post|false|null $delete
	 * @param WP_Post            $post
	 * @param bool               $force_delete
	 * @return WP_Post|false|null
	 */
	public function stop_revision_delete( $delete, WP_Post $post, bool $force_delete ) {

		// If ignore is already non null, don't check anything else.
		if ( $delete !== null ) {
			return $delete;
		}

		// Don't check revisions.
		if ( $post->post_type !== 'revision' ) {
			return $delete;
		}


		// Don't delete latest revision.
		$revisions = wp_get_post_revisions( $post->post_parent );
		if ( $post->ID === reset( $revisions )->ID ) {
			return false;
		}

		// Only delete revisions with a non publish or draft status.
		$delete_revision = null;
		if ( in_array( $post->post_status, [ 'publish', 'draft' ], true ) ) {
			$delete_revision = false;
		}

		/**
		 * Filters whether a revision is deleted or not.
		 *
		 * @since 0.0.0
		 *
		 * @param mixed   $delete_revision
		 * @param WP_Post $post
		 */
		return apply_filters( 'post_version_delete_revision', $delete_revision, $post );
	}

	/**
	 * Add filter to copy meta and terms to revision from original posts.
	 *
	 * @since 0.0.0
	 *
	 * @param  int     $post_id
	 * @param  WP_Post $post
	 * @param  bool    $update
	 * @return void
	 */
	public function add_filter_copy_meta_terms( int $post_id, WP_Post $post, bool $update ) : void {

		// Ignore non revisions.
		if ( $post->post_type !== 'revision' ) {
			return;
		}

		// Get post parent ID.
		$original_post_id = $post->post_parent;

		// Shouldn't happen.
		if ( ! is_int( $original_post_id ) || $original_post_id <= 0 ) {
			return;
		}

		// Get post parent.
		$original_post = get_post( $original_post_id );

		// Shouldn't happen.
		if ( $original_post === null ) {
			return;
		}

		// If original post type is not versioned, ignore this revision.
		if ( ! Options::is_post_type_versioned( $original_post->post_type ) ) {
			return;
		}

		/**
		 * Filters whether to stop a revisions meta and term duplication.
		 *
		 * @since 0.0.0
		 *
		 * @param bool    $duplicate
		 * @param int     $post_id
		 * @param WP_Post $post
		 * @param bool    $update
		 */
		if ( ! apply_filters( 'post_version_duplicate_meta_terms', true, $post_id, $post, $update ) ) {
			return;
		}

		/**
		 * Add filters to copy the meta and terms from original post to latest revision after post
		 * and its meta/terms are fully saved.
		 */
		add_filter( "_post_version_copy_meta_terms_{$original_post_id}", '__return_true' );
		add_action( 'wp_insert_post', [ $this, 'maybe_copy_meta_terms' ], PHP_INT_MAX, 3 );
	}

	/**
	 * Checks if the post being saved should have its meta and terms copied to its latest revision.
	 *
	 * @since 0.0.0
	 *
	 * @param int     $post_id
	 * @param WP_Post $post
	 * @param bool    $update
	 * @return void
	 */
	public function maybe_copy_meta_terms( int $post_id, WP_Post $post, bool $update ) : void {

		/**
		 * Filters whether to copy the meta and terms for specific post_id
		 *
		 * This is an internal filter used to make sure the meta and terms copy is only done once
		 * and for a specific post_id after the revision is saved.
		 *
		 * @since 0.0.0
		 * @param bool $copy
		 */
		if ( ! apply_filters( "_post_version_copy_meta_terms_{$post_id}", false ) ) {
			return;
		}
		remove_all_filters( "_post_version_copy_meta_terms_{$post_id}" );

		self::copy_meta_terms_to_latest_revision( $post_id, $post );
	}

	/**
	 * Copy a post's meta and terms to its latest revision.
	 *
	 * @since 0.0.0
	 *
	 * @param int     $post_id
	 * @param WP_Post $post
	 * @return void
	 */
	public static function copy_meta_terms_to_latest_revision( int $post_id, WP_Post $post ) : void {
		global $wpdb;

		// Ignore revisions and unversioned post types.
		if ( $post->post_type === 'revision' || ! Options::is_post_type_versioned( $post->post_type ) ) {
			return;
		}

		// Get post's latest revision.
		$post_revisions  = wp_get_post_revisions( $post_id );
		$latest_revision = reset( $post_revisions );

		if ( ! $latest_revision instanceof WP_Post ) {
			return;
		}

		/**
		 * Get meta that hasn't been added to the revision yet.
		 *
		 * ACF copies the meta to the revision already.
		 */

		/**
		 * Filters meta keys to ignore when copying the meta entries to the latest revision.
		 *
		 * @since 0.0.0
		 *
		 * @param int     $post_id
		 * @param WP_Post $post
		 * @param WP_Post $latest_revision
		 */
		$meta_keys_to_ignore = apply_filters( 'post_version_meta_keys_to_ignore', [ '_wp_old_slug' ], $post_id, $post, $latest_revision );

		$post_meta     = get_post_meta( $post_id );
		$revision_meta = get_post_meta( $latest_revision->ID );
		$meta_diff     = [];
		foreach ( $post_meta as $meta_key => $meta_values ) {
			if (
				in_array( $meta_key, $meta_keys_to_ignore, true )
				|| ! is_array( $meta_values )
			) {
				continue;
			}

			if ( ! isset( $revision_meta[ $meta_key ] ) ) {
				$meta_diff[ $meta_key ] = $meta_values;
				continue;
			}

			if ( ! is_array( $revision_meta[ $meta_key ] ) ) {
				continue;
			}

			$sub_meta_diff = array_diff( $meta_values, $revision_meta[ $meta_key ] );
			if ( ! empty( $sub_meta_diff ) ) {
				$meta_diff[ $meta_key ] = $sub_meta_diff;
			}
		}

		/**
		 * Filters the meta values that will be copied to the revision.
		 *
		 * Some meta values might have been copied already through other plugins like ACF.
		 *
		 * @since 0.0.0
		 *
		 * @param array   $meta_diff
		 * @param WP_Post $post
		 * @param WP_Post $latest_revision
		 */
		$meta_diff = apply_filters( 'post_version_meta_to_copy', $meta_diff, $post, $latest_revision );

		// Make sure there is only one post_version meta.
		$meta_diff = self::remove_unnecessary_post_versions( $meta_diff );

		// Copy meta to the revision.
		if ( is_array( $meta_diff ) ) {
			foreach ( $meta_diff as $meta_key => $meta_values ) {
				foreach ( $meta_values as $meta_value ) {
					add_metadata( 'post', $latest_revision->ID, $meta_key, maybe_unserialize( $meta_value ) );
				}
			}
		}

		// Copy post's term relationships to revision.
		// TODO: Add filter to the relationships.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->term_relationships} (object_id,term_taxonomy_id,term_order)
				SELECT %s as object_id, term_taxonomy_id, term_order
				FROM {$wpdb->term_relationships}
				WHERE object_id = %s",
				$latest_revision->ID,
				$post_id
			)
		);
	}

	/**
	 * Check for changes in the post's meta or terms when its saved.
	 *
	 * @since 0.0.0
	 *
	 * @param bool    $check_for_changes
	 * @param WP_Post $latest_revision
	 * @param WP_Post $post
	 * @return bool
	 */
	public function wp_save_post_revision_check_for_changes( bool $check_for_changes, WP_Post $latest_revision, WP_Post $post ) : bool {
		// TODO: Check meta changing.

		// Check terms changing.
		$revision_terms = get_terms( [ 'object_ids' => $latest_revision->ID, 'fields' => 'ids' ] );
		$post_terms     = get_terms( [ 'object_ids' => $post->ID, 'fields' => 'ids' ] );
		if ( ! empty( array_diff( $post_terms, $revision_terms ) ) ) {
			return false;
		}

		return $check_for_changes;
	}

	/**
	 * Allow for more post statues for revisions during queries.
	 *
	 * Allow 'publish' for revisions, along with the default 'inherit'. Also allow for 'draft'
	 * (hidden versions) revisions depending on a filter.
	 *
	 * Filter get_children called in wp-includes/revision.php:511. Published revisions don't show up
	 * in the list.
	 *
	 * @since 0.0.0
	 *
	 * @param WP_Query $query
	 * @return void
	 */
	public function allow_more_statuses_for_revisions( WP_Query $query ) : void {
		if (
			! isset( $query->query_vars['post_type'] )
			|| $query->query_vars['post_type'] !== 'revision'
			|| ! isset( $query->query_vars['post_status'] )
			|| $query->query_vars['post_status'] !== 'inherit'
		) {
			return;
		}

		/**
		 * Filters whether to show hidden post versions in revision queries.
		 *
		 * @since 0.0.0
		 *
		 * @param bool     $show_hidden_versions
		 * @param WP_Query $query
		 */
		$show_hidden_versions = apply_filters( 'post_version_show_hidden_versions_query', false, $query );

		$query->query_vars['post_status'] = [ 'inherit', 'publish' ];
		if ( $show_hidden_versions ) {
			$query->query_vars['post_status'][] = 'draft';
		}
	}

	/**
	 * Remove unnecessary post versions from an array of meta keys and meta values.
	 *
	 * Keep only the highest post version meta.
	 *
	 * @since 0.0.0
	 *
	 * @param array $meta
	 * @return array
	 */
	private static function remove_unnecessary_post_versions( array $meta ) : array {
		$post_versions = [];

		foreach ( array_keys( $meta ) as $meta_key ) {
			$matches = [];
			if ( ! preg_match( '/post_version_([0-9]+)/', $meta_key, $matches ) ) {
				continue;
			}

			$post_versions[ $matches[1] ] = $meta_key;
		}

		// Keep highest post version.
		krsort( $post_versions );
		$meta_key = array_shift( $post_versions );

		// Make sure only one value for the highest post_version
		$meta[ $meta_key ] = [ current( $meta[ $meta_key ] ) ];

		// Remove other versions.
		foreach ( $post_versions as $meta_key ) {
			unset( $meta[ $meta_key ] );
		}

		return $meta;
	}
}
