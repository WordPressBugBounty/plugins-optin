<?php // phpcs:ignore

namespace OPTN\Frontend;

use OPTN\Includes\Utils\Sanitizer;
use OPTN\Includes\Utils\Utils;
use OPTN\Includes\Utils\VisitorCount;
use OPTN\Includes\Db;
use OPTN\Includes\Analytics;
use OPTN\Includes\Settings;
use OPTN\Includes\Utils\DisplayRules;
use OPTN\Includes\Utils\AnalyticsToken;

/**
 * Registers frontend hooks and renders eligible campaigns for public requests.
 *
 * Applies display rules, validates extension-selected campaigns, records commerce
 * conversions, and enqueues only the assets required by rendered campaign content.
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
	 * @var \OPTN\Includes\DB $db
	 */
	private $db;

	/**
	 * VisitorCount instance
	 *
	 * @var \OPTN\Includes\Utils\VisitorCount $visitor_count
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

		// Cron schedules can load translations in other plugins.
		add_action( 'init', array( $this->visitor_count, 'schedule_rotation' ) );

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
		if ( ! Settings::is_google_fonts_enabled() || ! $this->get_allow_enqueuing() ) {
			return;
		}
		foreach ( apply_filters( 'optn_fonts', array() ) as $url ) {
			if ( is_string( $url ) && strpos( $url, 'https://fonts.googleapis.com/' ) === 0 ) {
				echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
				return;
			}
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
		$config = \OPTN\Includes\Utils\AssetConfig::enqueue( 'frontend' );
		$handle = $config['script']['handle'];
		$local  = array(
			'url'        => admin_url( 'admin-ajax.php' ),
			'rootUrl'    => rtrim( rest_url(), '/' ),
			'assetsUrl'  => OPTN_URL . 'assets',
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'isPreview'  => Utils::is_preview_mode() ? 'true' : 'false',
			'ipTracking' => Settings::is_ip_tracking_enabled() ? 'true' : 'false',
		);
		/** Extensions add edition-specific localization without starting another frontend runtime. */
		$local = apply_filters( 'optn_frontend_localized_payload', $local );
		$data  = array(
			'attrs' => apply_filters( 'optn_block_attrs', array() ),
			'data'  => apply_filters( 'optn_data', array() ),
		);
		if ( ! $this->use_alternate_render_strategy() ) {
			$data['html'] = Sanitizer::sanitize( apply_filters( 'optn_html', '' ) );
		}
		wp_add_inline_script( $handle, 'var optn = ' . wp_json_encode( $local, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ) . ';window._optn = ' . wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ) . ';', 'before' );
		foreach ( $config['styles'] as $style ) {
			wp_print_styles( $style['handle'] );
		}
		$style_handle = $this->plugin_name . '-generated-css';
		wp_register_style( $style_handle, false, array(), $this->version );
		wp_enqueue_style( $style_handle );
		wp_add_inline_style( $style_handle, wp_strip_all_tags( apply_filters( 'optn_css', '' ) ) );
		wp_print_styles( $style_handle );
		if ( $this->use_alternate_render_strategy() ) {
			Sanitizer::e_sanitize( $this->get_optin_html() );
		}
		$this->get_optin_fonts();
	}

	/**
	 * Enqueue and print optin fonts through WordPress stylesheet APIs.
	 *
	 * @return void
	 */
	private function get_optin_fonts() {
		foreach ( apply_filters( 'optn_fonts', array() ) as $id => $url ) {
			$handle = $this->plugin_name . '-font-' . sanitize_key( $id );
			// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google Fonts URLs identify the requested families and weights; omit a plugin version.
			wp_enqueue_style( $handle, esc_url_raw( $url ), array(), null );
			wp_print_styles( $handle );
		}
	}

	/**
	 * Get optin html
	 *
	 * @return string
	 */
	private function get_optin_html() {
		$html  = '';
		$html .= '<!-- OPTIN HTML START -->';
		$html .= apply_filters( 'optn_html', '' );
		$html .= '<!-- OPTIN HTML END -->';
		return $html;
	}

	/**
	 * Whether the alternate render strategy is enabled.
	 *
	 * @return boolean
	 */
	private function use_alternate_render_strategy() {
		return (bool) Settings::get_settings( 'global_alt_render_strategy' );
	}

	/**
	 * Setup embedded optins
	 *
	 * @return void
	 */
	public function setup_embedded_optins() {
		$posts = $this->select_campaigns( $this->db->get_conv_for_render( null, true, true ), 'embedded' );

		if ( empty( $posts ) ) {
			return;
		}

		foreach ( $posts as $post ) {
			if ( $this->check_display_rules( $post ) ) {

				$hook_data = Utils::get_embed_hook_and_pos( $post->embed );

				if ( empty( $hook_data['hook'] ) || empty( $hook_data['pos'] ) ) {
					continue;
				}

				$entry = array(
					'post'   => $post,
					'pos'    => $hook_data['pos'],
					'single' => isset( $hook_data['single'] ) ? $hook_data['single'] : false,
				);
				if ( 'action' === $hook_data['type'] ) {
					// Rendering collects campaign data, CSS and fonts before footer assets are printed.
					$entry['html'] = $this->renderer->render_inline_optin( $post, random_int( 1000, 9999 ) );
					$this->set_allow_enqueuing( true );
				}
				$this->embed_data[ $hook_data['hook'] ][] = $entry;

				if ( 'action' === $hook_data['type'] ) {
					$before   = 'before' === $hook_data['pos'];
					$callback = $before ? 'print_embedded_conversion_before' : 'print_embedded_conversion';
					add_action( $hook_data['hook'], array( $this, $callback ), $before ? 1 : 99999999, 0 );
				} else {
					add_filter( $hook_data['hook'], array( $this, 'get_embedded_conversion' ), 99999999 );
				}
			}
		}
	}

	/**
	 * Get embedded conversion
	 *
	 * @param mixed $content The content to process. Non-string values pass through.
	 * @return mixed
	 */
	public function get_embedded_conversion( $content ) {
		if ( ! is_string( $content ) ) {
			return $content;
		}

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
	 * Print embeds before other callbacks on an approved HTML output action.
	 *
	 * @return void
	 */
	public function print_embedded_conversion_before() {
		$this->print_embedded_conversion( 'before' );
	}

	/**
	 * Print only the campaigns assigned to this side of an approved output action.
	 *
	 * @param string $position Placement handled by this action callback.
	 * @return void
	 */
	public function print_embedded_conversion( $position = 'after' ) {
		$html = '';
		foreach ( $this->embed_data[ current_filter() ] ?? array() as $data ) {
			if ( $position === $data['pos'] ) {
				$html .= $data['html'];
			}
		}
		Sanitizer::e_sanitize( $html );
	}

	/**
	 * Process inline conversion
	 *
	 * @param array $sc_attr shortcode attrs.
	 * @return string
	 */
	public function get_inline_optin( $sc_attr ) {
		$sc_attr = shortcode_atts(
			array(
				'id'    => '',
				'index' => '',
			),
			is_array( $sc_attr ) ? $sc_attr : array(),
			'optn'
		);
		if ( ! is_scalar( $sc_attr['id'] ) || ! ctype_digit( (string) $sc_attr['id'] ) || (int) $sc_attr['id'] < 1 || ( $sc_attr['index'] !== '' && ( ! is_scalar( $sc_attr['index'] ) || ! ctype_digit( (string) $sc_attr['index'] ) ) ) ) {
			return ''; }

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

		$post = $this->select_campaigns( $this->db->get_conv_for_render( intval( $sc_attr['id'] ), false, true ), 'inline' );

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
		$filtered_posts = array();
		/** Null means no extension preview. An empty array means a handled preview with no campaign. */
		$preview_posts = apply_filters( 'optn_campaign_preview_posts', null );

		// For previewing a template in inside builder.
		if ( ! empty( $id ) ) {
			$optin = $this->db->get_conv_by_id( $id );
			if ( ! empty( $optin ) ) {
				$filtered_posts[] = $optin;
			}
		} // phpcs:ignore

		// For rendering optins in the front-end.
		elseif ( is_array( $preview_posts ) ) {
			$filtered_posts = $preview_posts;
		} else {
			$posts = $this->db->get_conv_for_render();

			if ( empty( $posts ) ) {
				return;
			}

			foreach ( $posts as $post ) {
				if ( $this->check_display_rules( $post ) ) {
					$filtered_posts[] = $post;
				}
			}
			$filtered_posts = $this->select_campaigns( $filtered_posts, 'popup' );
		}

		if ( ! empty( $filtered_posts ) ) {
			$this->set_allow_enqueuing( true );
			$this->renderer->render_optins( $filtered_posts );
		}
	}

	/**
	 * Determine whether a saved campaign satisfies the current display rules.
	 *
	 * Rejects missing or malformed campaign data, allows an explicit boolean extension
	 * result, and otherwise evaluates the supported single group of display rules.
	 *
	 * @param \stdClass $post Saved campaign record containing the serialized rule data.
	 * @return bool Whether the campaign is eligible for the current request.
	 */
	private function check_display_rules( $post ) {

		if ( empty( $post ) || ! isset( $post->data ) || empty( $post->data ) ) {
			return false;
		}

		$data = json_decode( $post->data );

		if ( empty( $data ) || ! isset( $data->displayRules ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			return false;
		}

		$rule_groups = $data->displayRules; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		/** Null delegates to shared single-group rules; only explicit booleans may replace evaluation. */
		$result = apply_filters( 'optn_campaign_display_rules', null, $rule_groups, $post );
		if ( null !== $result ) {
			return true === $result;
		}
		if ( ! is_array( $rule_groups ) || count( $rule_groups ) !== 1 || ! is_array( $rule_groups[0]->rules ?? null ) ) {
			return false;
		}

		// Free evaluates AND conditions inside its single supported group.
		foreach ( $rule_groups[0]->rules as $rule ) {
			if ( ! is_object( $rule ) || ! DisplayRules::is_valid_display_rule( $rule ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Let extensions select from already eligible campaigns for the current render context.
	 *
	 * Normalizes the candidate list, applies the selection filter, and discards any result
	 * that is not the same object instance as one of the supplied eligible campaigns.
	 *
	 * @param array<int, \stdClass> $posts Eligible campaign records produced by the database and display rules.
	 * @param string                $context Rendering context such as popup, inline, or embedded.
	 * @return array<int, \stdClass> Selected campaign records drawn only from the eligible candidates.
	 */
	private function select_campaigns( $posts, $context ) {
		$posts    = is_array( $posts ) ? $posts : array();
		$selected = apply_filters( 'optn_campaign_selection', $posts, $context );
		if ( ! is_array( $selected ) ) {
			return array();
		}
		// A handler may select candidates, never inject an unchecked campaign.
		return array_values(
			array_filter(
				$selected,
				function ( $post ) use ( $posts ) {
					return is_object( $post ) && in_array( $post, $posts, true );
				}
			)
		);
	}

	/**
	 * Record a completed Easy Digital Downloads purchase as a conversion event.
	 *
	 * Passes the payment identifier to the shared purchase tracker, which validates its
	 * type, checks the campaign tracking cookie, and records the conversion when valid.
	 *
	 * @param int|string $payment_id Easy Digital Downloads payment identifier.
	 * @return void
	 */
	public function edd_purchase_tracking( $payment_id ) {
		$this->track_purchase( 'edd', $payment_id );
	}

	/**
	 * Record a completed WooCommerce order as a conversion event.
	 *
	 * Passes the order identifier to the shared purchase tracker, which validates it,
	 * checks the campaign tracking cookie, and records the conversion when valid.
	 *
	 * @param int|string $order_id WooCommerce order identifier.
	 * @return void
	 */
	public function woo_purchase_tracking( $order_id ) {
		$this->track_purchase( 'woo', $order_id );
	}

	/**
	 * Validate and record a tracked purchase against its originating opt-in campaign.
	 *
	 * Reads the campaign ID from the tracking cookie, verifies that the purchase ID and
	 * campaign are valid, then delegates the purchase event to the database service.
	 *
	 * @param string     $order_type Commerce provider identifier, such as `edd` or `woo`.
	 * @param int|string $order_id   Purchase or order identifier supplied by that provider.
	 * @return void
	 */
	private function track_purchase( $order_type, $order_id ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw type and length are checked before unslashing; decoded fields are strictly validated and authenticated below.
		$cookie = $_COOKIE['optn_purchase_tracking'] ?? null;
		if ( ! is_string( $cookie ) || strlen( $cookie ) > 1024 ) {
			return;
		}
		$data = json_decode( wp_unslash( $cookie ) );
		if ( ! is_object( $data ) || ! is_int( $data->analyticsId ?? null ) || ! is_int( $data->postId ?? null ) || ! is_string( $data->token ?? null ) || ! AnalyticsToken::verify( $data->token, $data->analyticsId, $data->postId, 'purchase' ) ) {
			return;
		}
		$this->db->add_purchase( $data->analyticsId, $data->postId, $order_type, $order_id ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		setcookie( 'optn_purchase_tracking', '', strtotime( '-1 day' ) );
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		if ( $this->get_allow_enqueuing() ) {
			\OPTN\Includes\Utils\AssetConfig::enqueue( 'frontend' ); }
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
		if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
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
