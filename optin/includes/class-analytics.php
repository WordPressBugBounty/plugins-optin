<?php

namespace OPTN\Includes;

use WP_REST_Request;
use OPTN\Includes\Db;
use OPTN\Includes\Utils\VisitorCount;
use OPTN\Includes\Utils\Utils;

/**
 * Handles analytics for WowOptin
 */
class Analytics {
	use \OPTN\Includes\Traits\Singleton;

	/**
	 * DB class instance
	 *
	 * @var \OPTN\Includes\Db $db db class
	 */
	private $db;

	/**
	 * VisitorCount class instance
	 *
	 * @var \OPTN\Includes\Utils\VisitorCount $visitor_count
	 */
	private $visitor_count;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->db            = Db::get_instance();
		$this->visitor_count = VisitorCount::get_instance();
	}

	/**
	 * Register public routes
	 *
	 * @return void
	 */
	public function register_public_routes() {
		register_rest_route(
			'optn/v1',
			'/update-analytics/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_update_analytics' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'optn/v1',
			'/get-ipinfo/',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_get_ipinfo' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Register admin routes
	 *
	 * @return void
	 */
	public function register_admin_routes() {
		register_rest_route(
			'optn/v1',
			'/get-quick-view-data/',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_get_quick_view_data' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/get-valid-convs/',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_get_valid_convs' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/get-impressions/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_get_impression' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/get-impressions-device/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_get_impression_device' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/get-conversions/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_get_conversion' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/get-sales/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_get_sales' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/get-pop-optins/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_get_pop_optins' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/get-geo-view/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_get_geo_view' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);
	}

	public function handle_get_geo_view( $req ) {
		$days = isset( $req['days'] ) ? intval( $req['days'] ) : 7;

		$res = $this->db->get_geo_view_data( $days );

		return rest_ensure_response( $res );
	}

	public function handle_get_pop_optins( $req ) {
		$days  = isset( $req['days'] ) ? intval( $req['days'] ) : 7;
		$limit = isset( $req['limit'] ) ? intval( $req['limit'] ) : 6;
		$page  = isset( $req['page'] ) ? intval( $req['page'] ) : 1;

		$res = $this->db->get_pop_optin( $days, $limit, $page );

		return rest_ensure_response( $res );
	}

	public function handle_get_sales( WP_REST_Request $req ) {
		$id   = isset( $req['id'] ) ? intval( $req['id'] ) : null;
		$days = isset( $req['days'] ) ? intval( $req['days'] ) : 7;

		$res = $this->db->get_sales( $id, $days );
		return rest_ensure_response( $res );
	}

	public function handle_get_conversion( WP_REST_Request $req ) {
		$id   = isset( $req['id'] ) ? intval( $req['id'] ) : null;
		$days = isset( $req['days'] ) ? intval( $req['days'] ) : 7;

		$res = $this->db->get_conversions( $id, $days );
		return rest_ensure_response( $res );
	}

	public function handle_get_impression_device( WP_REST_Request $req ) {
		$id   = isset( $req['id'] ) ? intval( $req['id'] ) : null;
		$days = isset( $req['days'] ) ? intval( $req['days'] ) : 7;

		$res = $this->db->get_impressions_by_device( $id, $days );
		return rest_ensure_response( $res );
	}

	public function handle_get_impression( WP_REST_Request $req ) {
		$id     = isset( $req['id'] ) ? intval( $req['id'] ) : null;
		$days   = isset( $req['days'] ) ? intval( $req['days'] ) : 7;
		$unique = isset( $req['unique'] ) ? boolval( $req['unique'] ) : false;

		$res = $this->db->get_impressions( $id, $days, $unique );
		return rest_ensure_response( $res );
	}

	public function handle_get_valid_convs( WP_REST_Request $req ) {
		$res = $this->db->get_post_for_select();
		return $res;
	}

	public function handle_get_quick_view_data( WP_REST_Request $req ) {
		$id   = isset( $req['id'] ) ? intval( $req['id'] ) : null;
		$days = isset( $req['days'] ) ? intval( $req['days'] ) : 7;

		$db_quick_view = $this->db->get_quick_view_data( $id, $days );

		$visitors = $this->visitor_count->get_stats();

		$res = array_merge(
			$db_quick_view,
			$visitors,
		);

		return rest_ensure_response( $res );
	}

	public function handle_update_analytics( $request ) {
		$id        = isset( $request['id'] ) ? intval( $request['id'] ) : null;
		$type      = isset( $request['type'] ) ? sanitize_text_field( $request['type'] ) : '';
		$goal_type = isset( $request['goal_type'] ) ? sanitize_text_field( $request['goal_type'] ) : '';
		$value     = isset( $request['value'] ) ? floatval( $request['value'] ) : null;
		$path      = isset( $request['path'] ) ? sanitize_url( $request['path'] ) : null;

		$path = ! empty( $path ) ? ( str_ends_with( $path, '/' ) ? rtrim( $path, '/' ) : $path ) : null;

		$res = '';
		switch ( $type ) {
			case 'view':
				$res = $this->db->add_view( $id );
				break;
			case 'goal':
				$res = $this->db->add_goal( $id, $goal_type, $value, $path );
				break;
			case 'social':
				$res = $this->db->add_social_click( $id, $goal_type );
				break;
		}

		return rest_ensure_response( $res );
	}

	public function handle_get_ipinfo() {
		$user_ip = Utils::get_ip_from_request();

		if ( ! empty( $user_ip ) ) {
			return rest_ensure_response( 500 );
		}

		$url = "https://ipinfo.io/{$user_ip}/json";

		$response = wp_remote_get( $url );

		if ( is_wp_error( $response ) ) {
			return rest_ensure_response( 500 );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body );

		$success = is_object( $data ) && isset( $data->ip ) && isset( $data->country );

		$res = array(
			'success' => $success,
		);

		if ( $success ) {
			$res['data'] = array(
				'ip'      => $data->ip,
				'country' => $data->country,
			);
		}

		return rest_ensure_response( $res );
	}


	public function permission_callback() {
		return current_user_can( OPTN_MIN_CAPABILITY );
	}
}
