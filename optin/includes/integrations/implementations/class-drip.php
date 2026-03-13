<?php // phpcs:ignore

namespace OPTN\Includes\Integrations\Implementations;

use http\Url;
use OPTN\Includes\Integrations\Base\BaseMailIntegration;
use OPTN\Includes\Utils\Cache;
use OPTN\Includes\Utils\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Drip Integration class.
 *
 * @link https://developer.drip.com/
 */
class Drip extends BaseMailIntegration {

	/**
	 * Get name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'drip';
	}

	/**
	 * Adds a subscribe to Drip by account id
	 *
	 * @param array $data data.
	 * @return bool
	 *
	 * @link https://developer.drip.com/?shell#create-or-update-a-subscriber
	 */
	public function add_subscriber( $data ): bool {

		if ( ! isset( $data['fields'] ) || ! isset( $data['fields']['email'] ) ) {
			return false;
		}

		$subscribers = array();

		if ( ! empty( $data['fields'] ) ) {
			foreach ( $data['fields'] as $key => $value ) {
				$subscribers[ $key ] = $value;
			}
		}

		$body = array(
			'subscribers' => array( $subscribers ),
		);

		$success = false;

		foreach ( $data['list'] as $list_id ) {

			$res = wp_remote_post(
				'https://api.getdrip.com/v2/' . $list_id . '/subscribers',
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

		$leads_data = array(
			'email'       => sanitize_email( $fields['email'] ),
			'name'        => trim( ( $fields['first_name'] ?? '' ) . ' ' . ( $fields['last_name'] ?? '' ) ),
			'conv_id'     => intval( $data['conv_id'] ),
			'data'        => $fields,
			'integration' => 'drip',
		);

		$this->db->add_lead( $leads_data );
	}


	/**
	 * Gets the fields for Drip by account id
	 *
	 * @param array $args receive data as array.
	 * @return array
	 * @link https://developer.drip.com/?shell#custom-fields
	 */
	public function get_fields( $args = array() ): array {

		if ( empty( $args['list_id'] ) ) {
			return array();
		}
		$use_cache = $args['use_cache'];

		$list_ids      = is_array( $args['list_id'] ) ? $args['list_id'] : array( $args['list_id'] );
		$unique_fields = array(
			array(
				'value' => 'email',
				'label' => 'Email',
			),
		);

		foreach ( $list_ids as $list_id ) {
			if ( $use_cache ) {
				$cached = Cache::get( 'optn_int_drip_fields_' . $this->account_id . '_' . $list_id );
				if ( ! empty( $cached ) ) {
					$fields        = json_decode( $cached, true );
					$unique_fields = $this->merge_unique_fields( $unique_fields, $fields );
					continue;
				}
			}

			$res = wp_remote_get(
				'https://api.getdrip.com/v2/' . $list_id . '/custom_field_identifiers',
				array(
					'timeout' => 45,
					'headers' => $this->get_headers(),
				)
			);

			if ( ! is_wp_error( $res ) && 200 == wp_remote_retrieve_response_code( $res ) ) { // phpcs:ignore
				$body = wp_remote_retrieve_body( $res );
				$body = json_decode( $body, true );

				if ( isset( $body['custom_field_identifiers'] ) && is_array( $body['custom_field_identifiers'] ) ) {
					$fields = array();

					foreach ( $body['custom_field_identifiers'] as $d ) {
						$fields[] = array(
							'value' => $d,
							'label' => ucwords( str_replace( '_', ' ', $d ) ),
						);
					}

					// Cache the fields.
					Cache::set( 'optn_int_drip_fields_' . $this->account_id . '_' . $list_id, wp_json_encode( $fields ) );

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
	 * Gets account list for Drip
	 *
	 * @param array $args receive optional data as array.
	 * @return array
	 *
	 * @link https://developer.drip.com/?shell#list-all-accounts
	 */
	public function get_lists( $args = array() ): array {
		if ( ! empty( $args['list_ids'] ) ) {
			return array();
		}
		$use_cache = $args['use_cache'];
		if ( $use_cache ) {
			$res = Cache::get( 'optn_int_drip_lists_' . $this->account_id );

			if ( ! empty( $res ) ) {
				return json_decode( $res );
			}
		}

		$res = wp_remote_get(
			'https://api.getdrip.com/v2/accounts',
			array(
				'timeout' => 45,
				'headers' => $this->get_headers(),
			)
		);

		if ( ! is_wp_error( $res ) && 200 == wp_remote_retrieve_response_code( $res ) ) { // phpcs:ignore
			$body = wp_remote_retrieve_body( $res );
			$body = json_decode( $body, true );

			if ( isset( $body['accounts'] ) && is_array( $body['accounts'] ) ) {

				$ret = array();

				foreach ( $body['accounts'] as $d ) {
					$ret[] = array(
						'value' => $d['id'],
						'label' => $d['name'],
					);
				}

				Cache::set( 'optn_int_drip_lists_' . $this->account_id, wp_json_encode( $ret ) );

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

		$settings   = $this->get_settings();
		$basic_auth = base64_encode( $settings['apiKey'] . ':' ); // phpcs:ignore

		return array(
			'User-Agent'    => 'WowOptin/' . OPTN_VERSION,
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
			'Authorization' => 'Basic ' . $basic_auth,
		);
	}
}
