<?php

namespace TwentySixB\WP\Plugin\PostVersion;

use TwentySixB\WP\Plugin\PostVersion\Hooks\Admin;
use TwentySixB\WP\Plugin\PostVersion\Hooks\Query;
use TwentySixB\WP\Plugin\PostVersion\Hooks\Revision;
use TwentySixB\WP\Plugin\PostVersion\Hooks\Status;
use TwentySixB\WP\Plugin\PostVersion\Hooks\Version;
use WP_CLI;

/**
 * The core plugin class.
 *
 * This is used to define internationalization, dashboard-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since 0.0.1
 */
class Plugin {

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since  0.0.1
	 * @access protected
	 * @var    string
	 */
	protected $name;

	/**
	 * The current version of the plugin.
	 *
	 * @since  0.0.1
	 * @access protected
	 * @var    string
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * @since 0.0.1
	 * @param string $name    The plugin identifier.
	 * @param string $version Current version of the plugin.
	 */
	public function __construct( $name, $version ) {
		$this->name    = $name;
		$this->version = $version;
	}

	/**
	 * Run the loader to execute all the hooks with WordPress.
	 *
	 * Load the dependencies, define the locale, and set the hooks for the
	 * Dashboard and the public-facing side of the site.
	 *
	 * @since 0.0.1
	 */
	public function run() {
		$this->set_locale();
		$this->define_plugin_hooks();
		$this->define_frontend_hooks();
		$this->define_cli_commands();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since  0.0.1
	 * @return string The name of the plugin.
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Returns the version number of the plugin.
	 *
	 * @since  0.0.1
	 * @return string The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the I18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since  0.0.1
	 * @access private
	 */
	private function set_locale() {
		$i18n = new I18n();
		$i18n->set_domain( $this->get_name() );
		$i18n->load_plugin_textdomain();
	}

	/**
	 * Register all of the hooks related to the general functionality of the plugin.
	 *
	 * @since  0.0.1
	 * @access private
	 */
	private function define_plugin_hooks() {
		$components = [
			'admin'    => new Admin( $this ),
			'version'  => new Version( $this ),
			'revision' => new Revision( $this ),
			'status'   => new Status( $this ),
			'query'    => new Query( $this ),
		];

		foreach ( $components as $component ) {
			$component->register();
		}
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since  0.0.1
	 * @access private
	 */
	private function define_frontend_hooks() {

		$components = [
			'frontend' => new Frontend( $this ),
		];

		foreach ( $components as $component ) {
			$component->register();
		}
	}

	/**
	 * Define CLI commands.
	 *
	 * @since 0.0.3
	 *
	 * @return void
	 */
	private function define_cli_commands() : void {
		$commands = [
			CLI\Version::class => 'post-version',
		];
		\add_action( 'init', function () use ( $commands ) {
			foreach ( $commands as $cli_class => $command_name ) {
				if ( class_exists( 'WP_CLI' ) ) {
					WP_CLI::add_command( $command_name, $cli_class );
				}
			}
		} );
	}
}
