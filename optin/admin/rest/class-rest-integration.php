<?php // phpcs:ignore

namespace OPTN\Admin\Rest;

use OPTN\Includes\Db;
use OPTN\Includes\Integrations\Base\IntegrationFactory;
use OPTN\Includes\Settings;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Registers authenticated REST operations for saved email and webhook integrations.
 *
 * Validates account and destination settings, then delegates provider-specific checks
 * and persistence to the integration registry and database service.
 */
class RestIntegration {

	/**
	 * Database instance
	 *
	 * @var Db $db DB instance.
	 */
	private $db;

	/**
	 * Constructor function
	 */
	public function __construct() {
		$this->db = Db::get_instance();

		register_rest_route(
			'optn/v1',
			'/integration/',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_get_integration' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/integration/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_get_integration' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/integration',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_create_integration' ),
				'permission_callback' => array( $this, 'management_permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/integration/(?P<id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'handle_delete_integration' ),
				'permission_callback' => array( $this, 'management_permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/integration-data/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_get_integration_data' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);
	}

	/**
	 * Gets integration data by id
	 *
	 * @param WP_REST_Request $req rest object.
	 * @return array|null
	 */
	public function handle_get_integration( WP_REST_Request $req ) {
		$id   = isset( $req['id'] ) ? intval( $req['id'] ) : null;
		$type = isset( $req['type'] ) ? sanitize_key( $req['type'] ) : null;

		$third_party = $this->db->get_integration( $id, true, $type );
		if ( $id ) {
			$third_party = IntegrationFactory::get_provider( $third_party['type'] ?? '' ) ? $third_party : array();
		} else {
			$third_party = array_values(
				array_filter(
					$third_party,
					function ( $account ) {
							return (bool) IntegrationFactory::get_provider( $account['type'] ?? '' );
					}
				)
			); }

		$res = array(
			'third_party' => $third_party,
			'wp_plugins'  => Settings::get_integration_settings(),
		);

		return rest_ensure_response( new WP_HTTP_Response( $res ) );
	}

	/**
	 * Creates an integration
	 *
	 * @param WP_REST_Request $req rest object.
	 * @return array|null
	 */
	public function handle_create_integration( WP_REST_Request $req ) {
		$type = sanitize_key( $req['type'] ?? '' );
		/** Extensions may handle their own account types; null keeps common behavior. */
		$handled = apply_filters( 'optn_integration_create_response', null, $req, $this->db );
		if ( null !== $handled ) {
			return $handled; }
		$provider = IntegrationFactory::get_provider( $type );
		if ( ! $provider ) {
			return new WP_Error( 'optn_integration_unsupported', 'Integration unavailable', array( 'status' => 400 ) );
		}
		if ( ! $provider['account'] ) {
			Settings::set_settings( 'int_enable_' . $type, ! empty( $req['data']['enable'] ) );
			return new WP_REST_Response( array(), 201 );
		}
		if ( ! is_array( $req['data'] ?? null ) || empty( $req['data']['apiKey'] ) ) {
			return new WP_Error( 'optn_integration_config', 'API key required', array( 'status' => 400 ) );
		}
		$validation = IntegrationFactory::validate_account( $type, $req['data'] );
		if ( is_wp_error( $validation ) ) {
			return $validation; }
		$result = $this->db->add_integration( sanitize_text_field( $req['title'] ?? '' ), $type, $req['data'], absint( $req['id'] ?? 0 ), $provider['category'] );
		return new WP_REST_Response( array(), $result ? 201 : 500 );
	}

	/**
	 * Creates an integration
	 *
	 * @param WP_REST_Request $req rest object.
	 * @return array|null
	 */
	public function handle_delete_integration( WP_REST_Request $req ) {
		$id   = isset( $req['id'] ) ? intval( $req['id'] ) : null;
		$type = isset( $req['type'] ) ? sanitize_key( $req['type'] ) : null;

		if ( empty( $id ) ) {
			return rest_ensure_response( new WP_HTTP_Response( null, 400 ) );
		}

		$res = $this->db->delete_integration( $id );

		if ( $res ) {
			/** Notify extensions only after a successful account deletion. */
			do_action( 'optn_integration_deleted', $id, $type, $this->db );
		}

		return rest_ensure_response( new WP_HTTP_Response( array(), $res ? 204 : 500 ) );
	}


	/**
	 * Get integration data
	 *
	 * @param WP_REST_Request $req rest object.
	 * @return array|null
	 */
	public function handle_get_integration_data( WP_REST_Request $req ) {
		$args              = is_array( $req['args'] ?? null ) ? $req['args'] : array(); // will be validated later.
		$id                = isset( $req['id'] ) ? intval( $req['id'] ) : null;
		$type              = isset( $req['type'] ) ? sanitize_key( $req['type'] ) : null;
		$args['use_cache'] = isset( $req['use_cache'] ) ? boolval( $req['use_cache'] ) : true;
		if ( empty( $type ) ) {
			return rest_ensure_response( new WP_HTTP_Response( null, 400 ) );
		}

		$class = IntegrationFactory::get_integration_class( $type, $id );

		if ( is_null( $class ) ) {
			return rest_ensure_response( new WP_HTTP_Response( array(), 400 ) );
		}

		$res = array(
			'lists'  => $class->get_lists( $args ),
			'fields' => $class->get_fields( $args ),
		);

		return rest_ensure_response( new WP_HTTP_Response( $res ) );
	}

	/**
	 * Permission callback
	 *
	 * @return bool
	 */
	public function permission_callback() {
		return current_user_can( OPTN_MIN_CAPABILITY );
	}

	/**
	 * Restrict shared account and provider changes to site administrators.
	 *
	 * @return bool Whether the current user may manage integration accounts.
	 */
	public function management_permission_callback() {
		return current_user_can( 'manage_options' );
	}
}
