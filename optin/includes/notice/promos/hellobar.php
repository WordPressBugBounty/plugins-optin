<?php
/**
 * SURFACE: the hello bar in the React admin. Siblings: notice.php, plugin-meta.php.
 *
 * No `type` here — the design is fixed and lives in
 * react/components/HelloBar/index.js. This file is only data; PHP picks the
 * live entry and localizes it (Notice::get_hellobar_config()).
 *
 * - Dates are 'Y-m-d H:i e', Asia/Dhaka, `end` at 23:59.
 * - Entries are the same copy in consecutive windows, so the bar returns for
 *   anyone who dismissed it earlier. That only works if each `key` differs.
 * - `key` is also the dismissal transient (15 days) and the countdown's
 *   localStorage suffix. Renaming one un-dismisses the bar for those users.
 * - $config, $prefix, $brand_name etc. come from Notice::get_promos().
 */

use OPTN\Includes\Xpo;

defined( 'ABSPATH' ) || exit;

return array(

	/*
	 * Summer Sale 2026. One window — `key` is carried over verbatim from the
	 * pre-refactor hello bar, so anyone who already dismissed it stays
	 * dismissed. Add a second window (new `key`) to bring it back mid-campaign.
	 */
	array(
		'key'                => $prefix . '_helloBar_summer_sale_2026_123',
		'start'              => '2026-07-06 00:00 Asia/Dhaka',
		'end'                => '2026-08-01 23:59 Asia/Dhaka',
		'text'               => __( 'Summer Sale: Enjoy Up to 60% Off on', 'optin' ),
		'highlight'          => $brand_name . ' ' . __( 'Pro', 'optin' ),
		'countdown_duration' => 0, // Seconds; 0 hides the countdown.
		'url'                => Xpo::generate_utm_link(
			array(
				'config' => array(
					'source'   => $config['utm_source_hellobar'],
					'medium'   => 'summer-sale',
					'campaign' => $config['utm_campaign'],
				),
			)
		),
		'visibility'         => ! Xpo::is_lc_active(),
	),
	array(
		'key'                => $prefix . '_helloBar_summer_sale_2026_1234',
		'start'              => '2026-08-02 00:00 Asia/Dhaka',
		'end'                => '2026-08-16 23:59 Asia/Dhaka',
		'text'               => __( 'Summer Sale: Enjoy Up to 60% Off on', 'optin' ),
		'highlight'          => $brand_name . ' ' . __( 'Pro', 'optin' ),
		'countdown_duration' => 0, // Seconds; 0 hides the countdown.
		'url'                => Xpo::generate_utm_link(
			array(
				'config' => array(
					'source'   => $config['utm_source_hellobar'],
					'medium'   => 'summer-sale',
					'campaign' => $config['utm_campaign'],
				),
			)
		),
		'visibility'         => ! Xpo::is_lc_active(),
	),

);

/*
 * ---------------------------------------------------------------------------
 * BLANK — copy into the array above and uncomment.
 *
 * Add a SECOND entry with the same copy and a different `key` if the bar
 * should come back for people who dismissed the first window.
 * `countdown_duration` in seconds; 0 hides the timer.
 * ---------------------------------------------------------------------------
 *
 * array(
 *     'key'                => $prefix . '_helloBar_autumn_sale_2026_1',
 *     'start'              => '2026-09-01 00:00 Asia/Dhaka',
 *     'end'                => '2026-09-10 23:59 Asia/Dhaka',
 *     'text'               => __( 'Autumn Sale: Enjoy Up to 60% Off on', 'optin' ),
 *     'highlight'          => $brand_name . ' ' . __( 'Pro', 'optin' ),
 *     'countdown_duration' => 0,
 *     'url'                => Xpo::generate_utm_link(
 *         array(
 *             'config' => array(
 *                 'source'   => $config['utm_source_hellobar'],
 *                 'medium'   => 'autumn-sale',
 *                 'campaign' => $config['utm_campaign'],
 *             ),
 *         )
 *     ),
 *     'visibility'         => ! Xpo::is_lc_active(),
 * ),
 */
