<?php // phpcs:ignore

namespace OPTN\Admin\Rest;

use OPTN\Includes\AbTesting;
use OPTN\Includes\Db;
use OPTN\Includes\Dto\CreateAbTestDto;
use OPTN\Includes\Dto\UpdateAbTestDto;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Rest for handling Ab testing
 */
class RestAbTesting {

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
			'/abt/',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_get_abt_list' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/abt/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_get_abt' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/abt/(?P<id>\d+)/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_get_abt_stats' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/abt',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'handle_edit_test' ),
				'permission_callback' => array( $this, 'permission_callback' ),
				'args'                => $this->edit_args(),
			)
		);

		register_rest_route(
			'optn/v1',
			'/abt/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'handle_delete_test' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/abt/winner-selection',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_set_winner' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);
	}

	/**
	 * Gets integration data by id
	 *
	 * @param WP_REST_Request $req rest object.
	 * @return WP_REST_Response
	 */
	public function handle_get_abt( WP_REST_Request $req ) {
		$id = isset( $req['id'] ) ? intval( $req['id'] ) : null;

		if ( empty( $id ) ) {
			return rest_ensure_response( new WP_Error( 400, 'Missing id' ) );
		}

		return rest_ensure_response( $this->db->get_ab_test( $id ) );
	}

	/**
	 * Gets integration data by id
	 *
	 * @param WP_REST_Request $req rest object.
	 * @return WP_REST_Response
	 */
	public function handle_get_abt_stats( WP_REST_Request $req ) {
		$id = isset( $req['id'] ) ? intval( $req['id'] ) : null;

		if ( empty( $id ) ) {
			return rest_ensure_response( new WP_Error( 400, 'Missing id' ) );
		}

		return rest_ensure_response( $this->db->get_ab_test_stats( $id ) );
	}

	/**
	 * Gets integration data by id
	 *
	 * @param WP_REST_Request $req rest object.
	 * @return array|null
	 */
	public function handle_get_abt_list( WP_REST_Request $req ) {
		$status = isset( $req['status'] ) ? sanitize_key( $req['status'] ) : null;
		$search = isset( $req['search'] ) ? sanitize_text_field( $req['search'] ) : null;

		return rest_ensure_response( $this->db->get_ab_tests( $status, $search ) );
	}


	/**
	 * Arguments for creating a AB test
	 *
	 * @return array
	 */
	public function edit_args() {
		return array(
			'id'              => array(
				'description'       => esc_html__( 'ID of the A/B Test', 'optin' ),
				'required'          => false,
				'type'              => 'integer',
				'validate_callback' => function ( $value ) {
					return (bool) intval( $value );
				},
				'sanitize_callback' => function ( $value ) {
					return intval( $value );
				},
			),
			'title'           => array(
				'description'       => esc_html__( 'Title of the A/B Test', 'optin' ),
				'required'          => true,
				'type'              => 'string',
				'validate_callback' => function ( $value ) {
					return is_string( $value );
				},
				'sanitize_callback' => function ( $value ) {
					return sanitize_text_field( $value );
				},
			),
			'type'            => array(
				'description'       => esc_html__( 'Type of the A/B Test', 'optin' ),
				'required'          => true,
				'type'              => 'string',
				'enum'              => array( 'manual', 'automatic' ),
				'validate_callback' => function ( $value ) {
					return in_array( $value, array( 'manual', 'automatic' ), true );
				},
				'sanitize_callback' => function ( $value ) {
					return sanitize_key( $value );
				},
			),
			'status'          => array(
				'description'       => esc_html__( 'Status of the A/B Test', 'optin' ),
				'required'          => true,
				'type'              => 'string',
				'enum'              => array( 'active', 'draft', 'end' ),
				'validate_callback' => function ( $value ) {
					return in_array( $value, array( 'active', 'draft', 'end' ), true );
				},
				'sanitize_callback' => function ( $value ) {
					return sanitize_key( $value );
				},
			),
			'metric'          => array(
				'description'       => esc_html__( 'Metric of the A/B Test', 'optin' ),
				'required'          => true,
				'type'              => 'string',
				'enum'              => array( 'conv', 'cr', 'leads', 'rev' ),
				'validate_callback' => function ( $value ) {
					return in_array( $value, array( 'conv', 'cr', 'leads', 'rev' ), true );
				},
				'sanitize_callback' => function ( $value ) {
					return sanitize_key( $value );
				},
			),
			'duration'        => array(
				'description'       => esc_html__( 'Duration of the A/B Test', 'optin' ),
				'required'          => false,
				'type'              => 'mixed',
				'validate_callback' => function ( $value ) {
					return is_numeric( $value ) || is_null( $value );
				},
				'sanitize_callback' => function ( $value ) {
					$v = intval( $value );
					return ! empty( $v ) ? $v : null;
				},
			),
			'update_duration' => array(
				'description'       => esc_html__( 'Should update duration of the A/B Test', 'optin' ),
				'required'          => false,
				'type'              => 'boolean',
				'validate_callback' => function ( $value ) {
					return true;
				},
				'sanitize_callback' => function ( $value ) {
					return boolval( $value );
				},
			),
			'optins'          => array(
				'description'       => esc_html__( 'Optins of the A/B Test', 'optin' ),
				'required'          => true,
				'type'              => 'array',
				'validate_callback' => function ( $value ) {

					if ( ! is_array( $value ) ) {
						return false;
					}

					foreach ( $value as $optin ) {
						if ( ! isset( $optin['id'] ) || ! isset( $optin['t_dist'] ) ) {
							return false;
						}

						if ( ! is_numeric( $optin['id'] ) && intval( $optin['id'] ) === 0 ) {
							return false;
						}

						if ( ! is_numeric( $optin['t_dist'] ) ) {
							return false;
						}
					}

					return true;
				},
				'sanitize_callback' => function ( $value ) {
					$v = array();

					foreach ( $value as $optin ) {
						$v[] = array(
							'id'     => intval( $optin['id'] ),
							't_dist' => floatval( $optin['t_dist'] ),
						);
					}

					return $v;
				},
			),
		);
	}

	/**
	 * Creates an integration
	 *
	 * @param WP_REST_Request $req rest object.
	 * @return array|null
	 */
	public function handle_edit_test( WP_REST_Request $req ) {
		$res = false;
		$id  = isset( $req['id'] ) ? intval( $req['id'] ) : null;

		if ( empty( $id ) ) {
			$create_dto = new CreateAbTestDto(
				$req['title'],
				$req['type'],
				$req['status'],
				$req['metric'],
				$req['duration'],
				$req['optins']
			);

			$res = $this->db->add_ab_test( $create_dto );
		} else {
			$update_dto = new UpdateAbTestDto(
				$id,
				$req['title'],
				$req['type'],
				$req['status'],
				$req['metric'],
				$req['duration'],
				$req['update_duration'],
				$req['optins']
			);

			$res = $this->db->update_ab_test( $update_dto );
		}

		return rest_ensure_response( new WP_REST_Response( array( 'success' => $res ), $res ? 201 : 500 ) );
	}

	/**
	 * Creates an integration
	 *
	 * @param WP_REST_Request $req rest object.
	 * @return array|null
	 */
	public function handle_delete_test( WP_REST_Request $req ) {
		$id = isset( $req['id'] ) ? intval( $req['id'] ) : null;

		if ( empty( $id ) ) {
			return rest_ensure_response( new WP_HTTP_Response( 'Invalid ID', 400 ) );
		}

		$res = $this->db->delete_ab_test( $id );

		return rest_ensure_response( new WP_HTTP_Response( array( 'success' => $res ), $res ? 204 : 500 ) );
	}

	/**
	 * Sets winner of test
	 *
	 * @param WP_REST_Request $req rest object.
	 * @return WP_REST_Response
	 */
	public function handle_set_winner( WP_REST_Request $req ) {
		$test_id  = isset( $req['test_id'] ) ? intval( $req['test_id'] ) : null;
		$optin_id = isset( $req['optin_id'] ) ? intval( $req['optin_id'] ) : null;

		if ( empty( $test_id ) ) {
			return rest_ensure_response( new WP_Error( 400, 'Missing Test ID' ) );
		}

		if ( empty( $optin_id ) ) {
			return rest_ensure_response( new WP_Error( 400, 'Missing Optin ID' ) );
		}

		$res = AbTesting::handle_manual_winner_selection( $test_id, $optin_id );

		return rest_ensure_response( array( 'success' => $res ) );
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
