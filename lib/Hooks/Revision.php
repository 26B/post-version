<?php

namespace TwentySixB\WP\Plugin\PostVersion\Hooks;

use TwentySixB\WP\Plugin\PostVersion\Options;
use TwentySixB\WP\Plugin\PostVersion\Version;
use WP_Post;
use WP_Query;

class Revision {

	public function register() : void {
		add_action( 'wp_insert_post', [ $this, 'insert_meta_in_revision' ], PHP_INT_MAX, 3 );
		add_action( 'wp_save_post_revision_check_for_changes', [ $this, 'wp_save_post_revision_check_for_changes' ], PHP_INT_MAX, 10 );
		add_action( 'pre_delete_post', [ $this, 'stop_revision_delete' ], PHP_INT_MAX, 3 );

		// Filter get_children called in wp-includes/revision.php:511. Published revisions don't show up in the list.
		add_action( 'pre_get_posts', [ $this, 'remove_inherit_from_revision_query' ],  PHP_INT_MAX );
	}

	// TODO: Handle $force_delete
	public function stop_revision_delete( $delete, $post, $force_delete ) {
		if ( $delete !== null ) {
			return $delete;
		}

		if ( $post->post_type !== 'revision' ) {
			return $delete;
		}

		$revisions = wp_get_post_revisions( $post->post_parent );

		// Don't delete latest revision.
		if ( $post->ID === reset( $revisions )->ID ) {
			return false;
		}

		$delete_revision = null;
		if ( in_array( $post->post_status, [ 'publish', 'draft' ], true ) ) {
			$delete_revision = false;
		}

		return apply_filters( 'post_version_delete_revision', $delete_revision, $post );
	}

	public function insert_meta_in_revision( int $post_id, WP_Post $post, bool $update ) : void {
		if ( $post->post_type !== 'revision' ) {
			return;
		}

		$original_post_id = $post->post_parent;

		// Shouldn't happen.
		if ( ! is_int( $original_post_id ) || $original_post_id <= 0 ) {
			return;
		}

		$original_post = get_post( $original_post_id );

		// Shouldn't happen.
		if ( $original_post === null ) {
			return;
		}

		if ( ! Options::is_post_type_versioned( $original_post->post_type ) ) {
			return;
		}

		// FIXME: better filter name
		if ( apply_filters( 'post_version_stop_revision_meta_duping', false, $post_id, $post, $update ) ) {
			return;
		}

		$version = Version::get( $original_post_id );

		// TODO: shouldn't happen, how to handle?
		if ( is_null( $version ) ) {
			return;
		}

		// TODO: Have some way to only do it once for a post id and then remove the filter.
		add_action( 'wp_insert_post', [ self::class, 'copy_meta_terms' ], PHP_INT_MAX, 3 );
	}

	public static function copy_meta_terms( int $post_id, WP_Post $post, bool $update ) : void {
		global $wpdb;

		if ( ! Options::is_post_type_versioned( $post->post_type ) || $post->post_type === 'revision' ) {
			return;
		}

		$post_revisions  = wp_get_post_revisions( $post_id );
		$latest_revision = reset( $post_revisions );

		if ( ! $latest_revision instanceof WP_Post ) {
			return;
		}

		// Copy meta to revision.
		$post_meta     = get_post_meta( $post_id );
		$revision_meta = get_post_meta( $latest_revision->ID );
		$meta_diff     = [];

		foreach ( $post_meta as $meta_key => $meta_values ) {
			if (
				in_array( $meta_key, [ '_wp_old_slug' ], true )
				// TODO: double check these.
				|| ! is_array( $meta_values )
			) {
				continue;
			}

			if ( ! isset( $revision_meta[ $meta_key ] ) ) {
				$meta_diff[ $meta_key ] = $meta_values;
				continue;
			}

			// TODO: double check these.
			if ( ! is_array( $revision_meta[ $meta_key ] ) ) {
				continue;
			}

			// TODO: which meta keys to ignore.

			$sub_meta_diff = array_diff( $meta_values, $revision_meta[ $meta_key ] );
			if ( ! empty( $sub_meta_diff ) ) {
				$meta_diff[ $meta_key ] = $sub_meta_diff;
			}
		}

		// TODO: make sure there is only one post_version meta.

		foreach ( $meta_diff as $meta_key => $meta_values ) {
			foreach ( $meta_values as $meta_value ) {
				add_metadata( 'post', $latest_revision->ID, $meta_key, maybe_unserialize( $meta_value ) );
			}
		}

		// Copy terms to revision.
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

	public function remove_inherit_from_revision_query( WP_Query $query ) : void {
		if ( ! isset( $query->query_vars['post_type'] ) ) {
			return;
		}

		if ( $query->query_vars['post_type'] !== 'revision' ) {
			return;
		}
		if ( $query->query_vars['post_status'] !== 'inherit' ) {
			return;
		}

		$show_hidden_versions = apply_filters( 'post_version_show_hidden_versions_query', false, $query );

		$query->query_vars['post_status'] = [ 'inherit', 'publish' ];
		if ( $show_hidden_versions ) {
			$query->query_vars['post_status'][] = 'draft';
		}
	}
}
