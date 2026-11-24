<?php
/**
 * Registers and handles REST endpoints used by public opt-in forms.
 *
 * @package Optin
 */

namespace OPTN\Frontend;

use OPTN\Includes\Db;
use OPTN\Includes\Integrations\Base\IntegrationFactory;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Exposes public REST endpoints used to submit opt-in forms and track events.
 *
 * Validates visitor requests against saved campaign and form configuration before
 * storing leads, recording interactions, or dispatching integration submissions.
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

		$submission = \OPTN\Includes\Utils\FormSubmission::resolve( $req );
		if ( is_wp_error( $submission ) ) {
			return $submission;
		}
		// Resolve every adapter before any outbound request or lead event.
		$adapters = array();
		foreach ( $submission['destinations'] as $data ) {
			$adapter = IntegrationFactory::get_integration_class( $data['integration'], $data['id'] );
			if ( ! $adapter ) {
				return new WP_Error( 'optn_submission_integration', 'Integration unavailable', array( 'status' => 400 ) );
			}
			$adapters[] = array( $adapter, $data );
		}
		foreach ( $adapters as $entry ) {
			if ( ! $entry[0]->add_subscriber( $entry[1] ) ) {
				return new WP_Error( 'optn_submission_upstream', 'Integration request failed', array( 'status' => 502 ) );
			}
		}
		do_action( 'optn_lead_added', $submission['background'] );
		return new WP_REST_Response( array( 'success' => true ), 200 );
	}
}
