<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://justin-vogt.com
 * @since             1.0.0
 * @package           Multilingual_Bridge
 *
 * @wordpress-plugin
 * Plugin Name:       Multilingual Bridge
 * Description:       Bridges the gap between WPML and WordPress REST API, adding comprehensive multilingual support for modern WordPress applications.
 * Version:           1.1.1
 * Author:            Justin Vogt, Citation Media
 * Author URI:        https://citation.media
 * Requires PHP:      8.0
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       multilingual-bridge
 * Domain Path:       /languages
 * Requires Plugins: sitepress-multilingual-cms
 */

// If this file is called directly, abort.
use Multilingual_Bridge\Activator;
use Multilingual_Bridge\Deactivator;
use Multilingual_Bridge\Multilingual_Bridge;
use Multilingual_Bridge\Uninstallor;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Plugin absolute path
 */
define( 'MULTILINGUAL_BRIDGE_PATH', plugin_dir_path( __FILE__ ) );
define( 'MULTILINGUAL_BRIDGE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Use Composer PSR-4 Autoloading
 */
require plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

/**
 * The code that runs during plugin activation.
 */
function activate_multilingual_bridge(): void {
	Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_multilingual_bridge(): void {
	Deactivator::deactivate();
}

/**
 * The code that runs during plugin uninstallation.
 */
function uninstall_multilingual_bridge(): void {
	Uninstallor::uninstall();
}

register_activation_hook( __FILE__, 'activate_multilingual_bridge' );
register_deactivation_hook( __FILE__, 'deactivate_multilingual_bridge' );
register_uninstall_hook( __FILE__, 'uninstall_multilingual_bridge' );
add_action( 'activated_plugin', array( Activator::class, 'network_activation' ), 10, 2 );

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_multilingual_bridge(): void {
	$plugin = new Multilingual_Bridge();
	$plugin->run();
}

/**
 * Initialize the plugin after WPML is loaded.
 *
 * @since    1.1.2
 */
function init_multilingual_bridge(): void {
	// Check if WPML is active
	if ( ! defined( 'ICL_SITEPRESS_VERSION' ) || ! class_exists( 'SitePress' ) ) {
		// WPML is not active, show admin notice
		add_action(
			'admin_notices',
			function () {
				?>
			<div class="notice notice-error">
				<p><?php esc_html_e( 'Multilingual Bridge requires WPML to be installed and activated.', 'multilingual-bridge' ); ?></p>
			</div>
				<?php
			}
		);
		return;
	}

	// Use wpml_loaded hook if available for better compatibility
	if ( did_action( 'wpml_loaded' ) ) {
		// WPML is already loaded, initialize immediately
		run_multilingual_bridge();
	} else {
		// Wait for WPML to fully load
		add_action( 'wpml_loaded', 'run_multilingual_bridge' );
	}
}

// Hook into plugins_loaded with priority 11 to ensure WPML loads first (at priority 10)
add_action( 'plugins_loaded', 'init_multilingual_bridge', 11 );