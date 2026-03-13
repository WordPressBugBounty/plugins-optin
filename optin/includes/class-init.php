<?php // phcps:ignore

namespace OPTN\Includes;

use OPTN\Admin\Admin;
use OPTN\Frontend\Frontend;
use OPTN\Includes\Loader;

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    optin
 * @subpackage optin/includes
 */
class Init {


	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Loader $loader Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string $plugin_name The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string $version The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->version     = OPTN_VERSION;
		$this->plugin_name = 'optin';

		$this->loader = new Loader();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

		Db::get_instance()->maybe_install();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the OPTN_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new I18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Admin( $this->get_plugin_name(), $this->get_version() );

		// Admin customization.
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_menu_page' );
		$this->loader->add_action( 'admin_head', $plugin_admin, 'admin_head' );
		$this->loader->add_action( 'admin_footer', $plugin_admin, 'add_dropdown_html' );

		// Register REST routes.
		$this->loader->add_action( 'rest_api_init', $plugin_admin, 'register_routes' );

		// Admin panel scripts and styles.
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Frontend( $this->get_plugin_name(), $this->get_version() );

		// REST.
		$this->loader->add_action( 'rest_api_init', $plugin_public, 'register_routes' );

		// Visitor count.
		$this->loader->add_action( 'wp', $plugin_public, 'track_visit' );

		// Analytics.
		$this->loader->add_action( 'wp', $plugin_public, 'assign_user_id' );

		// Conversion content.
		$this->loader->add_action( 'safe_style_css', $plugin_public, 'add_to_style_allowlist' );
		$this->loader->add_action( 'safecss_filter_attr_allow_css', $plugin_public, 'allow_style_attrs', 999, 2 );
		// $this->loader->add_action( 'wp_kses_allowed_html', $plugin_public, 'add_to_tags_allowlist', 10, 2 );
		$this->loader->add_action( 'wp', $plugin_public, 'generate_optins' );
		$this->loader->add_action( 'wp_head', $plugin_public, 'preconnect_google_fonts', 0, 0 );
		$this->loader->add_action( 'wp_footer', $plugin_public, 'add_content', 999, 0 );

		// Sales tracking.
		$this->loader->add_action( 'edd_complete_purchase', $plugin_public, 'edd_purchase_tracking' );
		$this->loader->add_action( 'woocommerce_thankyou', $plugin_public, 'woo_purchase_tracking' );

		// Enqueue scripts and styles.
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @return    string    The name of the plugin.
	 * @since     1.0.0
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @return    Loader    Orchestrates the hooks of the plugin.
	 * @since     1.0.0
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @return    string    The version number of the plugin.
	 * @since     1.0.0
	 */
	public function get_version() {
		return $this->version;
	}
}
