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
use Multilingual_Bridge\Admin\Post_Translation_Widget;
use Multilingual_Bridge\Integrations\ACF\ACF_Translation_Modal;
use Multilingual_Bridge\REST\WPML_REST_Fields;
use Multilingual_Bridge\REST\WPML_REST_Translation;
use Multilingual_Bridge\Translation\Translation_Manager;
use Multilingual_Bridge\Translation\Providers\DeepL_Provider;
use Multilingual_Bridge\Translation\Post_Change_Tracker;

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
	const PLUGIN_VERSION = '1.3.6';

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
		$this->init_translation_system();
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
	 * Initialize the translation system
	 *
	 * Registers translation providers and sync tracking.
	 * This is the central initialization point for the translation architecture.
	 *
	 * @since    1.4.0
	 * @access   private
	 */
	private function init_translation_system(): void {
		// Get Translation Manager instance.
		$translation_manager = Translation_Manager::instance();

		// Fire action to allow third-party plugins to register providers.
		do_action( 'multilingual_bridge_register_translation_providers', $translation_manager );

		// Register default DeepL provider.
		$deepl_provider = new DeepL_Provider();
		$translation_manager->register_provider( $deepl_provider );

		// Set DeepL as default provider if available.
		if ( $deepl_provider->is_available() ) {
			$translation_manager->set_default_provider( 'deepl' );
		}

		// Register post change tracking for translation sync.
		$post_change_tracker = new Post_Change_Tracker();
		$post_change_tracker->register_hooks();

		/**
		 * Fires after translation system is initialized
		 *
		 * Use this hook to:
		 * - Register custom translation providers
		 * - Customize ACF field translation behavior
		 *
		 * @param Translation_Manager $translation_manager Translation Manager instance
		 */
		do_action( 'multilingual_bridge_translation_system_init', $translation_manager );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 */
	private function define_admin_hooks(): void {

		add_action(
			'admin_enqueue_scripts',
			function () {
				// Only enqueue translation modal assets if Classic Editor is active.
				if ( $this->is_classic_editor_active() ) {
					$this->enqueue_entrypoint( 'multilingual-bridge-admin' );
				}
			},
			100
		);

		// Register Language Debug functionality
		$language_debug = new Language_Debug();
		$language_debug->register_hooks();

		// Register ACF Translation functionality
		$acf_translation = new ACF_Translation_Modal();
		$acf_translation->register_hooks();

		// Register Post Translation Widget
		$post_translation_widget = new Post_Translation_Widget();
		$post_translation_widget->register_hooks();

		// Central plugin init: WPML/ACF hidden meta sync workaround
		add_action(
			'wpml_pro_translation_completed',
			array( \Multilingual_Bridge\Helpers\WPML_Post_Helper::class, 'sync_acf_hidden_meta_after_translation' ),
			999
		);
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks(): void {

		// Register REST API fields for WPML language support
		$wpml_rest_fields = new WPML_REST_Fields();
		$this->loader->add_action( 'rest_api_init', $wpml_rest_fields, 'register_fields', 10, 1 );

		// Register REST API endpoints for translation
		$wpml_rest_translation = new WPML_REST_Translation();
		$this->loader->add_action( 'rest_api_init', $wpml_rest_translation, 'register_routes', 10, 1 );
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
	 * Check if Classic Editor is active
	 *
	 * Detects whether the current editing screen uses the Classic Editor
	 * or the Block Editor (Gutenberg). Translation modal only works with
	 * Classic Editor + ACF fields.
	 *
	 * @since 1.4.0
	 * @return bool True if Classic Editor is active, false if Block Editor
	 */
	private function is_classic_editor_active(): bool {
		global $post;

		// Not on an edit screen.
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		// Not on a post edit screen.
		if ( ! $screen || ! in_array( $screen->base, array( 'post', 'post-new' ), true ) ) {
			return false;
		}

		// Check if Classic Editor plugin is active and enabled for this post type.
		if ( function_exists( 'classic_editor_replace_block_editor' ) ) {
			// Classic Editor plugin controls the editor.
			return classic_editor_replace_block_editor();
		}

		// Check if Block Editor is explicitly disabled via filter.
		$use_block_editor = use_block_editor_for_post( $post );

		/**
		 * Filter whether translation modal should be enabled
		 *
		 * Allows overriding the automatic detection. Useful for custom
		 * editor implementations or specific post types.
		 *
		 * @param bool $enabled Whether translation modal is enabled
		 * @param \WP_Post|null $post Current post object
		 * @param \WP_Screen|null $screen Current admin screen
		 */
		return apply_filters(
			'multilingual_bridge_enable_translation_modal',
			! $use_block_editor,
			$post,
			$screen
		);
	}

	/**
	 * Enqueue a script entrypoint
	 *
	 * @param string              $entry Name of the entrypoint defined in webpack.config.js.
	 * @param array<string,mixed> $localize_data Array of associated data. See https://developer.wordpress.org/reference/functions/wp_localize_script/ .
	 */
	private function enqueue_entrypoint( string $entry, array $localize_data = array() ): void {
		$asset_file = MULTILINGUAL_BRIDGE_PATH . "build/{$entry}.asset.php";

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;
		if ( ! isset( $asset['dependencies'], $asset['version'] ) ) {
			return;
		}

		$js_file = MULTILINGUAL_BRIDGE_PATH . "build/{$entry}.js";

		if ( file_exists( $js_file ) ) {
			wp_enqueue_script(
				self::PLUGIN_NAME . "/{$entry}",
				MULTILINGUAL_BRIDGE_URL . "build/{$entry}.js",
				$asset['dependencies'],
				$asset['version'],
				true
			);

			// CRITICAL: Enqueue WordPress component styles for Classic Editor
			// Classic Editor doesn't load these by default, but we need them for Modal
			wp_enqueue_style( 'wp-components' );

			// Potentially add localize data
			if ( ! empty( $localize_data ) ) {
				wp_localize_script(
					self::PLUGIN_NAME . "/{$entry}",
					str_replace( '-', '_', self::PLUGIN_NAME ),
					$localize_data
				);
			}
		}

		$css_file = MULTILINGUAL_BRIDGE_PATH . "build/{$entry}.css";

		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				self::PLUGIN_NAME . "/{$entry}",
				MULTILINGUAL_BRIDGE_URL . "build/{$entry}.css",
				array(),
				$asset['version']
			);
		}
	}
}
