<?php // phpcs:ignore
/**
 * Initialization Action.
 *
 * @package OPTN
 */
namespace OPTN\Includes; // CHANGE THIS.

defined( 'ABSPATH' ) || exit;

/**
 * Initialization class.
 */
class WowShippingPromotion {

	private const VERSION              = '20'; // Cache buster.
	private const MENU_SLUG            = 'wowoptin'; // CHANGE THIS.
	private const PROMOTED_PLUGIN_SLUG = 'wow-table-rate-shipping';
	private const PROMOTED_PLUGIN_FILE = 'wow-table-rate-shipping/wow-table-rate-shipping.php';

	/**
	 * Setup class.
	 */
	public function __construct() {
		add_filter( 'wtrs_promotion_hooks', array( $this, 'load' ) );
		add_action( 'plugins_loaded', array( $this, 'run_promotions' ) );
	}

	/**
	 * Run promotions.
	 *
	 * @return void
	 */
	public function run_promotions() {
		if ( ! class_exists( '\WooCommerce' ) ||
			defined( 'WTRS_VER' )
		) {
			return;
		}

		// Plugin sidemenu (Plugin Specific, Always run).
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 9999 );

		if ( $GLOBALS['wtrs_promotion']['init'] ?? false ) {
			return;
		}

		$GLOBALS['wtrs_promotion'] = array(
			'init' => true,
		);

		$hooks = apply_filters( 'wtrs_promotion_hooks', array() );

		if ( ! is_array( $hooks ) ) {
			return;
		}

		uksort( $hooks, 'version_compare' );

		$latest_hook = end( $hooks );

