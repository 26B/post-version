<?php

namespace TwentySixB\WP\Plugin\PostVersion\Hooks;

use DOMDocument;
use TwentySixB\WP\Plugin\PostVersion\Options;
use TwentySixB\WP\Plugin\PostVersion\Version;
use TwentySixB\WP\Plugin\PostVersion\VersionInterface;
use WP_Post;

/**
 * Hooks for admin/back-office of WordPress.
 *
 * @since 0.0.1
 */
class Admin {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function register() : void {

		// Hide post status selection when unreleased.
		add_action( 'admin_print_styles-post.php', [ $this, 'hide_post_status_selection' ] );

		// Handle create new version action.
		add_action( 'current_screen', [ $this, 'create_new_version' ], PHP_INT_MAX );

		// Edit the callback for the submit meta box.
		add_action( 'edit_form_after_editor', [ $this, 'edit_form_after_editor' ], PHP_INT_MAX );
	}

	/**
	 * Hide post status selection when unreleased.
	 *
	 * @since 0.0.1
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

	/**
	 * Handle create new version action.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function create_new_version() : void {

		// If in backoffice, check for post edit via current screen.
		if ( ! is_admin() || ! get_current_screen()->base === 'post' ) {
			return;
		}

		// Check url query for action is set.
		if ( ( $_GET['post-version-action'] ?? '' ) !== 'create-new-version' ) {
			return;
		}

		// Check post_id exists.
		$post_id = $_GET['post'];
		if ( ! ( get_post( $post_id ) instanceof WP_Post ) ) {
			return;
		}

		// Try to create new version.
		$status = VersionInterface::create_new_version( $post_id );

		// TODO: show status as a notice somehow.

		// Redirect back to the original url without the action.
		$new_url = remove_query_arg( 'post-version-action', $_SERVER['REQUEST_URI'] );
		nocache_headers();
		wp_safe_redirect( $new_url, 302, 'WordPress - Post Version' );
		exit;
	}

	/**
	 * Edit the callback for the submit meta box.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function edit_form_after_editor() : void {
		global $wp_meta_boxes;

		// If in backoffice, check for post edit via current screen.
		if ( ! is_admin() || ! get_current_screen()->base === 'post' ) {
			return;
		}


		// Check if the current screen is for a post_type that is versioned.
		$post_type = get_current_screen()->post_type;
		if ( ! Options::is_post_type_versioned( $post_type ) ) {
			return;
		}

		// TODO: search all the meta_boxes (deep search) to find where submitdiv is.

		$side_key = null;
		foreach ( [ 'core', 'sorted' ] as $meta_box_key ) {
			if (
				isset( $wp_meta_boxes[ $post_type ]['side'][ $meta_box_key ]['submitdiv'] )
				&& ! empty( $wp_meta_boxes[ $post_type ]['side'][ $meta_box_key ]['submitdiv'] )
			) {
				$side_key = $meta_box_key;
			}
		}

		// Alter the callback for the submit metabox to add PostVersion buttons.
		if ( $side_key ) {
			$wp_meta_boxes[ $post_type ]['side'][ $side_key ]['submitdiv']['args']['__old_callback'] = $wp_meta_boxes[ $post_type ]['side'][ $side_key ]['submitdiv']['callback'];
			$wp_meta_boxes[ $post_type ]['side'][ $side_key ]['submitdiv']['callback']               = [ $this, 'submit_meta_box' ];
		}
	}

	/**
	 * Alter the output for the original submit callback to include.
	 *
	 * @since 0.0.1
	 *
	 * @param $post
	 * @param $args
	 * @return void
	 */
	public function submit_meta_box( $post, $args = array() ) : void {

		// Get output from original callback.
		ob_start();
		$args['args']['__old_callback']( $post, $args );
		$content = ob_get_clean();

		// Load html into DOMDocument.
		$dom = new DOMDocument();
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $content );

		// Get post version.
		$version = Version::get( $post->ID );

		// If there's not a version, add warning.
		if ( $version === null ) {
			// TODO: Add warning css.
			$html = sprintf(
				"<?xml encoding='utf-8' ?>
				<div id='post-version-version'>
					<b>%s</b>
				</div>",
				__( 'Does not have a version yet. Save post to set version 1.' )
			);
			$this->add_html_to_dom( $dom, 'major-publishing-actions', $html, 'post-version-version' );

			// Output html.
			echo $dom->saveHTML();
			return;
		}

		// Add version info html to the box.
		$html = sprintf(
			"<?xml encoding='utf-8' ?>
			<div id='post-version-version'>
				<b>%s</b>
			</div>",
			/* translators: 1: Version label, 2: Version number */
			sprintf( __( 'Version: %1$s (%2$s)' ), $version->label(), $version->version() )
		);
		$this->add_html_to_dom( $dom, 'major-publishing-actions', $html, 'post-version-version' );

		// Add new version button when post is published.
		if ( $post->post_status === 'publish' ) {
			$href  = htmlentities( add_query_arg( 'post-version-action', 'create-new-version', $_SERVER['REQUEST_URI'] ) );
			$value = __( 'New version', 'post_version' );

			$html = "<?xml encoding='utf-8' ?>
			<div id='post-version-new'>
				<a class='button button-primary button-large' href='{$href}'>{$value}</a>
			</div>";
			$this->add_html_to_dom( $dom, 'major-publishing-actions', $html, 'post-version-new' );

			// Fix status label when post is unreleased.
		} else if ( $post->post_status === 'unreleased' ) {
			$dom->getElementById( 'post-status-display' )->nodeValue = __( 'Unreleased', 'post_version' );
		}

		// Output html.
		echo $dom->saveHTML();
	}

	/**
	 * Add html to a DOMDocument as a child to an element.
	 *
	 * @since 0.0.1
	 *
	 * @param DOMDocument &$dom
	 * @param string       $where_id
	 * @param string       $html
	 * @param string       $html_id
	 * @return void
	 */
	private function add_html_to_dom( DOMDocument &$dom, string $where_id, string $html, string $html_id ) : void {
		$new_html_dom = new DOMDocument();
		$new_html_dom->loadHTML( $html );
		$dom->getElementById( $where_id )->appendChild(
			$dom->importNode( $new_html_dom->getElementById( $html_id ), true )
		);
	}
}
