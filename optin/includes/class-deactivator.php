<?php

namespace OPTN\Includes;

/**
 * Fired during plugin deactivation
 *
 * @link       https://wpxpo.com
 * @since      1.0.0
 *
 * @package    optin
 * @subpackage optin/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    optin
 * @subpackage optin/includes
 */
class Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		$timestamp = wp_next_scheduled( 'optn_clean_db' );
		wp_unschedule_event( $timestamp, 'optn_clean_db' );
	}
}
