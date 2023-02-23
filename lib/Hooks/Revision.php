<?php

namespace TwentySixB\WP\Plugin\PostVersion\Hooks;

use TwentySixB\WP\Plugin\PostVersion\Options;
use TwentySixB\WP\Plugin\PostVersion\Version;
use WP_Post;
use WP_Query;

/**
 * Hooks for revisions related functionality of WordPress.
 *
 * @since 0.0.1
 */
class Revision {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function register() : void {

		// Stop revision's from being deleted when they are the last revision.
		add_filter( 'pre_delete_post', [ $this, 'stop_revision_delete' ], PHP_INT_MAX, 3 );

		// Add filter to copy meta and terms to revision from original posts.
		add_action( 'wp_insert_post', [ $this, 'add_filter_copy_meta_terms' ], PHP_INT_MAX, 3 );

		// Check for changes in the post's meta or terms when its saved.
		add_filter( 'wp_save_post_revision_check_for_changes', [ $this, 'wp_save_post_revision_check_for_changes' ], PHP_INT_MAX, 10 );

		// Allow for more post statues for revisions during queries.
		add_action( 'pre_get_posts', [ $this, 'allow_more_statuses_for_revisions' ], PHP_INT_MAX );

		// Add version to formatted revision title.
		add_filter( 'wp_post_revision_title_expanded', [ $this, 'add_version_to_revision_formatted_title' ], PHP_INT_MAX, 3 );

		// Fix revision post type object. Needed for missing admin bar buttons when loading old versions in the frontend.
		add_action( 'init', [ $this, 'update_revision_post_type_object' ] );

		// Return post parent edit link in most cases for a revision edit.
		add_filter( 'get_edit_post_link', [ $this, 'get_edit_post_link' ], PHP_INT_MAX, 3 );

		// Restore post revision version and copied meta.
		add_action( 'wp_restore_post_revision', [ $this, 'wp_restore_post_revision' ], 0, 2 );

		// Add terms diff to revision diff.
		// TODO: Add non ACF meta to diff.
		add_filter( 'wp_get_revision_ui_diff', [ $this, 'add_info_to_revision_diff' ], PHP_INT_MAX, 3 );
	}

