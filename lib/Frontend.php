<?php

namespace TwentySixB\WP\Plugin\PostVersion;

/**
 * The public-facing functionality of the plugin.
 *
 * @since 0.0.1
 */
class Frontend {

	/**
	 * The plugin's instance.
	 *
	 * @since  0.0.1
	 * @access private
	 * @var    Plugin
	 */
	private $plugin;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 0.0.1
	 * @param Plugin $plugin This plugin's instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Register hooks.
	 *
	 * @since 0.0.1
	 */
	public function register() {
		\add_action( 'init', [ $this, 'action_callback' ] );
	}

	/**
	 * My action callback.
	 *
	 * @since  0.0.1
	 * @return void
	 */
	public function action_callback() {}
}
