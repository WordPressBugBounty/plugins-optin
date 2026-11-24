<?php // phpcs:ignore

namespace OPTN\Admin;

use OPTN\Includes\Compatibility\CachingPlugins;
use OPTN\Includes\Db;
use OPTN\Includes\Settings;
use OPTN\Includes\Utils\PluginActions;
use OPTN\Includes\Utils\Utils;
use OPTN\Includes\Xpo;

/**
 * Registers administration hooks, screens, assets, and editor integrations.
 *
 * Connects WordPress admin lifecycle events to plugin settings, campaign editor assets,
 * notices, and compatibility behavior required by the administration interface.
 *
 * @link       https://wpxpo.com
 * @since      1.0.0
 *
 * @package    optin
 * @subpackage optin/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    optin
 * @subpackage optin/admin
 */
class Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $plugin_name The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $version The current version of this plugin.
	 */
	private $version;


	/**
	 * Initialize the class and set its properties.
	 *
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version The version of this plugin.
	 *
	 * @since    1.0.0
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		Settings::init_settings( '1.0.0' );

		$this->init_custom_hooks();
		// Cron schedules can load translations in other plugins.
		add_action( 'init', array( $this, 'init_scheduled_tasks' ) );

		new PluginActions();
		CachingPlugins::init();
		// Keep the custom admin notice removal hook disabled.
	}

	/**
	 * Remove All Notification From Menu Page
	 *
	 * @return void
	 */
	public function remove_all_notices() {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash($_GET['page']) ) : ''; // phpcs:ignore
		if ( strpos( $page, 'wowoptin' ) !== false ) {
			remove_all_actions( 'admin_notices' );
			remove_all_actions( 'all_admin_notices' );
			remove_all_actions( 'in_admin_header' );
		}
	}

	/**
	 * Initilizes custom hooks.
	 *
	 * @return void
	 */
	public function init_custom_hooks() {
		add_action(
			'admin_footer',
			function () {
				$data = apply_filters( 'optn_activate_recipes', array() );
				if ( is_array( $data ) && count( $data ) > 0 ) {
					foreach ( $data as $d ) {
						Db::get_instance()->activate_recipe( $d['id'], $d['args'] );
					}
				}
			}
		);
	}

	/**
	 * Init scheduled tasks
	 *
	 * @return void
	 */
	public function init_scheduled_tasks() {
		if ( ! wp_next_scheduled( 'optn_clean_db' ) ) {
			wp_schedule_event( time(), 'weekly', 'optn_clean_db' );
		}

		add_action(
			'optn_clean_db',
			function () {
				Db::get_instance()->clear_interactions( 30 );
			}
		);
	}

	/**
	 * Admin scripts and styles
	 *
	 * @return void
	 */
	public function admin_head() {}


	/**
	 * Register all routes for admin
	 *
	 * @return void
	 */
	public function register_routes() {
		new RestAdmin();
	}

	/**
	 * Add admin menu pages
	 *
	 * @return void
	 */
	public function add_menu_page() {

		// Main Menu.
		add_menu_page(
			__( 'WowOptin', 'optin' ),
			__( 'WowOptin', 'optin' ),
			OPTN_MIN_CAPABILITY,
			'wowoptin',
			'',
			OPTN_URL . '/assets/images/logo.svg',
			26
		);

		$submenus = array(
			array(
				'title' => __( 'WowOptin Dashboard', 'optin' ),
				'menu'  => __( 'Dashboard', 'optin' ),
				'slug'  => 'wowoptin-dashboard',
			),
			array(
				'title' => __( 'WowOptin Optins', 'optin' ),
				'menu'  => __( 'Optins', 'optin' ),
				'slug'  => 'wowoptin-optins',
			),
			array(
				'title' => __( 'WowOptin Templates', 'optin' ),
				'menu'  => __( 'Templates', 'optin' ),
				'slug'  => 'wowoptin-templates',
			),
			array(
				'title' => __( 'WowOptin Leads', 'optin' ),
				'menu'  => __( 'Leads', 'optin' ),
				'slug'  => 'wowoptin-leads',
			),
			array(
				'title' => __( 'WowOptin A/B Testing', 'optin' ),
				'menu'  => __( 'A/B Testing', 'optin' ),
				'slug'  => 'wowoptin-ab-test',
			),
			array(
				'title' => __( 'WowOptin Integrations', 'optin' ),
				'menu'  => __( 'Integrations', 'optin' ),
				'slug'  => 'wowoptin-integrations',
			),
			array(
				'title' => __( 'WowOptin Settings', 'optin' ),
				'menu'  => __( 'Settings', 'optin' ),
				'slug'  => 'wowoptin-settings',
			),
			array(
				'title' => __( 'WPXPO Plugins', 'optin' ),
				'menu'  => __( 'WPXPO Plugins', 'optin' ),
				'slug'  => 'wowoptin-wpxpo-plugins',
			),
			array(
				'title' => __( 'WowOptin Pro Features', 'optin' ),
				'menu'  => __( 'Pro Features', 'optin' ),
				'slug'  => 'wowoptin-pro-features',
			),
			array(
				'title' => __( 'WowOptin Builder', 'optin' ),
				'menu'  => __( 'Builder', 'optin' ),
				'slug'  => 'wowoptin-builder', // DO NOT CHANGE.
			),
		);

		$submenus = apply_filters( 'optn_admin_submenus', $submenus );
		foreach ( $submenus as $i => $submenu ) {
			if ( ! empty( $submenu['slug'] ) ) {
				add_submenu_page(
					'wowoptin',
					$submenu['title'],
					$submenu['menu'],
					in_array( $submenu['slug'], array( 'wowoptin-settings', 'wowoptin-integrations' ), true ) ? 'manage_options' : OPTN_MIN_CAPABILITY,
					$submenu['slug'],
					function () {},
					$i
				);
			}
		}

		remove_submenu_page( 'wowoptin', 'wowoptin' );
	}


	/**
	 * Add dropdown html
	 *
	 * @return void
	 */
	public function add_dropdown_html() {
		?>
			<div id='optn-dropdown' style='display:none;' className='optn-portal'></div>
		<?php

		if ( isset( $_GET['page'] ) && 'wowoptin-builder' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			?>
				<div id="optn-builder-placeholder"></div>
			<?php
		}
	}


	/**
	 * Check if in plugin's menu page
	 *
	 * @return boolean
	 */
	public function is_in_menu_page() {
		return isset( $_GET['page'] ) && strpos( wp_unslash( $_GET['page'] ), 'wowoptin' ) !== false; // phpcs:ignore
	}

	/**
	 * Enqueue styles
	 *
	 * @return void
	 */
	public function enqueue_styles() {

		$inline_css = '
			#toplevel_page_wowoptin li:has(a[href="admin.php?page=wowoptin-builder"]), 
			#toplevel_page_wowoptin li:has(a[href="admin.php?page=wowoptin-pro-features"]) {
				display:none;
			} 
			#toplevel_page_wowoptin .wp-menu-image img {
				width:20px;
				height:20px;
				padding: 7px 0 0;
			}
			#toplevel_page_wowoptin .wp-submenu a:hover {
				color: #f97415 !important;
			}
			#toplevel_page_wowoptin .wp-submenu a[href*="wowoptin-pro"] {
				display:none;
			}
			#toplevel_page_wowoptin .wp-submenu a.optn-pro-link {
				display: flex;
				align-items: center;
				gap: 5px;
				color: white !important;
				background-color: #f97415 !important;
				margin-inline: 10px;
				border-radius: 6px;
				margin-top: 6px;
				padding-block: 6px;
				box-shadow: none;
				white-space: nowrap;
				transition: background-color 200ms ease;
			}
			#toplevel_page_wowoptin .wp-submenu li.current a {
				color: #f97415 !important;
			}
			#toplevel_page_wowoptin .wp-submenu a.optn-pro-link:hover {
				background-color: #ea5a0c !important;
				color: white !important;
			}
		';

		wp_add_inline_style( 'wp-admin', $inline_css );

		if ( ! $this->is_in_menu_page() ) {
			return;
		}

		$config = \OPTN\Includes\Utils\AssetConfig::get( 'admin' );
		foreach ( $config['styles'] as $style ) {
			wp_enqueue_style( $style['handle'], $style['src'], $style['dependencies'], $style['version'] ); }
	}

	/**
	 * Enqueue scripts
	 *
	 * @return void
	 */
	public function enqueue_scripts() {

		if ( ! $this->is_in_menu_page() ) {
			return;
		}

		$config = \OPTN\Includes\Utils\AssetConfig::enqueue( 'admin' );
		wp_enqueue_media();
		$currency_symbol = Utils::get_currency_symbol();

		$user = get_userdata( get_current_user_id() );

		wp_localize_script(
			$config['script']['handle'],
			'optnAdmin',
			apply_filters(
				'optn_admin_localized_payload',
				array(
					'links'          => array(
						'adminUrl'       => admin_url( 'post-new.php' ),
						'templateUrl'    => admin_url( 'admin.php?page=wowoptin-templates' ),
						'builderUrl'     => admin_url( 'admin.php?page=wowoptin-builder' ),
						'dashboardUrl'   => admin_url( 'admin.php?page=wowoptin-dashboard' ),
						'integrationUrl' => admin_url( 'admin.php?page=wowoptin-integrations' ),
						'supportUrl'     => admin_url( 'admin.php?page=wowoptin-support' ),
						'optinsUrl'      => admin_url( 'admin.php?page=wowoptin-optins' ),
						'homeUrl'        => get_home_url(),
						'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
						'assetUrl'       => OPTN_URL . 'assets',
					),

					'userInfo'       => array(
						'name'  => $user->user_firstname ? $user->user_firstname . ( $user->user_lastname ? ' ' . $user->user_lastname : '' ) : $user->display_name,
						'email' => $user->user_email,
					),

					'nonce'          => wp_create_nonce( 'optin-nonce' ),
					'restNonce'      => wp_create_nonce( 'wp_rest' ),
					'asset_dir'      => OPTN_DIR . 'assets',
					'server_time'    => date_default_timezone_get(),
					'currencySymbol' => $currency_symbol,
					'curr_user'      => wp_get_current_user()->display_name,
					'dev_mode'       => defined( 'OPTN_DEV_MODE' ) && OPTN_DEV_MODE ? 'true' : 'false',
					'settings'       => Settings::get_settings(),
					'capabilities'   => array(
						'manageSettings' => current_user_can( 'manage_options' ),
					),
					'audio'          => Utils::get_audio_urls(),
					'version'        => OPTN_VERSION,
				)
			)
		);
	}
}
