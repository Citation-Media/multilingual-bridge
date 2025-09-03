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
 * Version:           1.3.4
 * Author:            Justin Vogt, Citation Media
 * Author URI:        https://citation.media
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       multilingual-bridge
 * Domain Path:       /languages
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
function multilingual_bridge_activate(): void {
	Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function multilingual_bridge_deactivate(): void {
	Deactivator::deactivate();
}

/**
 * The code that runs during plugin uninstallation.
 */
function multilingual_bridge_uninstall(): void {
	Uninstallor::uninstall();
}

register_activation_hook( __FILE__, 'multilingual_bridge_activate' );
register_deactivation_hook( __FILE__, 'multilingual_bridge_deactivate' );
register_uninstall_hook( __FILE__, 'multilingual_bridge_uninstall' );
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
function multilingual_bridge_run(): void {
	// Check if WPML is installed
	if ( ! defined( 'ICL_SITEPRESS_VERSION' ) || ! class_exists( 'SitePress' ) ) {
		// Show admin notice
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

	$plugin = new Multilingual_Bridge();
	$plugin->run();
}

// Hook to wpml_loaded to ensure WPML is fully initialized
add_action( 'wpml_loaded', 'multilingual_bridge_run' );