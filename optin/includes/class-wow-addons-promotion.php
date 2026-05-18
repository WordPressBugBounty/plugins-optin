<?php // phpcs:ignore
/**
 * Initialization Action.
 *
 * @package OPTN // CHANGE THIS.
 */
namespace OPTN\Includes; // CHANGE THIS.

defined( 'ABSPATH' ) || exit;

/**
 * Initialization class.
 */
class WowAddonsPromotion {

	private const VERSION              = '10'; // Cache buster.
	private const PRODUCTS_MENU_SLUG   = 'prad-promo-products-options';
	private const PROMOTED_PLUGIN_SLUG = 'product-addons';
	private const PROMOTED_PLUGIN_FILE = 'product-addons/product-addons.php';

	/**
	 * Setup class.
	 */
	public function __construct() {
		add_filter( 'prad_promo_promotion_hooks', array( $this, 'load' ) );
		add_action( 'plugins_loaded', array( $this, 'run_promotions' ) );
	}

	/**
	 * Run promotions.
	 *
	 * @return void
	 */
	public function run_promotions() {
		if ( ! class_exists( '\WooCommerce' ) ||
			defined( 'PRAD_VER' )
		) {
			return;
		}

		if ( $GLOBALS['prad_promo_promotion']['init'] ?? false ) {
			return;
		}

		$GLOBALS['prad_promo_promotion'] = array(
			'init' => true,
		);

		$hooks = apply_filters( 'prad_promo_promotion_hooks', array() );

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
			add_action( 'wp_ajax_prad_promo_dismiss_promotion', array( $this, 'ajax_dismiss_promotion' ) );
			add_action( 'wp_ajax_prad_promo_install_promotion_plugin', array( $this, 'ajax_install_promotion_plugin' ) );

			// Promotions.
			// ------------------.

			// Add Options Tab.
			add_filter( 'woocommerce_product_data_tabs', array( $this, 'register_product_options_tab' ) );
			add_action( 'woocommerce_product_data_panels', array( $this, 'render_product_options_panel' ) );

			// Variation tab notice.
			add_action( 'woocommerce_product_data_panels', array( $this, 'render_variations_notice' ) );

			// Products submenu modal.
			add_action( 'admin_menu', array( $this, 'add_products_submenu' ), 9999 );
			add_action( 'admin_footer', array( $this, 'render_products_submenu_modal' ) );
		};

