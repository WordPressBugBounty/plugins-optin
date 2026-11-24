<?php
/**
 * Clears plugin-owned scheduled events when the plugin is deactivated.
 *
 * @package Optin
 */

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
 * Performs plugin cleanup when WordPress deactivates the plugin.
 *
 * Removes the scheduled database-cleanup event only when doing so will not disrupt a
 * site where the plugin remains active during a network-wide deactivation.
 *
 * @since      1.0.0
 * @package    optin
 * @subpackage optin/includes
 */
class Deactivator {

	/**
	 * Clear the recurring cleanup event after checking site or network activation state.
	 *
	 * For network deactivation, checks each site and preserves the event while the plugin
	 * remains active on that site. The cleanup filter can prevent scheduled-hook removal.
	 *
	 * @param bool $network_wide Whether WordPress is deactivating the plugin network-wide.
	 * @return void
	 * @since    1.0.0
	 */
	public static function deactivate( $network_wide = false ) {
		if ( ! apply_filters( 'optn_should_cleanup', true, 'free' ) ) {
			return;
		}
		if ( $network_wide && is_multisite() ) {
			foreach ( get_sites(
				array(
					'fields'     => 'ids',
					'number'     => 0,
					'network_id' => get_current_network_id(),
				)
			) as $site_id ) {
				switch_to_blog( (int) $site_id );
				try {
					if ( ! in_array( OPTN_BASE, (array) get_option( 'active_plugins', array() ), true ) ) {
						wp_clear_scheduled_hook( 'optn_clean_db' );
					}
				} finally {
					restore_current_blog();
				}
			}
			return;
		}
		wp_clear_scheduled_hook( 'optn_clean_db' );
	}
}
