<?php // phpcs:ignore

namespace OPTN\Frontend;

use OPTN\Includes\AbTesting;
use OPTN\Includes\Utils\Sanitizer;
use OPTN\Includes\Utils\Utils;
use OPTN\Includes\Utils\VisitorCount;
use OPTN\Includes\Db;
use OPTN\Includes\Analytics;
use OPTN\Includes\Settings;
use OPTN\Includes\Utils\DisplayRules;
use OPTN\Includes\Utils\Templates;

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://wpxpo.com
 * @since      1.0.0
 *
 * @package    optin
 * @subpackage optin/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * @package    optin
 * @subpackage optin/public
 */
class Frontend {

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
	 * Conversion renderer
	 *
	 * @var OptinGenerator $renderer
	 */
	private $renderer;

	/**
	 * DB instance
	 *
	 * @var \OPTN\DB $db
	 */
	private $db;

	/**
	 * VisitorCount instance
	 *
	 * @var \OPTN\VisitorCount $visitor_count
	 */
	private $visitor_count;

	/**
	 * Embed data
	 *
	 * @var array
	 */
	private $embed_data;

	/**
	 * Shortcode content
	 *
	 * @var array
	 */
	private $sc_content;


	// Conversion types.
	const POPUP_CONVERSION        = 'popup';
	const BANNER_CONVERSION       = 'banner';
	const SLIDE_IN_CONVERSION     = 'slidein';
	const FLOATING_BAR_CONVERSION = 'floatingbar';

	/**
	 * Whether to enqueue the script/styles or not
	 *
	 * @var boolean
	 */
	private $should_enqueue = false;


	/**
	 * Initialize the class and set its properties.
	 *
	 * @param string $plugin_name The name of the plugin.
	 * @param string $version The version of this plugin.
	 *
	 * @since    1.0.0
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name   = $plugin_name;
		$this->version       = $version;
		$this->renderer      = new OptinGenerator( $plugin_name, $version );
		$this->db            = Db::get_instance();
		$this->visitor_count = VisitorCount::get_instance();
		$this->sc_content    = array();
		$this->embed_data    = array();

		$this->visitor_count->schedule_rotation();

		add_shortcode( 'optn', array( $this, 'get_inline_optin' ) );

		add_filter(
			'body_class',
			function ( $classes ) {
				$classes[] = 'optn';
				return $classes;
			}
		);

		add_action( 'wp', array( $this, 'setup_embedded_optins' ) );
	}

	/**
	 * Register routes
	 *
	 * @return void
	 */
	public function register_routes() {
		Analytics::get_instance()->register_public_routes();
		new RestFrontend();
	}

	/**
	 * Assign user id
	 *
	 * @return void
	 */
	public function assign_user_id() {
		if ( isset( $_COOKIE['optn_analytics_id'] ) ) {
			return;
		}

		$analytics_id = wp_generate_uuid4();

		setcookie( 'optn_analytics_id', $analytics_id, intval( time() + ( YEAR_IN_SECONDS ) ), '/' ); // GDPR compliant (Max 2 years).

		// Make the id available in this same request for immediate variant stickiness.
		$_COOKIE['optn_analytics_id'] = $analytics_id;
	}

	/**
	 * Track visit
	 *
	 * @return void
	 */
	public function track_visit() {
		$this->visitor_count->track_visit();
	}

	/**
	 * Adds styles to allow list
	 *
	 * @param array $styles styles array.
	 * @return array
	 */
	public function add_to_style_allowlist( $styles ) {
		return Sanitizer::add_to_style_allowlist( $styles );
	}

	/**
	 * Adds styles values to allow list
	 *
	 * @param boolean $allow_css allow css.
	 * @param string  $css_str css string.
	 * @return boolean
	 */
	public function allow_style_attrs( $allow_css, $css_str ) {
		return Sanitizer::allow_style_attrs( $allow_css, $css_str );
	}

	/**
	 * Adds tags to allow list
	 *
	 * @param array  $tags styles array.
	 * @param string $context context.
	 * @return array
	 */
	public function add_to_tags_allowlist( $tags, $context ) {
		return Sanitizer::add_to_tags_allowlist( $tags, $context );
	}

	/**
	 * Faster Google Fonts download
	 *
	 * @return void
	 */
	public function preconnect_google_fonts() {
		if ( Settings::get_settings( 'global_google_fonts' ) ) {
			echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
		}
	}

	/**
	 * Adds optin content to the footer
	 *
	 * @return void
	 */
	public function add_content() {

		if ( ! $this->get_allow_enqueuing() ) {
			return;
		}

		// Fonts.
		echo $this->get_optin_fonts(); // phpcs:ignore

		// CSS.
		echo $this->get_optin_css(); // phpcs:ignore

		// JS data.
		echo $this->get_optin_data(); // phpcs:ignore

		// JS (manual enqueue).
		$this->print_manual_fe_script();
	}

