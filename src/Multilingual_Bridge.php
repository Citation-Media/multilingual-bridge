<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Multilingual_Bridge
 */

namespace Multilingual_Bridge;

use Multilingual_Bridge\Admin\Language_Debug;
use Multilingual_Bridge\REST\WPML_REST_Fields;

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Multilingual_Bridge
 * @author     Justin Vogt <mail@juvo-design.de>
 */
class Multilingual_Bridge {


	const PLUGIN_NAME    = 'multilingual-bridge';
	const PLUGIN_VERSION = '1.3.2';

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin
	 *
	 * @var Loader
	 */
	protected Loader $loader;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {

		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies(): void {

		$this->loader = new Loader();
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 */
	private function define_admin_hooks(): void {

		add_action(
			'admin_enqueue_scripts',
			function () {
				$this->enqueue_bud_entrypoint( 'multilingual-bridge-admin' );
			},
			100
		);

		// Register Language Debug functionality
		$language_debug = new Language_Debug();
		$language_debug->register_hooks();
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks(): void {
		// Frontend scripts are currently disabled.
		// Uncomment the following code to enable frontend assets:

		/*
		Add_action(
			'wp_enqueue_scripts',
			function () {
				$this->enqueue_bud_entrypoint( 'multilingual-bridge-frontend' );
			},
			100
		);
		*/

		// Register REST API fields for WPML language support
		$wpml_rest_fields = new WPML_REST_Fields();
		$this->loader->add_action( 'rest_api_init', $wpml_rest_fields, 'register_fields', 10, 1 );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 */
	public function run(): void {
		$this->loader->run();
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @return Loader Orchestrates the hooks of the plugin.
	 */
	public function get_loader(): Loader {
		return $this->loader;
	}

	/**
	 * Enqueue a bud entrypoint
	 *
	 * @param string              $entry Name if the entrypoint defined in bud.js .
	 * @param array<string,mixed> $localize_data Array of associated data. See https://developer.wordpress.org/reference/functions/wp_localize_script/ .
	 */
	private function enqueue_bud_entrypoint( string $entry, array $localize_data = array() ): void {
		$entrypoints_manifest = MULTILINGUAL_BRIDGE_PATH . '/dist/entrypoints.json';

		// Try to get WordPress filesystem. If not possible load it.
		global $wp_filesystem;
		if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php'; // @phpstan-ignore requireOnce.fileNotFound
			WP_Filesystem();
		}

		$filesystem = new \WP_Filesystem_Direct( false );
		if ( ! $filesystem->exists( $entrypoints_manifest ) ) {
			return;
		}

		// parse json file
		$entrypoints = json_decode( $filesystem->get_contents( $entrypoints_manifest ) );

		// Iterate entrypoint groups
		foreach ( $entrypoints as $key => $bundle ) {

			// Only process the entrypoint that should be enqueued per call
			if ( $key !== $entry ) {
				continue;
			}

			// Iterate js and css files
			foreach ( $bundle as $type => $files ) {
				foreach ( $files as $file ) {
					if ( 'js' === $type ) {
						wp_enqueue_script(
							self::PLUGIN_NAME . "/$file",
							MULTILINGUAL_BRIDGE_URL . 'dist/' . $file,
							$bundle->dependencies ?? array(),
							self::PLUGIN_VERSION,
							true,
						);

						// Maybe localize js
						if ( ! empty( $localize_data ) ) {
							wp_localize_script( self::PLUGIN_NAME . "/$file", str_replace( '-', '_', self::PLUGIN_NAME ), $localize_data );

							// Unset after localize since we only need to localize one script per bundle so on next iteration will be skipped
							unset( $localize_data );
						}
					}

					if ( 'css' === $type ) {
						wp_enqueue_style(
							self::PLUGIN_NAME . "/$file",
							MULTILINGUAL_BRIDGE_URL . 'dist/' . $file,
							array(),
							self::PLUGIN_VERSION,
						);
					}
				}
			}
		}
	}
}
