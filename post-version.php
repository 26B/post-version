<?php
/**
 * @wordpress-plugin
 * Plugin Name: Post Version
 * Plugin URI:  https://github.com/26B/post-version
 * Description: A WordPress Plugin to version your posts.
 * Version:     0.0.3
 * Author:      26B
 * Author URI:  https://26b.io/
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: post-version
 * Domain Path: /languages
 */

if ( file_exists( dirname( __FILE__ ) . '/vendor/autoload.php' ) ) {
	require_once dirname( __FILE__ ) . '/vendor/autoload.php';
}

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The code that runs during plugin activation.
 * This action is documented in lib/Activator.php
 */
\register_activation_hook( __FILE__, '\TwentySixB\WP\Plugin\PostVersion\Activator::activate' );

/**
 * The code that runs during plugin deactivation.
 * This action is documented in lib/Deactivator.php
 */
\register_deactivation_hook( __FILE__, '\TwentySixB\WP\Plugin\PostVersion\Deactivator::deactivate' );

/**
 * Begins execution of the plugin.
 *
 * @since 0.0.1
 */
\add_action( 'plugins_loaded', function () {
	$plugin = new TwentySixB\WP\Plugin\PostVersion\Plugin( 'post-version', '0.0.3' );
	$plugin->run();
} );
