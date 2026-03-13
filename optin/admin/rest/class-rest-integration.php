<?php // phpcs:ignore

namespace OPTN\Admin\Rest;

use OPTN\Includes\Db;
use OPTN\Includes\Dto\ModelInput;
use OPTN\Includes\Integrations\Base\IntegrationFactory;
use OPTN\Includes\Settings;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Rest for handling integrations
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
			'/integration/info',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_get_integration_info' ),
				'permission_callback' => array( $this, 'permission_callback' ),
				'args'                => array(
					'type'        => array(
						'description'       => esc_html__( 'Type of the integration', 'optin' ),
						'required'          => true,
						'type'              => 'string',
						'validate_callback' => function ( $value ) {
							return is_string( $value );
						},
						'sanitize_callback' => function ( $value ) {
							return sanitize_key( $value );
						},
					),
					'integration' => array(
						'description'       => esc_html__( 'Integration Slug', 'optin' ),
						'required'          => true,
						'type'              => 'string',
						'validate_callback' => function ( $value ) {
							return is_string( $value );
						},
						'sanitize_callback' => function ( $value ) {
							return sanitize_key( $value );
						},
					),
					'query'       => array(
						'description'       => esc_html__( 'Query', 'optin' ),
						'required'          => true,
						'type'              => 'string',
						'validate_callback' => function ( $value ) {
							return is_string( $value );
						},
						'sanitize_callback' => function ( $value ) {
							return sanitize_key( $value );
						},
					),
				),
			)
		);

		register_rest_route(
			'optn/v1',
			'/integration',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_create_integration' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/integration/ai/prompt',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_ai_integration_prompt' ),
				'permission_callback' => array( $this, 'permission_callback' ),
				'args'                => array(
					'id'          => array(
						'description'       => esc_html__( 'ID of the integration', 'optin' ),
						'required'          => true,
						'type'              => 'integer',
						'validate_callback' => function ( $value ) {
							return is_numeric( $value );
						},
						'sanitize_callback' => function ( $value ) {
							return intval( $value );
						},
					),
					'type'        => array(
						'description'       => esc_html__( 'Type of the integration', 'optin' ),
						'required'          => true,
						'type'              => 'string',
						'validate_callback' => function ( $value ) {
							return is_string( $value );
						},
						'sanitize_callback' => function ( $value ) {
							return sanitize_key( $value );
						},
					),
					'user_prompt' => array(
						'description'       => esc_html__( 'User prompt', 'optin' ),
						'required'          => true,
						'type'              => 'string',
						'validate_callback' => function ( $value ) {
							return is_string( $value );
						},
						'sanitize_callback' => function ( $value ) {
							return sanitize_text_field( $value );
						},
					),
					'instruction' => array(
						'description'       => esc_html__( 'Instruction', 'optin' ),
						'required'          => false,
						'type'              => 'string',
						'validate_callback' => function ( $value ) {
							return is_string( $value );
						},
						'sanitize_callback' => function ( $value ) {
							return sanitize_text_field( $value );
						},
					),
					'task'        => array(
						'description'       => esc_html__( 'Task Type', 'optin' ),
						'required'          => false,
						'type'              => 'string',
						'validate_callback' => function ( $value ) {
							return is_string( $value );
						},
						'sanitize_callback' => function ( $value ) {
							return sanitize_key( $value );
						},
					),
				),
			)
		);

		register_rest_route(
			'optn/v1',
			'/integration/(?P<id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'handle_delete_integration' ),
				'permission_callback' => array( $this, 'permission_callback' ),
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

		$res = array(
			'third_party' => $third_party,
			'wp_plugins'  => Settings::get_integration_settings(),
		);

		return rest_ensure_response( new WP_HTTP_Response( $res ) );
	}

	/**
	 * Gets integration info
	 *
	 * @param WP_REST_Request $req rest object.
	 * @return WP_REST_Response
	 */
	public function handle_get_integration_info( WP_REST_Request $req ) {
		$res = IntegrationFactory::get_integration_info(
			$req['type'],
			$req['integration'],
			$req['query']
		);
		return rest_ensure_response(
			array(
				'success' => ! empty( $res ),
				'data'    => $res,
			)
		);
	}


	/**
	 * Creates an integration
	 *
	 * @param WP_REST_Request $req rest object.
	 * @return array|null
	 */
	public function handle_create_integration( WP_REST_Request $req ) {
		$is_wp_plugin        = isset( $req['wp_plugin'] ) ? boolval( $req['wp_plugin'] ) : false;
		$is_analytics_plugin = isset( $req['analytics_plugin'] ) ? boolval( $req['analytics_plugin'] ) : false;
		$title               = isset( $req['title'] ) ? sanitize_text_field( $req['title'] ) : null;
		$type                = isset( $req['type'] ) ? sanitize_text_field( $req['type'] ) : null;
		$data                = isset( $req['data'] ) && is_array( $req['data'] ) ? $req['data'] : null;
		$id                  = isset( $req['id'] ) ? intval( $req['id'] ) : null;
		$int_type            = isset( $req['intType'] ) ? sanitize_key( $req['intType'] ) : null;

		$res = true;

		if ( $is_wp_plugin ) {
			if ( empty( $type ) || empty( $data ) || ! isset( $data['enable'] ) ) {
				return rest_ensure_response( new WP_Error( 400, 'Missing data' ) );
			}

			$key = 'int_enable_' . sanitize_key( $type );

			Settings::set_settings( $key, boolval( $data['enable'] ) );
		} elseif ( $is_analytics_plugin ) {
			if ( empty( $type ) || empty( $data ) ) {
				return rest_ensure_response( new WP_Error( 400, 'Missing data' ) );
			}

			$skip_db = isset( $req['skip_db'] ) ? boolval( $req['skip_db'] ) : false;

			$enable = isset( $data['enable'] ) ? boolval( $data['enable'] ) : true;

			if ( ! $skip_db ) {
				$res = $this->db->add_integration( $title, $type, $data, $id );
			}

			if ( $res ) {
				$key = 'int_bg_enable_' . sanitize_key( $type );
				Settings::set_settings( $key, $enable );
			}
		} else {
			if ( empty( $title ) || empty( $data ) || empty( $type ) ) {
				return rest_ensure_response( new WP_Error( 400, 'Missing data' ) );
			}

			$res = $this->db->add_integration( $title, $type, $data, $id, $int_type );
		}

		return rest_ensure_response( new WP_REST_Response( array(), $res ? 201 : 500 ) );
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

		// Turn off GA if there is no account connected.
		if ( $res && 'ga' === $type ) {
			$ints = $this->db->get_integrations_by_type( $type );
			if ( count( $ints ) < 1 ) {
				Settings::set_settings( 'int_bg_enable_ga', false );
			}
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
		$args              = ! empty( $req['args'] ) ? $req['args'] : array(); // will be validated later.
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
	 * Ai prompt
	 *
	 * @param WP_REST_Request $req rest object.
	 * @return array|null
	 */
	public function handle_ai_integration_prompt( WP_REST_Request $req ) {
		$params = $req->get_params();

		$class = IntegrationFactory::get_ai_integration_class( $params['type'], $params['id'] );

		if ( is_null( $class ) ) {
			return rest_ensure_response( new WP_HTTP_Response( array(), 400 ) );
		}

		$input = new ModelInput(
			$params['user_prompt'],
			array(
				'instruction' => $params['instruction'],
				'task'        => $params['task'],
			)
		);

		$res = $class->generate_text( $input );

		$success = is_wp_error( $res ) ? false : true;

		return rest_ensure_response(
			array(
				'success' => $success,
				'data'    => $res,
			)
		);
	}


	/**
	 * Permission callback
	 *
	 * @return bool
	 */
	public function permission_callback() {
		return current_user_can( OPTN_MIN_CAPABILITY );
	}
}