	/**
	 * Print the localized script var (replacement for wp_localize_script).
	 *
	 * The built `build/fe/index.js` expects a global `optn` object.
	 *
	 * @return void
	 */
	private function print_manual_localize_var() {
		$assets_url = OPTN_URL . 'assets';

		$localize_data = array(
			'url'        => admin_url( 'admin-ajax.php' ),
			'rootUrl'    => rtrim( rest_url(), '/' ),
			'assetsUrl'  => $assets_url,
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'isPreview'  => Utils::is_preview_mode() ? 'true' : 'false',
			'ipTracking' => Settings::get_settings( 'global_ip_tracking' ) ? 'true' : 'false',
		);

		echo '<script>var optn = ' . wp_json_encode( $localize_data ) . ';</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Prints the built front-end bundle (`build/fe/index.js`) inline.
	 *
	 * Ensures the WP script dependencies listed in `build/fe/index.asset.php`
	 * are enqueued and printed before this bundle runs.
	 *
	 * @return void
	 */
	private function print_manual_fe_script() {
		$asset_path  = OPTN_DIR . 'build/fe/index.asset.php';
		$script_path = OPTN_DIR . 'build/fe/index.js';

		if ( ! file_exists( $script_path ) ) {
			// Fallback to the normal enqueue (if script was registered earlier).
			wp_enqueue_script( $this->plugin_name );
			return;
		}

		$deps = array();
		if ( file_exists( $asset_path ) ) {
			$assets = include $asset_path; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			if ( is_array( $assets ) && ! empty( $assets['dependencies'] ) && is_array( $assets['dependencies'] ) ) {
				$deps = $assets['dependencies'];
			}
		}

		// Enqueue + print dependencies before our inline bundle.
		if ( ! empty( $deps ) ) {
			foreach ( $deps as $dep ) {
				wp_enqueue_script( $dep );
			}
			wp_print_scripts( $deps );
		}

		if ( ! class_exists( 'WP_Filesystem_Direct' ) && defined( 'ABSPATH' ) ) {
			$base_file   = ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
			$direct_file = ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

			if ( file_exists( $base_file ) ) {
				require_once $base_file;
			}
			if ( file_exists( $direct_file ) ) {
				require_once $direct_file;
			}
		}

		$script = '';
		if ( class_exists( 'WP_Filesystem_Direct' ) ) {
			$fs     = new \WP_Filesystem_Direct( null );
			$script = $fs->get_contents( $script_path );
		}
		if ( empty( $script ) ) {
			wp_enqueue_script( $this->plugin_name );
			return;
		}

		// Replacement for script localization (global `optn`).
		$this->print_manual_localize_var();

		echo "<script defer id=\"optn-fe-index\">\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $script; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "\n</script>"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Get optin fonts
	 *
	 * @return string
	 */
	private function get_optin_fonts() {
		ob_start();
		echo '<!-- OPTIN FONTS START -->';
		$font_list = apply_filters( 'optn_fonts', array() );
		foreach ( $font_list as $id => $url ) {
			echo '<link rel="stylesheet" id="' . esc_attr( $id ) . '" href="' . esc_url( $url ) . '">'; // phpcs:ignore
		}
		echo '<!-- OPTIN FONTS END -->';
		return ob_get_clean();
	}

	/**
	 * Get optin css
	 *
	 * @return string
	 */
	private function get_optin_css() {
		$content  = '';
		$content .= '<!-- OPTIN CSS START -->';
		$content .= '<link rel="stylesheet" href="' . esc_url( OPTN_URL . 'frontend/css/wowoptin-public.min.css' ) . '?ver=' . $this->version . '" type="text/css" media="all">'; // phpcs:ignore
		$content .= '<style id="optin-block-styles" >' . wp_strip_all_tags( apply_filters( 'optn_css', '' ) ) . '</style>'; // phpcs:ignore
		$content .= '<!-- OPTIN CSS END -->';
		return $content;
	}

	/**
	 * Get optin html
	 *
	 * @return string
	 */
	private function get_optin_html() {
		$html  = '';
		$html .= '<!-- OPTIN HTML START -->';
		$html .= Sanitizer::sanitize( apply_filters( 'optn_html', '' ) );
		$html .= '<!-- OPTIN HTML END -->';
		return $html;
	}

	/**
	 * Get optin js
	 *
	 * @return string
	 */
	private function get_optin_data() {
		ob_start();
		echo '<!-- OPTIN JS START -->';
		?>
			<script>
				window._optn = {
					attrs: <?php echo wp_json_encode( apply_filters( 'optn_block_attrs', array() ) ); ?>,
					data: <?php echo wp_json_encode( apply_filters( 'optn_data', array() ) ); ?>,
					html: 
					<?php
					echo wp_json_encode(
						Sanitizer::sanitize( apply_filters( 'optn_html', '' ) ),
						JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
					);
					?>
					,
				}
			</script>
		<?php
		echo '<!-- OPTIN JS END -->';
		return ob_get_clean();
	}

	/**
	 * Setup embedded optins
	 *
	 * @return void
	 */
	public function setup_embedded_optins() {
		$posts = $this->db->get_conv_for_render( null, true, true );

		if ( empty( $posts ) ) {
			return;
		}

		foreach ( $posts as $post ) {
			if ( $this->check_display_rules( $post ) ) {

				$hook_data = Utils::get_embed_hook_and_pos( $post->embed );

				if ( empty( $hook_data['hook'] ) || empty( $hook_data['pos'] ) ) {
					continue;
				}

				if ( isset( $this->embed_data[ $hook_data['hook'] ] ) ) {
					$this->embed_data[ $hook_data['hook'] ][] = array(
						'post'   => $post,
						'pos'    => $hook_data['pos'],
						'single' => isset( $hook_data['single'] ) ? $hook_data['single'] : false,
					);
				} else {
					$this->embed_data[ $hook_data['hook'] ] = array(
						array(
							'post'   => $post,
							'pos'    => $hook_data['pos'],
							'single' => isset( $hook_data['single'] ) ? $hook_data['single'] : false,
						),
					);
				}

				add_filter( $hook_data['hook'], array( $this, 'get_embedded_conversion' ), 99999999 );
			}
		}
	}

	/**
	 * Get embedded conversion
	 *
	 * @param string $content The content to process.
	 * @return string
	 */
	public function get_embedded_conversion( $content ) {

		$curr_hook = current_filter();

		if ( ! isset( $this->embed_data[ $curr_hook ] ) || ! is_array( $this->embed_data[ $curr_hook ] ) ) {
			return $content;
		}

		$data = $this->embed_data[ $curr_hook ];

		$this->set_allow_enqueuing( true );

		foreach ( $data as $d ) {
			$html    = $this->renderer->render_inline_optin( $d['post'], random_int( 1000, 9999 ) );
			$content = 'before' === $d['pos'] ? $html . $content : $content . $html;
		}

		return $content;
	}

	/**
	 * Process inline conversion
	 *
	 * @param array $sc_attr shortcode attrs.
	 * @return string
	 */
	public function get_inline_optin( $sc_attr ) {

		if ( $this->is_frontend_builder_shortcode_preview() ) {
			return $this->renderer->render_inline_placeholder();
		}

		if ( ! is_numeric( $sc_attr['id'] ) ) {
			return '';
		}

		if ( isset( $sc_attr['index'] ) && isset( $this->sc_content[ $sc_attr['id'] . '-' . $sc_attr['index'] ] ) ) {
			return $this->sc_content[ $sc_attr['id'] . '-' . $sc_attr['index'] ];
		}

		$idx = isset( $sc_attr['index'] ) && is_numeric( $sc_attr['index'] ) ? intval( $sc_attr['index'] ) : random_int( 1000, 9999 );

		$post = $this->db->get_conv_for_render( intval( $sc_attr['id'] ), false, true );

		if ( empty( $post[0] ) || ! $this->check_display_rules( $post[0] ) ) {
			return '';
		}

		$this->set_allow_enqueuing( true );
		$html = $this->renderer->render_inline_optin( $post[0], $idx );

		return $html;
	}

	/**
	 * Generate Optins
	 *
	 * @return void
	 */
	public function generate_optins() {

		if ( $this->is_frontend_builder() ) {
			return;
		}

		$id             = Utils::get_optin_preview_id();
		$template_id    = Utils::get_template_preview_id();
		$filtered_posts = array();

		// For previewing a template in inside builder.
		if ( ! empty( $id ) ) {
			$optin = $this->db->get_conv_by_id( $id );
			if ( ! empty( $optin ) ) {
				$filtered_posts[] = $optin;
			}
		} // phpcs:ignore

		// For previewing a template in template page.
		elseif ( ! empty( $template_id ) ) {
			$optin = Templates::fetch_template_post( $template_id );
			if ( ! empty( $optin ) ) {
				$filtered_posts[] = $optin;
			}
		} // phpcs:ignore

		// For rendering optins in the front-end.
		else {
			$posts = $this->db->get_conv_for_render();

			if ( empty( $posts ) ) {
				return;
			}

			foreach ( $posts as $post ) {
				if ( $this->check_display_rules( $post ) ) {
					$filtered_posts[] = $post;
				}
			}

			$filtered_posts = AbTesting::check_ab_test( $filtered_posts );
		}

		if ( ! empty( $filtered_posts ) ) {
			$this->set_allow_enqueuing( true );
			$this->renderer->render_optins( $filtered_posts );
		}
	}

	/**
	 * Checks display rule conditions for a post
	 *
	 * @param object $post post object.
	 * @return bool
	 */
	private function check_display_rules( $post ) {

		if ( empty( $post ) || ! isset( $post->data ) || empty( $post->data ) ) {
			return false;
		}

		// Condition among groups -> OR
		// Condition among rule inside a group -> AND
		// So if one group is true, immediately return true (short-circuit),
		// and if one rule in a group is false, the whole group is false.
		// We should sort the groups by ascending order in terms of number of rules in a group.

		$data = json_decode( $post->data );

		if ( empty( $data ) || ! isset( $data->displayRules ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			return false;
		}

		$rule_groups = $data->displayRules; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$passed      = false;

		usort(
			$rule_groups,
			function ( $a, $b ) {
				return count( $a->rules ) - count( $b->rules );
			}
		);

		foreach ( $rule_groups as $rule_group ) {
			$res_group = true;

			foreach ( $rule_group->rules as $rule ) {
				if ( ! DisplayRules::is_valid_display_rule( $rule ) ) {
					$res_group = false;
					break;
				}
			}

			if ( $res_group ) {
				$passed = true;
				break;
			} else {
				// Reseting.
				$res_group = true;
			}
		}

		return $passed;
	}

	/**
	 * EDD purchase tracking
	 *
	 * @param mixed $payment_id The payment ID.
	 * @return void
	 */
	public function edd_purchase_tracking( $payment_id ) {
		$this->track_purchase( 'edd', $payment_id );
	}

	/**
	 * WooCommerce purchase tracking
	 *
	 * @param mixed $order_id The order ID.
	 * @return void
	 */
	public function woo_purchase_tracking( $order_id ) {
		$this->track_purchase( 'woo', $order_id );
	}

	/**
	 * Track purchase
	 *
	 * @param string $order_type The order type.
	 * @param mixed  $order_id The order ID.
	 * @return void
	 */
	private function track_purchase( $order_type, $order_id ) {
		$cookie = isset( $_COOKIE['optn_purchase_tracking'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['optn_purchase_tracking'] ) ) : null;

		if ( empty( $cookie ) ) {
			return;
		}

		$data = json_decode( stripslashes( $cookie ) );
		if ( ! empty( $data ) ) {
			$this->db->add_purchase( $data->analyticsId, $data->postId, $order_type, $order_id ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			setcookie( 'optn_purchase_tracking', '', strtotime( '-1 day' ) );
		}
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		$asset_path = OPTN_DIR . 'build/fe/index.asset.php';

		if ( ! file_exists( $asset_path ) ) {
			return;
		}

		$assets = include $asset_path; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable

		if ( empty( $assets['dependencies'] ) ) {
			return;
		}

		foreach ( $assets['dependencies'] as $dep ) {
			wp_enqueue_script( $dep );
		}
	}

	/**
	 * Set enqueing
	 *
	 * @param boolean $value value to be set.
	 * @return void
	 */
	private function set_allow_enqueuing( $value ) {
		$this->should_enqueue = is_bool( $value ) ? $value : false;
	}


	/**
	 * Get enqueuing
	 *
	 * @return boolean
	 */
	private function get_allow_enqueuing() {
		return $this->should_enqueue;
	}

	/**
	 * Check if its a shortcode preview by a frontend builder
	 *
	 * @return boolean
	 */
	private function is_frontend_builder_shortcode_preview() {

		// Shortcode previews usually are loaded via ajax.
		if ( wp_doing_ajax() || wp_is_serving_rest_request() ) {
			return true;
		}

		// Many builders use this key to preview shortcodes.
		if ( isset( $_GET['action'] ) || isset( $_POST['action'] ) ) { // phpcs:ignore
			return true;
		}

		// Divi.
		if ( isset( $_GET['et_pb_preview'] ) ) { // phpcs:ignore
			return true;
		}

		return false;
	}

	/**
	 * Blacklisted pages where optins will not be generated.
	 *
	 * @return boolean
	 */
	private function is_frontend_builder() {

		$params = array(
			'elementor-preview', // Elementor.

			'fl_builder', // Beaver Builder.
			'et_fb', // Divi builder.
			'ct_builder', // Oxygen builder.

			// WPBakery Builder.
			'vc_action',
			'vc_editable',

			// Bricks Builder.
			'bricks',
			'bricks_preview',
		);

		foreach ( $params as $param ) {
			if ( isset( $_GET[ $param ] ) ) { // phpcs:ignore
				return true;
			}
		}

		return false;
	}
}
