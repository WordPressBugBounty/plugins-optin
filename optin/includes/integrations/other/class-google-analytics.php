<?php // phpcs:ignore

namespace OPTN\Includes\Integrations\Other;

use OPTN\Includes\Db;
use OPTN\Includes\Utils\Utils;

defined( 'ABSPATH' ) || exit;

// TODO @samin: Implement batching.

/**
 * Google Analytics Integration class.
 */
class GoogleAnalytics {

	/**
	 * Google Analytics root url
	 *
	 * @var string
	 */
	private $ga_url = 'https://www.google-analytics.com/mp/collect';

	/**
	 * DB instance
	 *
	 * @var Db $db
	 */
	private $db;

	/**
	 * Settings
	 *
	 * @var array|null
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
		add_action( 'optn_viewed_optin', array( $this, 'emit_view_event' ) );
		add_action( 'optn_converted', array( $this, 'emit_conversion_event' ) );
		add_action( 'optn_purchased', array( $this, 'emit_purchase_event' ) );
	}

	/**
	 * View event
	 *
	 * @param array $data data.
	 * @return void
	 */
	public function emit_view_event( $data ) {

		$optin = $this->db->get_conv_by_id( $data['conv_id'] );

		$event_data = array(
			array(
				'name'   => 'impression',
				'params' => array(
					'optin_id'    => $optin->id,
					'optin_title' => $optin->title,
				),
			),
		);

		$this->send_to_ga4( $event_data );
	}

	/**
	 * Conversion Event
	 *
	 * @param array $data data.
	 * @return void
	 */
	public function emit_conversion_event( $data ) {
		$optin = $this->db->get_optin_by_interaction_id( $data['id'] );

		$event_data = array(
			array(
				'name'   => 'conversion',
				'params' => array(
					'optin_id'    => $optin->id,
					'optin_title' => $optin->title,
				),
			),
		);

		$this->send_to_ga4( $event_data );
	}

	/**
	 * Purchase Event
	 *
	 * @param array $data data.
	 * @return void
	 */
	public function emit_purchase_event( $data ) {
		$optin = $this->db->get_conv_by_id( $data['conv_id'] );

		$event_data = array(
			array(
				'name'   => 'purchase',
				'params' => array(
					'optin_id'    => $optin->id,
					'optin_title' => $optin->title,
				),
			),
		);

		$this->send_to_ga4( $event_data );
	}

	/**
	 * Send event to GA4
	 *
	 * @param array $events events.
	 * @return boolean
	 */
	private function send_to_ga4( $events ) {

		$settings = $this->get_settings();
		$ret      = true;

		foreach ( $settings as $setting ) {
			$url = add_query_arg(
				array(
					'measurement_id' => $setting['data']['measurementId'],
					'api_secret'     => $setting['data']['apiKey'],
				),
				$this->ga_url
			);

			$payload = array(
				'client_id' => Utils::get_user_hash(),
				'events'    => $events,
			);

			$resp = $this->send( $url, $payload );

			if ( is_wp_error( $resp ) || 204 !== wp_remote_retrieve_response_code( $resp ) ) {
				$ret = false;
			}
		}

		return $ret;
	}

	/**
	 * Send API Request
	 *
	 * @param string $url url.
	 * @param array  $payload payload.
	 * @return array|WP_Error
	 */
	private function send( $url, $payload ) {
		return wp_remote_post(
			$url,
			array(
				'body'    => wp_json_encode( $payload ),
				'headers' => array(
					'Content-Type' => 'application/json',
					'User-Agent'   => 'WowOptin/' . OPTN_VERSION,
				),
				'timeout' => 10,
			)
		);
	}

	/**
	 * Get settings.
	 *
	 * @return array
	 */
	private function get_settings() {
		if ( is_null( $this->settings ) ) {
			$this->settings = $this->db->get_integrations_by_type( 'ga' );
		}

		return $this->settings;
	}
}
