<?php // phpcs:ignore

namespace OPTN\Admin;

use OPTN\Includes\AbTesting;
use OPTN\Includes\Compatibility\CachingPlugins;
use OPTN\Includes\Db;
use OPTN\Includes\Integrations\Other\GoogleAnalytics;
use OPTN\Includes\Integrations\Other\Zapier;
use OPTN\Includes\Settings;
use OPTN\Includes\Utils\Deactive;
use OPTN\Includes\Utils\Notice;
use OPTN\Includes\Utils\PluginActions;
use OPTN\Includes\Utils\Utils;
use OPTN\Includes\WpxpoPlugins;
use OPTN\Includes\Xpo;

/**
 * The admin-specific functionality of the plugin.
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
		$this->init_integrations();
		$this->init_custom_hooks();
		$this->init_scheduled_tasks();
		new AbTesting();
		new Deactive();
		new Notice();
		new WpxpoPlugins();
		new PluginActions();
		CachingPlugins::init();
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
	public function admin_head() {
		if ( ! $this->is_in_menu_page() ) {
			return;
		}

		if ( defined( 'OPTN_DEV_MODE' ) && true === OPTN_DEV_MODE ) {
			echo '<script src="https://unpkg.com/react-scan/dist/auto.global.js"></script>'; // phpcs:ignore

			echo '<link href="https://cdn.jsdelivr.net/npm/dom-to-image-more@3.5.0/spec/resources/fonts/web-fonts/embedded.min.css" rel="stylesheet" crossorigin="anonymous"><script src="https://cdn.jsdelivr.net/npm/dom-to-image-more@3.5.0/dist/dom-to-image-more.min.js"></script>'; // phpcs:ignore
		}
	}


	/**
	 * Initilizes active integrations.
	 *
	 * @return void
	 */
	private function init_integrations() {
		$ints = Settings::get_integration_settings();

		if ( isset( $ints['int_bg_enable_ga'] ) && true === $ints['int_bg_enable_ga'] ) {
			new GoogleAnalytics();
		}

		if ( isset( $ints['int_bg_enable_zapier'] ) && true === $ints['int_bg_enable_zapier'] ) {
			new Zapier();
		}
	}

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
				'title' => __( 'WowOptin License', 'optin' ),
				'menu'  => __( 'License', 'optin' ),
				'slug'  => Utils::is_show_license_page() ? 'wowoptin-license' : null,
			),
			array(
				'title' => __( 'Our Products', 'optin' ),
				'menu'  => __( 'Our Products', 'optin' ),
				'slug'  => 'wowoptin-wpxpo-plugins',
			),
			array(
				'title' => __( 'WowOptin Builder', 'optin' ),
				'menu'  => __( 'Builder', 'optin' ),
				'slug'  => 'wowoptin-builder', // DO NOT CHANGE.
			),
		);

		$pro_link      = '';
		$pro_link_text = '';

		if ( ! Xpo::is_lc_active() ) {
			$pro_link      = 'https://www.wowoptin.com/#pricing';
			$pro_link_text = __( 'Upgrade to Pro', 'optin' );
		} elseif ( Xpo::is_lc_expired() ) {
			$license_key   = Utils::get_license_key() ?? '';
			$pro_link      = 'https://account.wpxpo.com/checkout/?edd_license_key=' . $license_key;
			$pro_link_text = __( 'Renew License', 'optin' );
		}

		if ( ! empty( $pro_link ) ) {
			ob_start();
			?>
			<a href="<?php echo esc_url( $pro_link ); ?>" target="_blank" class="optn-pro-link">
				<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M2.86 6.553a.5.5 0 01.823-.482l3.02 2.745c.196.178.506.13.64-.098L9.64 4.779a.417.417 0 01.72 0l2.297 3.939a.417.417 0 00.64.098l3.02-2.745a.5.5 0 01.823.482l-1.99 8.63a.833.833 0 01-.813.646H5.663a.833.833 0 01-.812-.646L2.86 6.553z" stroke="currentColor" stroke-width="1.5"></path>
				</svg>
				<span><?php echo esc_html( $pro_link_text ); ?></span>
			</a>
			<?php
			$submenu_content = ob_get_clean();

			$submenus[] = array(
				'title' => __( 'WowOptin Pro', 'optin' ),
				'menu'  => $submenu_content,
				'slug'  => 'wowoptin-pro',
			);
		}

		foreach ( $submenus as $i => $submenu ) {
			if ( ! empty( $submenu['slug'] ) ) {
				add_submenu_page(
					'wowoptin',
					$submenu['title'],
					$submenu['menu'],
					OPTN_MIN_CAPABILITY,
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
			#toplevel_page_wowoptin li:has(a[href="admin.php?page=wowoptin-wpxpo-plugins"]) {
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

		wp_enqueue_style( $this->plugin_name . '-admin-css', OPTN_URL . 'admin/css/wowoptin-admin.min.css', array(), $this->version, 'all' );

		$css_path = OPTN_URL . sprintf( '/build/admin/style-index%s.css', is_rtl() ? '-rtl' : '' );

		wp_enqueue_style( $this->plugin_name . '-admin-build-css', $css_path, array(), $this->version, 'all' );
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

		// Compatibility for version <6.6.
		global $wp_version;

		if ( version_compare( $wp_version, '6.6', '<' ) ) {
			wp_register_script(
				'react-jsx-runtime',
				OPTN_URL . 'assets/js/react-jsx-runtime.js',
				array( 'react' ),
				'18.3.0',
				true
			);
		}

		$assets = require_once OPTN_DIR . '/build/admin/index.asset.php';

		// For media upload feature.
		wp_enqueue_media();

		wp_enqueue_script(
			$this->plugin_name . '-admin-js',
			OPTN_URL . 'build/admin/index.js',
			$assets['dependencies'],
			$assets['version'],
			true
		);

		$currency_symbol = Utils::get_currency_symbol();

		$lic_info = Utils::get_license_info();

		$user = get_userdata( get_current_user_id() );

		wp_localize_script(
			$this->plugin_name . '-admin-js',
			'optnAdmin',
			array_merge(
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
					'pro'            => $lic_info['is_active'] ? 'true' : 'false',
					'settings'       => Settings::get_settings(),
					'audio'          => Utils::get_audio_urls(),
					'show_lic_page'  => Utils::is_show_license_page() ? 'true' : 'false',
					'version'        => OPTN_VERSION,
					'license'        => Utils::get_license_key(),
					'helloBar'       => Notice::get_hellobar_config(),
				),
				Xpo::get_wow_products_details()
			)
		);
	}
}
