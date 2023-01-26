<?php

namespace TwentySixB\WP\Plugin\PostVersion\Hooks;

use DOMDocument;
use TwentySixB\WP\Plugin\PostVersion\Options;
use TwentySixB\WP\Plugin\PostVersion\Version;
use TwentySixB\WP\Plugin\PostVersion\VersionInterface;
use WP_Post;

class Admin {

	public function register() {
		add_action( 'admin_print_styles-post.php', [ $this, 'hide_post_status_selection' ] );
		add_action( 'current_screen', [ $this, 'create_new_version' ], PHP_INT_MAX );
		add_action( 'edit_form_after_editor', [ $this, 'edit_form_after_editor' ], PHP_INT_MAX );
	}

	/**
	 * Hide post status selection when unreleased.
	 *
	 * @return void
	 */
	public function hide_post_status_selection() : void {
		// TODO: make sure it is the loaded post.
		$post = get_post();
		if (
			! $post instanceof WP_Post
			|| ! Options::is_post_type_versioned( $post->post_type )
			|| $post->post_status !== Status::UNRELEASED
		) {
			return;
		}

		echo '<style>.edit-post-status{display:none;}</style>';
	}

	public function create_new_version() : void {

		// If in backoffice, check for post edit via current screen.
		if ( ! is_admin() || ! get_current_screen()->base === 'post' ) {
			return;
		}

		if ( ( $_GET['post-version-action'] ?? '' ) !== 'create-new-version' ) {
			return;
		}

		$post_id = $_GET['post'];
		if ( ! ( get_post( $post_id ) instanceof WP_Post ) ) {
			return;
		}

		VersionInterface::create_new_version( $post_id );

		$new_url = remove_query_arg( 'post-version-action', $_SERVER['REQUEST_URI'] );

		// Redirect.
		nocache_headers();
		wp_safe_redirect( $new_url, 302, 'WordPress - Post Version' );
		exit;
	}

	public function edit_form_after_editor() : void {
		global $wp_meta_boxes;

		// If in backoffice, check for post edit via current screen.
		if ( ! is_admin() || ! get_current_screen()->base === 'post' ) {
			return;
		}

		$post_type = get_current_screen()->post_type;
		if ( ! Options::is_post_type_versioned( $post_type ) ) {
			return;
		}


		if (
			isset( $wp_meta_boxes[ $post_type ]['side']['core']['submitdiv'] )
			&& ! empty( $wp_meta_boxes[ $post_type ]['side']['core']['submitdiv'] )
		) {
			$wp_meta_boxes[ $post_type ]['side']['core']['submitdiv']['args']['__old_callback'] = $wp_meta_boxes[ $post_type ]['side']['core']['submitdiv']['callback'];
			$wp_meta_boxes[ $post_type ]['side']['core']['submitdiv']['callback']               = [ $this, 'publish_meta_box' ];
		}
	}

	public function publish_meta_box( $post, $args = array() ) : void {
		ob_start();
		$args['args']['__old_callback']( $post, $args );
		$content = ob_get_clean();

		$dom = new DOMDocument();
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $content );

		$version = Version::get( $post->ID );

		$html = sprintf(
			"<?xml encoding='utf-8' ?>
			<div id='post-version-version'>
				<b>%s</b>
			</div>",
			// TODO: translators note
			sprintf( __( 'Version: %s (%s)' ), $version->label(), $version->version() )
		);
		$this->add_html_to_dom( $dom, 'major-publishing-actions', $html, 'post-version-version' );


		if ( $post->post_status === 'publish' ) {
			$href  = htmlentities( add_query_arg( 'post-version-action', 'create-new-version', $_SERVER['REQUEST_URI'] ) );
			$value = __( 'New version', 'post_version' );

			$html = "<?xml encoding='utf-8' ?>
			<div id='post-version-new'>
				<a class='button button-primary button-large' href='{$href}'>{$value}</a>
			</div>";
			$this->add_html_to_dom( $dom, 'major-publishing-actions', $html, 'post-version-new' );

		} else if ( $post->post_status === 'unreleased' ) {
			$dom->getElementById( 'post-status-display' )->nodeValue = __( 'Unreleased', 'post_version' );
		}

		echo $dom->saveHTML();
	}

	private function add_html_to_dom( &$dom, $where_id, $html,$html_id ) : void {
		$new_html_dom = new DOMDocument();
		$new_html_dom->loadHTML( $html );
		$dom->getElementById( $where_id )->appendChild(
			$dom->importNode( $new_html_dom->getElementById( $html_id ), true )
		);
	}
}
