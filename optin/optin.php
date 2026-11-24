<?php
/**
 * Plugin Name:       WowOptin - Popup Maker and Optin Forms
 * Description:       A WordPress Optin plugin helps capture visitor info through customizable forms to grow your email list and boost lead generation!
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Version:           1.5.7
 * Author:            WPXPO
 * Author URI:        https://wpxpo.com/
 * License:           GPLv3
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       optin
 * Domain Path:       /languages
 *
 * @package           optin
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}


define( 'OPTN_VERSION', '1.5.7' );
define( 'OPTN_BASE', plugin_basename( __FILE__ ) );
define( 'OPTN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OPTN_URL', plugin_dir_url( __FILE__ ) );
define( 'OPTN_MIN_CAPABILITY', 'edit_pages' );
define( 'OPTN_CACHE_DAY', 3 );
// PHP campaign-content extension contract; independent of product/release versions.
define( 'OPTN_EXTENSION_API_VERSION', 2 );

use OPTN\Includes\Activator;
use OPTN\Includes\Deactivator;
use OPTN\Includes\Init;

/**
 * The code that runs during plugin activation.
 */
function optn_activate_plugin() {
	Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 *
 * @param bool $network_wide Whether WordPress is deactivating the plugin network-wide.
 * @return void
 */
function optn_deactivate_plugin( $network_wide = false ) {
	Deactivator::deactivate( $network_wide );
}

register_activation_hook( __FILE__, 'optn_activate_plugin' );
register_deactivation_hook( __FILE__, 'optn_deactivate_plugin' );


/**
 * Autoloader function
 *
 * @param string $class_name class name.
 * @return void
 */
function optn_autoloader( $class_name ) {
	$namespace = 'OPTN\\';
	$base_dir  = OPTN_DIR;

	$len = strlen( $namespace );
	if ( strncmp( $namespace, $class_name, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class_name, $len );

	$segments  = explode( '\\', $relative_class );
	$file_name = array_pop( $segments );
	$subfolder = strtolower( implode( '/', $segments ) );

	$prefix = ( strpos( $subfolder, 'traits' ) !== false ) ? 'trait-' : 'class-';

	$file_name = strtolower(
		preg_replace( '/([a-z])([A-Z])/', '$1-$2', $file_name )
	);

	$file = rtrim( $base_dir . $subfolder, '/' ) . '/' . $prefix . $file_name . '.php';

	if ( file_exists( $file ) ) {
		require_once $file;
		return;
	}
}

spl_autoload_register( 'optn_autoloader' );


/**
 * Begins execution of the plugin.
 *
 * @since    1.0.0
 */
function optn_run_plugin() {
	if ( ! apply_filters( 'optn_should_initialize', true ) ) {
		return;
	}
	$plugin = new Init();
	$plugin->run();
	do_action( 'optn_runtime_initialized', 'free', $plugin );
}

add_action( 'plugins_loaded', 'optn_run_plugin', 20 );
add_filter( 'script_loader_tag', array( '\\OPTN\\Includes\\Utils\\AssetConfig', 'cache_attributes' ), 10, 2 );
