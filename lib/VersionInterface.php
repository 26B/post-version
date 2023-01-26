<?php

namespace TwentySixB\WP\Plugin\PostVersion;

use TwentySixB\WP\Plugin\PostVersion\Hooks\Status;
use TwentySixB\WP\Plugin\PostVersion\Version;
use WP_Post;

// TODO: capabilities for actions.

class VersionInterface {

	public static function get_version( int $post_id, int|string $version ) : ?WP_Post {
		$post_versions = self::get_versions( $post_id );
		if ( is_int( $version ) ) {
			return $post_versions[ $version ] ?? null;
		}

		foreach ( $post_versions as $post_version ) {
			if ( $post_version->post_version->label() === $version ) {
				return $post_version;
			}
		}

		return null;
	}

	public static function create_new_version( int $post_id ) : void {
		$current_version = Version::get( $post_id );
		if ( ! $current_version instanceof Version ) {
			return;
		}

		$new_version_number = $current_version->version() + 1;
		$new_version_label  = apply_filters( 'post_version_new_version_label', $new_version_number, $post_id, $current_version );

		delete_post_meta( $post_id, sprintf( 'post_version_%s', $current_version->version() ), $current_version->label() );

		add_post_meta( $post_id, sprintf( 'post_version_%s', $new_version_number ), $new_version_label, true );

		// Stop duplication of meta's on revisions.
		add_filter( 'post_version_stop_revision_meta_duping', '__return_true' );

		remove_action( 'pre_post_update', 'wp_save_post_revision' );
		$status = wp_update_post( [ 'ID' => $post_id, 'post_status' => Status::UNRELEASED ], true );
		add_action( 'pre_post_update', 'wp_save_post_revision' );
		if ( is_wp_error( $status ) ) {
			// TODO:
			return;
		}

		$post_revisions = wp_get_post_revisions( $post_id );

		if ( ! empty( $post_revisions ) ) {
			$latest_revision = reset( $post_revisions );
			// TODO: if latest revision is a versioned revision, we need to throw an error or create a new revision.

			remove_action( 'pre_post_update', 'wp_save_post_revision' );
			$status = wp_update_post( [ 'ID' => $latest_revision->ID, 'post_status' => 'publish' ], true );
			add_action( 'pre_post_update', 'wp_save_post_revision' );

			if ( is_wp_error( $status ) ) {
				// TODO:
				return;
			}
		}

		remove_filter( 'post_version_stop_revision_meta_duping', '__return_true' );
	}

	public static function get_versions( int $post_id ) : array {
		$versions_found = [];

		// If the post is published, add it to the list.
		$post = get_post( $post_id );
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

		$show_hidden_versions = apply_filters( 'post_version_show_hidden_versions', false, $post_id );

		// Get post revisions.
		$show_hidden_versions && add_filter( 'post_version_show_hidden_versions_query', '__return_true' );
		$revisions = wp_get_post_revisions( $post_id );
		$show_hidden_versions && remove_filter( 'post_version_show_hidden_versions_query', '__return_true' );

		// Filter to the ones that are published.
		$revisions = array_filter(
			$revisions,
			fn ( $revision ) => in_array( $revision->post_status, $show_hidden_versions ? [ 'publish', 'draft' ] : [ 'publish' ], true )
		);

		foreach ( $revisions as $revision ) {
			$version = Version::get( $revision->ID );
			if ( ! $version instanceof Version || isset( $versions_found[ $version->version() ] ) ) {
				continue;
			}

			// Add post_version and fix post_name.
			$revision->post_version                = $version;
			$revision->post_name                   = $post->post_name;
			$versions_found[ $version->version() ] = $revision;
		}

		return $versions_found;
	}

	public static function get_current_version( int $post_id ) : WP_Post {
		$post = get_post( $post_id );
		if ( $post->post_status === 'publish' ) {
			$version = Version::get( $post_id );
			if ( $version instanceof Version ) {
				$post->post_version = $version;
			}
			return $post;
		}

		// TODO: more optimal way to get latest published revision.
		$versions = self::get_versions( $post_id );
		if ( empty( $versions ) ) {
			// TODO: if nothing is published, return $post or return null?
			return $post;
		}

		return reset( $versions );
	}

	public static function delete_version( int $post_id, int|string $version ) : bool {
		$version = self::get_version( $post_id, $version );
		if ( $version === null ) {
			return false;
		}

		// If it's the latest version, don't delete it.
		if ( empty( $version->post_parent ) ) {
			return false;
		}

		add_filter( 'post_version_delete_revision', '__return_null' );
		$status = (bool) wp_delete_post_revision( $version );
		remove_filter( 'post_version_delete_revision', '__return_null' );
		return $status;
	}

	public static function hide_version( int $post_id, int|string $version ) : bool {
		$version = self::get_version( $post_id, $version );
		if ( $version === null ) {
			return false;
		}

		// The latest version cannot be hidden.
		if ( empty( $version->post_parent ) ) {
			return false;
		}

		if ( $version->post_status === 'draft' ) {
			return true;
		}

		remove_action( 'pre_post_update', 'wp_save_post_revision' );
		$status = wp_update_post( [ 'ID' => $version->ID, 'post_status' => 'draft' ], true );
		add_action( 'pre_post_update', 'wp_save_post_revision' );
		return ! is_wp_error( $status );
	}

	public static function unhide_version( int $post_id, int|string $version ) : bool {
		add_filter( 'post_version_show_hidden_versions', '__return_true' );
		$version = self::get_version( $post_id, $version );
		remove_filter( 'post_version_show_hidden_versions', '__return_true' );
		if ( $version === null ) {
			return false;
		}

		// The latest version cannot be hidden.
		if ( empty( $version->post_parent ) ) {
			return false;
		}

		if ( $version->post_status === 'publish' ) {
			return true;
		}

		remove_action( 'pre_post_update', 'wp_save_post_revision' );
		$status = wp_update_post( [ 'ID' => $version->ID, 'post_status' => 'publish' ], true );
		add_action( 'pre_post_update', 'wp_save_post_revision' );
		return ! is_wp_error( $status );
	}
}
