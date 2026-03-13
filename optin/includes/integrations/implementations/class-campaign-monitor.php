<?php // phpcs:ignore

namespace OPTN\Includes\Integrations\Implementations;

use http\Url;
use OPTN\Includes\Integrations\Base\BaseMailIntegration;
use OPTN\Includes\Utils\Cache;
use OPTN\Includes\Utils\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Campaign Monitor Integration class.
 *
 * @link https://campaignmonitor.com/developer/marketing/docs/fundamentals/
 */
class CampaignMonitor extends BaseMailIntegration {

	/**
	 * Get name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'campaignmonitor';
	}

	/**
	 * Endpoint for Campaign Monitor
	 *
	 * @var Url $end_point
	 */
	private $client_id;

	/**
	 * Constructor
	 *
	 * @param int|null $account_id account id.
	 */
	public function __construct( $account_id ) {
		parent::__construct( $account_id );
		$settings        = $this->get_settings();
		$this->client_id = $settings['client_id'];
	}

	/**
	 * Adds a subscribe to campaign monitor by list id
	 *
	 * @param array $data data.
	 * @return bool
	 *
	 * @link https://campaignmonitor.com/developer/marketing/api/list-members/add-member-to-list/
	 */
	public function add_subscriber( $data ): bool {

		if ( ! isset( $data['fields'] ) || ! isset( $data['fields']['EmailAddress'] ) ) {
			return false;
		}

		$email = $data['fields']['EmailAddress'];
		unset( $data['fields']['EmailAddress'] );
		$properties = array();
		foreach ( $data['fields'] as $field_name => $field_value ) {
			if ( ! empty( $field_name ) && ! empty( $field_value ) ) {
				$properties[] = array(
					'key'   => $field_name,
					'value' => $field_value,
				);
			}
		}
		$body = array(
			'EmailAddress'   => $email,
			'ConsentToTrack' => 'Unchanged',
			'CustomFields'   => $properties,
		);
		if ( ! empty( $data['fields']['Name'] ) ) {
			$body['Name'] = $data['fields']['Name'];
		}

		$success = false;

		foreach ( $data['list'] as $list_id ) {

			$res = wp_remote_post(
				'https://api.createsend.com/api/v3.3/subscribers/' . $list_id . '.json',
				array(
					'body'    => wp_json_encode( $body ),
					'timeout' => 45,
					'headers' => $this->get_headers(),
				)
			);

			// Response code should be either 200 or 201.
			if ( ! is_wp_error( $res ) && in_array( wp_remote_retrieve_response_code( $res ), array( 200, 201 ) ) ) { // phpcs:ignore
				$success = true;
			}
		}

		if ( $success ) {
			$data['fields']['email'] = $email;
			$this->add_lead( $data );
		}

		return $success;
	}


	/**
	 * Adds a lead to the leads table
	 *
	 * @param array $data data.
	 * @return void
	 */
	public function add_lead( $data ) {

		$fields       = Utils::sanitize_json_array( $data['fields'] );
		$fields['ip'] = Utils::get_user_ip();
		$leads_data   = array(
			'email'       => sanitize_email( $fields['email'] ),
			'name'        => isset( $fields['name'] ) ? $fields['name'] : null,
			'conv_id'     => intval( $data['conv_id'] ),
			'data'        => $fields,
			'integration' => 'campaignmonitor',
		);

		$this->db->add_lead( $leads_data );
	}


