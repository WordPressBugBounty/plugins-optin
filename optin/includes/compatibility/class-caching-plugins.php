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
		// Keep the frontend bundle and its WordPress dependencies in execution order.
		add_filter( 'rocket_delay_js_exclusions', array( __CLASS__, 'exclude_frontend_js' ) );
		add_filter( 'rocket_delay_js_exclusions', array( __CLASS__, 'exclude_inline_js' ) );
		add_filter( 'rocket_exclude_defer_js', array( __CLASS__, 'exclude_frontend_js' ) );
		add_filter( 'rocket_exclude_js', array( __CLASS__, 'exclude_frontend_js' ) );
		add_filter( 'rocket_defer_inline_exclusions', array( __CLASS__, 'exclude_inline_js' ) );

		// Optins are inserted by JavaScript, so their styles must survive RUCSS.
		add_filter( 'rocket_exclude_css', array( __CLASS__, 'exclude_frontend_css' ) );
		add_filter( 'rocket_rucss_external_exclusions', array( __CLASS__, 'exclude_frontend_css' ) );
		add_filter( 'rocket_rucss_inline_atts_exclusions', array( __CLASS__, 'exclude_inline_css' ) );
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

	/**
	 * Exclude scripts that must run before the Optin frontend bundle.
	 *
	 * @param array $excluded Existing exclusions.
	 * @return array
	 */
	public static function exclude_frontend_js( $excluded ) {
		return array_merge(
			(array) $excluded,
			array(
				'/wp-includes/js/jquery/jquery',
				'/wp-includes/js/dist/hooks',
				'/wp-includes/js/dist/i18n',
				'/wp-includes/js/dist/private-apis',
				'/wp-includes/js/dist/url',
				'/wp-includes/js/dist/api-fetch',
			)
		);
	}

	/**
	 * Keep Optin's inline data and bundle ahead of dependent execution.
	 *
	 * @param array $excluded Existing exclusions.
	 * @return array
	 */
	public static function exclude_inline_js( $excluded ) {
		return array_merge(
			(array) $excluded,
			array( 'var optn =', 'window._optn =', 'Optin: Fallback Init', 'wp.apiFetch.use(' )
		);
	}

	/**
	 * Preserve the Optin frontend stylesheet.
	 *
	 * @param array $excluded Existing exclusions.
	 * @return array
	 */
	public static function exclude_frontend_css( $excluded ) {
		$excluded[] = 'frontend/css/wowoptin-public.min.css';
		return $excluded;
	}

	/**
	 * Preserve the per-optin inline stylesheet generated in the footer.
	 *
	 * @param array $excluded Existing exclusions.
	 * @return array
	 */
	public static function exclude_inline_css( $excluded ) {
		$excluded[] = 'id="optin-block-styles"';
		return $excluded;
	}
}
