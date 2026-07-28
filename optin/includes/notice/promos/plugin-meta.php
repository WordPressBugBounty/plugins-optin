<?php
/**
 * SURFACE: the plugins-list row link. Siblings: notice.php, hellobar.php.
 *
 * Smallest surface: a label plus a URL, no template and no dismissal, so one
 * entry per campaign covering the whole run is normal.
 *
 * Entries are OVERRIDES — with nothing live the row falls back to the evergreen
 * "Upgrade to Pro" link built by the plugin's plugin_action_links handler.
 * `key` exists only to satisfy Notice::promo_is_live(); nothing is stored.
 *
 * - Dates are 'Y-m-d H:i e', Asia/Dhaka, `end` at 23:59.
 * - $config, $prefix, $brand_name etc. come from Notice::get_promos().
 */

use OPTN\Includes\Xpo;

defined( 'ABSPATH' ) || exit;

return array(

	// Summer Sale 2026 — one flat window for the whole campaign.
	array(
		'key'        => $prefix . '_plugin_meta_summer_sale_2026',
		'start'      => '2026-07-06 00:00 Asia/Dhaka',
		'end'        => '2026-08-16 23:59 Asia/Dhaka',
		'text'       => __( 'Summer Sale - Up to 60% OFF', 'optin' ),
		'url'        => Xpo::generate_utm_link(
			array(
				'config' => array(
					'source'   => $config['utm_source_plugin_meta'],
					'medium'   => 'summer-sale',
					'campaign' => $config['utm_campaign'],
				),
			)
		),
		'visibility' => ! Xpo::is_lc_active(),
	),

);

/*
 * ---------------------------------------------------------------------------
 * BLANK — copy into the array above and uncomment.
 *
 * `text` is the whole link label, so keep it short. With nothing live the row
 * falls back to the evergreen "Upgrade to Pro" link the plugin builds itself.
 * ---------------------------------------------------------------------------
 *
 * array(
 *     'key'        => $prefix . '_plugin_meta_autumn_sale_2026',
 *     'start'      => '2026-09-01 00:00 Asia/Dhaka',
 *     'end'        => '2026-09-10 23:59 Asia/Dhaka',
 *     'text'       => __( 'Autumn Sale - Up to 60% OFF', 'optin' ),
 *     'url'        => Xpo::generate_utm_link(
 *         array(
 *             'config' => array(
 *                 'source'   => $config['utm_source_plugin_meta'],
 *                 'medium'   => 'autumn-sale',
 *                 'campaign' => $config['utm_campaign'],
 *             ),
 *         )
 *     ),
 *     'visibility' => ! Xpo::is_lc_active(),
 * ),
 */
