<?php //phpcs:ignore

namespace OPTN\Includes\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin deactivation feedback and reporting.
 */
class Deactive {

	/**
	 * Plugin slug
	 *
	 * @var string
	 */
	private $plugin_slug = 'optin';

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $pagenow;

		if ( 'plugins.php' === $pagenow ) {
			add_action( 'admin_footer', array( $this, 'get_source_data_callback' ) );
		}
		add_action( 'wp_ajax_optn_deactive_plugin', array( $this, 'send_plugin_data' ) );
	}

	/**
	 * Send plugin deactivation data to remote server.
	 *
	 * @return void
	 */
	public function send_plugin_data() {
		DurbinClient::send( DurbinClient::DEACTIVATE_ACTION );
		die();
	}

	/**
	 * Output deactivation modal markup, CSS, and JS.
	 *
	 * @return void
	 */
	public function get_source_data_callback() {
		$this->deactive_container_css();
		$this->deactive_container_js();
		$this->deactive_html_container();
	}

	/**
	 * Get deactivation reasons and field settings.
	 *
	 * @return array[] List of deactivation options.
	 */
	public function get_deactive_settings() {
		return array(
			array(
				'id'    => 'not-working',
				'input' => false,
				'text'  => __( 'The plugin isn’t working properly.', 'optin' ),
			),
			array(
				'id'    => 'limited-features',
				'input' => false,
				'text'  => __( 'Limited features on the free version.', 'optin' ),
			),
			array(
				'id'          => 'better-plugin',
				'input'       => true,
				'text'        => __( 'I found a better plugin.', 'optin' ),
				'placeholder' => __( 'Please share which plugin.', 'optin' ),
			),
			array(
				'id'    => 'temporary-deactivation',
				'input' => false,
				'text'  => __( "It's a temporary deactivation.", 'optin' ),
			),
			array(
				'id'          => 'other',
				'input'       => true,
				'text'        => __( 'Other.', 'optin' ),
				'placeholder' => __( 'Please share the reason.', 'optin' ),
			),
		);
	}

	/**
	 * Output HTML for the deactivation modal.
	 *
	 * @return void
	 */
	public function deactive_html_container() {
		?>
		<div class="optn-deactive-modal" id="optn-deactive-deactive-modal">
			<div class="optn-deactive-modal-wrap">
			
				<div class="optn-deactive-modal-header">
					<h2><?php esc_html_e( 'Quick Feedback', 'optin' ); ?></h2>
					<button class="optn-deactive-modal-cancel"><span class="dashicons dashicons-no-alt"></span></button>
				</div>

				<div class="optn-deactive-modal-body">
					<h3><?php esc_html_e( 'If you have a moment, please let us know why you are deactivating WowOptin:', 'optin' ); ?></h3>
					<ul class="optn-deactive-modal-input">
						<?php foreach ( $this->get_deactive_settings() as $key => $setting ) { ?>
							<li>
								<label>
									<input type="radio" <?php echo 0 == $key ? 'checked="checked"' : ''; ?> id="<?php echo esc_attr( $setting['id'] ); ?>" name="<?php echo esc_attr( $this->plugin_slug ); ?>" value="<?php echo esc_attr( $setting['text'] ); ?>">
									<div class="optn-deactive-reason-text"><?php echo esc_html( $setting['text'] ); ?></div>
									<?php if ( isset( $setting['input'] ) && $setting['input'] ) { ?>
										<textarea placeholder="<?php echo esc_attr( $setting['placeholder'] ); ?>" class="optn-deactive-reason-input <?php echo $key == 0 ? 'optn-deactive-active' : ''; ?> <?php echo esc_html( $setting['id'] ); ?>"></textarea>
									<?php } ?>
								</label>
							</li>
						<?php } ?>
					</ul>
				</div>

				<div class="optn-deactive-modal-footer">
					<a class="optn-deactive-modal-submit optn-deactive-btn optn-deactive-btn-primary" href="#"><?php esc_html_e( 'Submit & Deactivate', 'optin' ); ?><span class="dashicons dashicons-update rotate"></span></a>
					<a class="optn-deactive-modal-deactive" href="#"><?php esc_html_e( 'Skip & Deactivate', 'optin' ); ?></a>
				</div>
				
			</div>
		</div>
		<?php
	}

	/**
	 * Output inline CSS for the modal.
	 *
	 * @return void
	 */
	public function deactive_container_css() {
		?>
		<style type="text/css">
			.optn-deactive-modal {
				position: fixed;
				z-index: 99999;
				top: 0;
				right: 0;
				bottom: 0;
				left: 0;
				background: rgba(0,0,0,0.5);
				display: none;
				box-sizing: border-box;
				overflow: scroll;
			}
			.optn-deactive-modal * {
				box-sizing: border-box;
			}
			.optn-deactive-modal.modal-active {
				display: block;
			}
			.optn-deactive-modal-wrap {
				max-width: 870px;
				width: 100%;
				position: relative;
				margin: 10% auto;
				background: #fff;
			}
			.optn-deactive-reason-input{
				display: none;
			}
			.optn-deactive-reason-input.optn-deactive-active{
				display: block;
			}
			.rotate{
				animation: rotate 1.5s linear infinite; 
			}
			@keyframes rotate{
				to{ transform: rotate(360deg); }
			}
			.optn-deactive-popup-rotate{
				animation: popupRotate 1s linear infinite; 
			}
			@keyframes popupRotate{
				to{ transform: rotate(360deg); }
			}
			#optn-deactive-deactive-modal {
				background: rgb(0 0 0 / 85%);
				overflow: hidden;
			}
			#optn-deactive-deactive-modal .optn-deactive-modal-wrap {
				max-width: 570px;
				border-radius: 5px;
				margin: 5% auto;
				overflow: hidden
			}
			#optn-deactive-deactive-modal .optn-deactive-modal-header {
				padding: 17px 30px;
				border-bottom: 1px solid #ececec;
				display: flex;
				align-items: center;
				background: #f5f5f5;
			}
			#optn-deactive-deactive-modal .optn-deactive-modal-header .optn-deactive-modal-cancel {
				padding: 0;
				border-radius: 100px;
				border: 1px solid #b9b9b9;
				background: none;
				color: #b9b9b9;
				cursor: pointer;
				transition: 400ms;
			}
			#optn-deactive-deactive-modal .optn-deactive-modal-header .optn-deactive-modal-cancel:focus {
				color: red;
				border: 1px solid red;
				outline: 0;
			}
			#optn-deactive-deactive-modal .optn-deactive-modal-header .optn-deactive-modal-cancel:hover {
				color: red;
				border: 1px solid red;
			}
			#optn-deactive-deactive-modal .optn-deactive-modal-header h2 {
				margin: 0;
				padding: 0;
				flex: 1;
				line-height: 1;
				font-size: 20px;
				text-transform: uppercase;
				color: #8e8d8d;
			}
			#optn-deactive-deactive-modal .optn-deactive-modal-body {
				padding: 25px 30px;
			}
			#optn-deactive-deactive-modal .optn-deactive-modal-body h3{
				padding: 0;
				margin: 0;
				line-height: 1.4;
				font-size: 15px;
			}
			#optn-deactive-deactive-modal .optn-deactive-modal-body ul {
				margin: 25px 0 10px;
			}
			#optn-deactive-deactive-modal .optn-deactive-modal-body ul li {
				display: flex;
				margin-bottom: 10px;
				color: #807d7d;
			}
			#optn-deactive-deactive-modal .optn-deactive-modal-body ul li:last-child {
				margin-bottom: 0;
			}
			#optn-deactive-deactive-modal .optn-deactive-modal-body ul li label {
				align-items: center;
				width: 100%;
			}
			#optn-deactive-deactive-modal .optn-deactive-modal-body ul li label input {
				padding: 0 !important;
				margin: 0;
				display: inline-block;
			}
			#optn-deactive-deactive-modal .optn-deactive-modal-body ul li label textarea {
				margin-top: 8px;
				width: 350px;
			}
			#optn-deactive-deactive-modal .optn-deactive-modal-body ul li label .optn-deactive-reason-text {
				margin-left: 8px;
				display: inline-block;
			}
			#optn-deactive-deactive-modal .optn-deactive-modal-footer {
				padding: 0 30px 30px 30px;
				display: flex;
				align-items: center;
			}
			#optn-deactive-deactive-modal .optn-deactive-modal-footer .optn-deactive-modal-submit {
				display: flex;
				align-items: center;
				padding: 12px 22px;
				border-radius: 3px;
				background: #f97415;
				color: #fff;
				font-size: 16px;
				font-weight: 600;
				text-decoration: none;
			}
			#optn-deactive-deactive-modal .optn-deactive-modal-footer .optn-deactive-modal-submit span {
				margin-left: 4px;
				display: none;
			}
			#optn-deactive-deactive-modal .optn-deactive-modal-footer .optn-deactive-modal-submit.loading span {
				display: block;
			}
			#optn-deactive-deactive-modal .optn-deactive-modal-footer .optn-deactive-modal-deactive {
				margin-left: auto;
				color: #c5c5c5;
				text-decoration: none;
			}
			.wpxpo-btn-tracking-notice {
				display: flex;
				align-items: center;
				flex-wrap: wrap;
				padding: 5px 0;
			}
			.wpxpo-btn-tracking-notice .wpxpo-btn-tracking {
				margin: 0 5px;
				text-decoration: none;
			}
		</style>
		<?php
	}

	/**
	 * Output inline JavaScript for the modal logic.
	 *
	 * @return void
	 */
	public function deactive_container_js() {
		?>
		<script type="text/javascript">
			jQuery( document ).ready( function( $ ) {
				'use strict';

				// Modal Radio Input Click Action
				$('.optn-deactive-modal-input input[type=radio]').on( 'change', function(e) {
					$('.optn-deactive-reason-input').removeClass('optn-deactive-active');
					$('.optn-deactive-modal-input').find( '.'+$(this).attr('id') ).addClass('optn-deactive-active');
				});

				// Modal Cancel Click Action
				$( document ).on( 'click', '.optn-deactive-modal-cancel', function(e) {
					$( '#optn-deactive-deactive-modal' ).removeClass( 'modal-active' );
				});
				
				$(document).on('click', function(event) {
					const $popup = $('#optn-deactive-deactive-modal');
					const $modalWrap = $popup.find('.optn-deactive-modal-wrap');

					if ( !$modalWrap.is(event.target) && $modalWrap.has(event.target).length === 0 && $popup.hasClass('modal-active')) {
						$popup.removeClass('modal-active');
					}
				});

				// Deactivate Button Click Action
				$( document ).on( 'click', '#deactivate-optin', function(e) {
					e.preventDefault();
					e.stopPropagation();
					$( '#optn-deactive-deactive-modal' ).addClass( 'modal-active' );
					$( '.optn-deactive-modal-deactive' ).attr( 'href', $(this).attr('href') );
					$( '.optn-deactive-modal-submit' ).attr( 'href', $(this).attr('href') );
				});

				// Submit to Remote Server
				$( document ).on( 'click', '.optn-deactive-modal-submit', function(e) {
					e.preventDefault();
					
					$(this).addClass('loading');
					const url = $(this).attr('href')

					$.ajax({
						url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
						type: 'POST',
						data: { 
							action: 'optn_deactive_plugin',
							cause_id: $('#optn-deactive-deactive-modal input[type=radio]:checked').attr('id'),
							cause_title: $('#optn-deactive-deactive-modal .optn-deactive-modal-input input[type=radio]:checked').val(),
							cause_details: $('#optn-deactive-deactive-modal .optn-deactive-reason-input.optn-deactive-active').val()
						},
						success: function (data) {
							$( '#optn-deactive-deactive-modal' ).removeClass( 'modal-active' );
							window.location.href = url;
						},
						error: function(xhr) {
							console.log( 'Error occured. Please try again' + xhr.statusText + xhr.responseText );
						},
					});

				});

			});
		</script>
		<?php
	}
}
