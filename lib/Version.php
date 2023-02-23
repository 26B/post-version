<?php

namespace TwentySixB\WP\Plugin\PostVersion;

use WP_Post;

class Version {

	private int $post_id;
	private int $version;
	private string $label;
	private string $status;

	public static function get( int $post_id ) : ?Version {

		// Check post's type is versioned.
		$post = get_post( $post_id );
		if (
			( $post->post_type !== 'revision'
				&& ! Options::is_post_type_versioned( $post->post_type ) )
			|| ( $post->post_type === 'revision'
				&& ! Options::is_post_type_versioned( get_post( $post->post_parent )->post_type ) )
		) {
			return null;
		}

		$post_meta = get_post_meta( $post_id );

		$version         = null;
		$highest_version = null;
		foreach ( $post_meta as $meta_key => $meta_values ) {
			$matches = [];
			if ( ! preg_match( '/post_version_([0-9]+)/', $meta_key, $matches ) ) {
				continue;
			}
			$version_in_meta = $matches[1];

			if ( ! is_numeric( $version_in_meta ) ) {
				continue;
			}

			$highest_version = [ $version_in_meta, current( $meta_values ) ] ;
		}

		if ( $highest_version ) {
			$version = new self( $post_id, $highest_version[0], $highest_version[1], self::get_status( $post ) );
		}

		return $version;
	}

	public function __construct( int $post_id, int $version, string $label, string $status ) {
		$this->post_id = $post_id;
		$this->version = $version;
		$this->label   = $label;
		$this->status   = $status;
	}

	public function post_id() : int {
		return $this->post_id;
	}

	public function version() : int {
		return $this->version;
	}

	public function label() : string {
		return $this->label;
	}

	public function status() : string {
		return $this->status;
	}

	private static function get_status( WP_Post $post ) : string {
		$status_string = __( 'Unknown', 'post-version' );
		switch ( $post->post_status ) {
			case 'publish':
				$status_string = __( 'Live', 'post-version' );
				break;
			case 'draft':
				$status_string = __( 'Hidden', 'post-version' );
				break;
			case 'unreleased':
				$status_string = __( 'Unreleased', 'post-version' );
				break;
		}
		return $status_string;
	}
}
