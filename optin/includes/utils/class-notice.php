<?php //phpcs:ignore

namespace OPTN\Includes\Utils;

use OPTN\Includes\Xpo;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin Notice
 */
class Notice {


	/**
	 * Notice version
	 *
	 * @var string
	 */
	private $notice_version = 'v103';

	/**
	 * Notice JS/CSS applied
	 *
	 * @var boolean
	 */
	private $notice_js_css_applied = false;


	/**
	 * Notice Constructor
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'admin_notices_callback' ) );
		add_action( 'admin_init', array( $this, 'set_dismiss_notice_callback' ) );

		// REST API routes.
		add_action( 'rest_api_init', array( $this, 'register_rest_route' ) );

		// Woocommerce Install Action.
		add_action( 'wp_ajax_optn_install', array( $this, 'install_activate_plugin' ) );
	}


	/**
	 * Registers REST API endpoints.
	 *
	 * @return void
	 */
	public function register_rest_route() {
		$routes = array(
			// Hello Bar.
			array(
				'endpoint'            => 'hello_bar',
				'methods'             => 'POST',
				'callback'            => array( $this, 'hello_bar_callback' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			),
		);

		foreach ( $routes as $route ) {
			register_rest_route(
				'optn/v1',
				$route['endpoint'],
				array(
					array(
						'methods'             => $route['methods'],
						'callback'            => $route['callback'],
						'permission_callback' => $route['permission_callback'],
					),
				)
			);
		}
	}

	/**
	 * Hellobar config
	 *
	 * @return array
	 */
	public static function get_hellobar_config() {
		return array(
			'optn_helloBar_spring_sale_2026_1' => Xpo::get_transient_without_cache( 'optn_helloBar_spring_sale_2026_1' ),
		);
	}

	/**
	 * Handles Hello Bar dismissal action via REST API .
	 *
	 * @param \WP_REST_Request $request REST request object .
	 * @return \WP_REST_Response
	 */
	public function hello_bar_callback( \WP_REST_Request $request ) {
		$request_params = $request->get_params();
		$type           = isset( $request_params['type'] ) ? $request_params['type'] : '';
		$id             = isset( $request_params['id'] ) ? $request_params['id'] : '';

		if ( 'hello_bar' === $type && ! empty( $id ) ) {
			Xpo::set_transient_without_cache( $id, 'hide', 1296000 );
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Hello Bar Action performed', 'optin' ),
			),
			200
		);
	}

