<?php // phcps:ignore

namespace OPTN\Frontend;

use OPTN\Includes\Db;
use OPTN\Includes\Integrations\Base\IntegrationFactory;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Rest APIs for Public
 */
class RestFrontend {

	/**
	 * Database instance
	 *
	 * @var Db
	 */
	private $db;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->db = Db::get_instance();
		$this->register_routes();
	}

	/**
	 * Registers rest routes
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'optn/v1',
			'/integration-action',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_integration_action' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Integration action
	 *
	 * @param WP_REST_Request $req rest object.
	 * @return array|null
	 */
	public function handle_integration_action( WP_REST_Request $req ) {
		$data = isset( $req['data'] ) && is_array( $req['data'] ) ? $req['data'] : null;

		if ( empty( $data ) ) {
			return rest_ensure_response( new WP_Error( 400, 'Missing data' ) );
		}

		$res = true;

		foreach ( $data as $d ) {
			$id   = isset( $d['id'] ) ? intval( $d['id'] ) : null;
			$type = isset( $d['integration'] ) ? sanitize_key( $d['integration'] ) : null;

			if ( empty( $data ) || empty( $type ) ) {
				return rest_ensure_response( new WP_Error( 400, 'Missing data' ) );
			}

			if ( 'bg' === $type ) {
				do_action( 'optn_lead_added', $d );
			} else {
				$class = IntegrationFactory::get_integration_class( $type, $id );

				if ( is_null( $class ) ) {
					return rest_ensure_response( new WP_Error( 400, 'Invalid integration type' ) );
				}

				$res = $class->add_subscriber( $d );

			}
		}

		return rest_ensure_response( new WP_REST_Response( array(), $res ? 200 : 500 ) );
	}
}
