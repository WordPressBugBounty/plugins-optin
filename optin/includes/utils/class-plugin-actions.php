<?php // phpcs:ignore

namespace OPTN\Includes\Utils;

use OPTN\Includes\Xpo;

defined( 'ABSPATH' ) || exit;

/**
 * Adds plugin-specific links to its row on the WordPress Plugins screen.
 *
 * Registers callbacks for the action-link and metadata-link filters so administrators
 * can reach the plugin settings and related product information from the plugin list.
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
		$links['optn_options'] = '<a href="' . esc_url( admin_url( 'admin.php?page=wowoptin-optins' ) ) . '">' . esc_html__( 'Optins', 'optin' ) . '</a>';
		$links['optn_pro']     = '<a target="_blank" href="https://www.wowoptin.com/#pricing">' . esc_html__( 'Upgrade to Pro', 'optin' ) . '</a>';
		return apply_filters( 'optn_plugin_action_links', $links );
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