	/**
	 * Gets the fields for campaign monitor by list id
	 *
	 * @param array $args receive data as array.
	 * @return array
	 * @link https://www.campaignmonitor.com/api/v3-3/lists/#list-custom-fields
	 */
	public function get_fields( $args = array() ): array {

		if ( empty( $args['list_id'] ) ) {
			return array();
		}
		$use_cache = $args['use_cache'];

		$list_ids      = is_array( $args['list_id'] ) ? $args['list_id'] : array( $args['list_id'] );
		$unique_fields = array(
			array(
				'value' => 'EmailAddress',
				'label' => 'Email Address',
			),
			array(
				'value' => 'Name',
				'label' => 'Name',
			),
			array(
				'value' => 'MobileNumber',
				'label' => 'Mobile Number',
			),
		);

		foreach ( $list_ids as $list_id ) {
			if ( $use_cache ) {
				$cached = Cache::get( 'optn_int_campaignmonitor_fields_' . $this->account_id . '_' . $list_id );
				if ( ! empty( $cached ) ) {
					$fields        = json_decode( $cached, true );
					$unique_fields = $this->merge_unique_fields( $unique_fields, $fields );
					continue;
				}
			}

			$res = wp_remote_get(
				'https://api.createsend.com/api/v3.3/lists/' . $list_id . '/customfields.json',
				array(
					'timeout' => 45,
					'headers' => $this->get_headers(),
				)
			);

			if ( ! is_wp_error( $res ) && 200 == wp_remote_retrieve_response_code( $res ) ) { // phpcs:ignore
				$body = wp_remote_retrieve_body( $res );
				$body = json_decode( $body, true );

				if ( isset( $body ) && is_array( $body ) ) {
					$fields = array();

					foreach ( $body as $d ) {
						$fields[] = array(
							'value' => $d['FieldName'],
							'label' => $d['FieldName'],
						);
					}

					// Cache the fields.
					Cache::set( 'optn_int_campaignmonitor_fields_' . $this->account_id . '_' . $list_id, wp_json_encode( $fields ) );

					$unique_fields = $this->merge_unique_fields( $unique_fields, $fields );
				}
			}
		}

		return $unique_fields;
	}

	/**
	 * Merge fields for multiple list
	 *
	 * @param array $existing_fields existing fields.
	 * @param array $new_fields new fields.
	 * @return array
	 */
	private function merge_unique_fields( array $existing_fields, array $new_fields ): array {
		foreach ( $new_fields as $field ) {
			if ( ! array_key_exists( $field['value'], array_column( $existing_fields, 'value' ) ) ) {
				$existing_fields[] = $field;
			}
		}

		return $existing_fields;
	}

	/**
	 * Gets list for campaign monitor
	 *
	 * @param array $args receive optional data as array.
	 * @return array
	 *
	 * @link https://www.campaignmonitor.com/api/v3-3/clients/#getting-subscriber-lists
	 */
	public function get_lists( $args = array() ): array {
		if ( ! empty( $args['list_ids'] ) ) {
			return array();
		}
		$use_cache = $args['use_cache'];
		if ( $use_cache ) {
			$res = Cache::get( 'optn_int_campaignmonitor_lists_' . $this->account_id );

			if ( ! empty( $res ) ) {
				return json_decode( $res );
			}
		}

		$res = wp_remote_get(
			'https://api.createsend.com/api/v3.3/clients/' . $this->client_id . '/lists.json',
			array(
				'timeout' => 45,
				'headers' => $this->get_headers(),
			)
		);

		if ( ! is_wp_error( $res ) && 200 == wp_remote_retrieve_response_code( $res ) ) { // phpcs:ignore
			$body = wp_remote_retrieve_body( $res );
			$body = json_decode( $body, true );

			if ( isset( $body ) && is_array( $body ) ) {

				$ret = array();

				foreach ( $body as $d ) {
					$ret[] = array(
						'value' => $d['ListID'],
						'label' => $d['Name'],
					);
				}

				Cache::set( 'optn_int_campaignmonitor_lists_' . $this->account_id, wp_json_encode( $ret ) );

				return $ret;
			}
		}

		return array();
	}

	/**
	 * Gets headers with authorization token
	 *
	 * @return array
	 */
	protected function get_headers() {

		$settings    = $this->get_settings();
		$credentials = base64_encode( $settings['apiKey'] . ':' ); // phpcs:ignore

		return array(
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
			'Authorization' => 'Basic ' . $credentials,
		);
	}
}
