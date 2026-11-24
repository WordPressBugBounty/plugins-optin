<?php // phpcs:ignore

namespace OPTN\Includes;

use OPTN\Includes\Db;

/**
 * Fired during plugin activation
 *
 * @link       https://wpxpo.com
 * @since      1.0.0
 *
 * @package    optin
 * @subpackage optin/includes
 */

/**
 * Creates or upgrades the plugin's database tables and activation-time settings.
 *
 * Activation is delegated to the database and migration services so the plugin can
 * prepare its storage before admin and frontend components begin using it.
 *
 * @since      1.0.0
 * @package    optin
 * @subpackage optin/includes
 */
class Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
	}
}
