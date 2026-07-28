<?php // phpcs:ignore

namespace OPTN\Includes\Utils;

use OPTN\Includes\Notice\Notice;
use OPTN\Includes\Xpo;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin Actions
 */
class PluginActions {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'plugin_action_links_' . OPTN_BASE, array( $this, 'plugin_action_links_callback' ) );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_settings_meta' ), 10, 2 );
	}

	/**
	 * Adds quick action links below the plugin name.
	 *
	 * @param array $links Default plugin action links.
	 * @return array Modified plugin action links.
	 */
	public function plugin_action_links_callback( $links ) {

		$setting_link                 = array();
		$setting_link['optn_options'] = '<a href="' . esc_url( admin_url( 'admin.php?page=wowoptin-optins' ) ) . '">' . esc_html__( 'Optins', 'optin' ) . '</a>';

		$upgrade_link = array();

		// Free user or expired license user.
		if ( ! defined( 'OPTN_PRO_VERSION' ) || Xpo::is_lc_expired() ) {

			$license_key = Utils::get_license_key() ?? '';

			if ( Xpo::is_lc_expired() ) {
				$text = esc_html__( 'Renew License', 'optin' );
				$url  = Xpo::get_renew_link();
			} else {

				$text = esc_html__( 'Upgrade to Pro', 'optin' );
				$url  = Xpo::generate_utm_link();

				// A live promo overrides the evergreen link. The promo data
				// lives in includes/notice/promos/plugin-meta.php; with nothing
				// live we keep the "Upgrade to Pro" link built above.
				$promo = Notice::get_active_promo( 'plugin-meta' );
				if ( $promo ) {
					$text = $promo['text'];
					$url  = $promo['url'];
				}
			}

			$link_color = Notice::config()['brand_color'];

			$upgrade_link['optn_pro'] = '<a style="color: ' . esc_attr( $link_color ) . '; font-weight: bold;" target="_blank" href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a>';
		}

		return array_merge( $setting_link, $links, $upgrade_link );
	}

	/**
	 * Adds extra links to the plugin row meta on the plugins page.
	 *
	 * @param array  $links Existing plugin meta links.
	 * @param string $file  Plugin file path.
	 * @return array Modified plugin meta links.
	 */
	public function plugin_settings_meta( $links, $file ) {
		if ( strpos( $file, 'optin.php' ) !== false ) {
			$new_links = array(

				'optn_docs'    => '<a target="_blank" href="https://wowoptin.com/docs/">' . esc_html__( 'Docs', 'optin' ) . '</a>',

				'optn_support' => '<a href="https://www.wpxpo.com/contact/" target="_blank">' . esc_html__( 'Support', 'optin' ) . '</a>',
			);
			$links     = array_merge( $links, $new_links );
		}
		return $links;
	}
}