	/**
	 * Stop revision's from being deleted when they are the last revision.
	 *
	 * TODO: Handle $force_delete.
	 *
	 * @since 0.0.1
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
		 * @since 0.0.1
		 *
		 * @param mixed   $delete_revision
		 * @param WP_Post $post
		 */
		return apply_filters( 'post_version_delete_revision', $delete_revision, $post );
	}

	/**
	 * Add filter to copy meta and terms to revision from original posts.
	 *
	 * @since 0.0.1
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
		 * @since 0.0.1
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
	 * @since 0.0.1
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
		 * @since 0.0.1
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
	 * @since 0.0.1
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

		// Get meta diff.
		$meta_diff = self::meta_diff( $post_id, $latest_revision->ID );

		/**
		 * Filters the meta values that will be copied to the revision.
		 *
		 * Some meta values might have been copied already through other plugins like ACF.
		 *
		 * @since 0.0.1
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
	 * @since 0.0.1
	 *
	 * @param bool    $check_for_changes
	 * @param WP_Post $latest_revision
	 * @param WP_Post $post
	 * @return bool
	 */
	public function wp_save_post_revision_check_for_changes( bool $check_for_changes, WP_Post $latest_revision, WP_Post $post ) : bool {

		// Check meta changing.
		$meta_diff = self::meta_diff( $post->ID, $latest_revision->ID );

		// Remove post_versions from consideration.
		$meta_diff = array_filter(
			$meta_diff,
			fn ( $meta_key ) => ! preg_match( '/post_version_([0-9]+)/', $meta_key, $matches ) ,
			ARRAY_FILTER_USE_KEY
		);

		// If there are new meta entries, let WordPress create a new revision.
		if ( ! empty( $meta_diff ) ) {
			return false;
		}

		// Check terms changing.
		$revision_terms = get_terms( [ 'object_ids' => $latest_revision->ID, 'fields' => 'ids' ] );
		$post_terms     = get_terms( [ 'object_ids' => $post->ID, 'fields' => 'ids' ] );

		// If there are new term relations, let WordPress create a new revision.
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
	 * @since 0.0.1
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
		 * @since 0.0.1
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
	 * @since 0.0.1
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

	/**
	 * Get meta entries that exist in $post_1 that don't exist in $post_2.
	 *
	 * @since 0.0.1
	 *
	 * @param int $post_1
	 * @param int $post_2
	 * @return array
	 */
	private static function meta_diff( int $post_1, int $post_2 ) : array {

		$default_ignore = [
			'_wp_old_slug',
			'_edit_lock',
			'_encloseme',
		];

		/**
		 * Filters meta keys to ignore when copying the meta entries.
		 *
		 * @since 0.0.1
		 *
		 * @param array $meta_keys_to_ignore
		 * @param int   $post_1
		 * @param int   $post_2
		 */
		$meta_keys_to_ignore = apply_filters( 'post_version_meta_keys_to_ignore', $default_ignore, $post_1, $post_2 );

		$post_1_meta = get_post_meta( $post_1 );
		$post_2_meta = get_post_meta( $post_2 );
		$meta_diff   = [];
		foreach ( $post_1_meta as $meta_key => $meta_values ) {
			if (
				in_array( $meta_key, $meta_keys_to_ignore, true )
				|| ! is_array( $meta_values )
			) {
				continue;
			}

			if ( ! isset( $post_2_meta[ $meta_key ] ) ) {
				$meta_diff[ $meta_key ] = $meta_values;
				continue;
			}

			if ( ! is_array( $post_2_meta[ $meta_key ] ) ) {
				continue;
			}

			$sub_meta_diff = array_diff( $meta_values, $post_2_meta[ $meta_key ] );
			if ( ! empty( $sub_meta_diff ) ) {
				$meta_diff[ $meta_key ] = $sub_meta_diff;
			}
		}

		return $meta_diff;
	}

	/**
	 * Add version information to revision's formatted title shown in revisions list.
	 *
	 * @since 0.0.1
	 *
	 * @param string  $revision_date_author
	 * @param WP_Post $revision
	 * @param bool    $link
	 * @return string
	 */
	public function add_version_to_revision_formatted_title( string $revision_date_author, WP_Post $revision, bool $link ) : string {
		// TODO: Add url with version arg.

		// Ignore non versioned revisions.
		if ( $revision->post_status !== 'draft' && $revision->post_status !== 'publish' ) {
			return $revision_date_author;
		}

		// Ignore supposedly versioned revisions that are missing their revision meta.
		$version = Version::get( $revision->ID );
		if ( $version === null ) {
			return $revision_date_author;
		}

		// Get the time diff string to add the version string to the original revision title string.
		$time_diff            = human_time_diff( strtotime( $revision->post_modified_gmt ) );
		$revision_date_author = str_replace(
			"{$time_diff} ago",
			"{$time_diff} ago <b>Version {$version->label()}</b>",
			$revision_date_author
		);

		return $revision_date_author;
	}

	/**
	 * Update revision post type object.
	 *
	 * @since 0.0.2
	 *
	 * @return void
	 */
	public function update_revision_post_type_object() : void {
		global $wp_post_types;

		if ( ! isset( $wp_post_types['revision'] ) ) {
			return;
		}

		$wp_post_types['revision']->show_in_admin_bar = true;
	}

	/**
	 * Return revision's post_parent's edit link in most context's.
	 *
	 * @since 0.0.2
	 *
	 * @param string $link
	 * @param int    $post_id
	 * @param string $context
	 * @return string
	 */
	public function get_edit_post_link( string $link, int $post_id, string $context ) : string {
		$post = get_post( $post_id );
		if (
			! $post instanceof WP_Post
			|| $post->post_type !== 'revision'
			|| ! Options::is_post_type_versioned( get_post( $post->post_parent )->post_type )
		) {
			return $link;
		}

		// In post edit, we need to return the normal link to the revisions edit page.
		if ( is_admin() && get_current_screen()->base === 'post' ) {
			return $link;
		}

		return get_edit_post_link( $post->post_parent );
	}

	public function wp_restore_post_revision( $post_id, $revision_id ) : void {

		// Core keys to keep in the original post.
		$core_meta_keys = [
			'full_meta_keys' => [
				'_wp_attached_file',
				'_wp_attachment_metadata',
				'_edit_lock',
				'_edit_last',
				'_wp_old_slug',
				'_wp_old_date',
				'_wp_page_template',
				'_thumbnail_id',
			],
			'prefix_meta_keys' => [
				'_menu_item_',
			],
		];

		// Remove all of the non WordPress core meta entries.
		$post_meta = get_post_meta( $post_id );
		foreach ( array_keys( $post_meta ) as $meta_key ) {
			if ( in_array( $meta_key, $core_meta_keys['full_meta_keys'], true ) ) {
				continue;
			}
			foreach ( $core_meta_keys['prefix_meta_keys'] as $prefix ) {
				if ( str_starts_with( $meta_key, $prefix ) ) {
					continue 2;
				}
			}
			delete_post_meta( $post_id, $meta_key );
		}

		// Copy every meta entry from $revision_id over to $post_id.
		$revision_meta = get_post_meta( $revision_id );
		foreach ( $revision_meta as $meta_key => $meta_values ) {
			foreach ( $meta_values as $meta_value ) {
				add_post_meta( $post_id, $meta_key, $meta_value );
			}
		}

		// TODO: ACF will run after this so there might be issues.
	}

	/**
	 * Add information to revision diff.
	 *
	 * @since 0.0.1
	 *
	 * @param array        $return
	 * @param bool|WP_Post $compare_from
	 * @param WP_Post      $compare_to
	 * @return array
	 */
	public function add_info_to_revision_diff( array $return, $compare_from, WP_Post $compare_to ) : array {
		if ( ! Options::is_post_type_versioned( get_post( $compare_to->post_parent )->post_type ) ) {
			return $return;
		}

		$return = $this->add_terms_diff( $return, $compare_from, $compare_to );
		$return = $this->add_version_diff( $return, $compare_from, $compare_to );
		return $return;
	}

	/**
	 * Add the difference in terms between posts (revisions).
	 *
	 * @since 0.0.1
	 *
	 * @param array        $return
	 * @param bool|WP_Post $compare_from
	 * @param WP_Post      $compare_to
	 * @return array
	 */
	private function add_terms_diff( array $return, $compare_from, WP_Post $compare_to ) : array {

		// Function to output term into a string.
		$term_output_fn = fn ( $term ) => "{$term->name} ({$term->term_id}, {$term->taxonomy})";

		// Get terms for both posts and turn them into strings.
		$from_terms = [];
		$to_terms   = array_map( $term_output_fn, get_terms( [ 'object_ids' => $compare_to->ID ] ) );

		// $compare_from might be bool when dealing with first revision.
		if ( $compare_from instanceof WP_Post ) {
			$from_terms = array_map( $term_output_fn, get_terms( [ 'object_ids' => $compare_from->ID ] ) );
		}

		// If there are no terms on both sides, ignore it.
		if ( empty( $from_terms ) && empty( $to_terms ) ) {
			return $return;
		}

		// Add terms difference to the revisions ui.

		$args = [
			'show_split_view' => true,
			'title_left'      => __( 'Removed' ),
			'title_right'     => __( 'Added' ),
		];

		$return[] = [
			'id'   => 'post-version-terms',
			'name' => __( 'Terms', 'post-version' ),
			'diff' => wp_text_diff( implode( "\n", $from_terms ), implode( "\n", $to_terms ), $args ),
		];

		return $return;
	}

	/**
	 * Add the difference in version between posts (revisions).
	 *
	 * @since 0.0.1
	 *
	 * @param array        $return
	 * @param bool|WP_Post $compare_from
	 * @param WP_Post      $compare_to
	 * @return array
	 */
	private function add_version_diff( array $return, $compare_from, WP_Post $compare_to ) : array  {
		$args = [ 'show_split_view' => true ];

		// Get versions for both revisions.
		$to_version   = Version::get( $compare_to->ID );
		$from_version = $to_version;
		if ( $compare_from instanceof WP_Post ) {
			$from_version = Version::get( $compare_from->ID );
		}

		/** translators: 1: Version label, 2: Version number */
		$name = sprintf( __( 'Post version %s (%s)', 'post-version' ), $to_version->label(), $to_version->version() );
		$diff = '';

		// Show side to side only if both versions are not the same.
		if ( $to_version->version() !== $from_version->version() ) {
			$name = __( 'Post version', 'post-version' );
			$diff = wp_text_diff(
				sprintf( '%s (%s)', $from_version->label(), $from_version->version() ),
				sprintf( '%s (%s)', $to_version->label(), $to_version->version() ),
				$args
			);
		}

		// Add it at the start of the differences.
		array_unshift(
			$return,
			[
				'id'   => 'post-version-version',
				'name' => $name,
				'diff' => $diff,
			]
		);

		return $return;
	}
}
