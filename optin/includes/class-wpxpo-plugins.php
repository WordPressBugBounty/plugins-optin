<?php // phpcs:ignore

namespace OPTN\Includes;

defined( 'ABSPATH' ) || exit;

/**
 * Class WpxpoPlugins
 */
class WpxpoPlugins {


	/**
	 * Constructor. Hooks into various WordPress actions.
	 */
	public function __construct() {
		// Our Plugin Activation Hooks.
		add_action( 'wp_ajax_optn_install_plugin', array( $this, 'install_plugin_callback' ) );
	}

	/**
	 * Handles plugin installation and activation via AJAX.
	 *
	 * @return void
	 */
	public function install_plugin_callback() {

		$nonce = isset( $_POST['wpnonce'] ) ? sanitize_key( wp_unslash( $_POST['wpnonce'] ) ) : '';

		if ( ! isset( $nonce ) ) {
			wp_send_json_error( esc_html__( 'Nonce is missing.', 'optin' ) );
		}

		if ( wp_verify_nonce( $nonce, 'optin-nonce' ) === false ) {
			wp_send_json_error( esc_html__( 'Invalid nonce.', 'optin' ) );
		}

		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions.', 'optin' ) );
		}

		$plugin = isset( $_POST['plugin'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin'] ) ) : '';

		if ( empty( $plugin ) ) {
			wp_send_json_error( array( 'message' => 'No plugin specified' ) );
		}

		$res = array( 'message' => 'false' );

		if ( $plugin ) {
			$res = Xpo::install_and_active_plugin( $plugin );
		}

		wp_send_json_success( array( 'message' => $res ) );

		die();
	}
}
