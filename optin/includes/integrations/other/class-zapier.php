<?php

namespace OPTN\Includes\Integrations\Other;

use OPTN\Includes\Db;
use OPTN\Includes\Utils\Utils;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Zapier Integration Class
 */
class Zapier {

	/**
	 * Sample data for testing in Zapier
	 *
	 * @var array
	 */
	private $sample_data = array(
		'lead'  => array(
			'email'     => 'hello@gmail.com',
			'ipAddress' => '1.2.3.4',
			'referrer'  => 'https://www.wowoptin.com',
			'timestamp' => 1699985224,
			'firstName' => 'Samin',
			'lastName'  => 'Yaser',
			'phone'     => '888-888-8888',
		),
		'optin' => array(
			'id'    => '1',
			'title' => 'Wow Popup',
		),
		'meta'  => array(),
		// 'smart_tags' => array(
		// 'day'             => 'Tuesday',
		// 'month'           => 'November',
		// 'year'            => '2023',
		// 'date'            => 'November 14, 2023',
		// 'page_url'        => 'https://optinmonster.com/',
		// 'referrer_url'    => '',
		// 'pages_visited'   => '5',
		// 'time_on_site'    => 319,
		// 'visit_timestamp' => '1699984887342',
		// 'page_title'      => 'Check out my campaign powered by OptinMonster!',
		// 'coupon_label'    => '',
		// 'coupon_code'     => '',
		// ),
	);


	/**
	 * DB Instance
	 *
	 * @var Db
	 */
	private $db;


	/**
	 * Settings
	 *
	 * @var array
	 */
	private $settings;


	/**
	 * Constructor
	 */
	public function __construct() {
		$this->db = Db::get_instance();
		$this->add_hooks();
	}

	/**
	 * Add hooks
	 *
	 * @return void
	 */
	public function add_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'optn_lead_added', array( $this, 'emit_lead_added_event' ) );
	}

	/**
	 * Registers routes
	 *
	 * @return void
	 */
	public function register_rest_routes() {

		register_rest_route(
			'optn/v1',
			'/zapier',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_sample_data' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/zapier',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_post_leads' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/zapier',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'handle_delete' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);
	}


	/**
	 * Handle sample data from testing.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_sample_data() {
		return rest_ensure_response(
			new WP_REST_Response( array( $this->sample_data ), 200 )
		);
	}


	/**
	 * Handle verification request
	 *
	 * @param WP_REST_Request $req Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_post_leads( WP_REST_Request $req ) {

		$target_url = $req->get_param( 'hookUrl' );
		$zap_id     = $req->get_param( 'zapId' );

		$this->save_settings( $target_url, $zap_id );

		return new WP_REST_Response(
			array(),
			201
		);
	}

	/**
	 * Delete Zapier callback url
	 *
	 * @param WP_REST_Request $req Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_delete( WP_REST_Request $req ) {
		$id = $req->get_param( 'zapId' );

		$this->delete_setting( $id );

		return rest_ensure_response(
			new WP_REST_Response( null, 204 )
		);
	}

	/**
	 * Conversion Event
	 *
	 * @param array $data data.
	 * @return boolean
	 */
	public function emit_lead_added_event( $data ) {
		$optin  = $this->db->get_conv_by_id( $data['conv_id'] );
		$fields = $data['fields']['fixed'];

		$lead_data = array(
			'lead'  => array(),
			'optin' => array(
				'id'    => strval( $optin->id ),
				'title' => $optin->title,
			),
			'meta'  => array(),
		);

		// Fixed fields.
		if ( isset( $fields['email'] ) ) {
			$lead_data['lead']['email'] = $fields['email'];
		}

		if ( isset( $fields['name'] ) ) {
			$lead_data['lead']['name'] = $fields['name'];
		}

		if ( isset( $fields['firstName'] ) ) {
			$lead_data['lead']['firstName'] = $fields['firstName'];
		}

		if ( isset( $fields['lastName'] ) ) {
			$lead_data['lead']['lastName'] = $fields['lastName'];
		}

		if ( isset( $fields['phone'] ) ) {
			$lead_data['lead']['phone'] = $fields['phone'];
		}

		if ( isset( $fields['privacyPolicy'] ) ) {
			$lead_data['lead']['privacyPolicy'] = $fields['privacyPolicy'];
		}

		// custom fields.
		foreach ( $data['fields']['custom'] as $key => $value ) {
			$lead_data['meta'][ $key ] = $value;
		}

		// Other data.
		$lead_data['lead']['ipAddress'] = Utils::get_user_ip() ?? '';
		$lead_data['lead']['referrer']  = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : ''; // phpcs:ignore
		$lead_data['lead']['timestamp'] = time();

		$this->add_lead( $lead_data );

		$settings = $this->get_settings();
		$res      = true;

		foreach ( $settings as $zap_id => $data ) {
			$is_success = $this->send_to_zapier( $data['target_url'], $lead_data );

			if ( ! $is_success ) {
				$res = false;
			}
		}

		return $res;
	}

	/**
	 * Add to lead table
	 *
	 * @param array $data data.
	 * @return void
	 */
	private function add_lead( $data ) {
		$lead_data = $data['lead'];

		$this->db->add_lead(
			array(
				'conv_id'     => $data['optin']['id'],
				'email'       => isset( $lead_data['email'] ) ? $lead_data['email'] : null,
				'name'        => isset( $lead_data['name'] ) ? $lead_data['name'] : null,
				'data'        => $data,
				'integration' => 'zapier',
			)
		);
	}

	/**
	 * Delete Zapier callback url
	 *
	 * @param string $url zapier callback url.
	 * @param array  $lead_data lead data.
	 * @return boolean
	 */
	public function send_to_zapier( $url, $lead_data ) {
		$res = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $lead_data ),
				'timeout' => 30,
			)
		);

		return ! is_wp_error( $res );
	}

	/**
	 * Get Settings
	 *
	 * @return array
	 */
	private function get_settings() {
		if ( empty( $this->settings ) ) {
			$this->settings = get_option( 'optn_int_zapier_data', array() );
		}

		return $this->settings;
	}

	/**
	 * Get Settings
	 *
	 * @param mixed $zap_id zap id.
	 * @return void
	 */
	private function delete_setting( $zap_id ) {
		$settings = get_option( 'optn_int_zapier_data', array() );

		unset( $settings[ $zap_id ] );

		$this->settings = $settings;

		update_option(
			'optn_int_zapier_data',
			$settings
		);
	}

	/**
	 * Set Settings
	 *
	 * @param string $target_url target url (hookUrl).
	 * @param string $zap_id zap id.
	 * @return void
	 */
	private function save_settings( $target_url, $zap_id ) {
		$settings = get_option( 'optn_int_zapier_data', array() );

		$settings[ $zap_id ] = array(
			'target_url' => $target_url,
		);

		$this->settings = $settings;

		update_option(
			'optn_int_zapier_data',
			$settings
		);
	}

	/**
	 * Permission callback.
	 *
	 * @return boolean
	 */
	public function permission_callback() {
		return true; // Public endpoint.
	}
}