	/**
	 * Set Notice Dismiss Callback
	 *
	 * @return void
	 */
	public function set_dismiss_notice_callback() {

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['wpnonce'] ?? '' ) ), 'optn-nonce' ) ) {
			return;
		}

		$durbin_key = sanitize_text_field( wp_unslash( $_GET['optn_durbin_key'] ?? '' ) );

		// Durbin notice dismiss.
		if ( ! empty( $durbin_key ) ) {
			Xpo::set_transient_without_cache( 'optn_durbin_notice_' . $durbin_key, 'off' );

			if ( 'get' === sanitize_text_field( wp_unslash( $_GET['optn_get_durbin'] ?? '' ) ) ) {
				DurbinClient::send( DurbinClient::ACTIVATE_ACTION );
			}
		}

		// Install notice dismiss.
		$install_key = sanitize_text_field( wp_unslash( $_GET['optn_install_key'] ?? '' ) );
		if ( ! empty( $install_key ) ) {
			Xpo::set_transient_without_cache( 'optn_install_notice_' . $install_key, 'off' );
		}

		$notice_key = sanitize_text_field( wp_unslash( $_GET['disable_optn_notice'] ?? '' ) );
		if ( ! empty( $notice_key ) ) {
			$interval = (int) sanitize_text_field( wp_unslash( $_GET['optn_interval'] ?? '' ) );
			if ( ! empty( $interval ) ) {
				Xpo::set_transient_without_cache( 'optn_get_pro_notice_' . $notice_key, 'off', $interval );
			} else {
				Xpo::set_transient_without_cache( 'optn_get_pro_notice_' . $notice_key, 'off' );
			}
		}
	}

	/**
	 * Admin Notices Callback
	 *
	 * @return void
	 */
	public function admin_notices_callback() {
		$this->optn_dashboard_notice_callback();
		$this->optn_dashboard_durbin_notice_callback();
		$this->optn_dashboard_content_notice();
	}

	/**
	 * Admin Dashboard Notice Callback
	 *
	 * @return void
	 */
	public function optn_dashboard_notice_callback() {
		$this->optn_dashboard_banner_notice();
	}

		/**
		 * Dashboard Content Notice
		 *
		 * @return void
		 */
	public function optn_dashboard_content_notice() {

		$content_notices = array(
			array(
				'key'                => 'optn_dashboard_content_notice_spring_sale_v1',
				'start'              => '2026-03-16 00:00 Asia/Dhaka',
				'end'                => '2026-03-25 23:59 Asia/Dhaka',
				'url'                => Xpo::generate_utm_link(
					array(
						'utmKey' => 'spring',
					)
				),
				'visibility'         => ! Xpo::is_lc_active(),
				'content_heading'    => __( 'Spring Sale:', 'optin' ),
				'content_subheading' => __( 'WowOptin offers are live - Enjoy %s off on WowOptin Pro.', 'optin' ),
				'discount_content'   => ' up to 60% OFF',
				'border_color'       => '#f97415',
				'icon'               => OPTN_URL . 'assets/images/banners/discount.svg',
				'button_text'        => __( 'Claim Your Discount!', 'optin' ),
				'is_discount_logo'   => true,
			),
			array(
				'key'                => 'optn_dashboard_content_notice_spring_sale_v2',
				'start'              => '2026-03-26 00:00 Asia/Dhaka',
				'end'                => '2026-04-04 23:59 Asia/Dhaka',
				'url'                => Xpo::generate_utm_link(
					array(
						'utmKey' => 'spring',
					)
				),
				'visibility'         => ! Xpo::is_lc_active(),
				'content_heading'    => __( 'Spring Sale:', 'optin' ),
				'content_subheading' => __( 'WowOptin offers are live - Enjoy %s off on WowOptin Pro.', 'optin' ),
				'discount_content'   => ' up to 60% OFF',
				'border_color'       => '#f97415',
				'icon'               => OPTN_URL . 'assets/images/banners/logo.svg',
				'button_text'        => __( 'Claim Your Discount!', 'optin' ),
				'is_discount_logo'   => true,
			),

		);

		$optn_db_nonce = wp_create_nonce( 'optn-nonce' );

		foreach ( $content_notices as $key => $notice ) {
			$notice_key = isset( $notice['key'] ) ? $notice['key'] : $this->notice_version;
			if ( isset( $_GET['disable_optn_notice'] ) && $notice_key === $_GET['disable_optn_notice'] ) {
				continue;
			} else {
				$border_color = $notice['border_color'];

				$current_time = gmdate( 'U' );
				$notice_start = gmdate( 'U', strtotime( $notice['start'] ) );
				$notice_end   = gmdate( 'U', strtotime( $notice['end'] ) );
				if ( $current_time >= $notice_start && $current_time <= $notice_end && $notice['visibility'] ) {
					$notice_transient = Xpo::get_transient_without_cache( 'optn_get_pro_notice_' . $notice_key );

					if ( 'off' !== $notice_transient ) {

						$query_args = array(
							'disable_optn_notice' => $notice_key,
							'optn_db_nonce'       => $optn_db_nonce,
						);
						if ( isset( $notice['repeat_interval'] ) && $notice['repeat_interval'] ) {
							$query_args['optn_interval'] = $notice['repeat_interval'];
						}

						$url = isset( $notice['url'] ) ? $notice['url'] : Xpo::generate_utm_link(
							array(
								'utmKey' => 'content_notice',
							)
						);

						?>

						<style id="optn-notice-css" type="text/css">
							.optn-content-notice-wrapper {
								border: 1px solid #c3c4c7;
								border-left: 3px solid #037fff;
								margin: 15px 0 !important;
								display: flex;
								align-items: center;
								background: #ffffff;
								width: 100%;
								padding: 10px 0;
								position: relative;
								box-sizing: border-box;
							}

							.optn-content-notice-wrapper.notice {
								margin: 10px 0;
								width: calc(100% - 20px);
							}

							.wrap .optn-content-notice-wrapper.notice {
								width: 100%;
							}

							.optn-content-notice-icon {
								margin-left: 15px;
							}

							.optn-content-notice-discout-icon {
								margin-left: 10px;
							}

							.optn-content-notice-icon img {
								max-width: 42px;
								height: 70px;
							}

							.optn-content-notice-discout-icon img {
								height: 70px;
								width: 70px;
							}

							.optn-notice-content-wrapper {
								display: flex;
								flex-direction: column;
								gap: 8px;
								font-size: 14px;
								line-height: 20px;
								margin-left: 15px;
							}

							.optn-content-notice-buttons {
								display: flex;
								align-items: center;
								gap: 15px;
							}

							.optn-content-notice-btn {
								font-weight: 600;
								text-transform: uppercase !important;
								padding: 2px 10px !important;
								background-color: #86a62c;
								border: none !important;
							}

							.optn-content-discount_btn {
								background-color: #ffffff;
								text-decoration: none;
								border: 1px solid #f97415;
								padding: 5px 10px;
								border-radius: 5px;
								font-weight: 500;
								text-transform: uppercase;
								color: #f97415 !important;
							}

							.optn-content-notice-close {
								position: absolute;
								right: 2px;
								top: 5px;
								text-decoration: none;
								color: #b6b6b6;
								font-family: dashicons;
								font-size: 16px;
								line-height: 20px;
							}

							.optn-content-notice-close-icon {
								font-size: 14px;
							}
						</style>
					<div class="optn-content-notice-wrapper notice data_collection_notice" 
					style="border-left: 3px solid <?php echo esc_attr( $border_color ); ?>;"
					> 
						<?php
						if ( $notice['is_discount_logo'] ) {
							?>
								<div class="optn-content-notice-discout-icon"> <img src="<?php echo esc_url( $notice['icon'] ); ?>"/>  </div>
							<?php
						} else {
							?>
								<div class="optn-content-notice-icon"> <img src="<?php echo esc_url( $notice['icon'] ); ?>"/>  </div>
							<?php
						}
						?>
						
						<div class="optn-notice-content-wrapper">
							<div class="">
								<strong><?php printf( esc_html( $notice['content_heading'] ) ); ?> </strong>
						<?php
						printf(
							wp_kses_post( $notice['content_subheading'] ),
							'<strong>' . esc_html( $notice['discount_content'] ) . '</strong>'
						);
						?>
							</div>
							<div class="optn-content-notice-buttons">
							<?php if ( isset( $notice['is_discount_logo'] ) && $notice['is_discount_logo'] ) : ?>
									<a class="optn-content-discount_btn" href="<?php echo esc_url( $url ); ?>" target="_blank">
										<?php echo esc_html( $notice['button_text'] ); ?>
									</a>
								<?php else : ?>
									<a class="optn-content-notice-btn button button-primary" href="<?php echo esc_url( $url ); ?>" target="_blank" style="background-color: <?php echo ! empty( $notice['background_color'] ) ? esc_attr( $notice['background_color'] ) : '#86a62c'; ?>;">
									<?php echo esc_html( $notice['button_text'] ); ?>
										
									</a>
								<?php endif; ?>
							</div>
						</div>
						<a href=
							<?php
							echo esc_url(
								add_query_arg(
									$query_args
								)
							);
							?>
						class="optn-content-notice-close"><span class="optn-content-notice-close-icon dashicons dashicons-dismiss"> </span></a>
					</div>
								<?php
					}
				}
			}
		}
	}

	/**
	 * Dashboard Banner Notice
	 *
	 * @return void
	 */
	public function optn_dashboard_banner_notice() {
		$optn_db_nonce  = wp_create_nonce( 'optn-nonce' );
		$banner_notices = array(
			array(
				'key'                => 'optn_spring_sale_2026_1',
				'start'              => '2026-04-05 00:00 Asia/Dhaka',
				'end'                => '2026-04-14 23:59 Asia/Dhaka', // format YY-MM-DD always set time 23:59 and zone Asia/Dhaka.

				'brand_color'        => '#f97415',

				'left_image'         => OPTN_URL . '/assets/images/banners/banner.png',
				'right_image'        => OPTN_URL . '/assets/images/banners/right.png',
				'bg_image'           => OPTN_URL . '/assets/images/banners/bg.png',
				'text'               => 'Hurry Before It Ends!',
				'countdown_duration' => 259200, // Duration in seconds.
				'countdown_color'    => 'red',
				'url'                => Xpo::generate_utm_link(
					array(
						'utmKey' => 'spring',
					)
				),

				'visibility'         => ! Xpo::is_lc_active(),
			),
		);

		foreach ( $banner_notices as $notice ) {
			$notice_key = isset( $notice['key'] ) ? $notice['key'] : $this->notice_version;
			if ( isset( $_GET['disable_optn_notice'] ) && $notice_key === sanitize_text_field(wp_unslash($_GET['disable_optn_notice'])) ) { // phpcs:ignore
				return;
			}

			$current_time = gmdate( 'U' );
			$notice_start = gmdate( 'U', strtotime( $notice['start'] ) );
			$notice_end   = gmdate( 'U', strtotime( $notice['end'] ) );
			if ( $current_time >= $notice_start && $current_time <= $notice_end && $notice['visibility'] ) {

				$notice_transient = Xpo::get_transient_without_cache( 'optn_get_pro_notice_' . $notice_key );

				if ( 'off' === $notice_transient ) {
					return;
				}

				if ( ! $this->notice_js_css_applied ) {
					$this->optn_banner_notice_js();
					$this->notice_js_css_applied = true;
				}
				$query_args = array(
					'disable_optn_notice' => $notice_key,
					'wpnonce'             => $optn_db_nonce,
				);
				if ( isset( $notice['repeat_interval'] ) && $notice['repeat_interval'] ) {
					$query_args['optn_interval'] = $notice['repeat_interval'];
				}
				?>
				<style type="text/css">
					.optn-notice-wrapper.optn-banner-notice {
						height: auto !important;
						min-height: 90px;
						padding: 0 !important;
						position: relative;
						box-sizing: border-box;
						background-repeat: no-repeat;
						background-size: cover;
						background-position: center;
					}
					.optn-notice-wrapper.optn-banner-notice .optn-banner-link {
						width: 100%;
						text-decoration: none;
						display: block;
					}
					.optn-notice-wrapper.optn-banner-notice .optn-banner-content {
						display: flex;
						justify-content: space-between;
						align-items: center;
						max-width: 1358px;
						margin: 0 auto;
						padding: 10px 16px;
						gap: 16px;
					}
					.optn-notice-wrapper.optn-banner-notice .optn-banner-side-image {
						display: block;
						max-width: 100%;
						height: auto;
					}
					.optn-notice-wrapper.optn-banner-notice .optn-banner-main {
						display: flex;
						flex-direction: column;
						gap: 4px;
						align-items: center;
						justify-content: center;
						font-weight: 700;
						font-size: 28px;
						color: #fff;
						line-height: 32px;
						text-align: center;
					}

					@media screen and (max-width: 1100px) {
						.optn-notice-wrapper.optn-banner-notice .optn-banner-content {
							flex-direction: column;
						}
					}

					@media screen and (max-width: 782px) {
						.optn-notice-wrapper.optn-banner-notice .optn-banner-content {
							justify-content: center;
							padding: 12px 32px 12px 12px;
						}
						.optn-notice-wrapper.optn-banner-notice .optn-banner-main {
							font-size: 22px;
							line-height: 28px;
						}
					}
					@media screen and (max-width: 480px) {
						.optn-notice-wrapper.optn-banner-notice .optn-banner-content {
							padding: 10px 32px 10px 10px;
						}
						.optn-notice-wrapper.optn-banner-notice .optn-banner-main {
							font-size: 18px;
							line-height: 24px;
						}
					}
				</style>
				<div 
					class="optn-notice-wrapper optn-banner-notice notice" 
					style="
						border-left: 3px solid <?php echo esc_attr( $notice['brand_color'] ); ?>;
						background-image: url('<?php echo esc_attr( $notice['bg_image'] ); ?>');
				">
					<a 
						class="wc-dismiss-notice dashicons dashicons-no-alt" 
						style="
							position: absolute;
							top: 1px;
							right: 1px;
							border-radius: 50%;
							background-color: black;
							color: white;
							font-size: 14px;
							display: flex;
							align-items: center;
							justify-content: center;
						"
						aria-label="<?php esc_html_e( 'Close Banner', 'optin' ); ?>"
						href="<?php echo esc_url( add_query_arg( $query_args ) ); ?>">
					</a>

					<a class="optn-banner-link" target="_blank" href="<?php echo esc_url( $notice['url'] ); ?>">
						<div class="optn-banner-content">
							<img class="optn-banner-side-image" loading="lazy" src="<?php echo esc_url( $notice['left_image'] ); ?>" />
							<div class="optn-banner-main">
								<span style="color:black;">
									<?php echo esc_html( $notice['text'] ); ?>
								</span>	
								<div 
									class="optn-notice-countdown" 
									style="
										color: <?php echo esc_attr( $notice['countdown_color'] ); ?>;
									"
									data-notice-key="<?php echo esc_attr( $notice_key . '-countdown' ); ?>" 
									data-duration="<?php echo esc_attr( $notice['countdown_duration'] ); ?>">
									00:00:00:00
								</div>
							</div>
							<img class="optn-banner-side-image" loading="lazy" src="<?php echo esc_url( $notice['right_image'] ); ?>" />
						</div>
					</a>
				</div>
				<?php
			}
		}
	}

	/**
	 * Banner JS
	 *
	 * @return void
	 */
	public function optn_banner_notice_js() {
		?>
		<script type="text/javascript">
			jQuery(function($) {
				'use strict';

				const storagePrefix = 'optn_notice_countdown_';

				const formatCountdown = function(seconds) {
					const days = Math.floor(seconds / 86400);
					const hours = Math.floor((seconds % 86400) / 3600);
					const minutes = Math.floor((seconds % 3600) / 60);
					const secs = seconds % 60;

					return String(days).padStart(2, '0') + ':' + String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
				};

				const parseDurationToSeconds = function(duration) {
					if (typeof duration === 'number' && Number.isFinite(duration) && duration > 0) {
						return Math.floor(duration);
					}

					const durationString = String(duration || '').trim();
					if (/^\d+$/.test(durationString)) {
						return parseInt(durationString, 10);
					}

					return 0;
				};

				const nowInSeconds = function() {
					return Math.floor(Date.now() / 1000);
				};

				$('.optn-notice-countdown').each(function() {
					const countdownElement = $(this);
					const noticeKey = String(countdownElement.data('noticeKey') || '');
					const duration = parseDurationToSeconds(countdownElement.data('duration'));

					if (!noticeKey || duration <= 0) {
						return;
					}

					const storageKey = storagePrefix + noticeKey;
					let endAt = 0;

					try {
						const storedDataRaw = window.localStorage.getItem(storageKey);
						if (storedDataRaw) {
							const storedData = JSON.parse(storedDataRaw);
							if (storedData && parseInt(storedData.duration, 10) === duration) {
								endAt = parseInt(storedData.endAt, 10) || 0;
							}
						}
					} catch (error) {
						endAt = 0;
					}

					const saveTimerState = function(nextEndAt) {
						try {
							window.localStorage.setItem(
								storageKey,
								JSON.stringify({
									endAt: nextEndAt,
									duration: duration,
								})
							);
						} catch (error) {
							// No-op.
						}
					};

					const resetTimer = function(currentTime) {
						endAt = currentTime + duration;
						saveTimerState(endAt);
					};

					const tick = function() {
						const currentTime = nowInSeconds();

						if (endAt <= currentTime) {
							resetTimer(currentTime);
						}

						const remaining = Math.max(endAt - currentTime, 0);
						countdownElement.text(formatCountdown(remaining));
					};

					if (endAt <= nowInSeconds()) {
						resetTimer(nowInSeconds());
					}

					tick();
					window.setInterval(tick, 1000);
				});
			});
		</script>
		<?php
	}


	/**
	 * The Durbin Html
	 *
	 * @return void
	 */
	public function optn_dashboard_durbin_notice_callback() {
		$durbin_key = 'optn_durbin_dc1';

		if (
			isset( $_GET['optn_durbin_key'] ) || // phpcs:ignore
			'off' === Xpo::get_transient_without_cache( 'optn_durbin_notice_' . $durbin_key )
		) {
			return;
		}

		if ( ! $this->notice_js_css_applied ) {
			$this->notice_js_css_applied = true;
		}

		$optn_db_nonce = wp_create_nonce( 'optn-nonce' );

		?>
		<style>
				.optn-consent-box {
					width: 656px;
					padding: 16px;
					border: 1px solid #070707;
					border-left-width: 4px;
					border-radius: 4px;
					background-color: #fff;
					position: relative;
					width: 100%;
					box-sizing: border-box;
				}
				.optn-consent-content {
					display: flex;
					justify-content: flex-start;
					align-items: flex-end;
					gap: 26px;
				}
 
				.optn-consent-text-first {
					font-size: 14px;
					font-weight: 600;
					color: #070707;
				}
				.optn-consent-text-last {
					margin: 4px 0 0;
					font-size: 14px;
					color: #070707;
				}
 
				.optn-consent-accept {
					background-color: #070707;
					color: #fff;
					border: none;
					padding: 6px 10px;
					border-radius: 4px;
					cursor: pointer;
					font-size: 12px;
					font-weight: 600;
					text-decoration: none;
				}
				.optn-consent-accept:hover {
					background-color:rgb(38, 38, 38);
					color: #fff;
				}
			</style>
			<div class="optn-consent-box optn-notice-wrapper notice data_collection_notice">
			<div class="optn-consent-content">
			<div class="optn-consent-text">
			<div class="optn-consent-text-first"><?php esc_html_e( 'Want to help make WowOptin even more awesome?', 'optin' ); ?></div>
			<div class="optn-consent-text-last">
					<?php esc_html_e( 'Allow us to collect diagnostic data and usage information. see ', 'optin' ); ?>
			<a href="https://www.wpxpo.com/data-collection-policy/" target="_blank" ><?php esc_html_e( 'what we collect.', 'optin' ); ?></a>
			</div>
			</div>
			<a
					class="optn-consent-accept"
					href=
					<?php
									echo esc_url(
										add_query_arg(
											array(
												'optn_durbin_key' => $durbin_key,
												'optn_get_durbin' => 'get',
												'wpnonce' => $optn_db_nonce,
											)
										)
									);
					?>
									class="optn-notice-close"
			><?php esc_html_e( 'Accept & Close', 'optin' ); ?></a>
			</div>
			<a href=
				<?php
							echo esc_url(
								add_query_arg(
									array(
										'optn_durbin_key' => $durbin_key,
										'wpnonce'         => $optn_db_nonce,
									)
								)
							);
				?>
				class="optn-notice-close"
				style="
					position: absolute;
					right: 2px;
					top: 5px;
					text-decoration: unset;
					color: #b6b6b6;
					font-family: dashicons;
					font-size: 16px;
					font-style: normal;
					font-weight: 400;
					line-height: 20px;
				"
			>
				<span 
				style="font-size: 14px;"
				class="optn-notice-close-icon dashicons dashicons-dismiss"> </span></a>
			</div>
		<?php
	}

	/**
	 * Plugin Install and Active Action
	 *
	 * @return void
	 */
	public function install_activate_plugin() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpnonce'] ?? '' ) ), 'optn-nonce' ) ) {
			wp_send_json_error( esc_html__( 'Invalid nonce.', 'optin' ) );
		}

		if ( ! isset( $_POST['install_plugin'] ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Invalid request.', 'optin' ) );
		}
		$plugin_slug = sanitize_text_field( wp_unslash( $_POST['install_plugin'] ) );

		Xpo::install_and_active_plugin( $plugin_slug );

		$action = sanitize_text_field( wp_unslash( $_POST['action'] ?? '' ) ); // phpcs:ignore

		if ( wp_doing_ajax() || is_network_admin() || isset( $_GET['activate-multi'] ) || 'activate-selected' === $action ) { //phpcs:ignore
			die();
		}

		wp_send_json_success( admin_url( 'admin.php?page=optn-dashboard#dashboard' ) );
	}

	/**
	 * Installation Notice JS
	 *
	 * @return void
	 */
	public function install_notice_js() {
		?>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				'use strict';
				$(document).on('click', '.wc-install-btn.optn-install-btn', function(e) {
					e.preventDefault();
					const $that = $(this);
					console.log($that.attr('data-plugin-slug'));
					$.ajax({
						type: 'POST',
						url: ajaxurl,
						data: {
							install_plugin: $that.attr('data-plugin-slug'),
							action: 'optn_install',
							wpnonce: '<?php echo esc_js( wp_create_nonce( 'optn-nonce' ) ); ?>',
						},
						beforeSend: function() {
							$that.parents('.wc-install').addClass('loading');
						},
						success: function(response) {
							window.location.reload()
						},
						complete: function() {
							// $that.parents('.wc-install').removeClass('loading');
						}
					});
				});
			});
		</script>
		<?php
	}
}
