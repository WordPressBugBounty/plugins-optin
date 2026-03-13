<?php // phpcs:ignore

namespace OPTN\Includes\Compatibility;

defined( 'ABSPATH' ) || exit;

/**
 * Class CachingPlugins
 */
class CachingPlugins {

	/**
	 * Init hooks
	 *
	 * @return void
	 */
	public static function init() {
		add_action(
			'optn_compat_purge_cache',
			function () {

				// Litespeed.
				// ISSUE:OPT-106 Disabling for now.
				// do_action( 'litespeed_purge_all' );

				// WP Rocket.
				if ( function_exists( 'rocket_clean_domain' ) ) {
					rocket_clean_domain();
				}

				// W3 Total Cache.
				if ( function_exists( 'w3tc_flush_all' ) ) {
					w3tc_flush_all();
				}
			}
		);
	}
}