		return $callbacks;
	}

	/**
	 * Add the promotional submenu item under Products.
	 *
	 * @return void
	 */
	public function add_products_submenu() {
		add_submenu_page(
			'woocommerce',
			'Products Options',
			'Products Options',
			'edit_posts',
			self::PRODUCTS_MENU_SLUG,
			'__return_null',
			6
		);

		add_submenu_page(
			'edit.php?post_type=product',
			'Products Options',
			'Products Options',
			'edit_posts',
			self::PRODUCTS_MENU_SLUG,
			'__return_null',
			6
		);
	}

	/**
	 * Register a promotion tab in the WooCommerce product data metabox.
	 *
	 * @param array $tabs Existing product data tabs.
	 * @return array
	 */
	public function register_product_options_tab( $tabs ) {
		if ( ! is_array( $tabs ) || ! $this->should_show_promotion( 'add_product_options' ) ) {
			return $tabs;
		}

		$tabs['prad-promo-add-product-options'] = array(
			'label'    => 'Add Product Options',
			'target'   => 'prad-promo-add-product-options',
			'class'    => array( 'hide_if_grouped' ),
			'priority' => 65,
		);

		return $tabs;
	}

	/**
	 * Render the promotion panel for the product options tab.
	 *
	 * @return void
	 */
	public function render_product_options_panel() {
		if ( ! $this->should_show_promotion( 'add_product_options' ) ) {
			return;
		}

		?>
		<div id="prad-promo-add-product-options" class="panel woocommerce_options_panel">
			<?php
			$this->render_promotion_notice(
				'prad-promo-add-product-options',
				'add_product_options',
				'
					<div style="font-weight:bold;margin-bottom:12px;font-size:large;">
						Create Unlimited Product Options in Minutes
					</div>
					<div style=""margin-bottom:12px;">
						Go beyond default variations, add flexible fields, pricing, and start selling customizable products
					</div>
					<ul style="padding-left: 1.5rem;">
						<li>
							<span style="font-weight:bold;">
								&#10003;&nbsp;
							</span>
							Add as many product fields and option sets as you need.
						</li>
						<li>
							<span style="font-weight:bold;">
								&#10003; &nbsp;
							</span>
							Show or hide fields and add additional charges.
						</li>
						<li>
							<span style="font-weight:bold;">
								&#10003; &nbsp;
							</span>
							Seamlessly add options to simple, variable, or grouped products.
						</li>
					</ul>
				',
				'margin-inline: 20px;',
				true,
				array(
					'default' => 'Start Now',
					'loading' => 'Starting...',
				),
				'align-items:start;flex-direction:column;'
			);
			?>
		</div>
		<?php
	}

	/**
	 * Render promotion notice at the top of the variations tab.
	 *
	 * @return void
	 */
	public function render_variations_notice() {
		if ( ! $this->should_show_promotion( 'variations_tab' ) ) {
			return;
		}

		?>
		<div id="prad-promo-variations-tab-container" style="display:none;">
		<?php
		ob_start();
		?>
		<script>
			jQuery( function( $ ) {
				$(document).ready(function() {
					const $notice = $( '#prad-promo-variations-tab' );
					const $panel = $( '#variable_product_options_inner' );

					if ( ! $notice.length || ! $panel.length ) {
						return;
					}

					$notice.prependTo( $panel ).show();
				});
			} );
		</script>
		<?php
		echo ob_get_clean(); // phpcs:ignore

		$this->render_promotion_notice(
			'prad-promo-variations-tab',
			'variations_tab',
			'Create Unlimited Product Variations in a Few Clicks',
			'margin:16px 12px 16px 12px;border: 1px solid #c3c4c7;border-left-width: 4px;border-left-color: #72aee6;width:fit-content;',
			true,
			array(
				'default' => 'Configure Now',
				'loading' => 'Configuring...',
			)
		);
		?>
		</div>
		<?php
	}

	/**
	 * Render the promotional modal for the Products submenu item.
	 *
	 * @return void
	 */
	public function render_products_submenu_modal() {
		if ( ! is_admin() || ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$submenu_url  = admin_url( 'edit.php?post_type=product&page=' . self::PRODUCTS_MENU_SLUG );
		$products_url = admin_url( 'edit.php?post_type=product' );
		$learn_more   = 'https://wordpress.org/plugins/product-addons/';
		$nonce        = wp_create_nonce( 'prad_promo_promotion_nonce' );
		$img_src      = OPTN_URL . '/assets/images/wowaddons-modal.png'; // CHANGE THIS.
		?>
		<style>
			.prad-promo-products-modal {
				position: fixed;
				inset: 0;
				z-index: 99999;
				display: none;
				align-items: center;
				justify-content: center;
				padding: 24px;
				background: rgba(15, 23, 42, 0.62);
			}

			.prad-promo-products-modal.is-active {
				display: flex;
			}

			body.prad-promo-products-modal-open {
				overflow: hidden;
			}

			.prad-promo-products-modal *,
			.prad-promo-products-modal *::before,
			.prad-promo-products-modal *::after {
				box-sizing: border-box;
			}

			.prad-promo-products-modal__dialog {
				position: relative;
				width: min(100%, 470px);
				padding: 28px 28px 22px;
				border-radius: 22px;
				background: linear-gradient(180deg, #ffffff 0%, #f6f9ff 100%);
				box-shadow: 0 28px 80px rgba(15, 23, 42, 0.28);
				text-align: center;
			}

			.prad-promo-products-modal__close {
				position: absolute;
				top: 18px;
				right: 18px;
				border: 0;
				background: transparent;
				color: #64748b;
				cursor: pointer;
				font-size: 24px;
				line-height: 1;
			}

			.prad-promo-products-modal__close:hover,
			.prad-promo-products-modal__close:focus {
				color: #0f172a;
				outline: none;
			}

			.prad-promo-products-modal__title {
				margin: 0 0 10px;
				color: #16213e;
				font-size: 34px;
				line-height: 1.08;
				font-weight: 800;
				letter-spacing: -0.03em;
			}

			.prad-promo-products-modal__text {
				max-width: 330px;
				margin: 0 auto 20px;
				color: #64748b;
				font-size: 15px;
				line-height: 1.55;
			}

			.prad-promo-products-modal__list {
				margin: 0 auto 24px;
				padding: 0;
				/* max-width: 320px; */
				list-style: none;
				text-align: left;
			}

			.prad-promo-products-modal__list li {
				display: flex;
				align-items: center;
				gap: 12px;
				padding: 11px 0;
				border-bottom: 1px solid #e2e8f0;
				color: #1e293b;
				font-size: 14px;
				font-weight: 600;
			}

			.prad-promo-products-modal__list li:last-child {
				border-bottom: 0;
			}

			.prad-promo-products-modal__check {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				width: 20px;
				height: 20px;
				border-radius: 50%;
				background: #dcfce7;
				color: #16a34a;
				font-size: 12px;
				font-weight: 700;
				flex: 0 0 20px;
			}

			.prad-promo-products-modal__button {
				display: flex;
				align-items: center;
				justify-content: center;
				gap: 10px;
				width: 100%;
				padding: 15px 18px;
				border: 0;
				border-radius: 10px;
				background: linear-gradient(180deg, #3b82f6 0%, #2563eb 100%);
				color: #fff;
				font-size: 15px;
				font-weight: 700;
				cursor: pointer;
				box-shadow: 0 14px 28px rgba(37, 99, 235, 0.24);
			}

			.prad-promo-products-modal__button:hover,
			.prad-promo-products-modal__button:focus {
				background: linear-gradient(180deg, #2563eb 0%, #1d4ed8 100%);
				color: #fff;
				outline: none;
			}

			.prad-promo-products-modal__button[aria-disabled="true"] {
				opacity: 0.75;
				cursor: wait;
			}

			.prad-promo-products-modal__learn {
				display: inline-block;
				margin-top: 12px;
				color: #2563eb;
				font-size: 13px;
				font-weight: 600;
				text-decoration: underline;
			}

			.prad-promo-products-modal__footer {
				display: grid;
				grid-template-columns: repeat(3, minmax(0, 1fr));
				gap: 10px;
				margin-top: 18px;
				padding: 12px;
				border: 1px solid #e2e8f0;
				border-radius: 14px;
				background: rgba(255, 255, 255, 0.85);
			}

			.prad-promo-products-modal__meta {
				display: flex;
				align-items: center;
				justify-content: center;
				gap: 6px;
				color: #64748b;
				font-size: 12px;
				font-weight: 600;
			}

			.prad-promo-products-modal__meta .dashicons {
				width: 16px;
				height: 16px;
				font-size: 16px;
			}

			@media (max-width: 520px) {
				.prad-promo-products-modal {
					padding: 14px;
				}

				.prad-promo-products-modal__dialog {
					padding: 24px 18px 18px;
					border-radius: 18px;
				}

				.prad-promo-products-modal__title {
					font-size: 28px;
				}

				.prad-promo-products-modal__footer {
					grid-template-columns: 1fr;
				}

			}
			.prad-promo-products-modal__img {
				max-width: 100%;
				height: auto;
				margin-bottom: 24px;
			}
		</style>

		<div id="prad-promo-products-modal" class="prad-promo-products-modal" aria-hidden="true">
			<div class="prad-promo-products-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="prad-promo-products-modal-title">
				<button type="button" class="prad-promo-products-modal__close" aria-label="Close">&times;</button>
				<img class="prad-promo-products-modal__img"  src="<?php echo esc_attr( $img_src ); ?>" />
				<h2 id="prad-promo-products-modal-title" class="prad-promo-products-modal__title">
					Create Unlimited Product Options in Minutes
				</h2>
				<p class="prad-promo-products-modal__text">
					Go beyond default variations, add flexible fields, pricing, and start selling customizable products
				</p>
				<ul class="prad-promo-products-modal__list">
					<li><span class="prad-promo-products-modal__check">&#10003;</span><span>
						Add as many product fields and option sets as you need.
					</span></li>
					<li><span class="prad-promo-products-modal__check">&#10003;</span><span>
						Show or hide fields and add additional charges.
					</span></li>
					<li><span class="prad-promo-products-modal__check">&#10003;</span><span>
						Seamlessly add options to simple, variable, or grouped products.
					</span></li>
				</ul>
				<button
					type="button"
					class="prad-promo-products-modal__button"
					data-default-label="Start Creating Custom Options"
					data-loading-label="Starting..."
				>
					<span class="dashicons dashicons-admin-tools"></span>
					<span>Start Creating Custom Options</span>
				</button>
			</div>
		</div>

		<script>
			jQuery( function( $ ) {
				const modal = $( '#prad-promo-products-modal' );
				const dialog = modal.find( '.prad-promo-products-modal__dialog' );
				const button = modal.find( '.prad-promo-products-modal__button' );
				const buttonText = button.find( 'span' ).last();
				const submenuSlug = <?php echo wp_json_encode( self::PRODUCTS_MENU_SLUG ); ?>;
				const fallbackUrl = <?php echo wp_json_encode( $products_url ); ?>;
				const nonce = <?php echo wp_json_encode( $nonce ); ?>;
				const dashboardUrl = <?php echo wp_json_encode( $this->get_dashboard_url() ); ?>;
				const defaultErrorMessage = <?php echo wp_json_encode( 'Failed to install WowAddons.' ); ?>;
				let openedFromModalPage = window.location.search.indexOf( 'page=<?php echo esc_js( self::PRODUCTS_MENU_SLUG ); ?>' ) !== -1;

				function openModal() {
					modal.addClass( 'is-active' ).attr( 'aria-hidden', 'false' );
					$( 'body' ).addClass( 'prad-promo-products-modal-open' );
				}

				function closeModal() {
					modal.removeClass( 'is-active' ).attr( 'aria-hidden', 'true' );
					$( 'body' ).removeClass( 'prad-promo-products-modal-open' );

					if ( openedFromModalPage ) {
						window.location.href = fallbackUrl;
					}
				}

				$( document ).on( 'click', '#adminmenu a[href*="page=' + submenuSlug + '"], .wp-submenu a[href*="page=' + submenuSlug + '"]', function( event ) {
					event.preventDefault();
					event.stopImmediatePropagation();
					openedFromModalPage = false;
					openModal();
					return false;
				} );

				modal.on( 'click', function( event ) {
					if ( ! dialog.is( event.target ) && 0 === dialog.has( event.target ).length ) {
						closeModal();
					}
				} );

				modal.find( '.prad-promo-products-modal__close' ).on( 'click', function() {
					closeModal();
				} );

				$( document ).on( 'keydown', function( event ) {
					if ( 'Escape' === event.key && modal.hasClass( 'is-active' ) ) {
						closeModal();
					}
				} );

				button.on( 'click', function( event ) {
					event.preventDefault();

					if ( 'true' === button.attr( 'aria-disabled' ) ) {
						return;
					}

					button.attr( 'aria-disabled', 'true' );
					buttonText.text( button.data( 'loading-label' ) );

					$.ajax( {
						url: <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
						type: 'POST',
						dataType: 'json',
						data: {
							action: 'prad_promo_install_promotion_plugin',
							nonce: nonce
						}
					} ).done( function( response ) {
						if ( ! response || ! response.success ) {
							button.attr( 'aria-disabled', 'false' );
							buttonText.text( button.data( 'default-label' ) );
							window.alert( defaultErrorMessage );
							return;
						}

						window.location.href = dashboardUrl;
					} ).fail( function( xhr ) {
						const message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
							? xhr.responseJSON.data.message
							: defaultErrorMessage;

						button.attr( 'aria-disabled', 'false' );
						buttonText.text( button.data( 'default-label' ) );
						window.alert( message );
					} );
				} );

				if ( openedFromModalPage ) {
					openModal();
				}
			} );
		</script>
		<?php
	}

	/**
	 * Ajax handler for dismissing promotion notice.
	 *
	 * @return void
	 */
	public function ajax_dismiss_promotion() {
		check_ajax_referer( 'prad_promo_promotion_nonce', 'nonce' );

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
		check_ajax_referer( 'prad_promo_promotion_nonce', 'nonce' );

		$plugin_exists = file_exists( WP_PLUGIN_DIR . '/' . self::PROMOTED_PLUGIN_FILE );

		if ( ! $plugin_exists && ! current_user_can( 'install_plugins' ) ) {
			wp_send_json_error(
				array(
					'message' => 'You are not allowed to install plugins.',
				)
			);
		}

		if ( $plugin_exists && ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error(
				array(
					'message' => 'You are not allowed to activate plugins.',
				)
			);
		}

		$result = $this->install_and_active_plugin();

		if ( false === $result ) {
			wp_send_json_error(
				array(
					'message' => 'Failed to install WowAddons.',
				)
			);
		}

		wp_send_json_success(
			array(
				'status'        => $result,
				'dashboard_url' => $this->get_dashboard_url(),
				'message'       => 'WowAddons installed successfully.',
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
		return 'prad_promo_promotion_is_closed_' . self::VERSION . '_' . $type;
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
		$ran_once = boolval( $GLOBALS['prad_promo_promotion'][ $type ] ?? false );
		if ( $ran_once ) {
			return false;
		}
		return get_transient( $this->get_dismiss_key( $type ) ) !== 'yes';
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
	private function render_promotion_notice( $id, $type, $message, $style = '', $inline = true, $button_labels = array(), $container_cls = '' ) {
		$GLOBALS['prad_promo_promotion'][ $type ] = true;

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
			<div style="padding-block:12px;display:flex;gap:1rem;align-items:center;<?php echo esc_attr( $container_cls ); ?>">
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
				<span class="screen-reader-text">Dismiss this notice</span>
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
							action: 'prad_promo_dismiss_promotion',
							nonce: <?php echo wp_json_encode( wp_create_nonce( 'prad_promo_promotion_nonce' ) ); ?>,
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
					var defaultErrorMessage = <?php echo wp_json_encode( 'Failed to install WowAddons.' ); ?>;

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
							action: 'prad_promo_install_promotion_plugin',
							nonce: <?php echo wp_json_encode( wp_create_nonce( 'prad_promo_promotion_nonce' ) ); ?>
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
		return admin_url( 'admin.php?page=prad-dashboard#dashboard' );
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
