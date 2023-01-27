<?php

namespace TwentySixB\WP\Plugin\PostVersion;

class Version {

	private int $post_id;
	private int $version;
	private string $label;

	public static function get( int $post_id ) : ?Version {
		// TODO: should we check to see if the post is versioned?

		$post_meta = get_post_meta( $post_id );

		$version = null;
		foreach ( $post_meta as $meta_key => $meta_values ) {
			if ( ! str_starts_with( $meta_key, 'post_version_' ) ) {
				continue;
			}
			$version_in_meta = substr( $meta_key, strlen( 'post_version_' ) );

			if ( ! is_numeric( $version_in_meta ) ) {
				continue;
			}

			$version = new self( $post_id, $version_in_meta, current( $meta_values ) );
			break;
		}

		return $version;
	}

	public function __construct( int $post_id, int $version, string $label ) {
		$this->post_id = $post_id;
		$this->version = $version;
		$this->label   = $label;
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
}