		if ( is_callable( $latest_hook ) ) {
			$latest_hook();
		}
	}

	/**
	 * Load plugin
	 *
	 * @param array $callbacks Callbacks.
	 * @return array
	 */
	public function load( $callbacks ) {

		if ( isset( $callbacks[ self::VERSION ] ) ) {
			return $callbacks;
		}

		$callbacks[ self::VERSION ] = function () {
			// Dismiss actions.
			add_action( 'wp_ajax_wtrs_dismiss_promotion', array( $this, 'ajax_dismiss_promotion' ) );
			add_action( 'wp_ajax_wtrs_install_promotion_plugin', array( $this, 'ajax_install_promotion_plugin' ) );

			// Promotions.
			// ------------------.

			// Product edit shipping tab.
			add_action( 'woocommerce_product_options_shipping', array( $this, 'render_shipping_notice' ) );

			// Product category page.
			add_action( 'product_cat_add_form_fields', array( $this, 'render_product_category_add_notice' ) );

			// WC General settings.
			add_filter( 'woocommerce_general_settings', array( $this, 'register_general_shipping_location_notice' ) );
			add_filter( 'woocommerce_product_settings', array( $this, 'register_product_dimensions_notice' ) );
			add_action( 'woocommerce_admin_field_wtrs_promotion_notice', array( $this, 'render_settings_promotion_field' ) );

			// Order Page.
			add_action( 'admin_notices', array( $this, 'render_orders_page_notice' ) );

			// Shipping Settings page.
			add_action( 'admin_notices', array( $this, 'render_shipping_page_notice' ) );
		};

		return $callbacks;
	}


	/**
	 * Add promotinal submenu link for the promoted plugin dashboard.
	 */
	public function add_submenu() {

		$url = admin_url( 'admin.php?page=wowoptin-wpxpo-plugins#wow_shipping' ); // CHANGE THIS.

		ob_start();
		?>
		<style>
			ul a[href="admin.php?page=wtrs-promotion"] {
				display: none !important;
			}
			#wtrs-submenu-link {
				color: #297cff !important;
			}
		</style>
		<a id="wtrs-submenu-link" href="<?php echo esc_url( $url ); ?>">
			<span>Add Shipping Rules</span>
		</a>
		<?php
		$submenu_content = ob_get_clean();

		add_submenu_page(
			self::MENU_SLUG,
			'Add Shipping Rules',
			$submenu_content,
			'edit_pages', // CHANGE THIS IF NEEDED.
			'wtrs-promotion',
			'__return_false'
		);
	}

	/**
	 * Ajax handler for dismissing promotion notice.
	 *
	 * @return void
	 */
	public function ajax_dismiss_promotion() {
		check_ajax_referer( 'wtrs_promotion_nonce', 'nonce' );

		$type = sanitize_text_field( wp_unslash( $_POST['type'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->set_dismissed( $type );

		wp_send_json_success();
	}

	/**
	 * Ajax handler for installing the promoted plugin.
	 *
	 * @return void
	 */
	public function ajax_install_promotion_plugin() {
		check_ajax_referer( 'wtrs_promotion_nonce', 'nonce' );

		$plugin_exists = file_exists( WP_PLUGIN_DIR . '/' . self::PROMOTED_PLUGIN_FILE );

		if ( ! $plugin_exists && ! current_user_can( 'install_plugins' ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'You are not allowed to install plugins.', 'optin' ),
				)
			);
		}

		if ( $plugin_exists && ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'You are not allowed to activate plugins.', 'optin' ),
				)
			);
		}

		$result = $this->install_and_active_plugin();

		if ( false === $result ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Failed to install WowShipping.', 'optin' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'status'        => $result,
				'dashboard_url' => $this->get_dashboard_url(),
				'message'       => esc_html__( 'WowShipping installed successfully.', 'optin' ),
			)
		);
	}

	/**
	 * Get dismiss key
	 *
	 * @param string $type Promotion type.
	 * @return string
	 */
	private function get_dismiss_key( $type ) {
		return 'wtrs_promotion_is_closed_' . self::VERSION . '_' . $type;
	}

	/**
	 * Should show a promotion
	 *
	 * @param string $type Promotion type.
	 * @return void
	 */
	private function set_dismissed( $type ) {
		set_transient( $this->get_dismiss_key( $type ), 'yes', DAY_IN_SECONDS * 30 );
	}

	/**
	 * Should show a promotion
	 * Dont show promotions if:
	 * - The promotion was dismissed by the user.
	 * - The promotion hook already ran in the current page load by another plugin.
	 *
	 * @param string $type Promotion type.
	 * @return boolean
	 */
	private function should_show_promotion( $type ) {
		$ran_once = boolval( $GLOBALS['wtrs_promotion'][ $type ] ?? false );
		if ( $ran_once ) {
			return false;
		}
		return get_transient( $this->get_dismiss_key( $type ) ) !== 'yes';
	}

	/**
	 * Render promotion notice in the new product shipping tab.
	 *
	 * @return void
	 */
	public function render_shipping_notice() {
		if ( ! $this->should_show_promotion( 'shipping_options' ) ) {
			return;
		}

		global $pagenow;
		$post_type = get_post_type();
		if ( empty( $post_type ) ) {
			$post_type = sanitize_text_field( wp_unslash( $_GET['post_type'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		$action = sanitize_text_field( wp_unslash( $_GET['action'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$is_edit_product_page = 'post.php' === $pagenow && 'edit' === $action;
		$is_new_product_page  = 'post-new.php' === $pagenow;

		if ( 'product' !== $post_type || ( ! $is_edit_product_page && ! $is_new_product_page ) ) {
			return;
		}

		$this->render_promotion_notice(
			'wtrs-product-shipping',
			'shipping_options',
			'Set shipping rates based on <strong>Cart Weight</strong> and <strong>Dimension</strong>',
			'margin-inline:10px;',
			true,
			array(
				'default' => 'Get Started',
				'loading' => 'Starting...',
			)
		);
	}

	/**
	 * Render promotion notice in the product category create form.
	 *
	 * @return void
	 */
	public function render_product_category_add_notice() {
		if ( ! $this->should_show_promotion( 'product_category_options' ) ) {
			return;
		}

		$this->render_promotion_notice(
			'wtrs-product-category',
			'product_category_options',
			'Add Shipping Rule on the specific Products or Product Category',
			'display:none;',
			true,
			array(
				'default' => 'Start Now',
				'loading' => 'Starting...',
			),
		);

		ob_start();
		?>
		<script>
			jQuery( function( $ ) {
				$(document).ready(function() {
					$( '#wtrs-product-category' ).insertAfter( '#addtag').slideDown(300);
				});
			} );
		</script>
		<?php
		echo ob_get_clean(); // phpcs:ignore
	}

	/**
	 * Insert a promotion field after the Shipping location(s) setting.
	 *
	 * @param array $settings WooCommerce general settings.
	 * @return array
	 */
	public function register_general_shipping_location_notice( $settings ) {
		if ( ! is_array( $settings ) || ! $this->should_show_promotion( 'general_shipping_location' ) ) {
			return $settings;
		}

		$notice = array(
			'title' => '',
			'type'  => 'wtrs_promotion_notice',
			'id'    => 'wtrs-general-shipping-location',
		);

		$updated_settings = array();

		foreach ( $settings as $setting ) {
			$updated_settings[] = $setting;

			if ( 'woocommerce_specific_ship_to_countries' === ( $setting['id'] ?? '' ) ) {
				$updated_settings[] = $notice;
			}
		}

		return $updated_settings;
	}

	/**
	 * Insert a promotion field after the Dimensions unit setting.
	 *
	 * @param array $settings WooCommerce products settings.
	 * @return array
	 */
	public function register_product_dimensions_notice( $settings ) {
		if ( ! is_array( $settings ) || ! $this->should_show_promotion( 'product_dimensions_unit' ) ) {
			return $settings;
		}

		$notice = array(
			'title' => '',
			'type'  => 'wtrs_promotion_notice',
			'id'    => 'wtrs-product-dimensions-unit',
		);

		$updated_settings = array();

		foreach ( $settings as $setting ) {
			$updated_settings[] = $setting;

			if ( 'woocommerce_dimension_unit' === ( $setting['id'] ?? '' ) ) {
				$updated_settings[] = $notice;
			}
		}

		return $updated_settings;
	}

	/**
	 * Render the WooCommerce settings promotion row.
	 *
	 * @param array $field Custom field definition.
	 * @return void
	 */
	public function render_settings_promotion_field( $field ) {

		$field_id = $field['id'] ?? '';

		if ( 'wtrs-general-shipping-location' === $field_id ) {
			$message = 'Set Shipping Rules for Specific Location';
			$type    = 'general_shipping_location';
			$id      = 'wtrs-general-shipping-location';
			$style   = 'width:400px;';
			$labels  = array(
				'default' => 'Quick Setup',
				'loading' => 'Setting up...',
			);
		} elseif ( 'wtrs-product-dimensions-unit' === $field_id ) {
			$message = 'Set shipping rates based on <strong>cart weight</strong> and <strong>dimension</strong>';
			$type    = 'product_dimensions_unit';
			$id      = 'wtrs-product-dimensions-unit';
			$style   = 'width:400px;';
			$labels  = array(
				'default' => 'Get Started',
				'loading' => 'Starting...',
			);
		} else {
			return;
		}

		?>
		<tr valign="top">
			<th scope="row"></th>
			<td>
				<?php
				$this->render_promotion_notice(
					$id,
					$type,
					$message,
					$style,
					true,
					$labels
				);
				?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render promotion notice at the top of the WooCommerce orders page.
	 *
	 * @return void
	 */
	public function render_orders_page_notice() {
		if ( ! $this->should_show_promotion( 'orders_page' ) || ! $this->is_orders_page_screen() ) {
			return;
		}

		$this->render_promotion_notice(
			'wtrs-orders-page',
			'orders_page',
			'Want to increase order value? Add extra charges to every orders by adding shipping rules',
			'margin:12px 0;',
			false,
			array(
				'default' => 'Start Now',
				'loading' => 'Starting...',
			)
		);
	}

	/**
	 * Render promotion notice at the top of the WooCommerce shipping page.
	 *
	 * @return void
	 */
	public function render_shipping_page_notice() {

		global $pagenow;
		$page = sanitize_text_field( wp_unslash( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab  = sanitize_text_field( wp_unslash( $_GET['tab'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if (
			! $this->should_show_promotion( 'shipping_page' ) ||
			'admin.php' !== $pagenow ||
			'shipping' !== $tab ||
			'wc-settings' !== $page
		) {
			return;
		}

		$this->render_promotion_notice(
			'wtrs-shipping-page',
			'shipping_page',
			'Automatically calculate shipping rates based on 30+ smart conditions',
			'margin:12px 0;display:none;',
			false,
			array(
				'default' => 'Activate Rules',
				'loading' => 'Activating...',
			)
		);
		ob_start();
		?>
		<script>
			jQuery( function( $ ) {
				$(document).ready(function() {
					$( '#wtrs-shipping-page' ).insertAfter( '.wc-shipping-zones-heading' ).slideDown(300);
				});
			} );
		</script>
		<?php
		echo ob_get_clean(); // phpcs:ignore
	}

	/**
	 * Determine whether the current admin screen is a WooCommerce orders list.
	 *
	 * @return boolean
	 */
	private function is_orders_page_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		if ( ! $screen || empty( $screen->id ) ) {
			return false;
		}

		$screen_ids = array( 'edit-shop_order', 'woocommerce_page_wc-orders', 'admin_page_wc-orders' );

		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screen_ids[] = wc_get_page_screen_id( 'shop_order' );
		}

		return in_array( $screen->id, array_filter( array_unique( $screen_ids ) ), true );
	}

	/**
	 * Render a reusable promotion notice block.
	 *
	 * @param string  $id      Notice DOM id.
	 * @param string  $type    Promotion type.
	 * @param string  $message Notice message.
	 * @param string  $style   Inline wrapper style.
	 * @param boolean $inline Whether the notice should use inline positioning classes.
	 * @param array   $button_labels Button text overrides.
	 * @return void
	 */
	private function render_promotion_notice( $id, $type, $message, $style = '', $inline = true, $button_labels = array() ) {
		$GLOBALS['wtrs_promotion'][ $type ] = true;

		$button_labels = wp_parse_args(
			is_array( $button_labels ) ? $button_labels : array(),
			array(
				'default' => 'Start Now',
				'loading' => 'Starting...',
			)
		);

		$classes = array( 'notice', 'notice-info', 'is-dismissible', 'wtrs-promotion-notice' );

		if ( $inline ) {
			$classes[] = 'inline';
		}

		ob_start();
		?>

		<div 
			id="<?php echo esc_attr( $id ); ?>" 
			class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" 
			data-type="<?php echo esc_attr( $type ); ?>"
			style="<?php echo esc_attr( $style ); ?>"
		>
			<div style="padding-block:12px;display:flex;gap:1rem;align-items:center;">
				<span>
					<?php echo wp_kses_post( $message ); ?>
				</span>
				<a
					href="#"
					class="button button-secondary wtrs-promotion-install-link"
					role="button"
					data-default-label="<?php echo esc_attr( $button_labels['default'] ); ?>"
					data-loading-label="<?php echo esc_attr( $button_labels['loading'] ); ?>"
				>
					<?php echo esc_html( $button_labels['default'] ); ?>
				</a>
			</div>
			<button type="button" class="notice-dismiss">
				<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'optin' ); ?></span>
			</button>
		</div>
		<?php $this->echo_dismiss_notice_js( '#' . $id ); ?>
		<?php $this->echo_install_notice_js( '#' . $id ); ?>
		<?php
		echo ob_get_clean(); // phpcs:ignore
	}

	/**
	 * Notice dismiss js
	 *
	 * @param string $id Notice ID.
	 * @return void
	 */
	private function echo_dismiss_notice_js( $id ) {
		ob_start();
		?>
		<script>
			jQuery( function( $ ) {
				$( document ).on( 'click', '<?php echo esc_js( $id ); ?> .notice-dismiss', function() {
					var $notice = $( this ).closest( '<?php echo esc_js( $id ); ?>' );

					if ( ! $notice.length || 'true' === $notice.attr( 'data-dismissed' ) ) {
						return;
					}

					$notice.slideUp( 300, function() {
						$notice.remove();
					} ).attr( 'data-dismissed', 'true' );

					$.ajax( {
						url: <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
						type: 'POST',
						data: {
							action: 'wtrs_dismiss_promotion',
							nonce: <?php echo wp_json_encode( wp_create_nonce( 'wtrs_promotion_nonce' ) ); ?>,
							type: $notice.data( 'type' ) || ''
						}
					} )
				} );
			} );
		</script>
		<?php
		echo ob_get_clean(); // phpcs:ignore
	}

	/**
	 * Notice install js.
	 *
	 * @param string $id Notice ID.
	 * @return void
	 */
	private function echo_install_notice_js( $id ) {
		ob_start();
		?>
		<script>
			jQuery( function( $ ) {
				$( document ).on( 'click', '<?php echo esc_js( $id ); ?> .wtrs-promotion-install-link', function( event ) {
					var $button = $( this );
					var $notice = $button.closest( '<?php echo esc_js( $id ); ?>' );
					var defaultErrorMessage = <?php echo wp_json_encode( esc_html__( 'Failed to install WowShipping.', 'optin' ) ); ?>;

					event.preventDefault();

					if ( ! $notice.length || 'true' === $button.attr( 'aria-disabled' ) ) {
						return;
					}

					$button.attr( 'aria-disabled', 'true' )
					.addClass( 'button-disabled' )
					.text($button.data( 'loading-label' ))

					$.ajax( {
						url: <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
						type: 'POST',
						dataType: 'json',
						data: {
							action: 'wtrs_install_promotion_plugin',
							nonce: <?php echo wp_json_encode( wp_create_nonce( 'wtrs_promotion_nonce' ) ); ?>
						}
					} ).done( function( response ) {
						if ( ! response || ! response.success || ! response.data ) {
							$button.attr( 'aria-disabled', 'false' ).removeClass( 'button-disabled' ).text( $button.data( 'default-label' ) );
							window.alert( defaultErrorMessage );
							return;
						}
						window.location.href = "<?php echo esc_js( $this->get_dashboard_url() ); ?>";
					} ).fail( function( xhr ) {
						var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
							? xhr.responseJSON.data.message
							: defaultErrorMessage;

						$button.attr( 'aria-disabled', 'false' ).removeClass( 'button-disabled' ).text( $button.data( 'default-label' ) );
						window.alert( message );
					} );
				} );
			} );
		</script>
		<?php
		echo ob_get_clean(); // phpcs:ignore
	}

	/**
	 * Get the dashboard URL for the promoted plugin.
	 *
	 * @return string
	 */
	private function get_dashboard_url() {
		return admin_url( 'admin.php?page=wtrs-dashboard#overview' );
	}

	/**
	 * Installs and activates the promoted plugin.
	 *
	 * @return string|false
	 */
	public function install_and_active_plugin() {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';

		if ( is_plugin_active( self::PROMOTED_PLUGIN_FILE ) ) {
			return 'active';
		}

		if ( ! file_exists( WP_PLUGIN_DIR . '/' . self::PROMOTED_PLUGIN_FILE ) ) {
			if ( ! $this->download_plugin( self::PROMOTED_PLUGIN_FILE, self::PROMOTED_PLUGIN_SLUG ) ) {
				return false;
			}
		}

		$res = activate_plugin( self::PROMOTED_PLUGIN_FILE );

		return is_wp_error( $res ) ? false : 'installed';
	}

	/**
	 * Installs a plugin based on the provided plugin file and slug.
	 *
	 * This function is expected to handle the logic required to install a plugin,
	 * such as downloading, unpacking, and activating the plugin using the provided
	 * plugin file and slug.
	 *
	 * @param string $plugin The plugin file path or identifier (e.g., 'plugin-directory/plugin-file.php').
	 * @param string $slug   The plugin slug (typically the directory name of the plugin).
	 */
	private function download_plugin( $plugin, $slug ) {
		include ABSPATH . 'wp-admin/includes/plugin-install.php';
		include ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		if ( ! class_exists( 'Plugin_Upgrader' ) ) {
			include ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
		}
		if ( ! class_exists( 'WP_Ajax_Upgrader_Skin' ) ) {
			include ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
		}

		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => $slug,
				'fields' => array(
					'short_description' => false,
					'sections'          => false,
					'requires'          => false,
					'rating'            => false,
					'ratings'           => false,
					'downloaded'        => false,
					'last_updated'      => false,
					'added'             => false,
					'tags'              => false,
					'compatibility'     => false,
					'homepage'          => false,
					'donate_link'       => false,
				),
			)
		);

		if ( is_wp_error( $api ) ) {
			return false;
		}

		$upgrader       = new \Plugin_Upgrader( new \WP_Ajax_Upgrader_Skin() );
		$install_result = $upgrader->install( $api->download_link );

		return is_wp_error( $install_result ) || false === $install_result ? false : true;
	}
}
