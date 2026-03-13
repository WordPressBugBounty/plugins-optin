<?php // phpcs:ignore

namespace OPTN\Includes\Integrations\Implementations;

use http\Url;
use OPTN\Includes\Integrations\Base\BaseMailIntegration;
use OPTN\Includes\Utils\Cache;
use OPTN\Includes\Utils\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Moosend Integration class.
 *
 * @link https://docs.moosend.com/developers/api-documentation/en/index-en.html
 */
class Moosend extends BaseMailIntegration {

	/**
	 * Get name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'moosend';
	}

	/**
	 * Adds a subscriber to Moosend by list id
	 *
	 * @param array $data data.
	 * @return bool
	 *
	 * @link https://docs.moosend.com/developers/api-documentation/en/index-en.html#UUID-ea43cb6a-a7ab-b638-dec2-ff7cedf8efa3
	 */
	public function add_subscriber( $data ): bool {

		if ( ! isset( $data['fields'] ) || ! isset( $data['fields']['Email'] ) ) {
			return false;
		}
		$settings = $this->get_settings();

		// Static fields array.
		$static_fields = array( 'Email', 'Name', 'Mobile', 'Preferences' );

		$body_template = array( 'HasExternalDoubleOptIn' => ! empty( $data['doubleOpt'] ) );

		// Custom dynamic fields.
		if ( ! empty( $data['fields'] ) ) {
			$custom_fields = array();
			foreach ( $data['fields'] as $key => $value ) {
				if ( ! in_array( $key, $static_fields ) ) {
					$custom_fields[] = "{$key}={$value}";
				}
			}

			$body_template['CustomFields'] = $custom_fields;
		}

		// Add static field to request body.
		foreach ( $static_fields as $field ) {
			if ( ! empty( $data['fields'][ $field ] ) ) {
				$body_template[ $field ] = $data['fields'][ $field ];
			}
		}

		$success = false;

		foreach ( $data['list'] as $list_id ) {
			$body = $body_template;

			$res = wp_remote_post(
				'https://api.moosend.com/v3/subscribers/' . $list_id . '/subscribe.json?apiKey=' . $settings['apiKey'],
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
			'name'        => $fields['name'] ?? null,
			'conv_id'     => intval( $data['conv_id'] ),
			'data'        => $fields,
			'integration' => 'moosend',
		);

		$this->db->add_lead( $leads_data );
	}


	/**
	 * Gets the fields for Moosend by list id
	 *
	 * @param array $args receive data as array.
	 * @return array
	 * @link https://docs.moosend.com/developers/api-documentation/en/index-en.html#UUID-2a6284dc-a340-66fa-c213-7e6490529f03
	 */
	public function get_fields( $args = array() ): array {

		if ( empty( $args['list_id'] ) ) {
			return array();
		}
		$use_cache = $args['use_cache'];

		$list_ids      = is_array( $args['list_id'] ) ? $args['list_id'] : array( $args['list_id'] );
		$settings      = $this->get_settings();
		$unique_fields = array(
			array(
				'value' => 'Email',
				'label' => 'Email',
			),
			array(
				'value' => 'Name',
				'label' => 'Name',
			),
			array(
				'value' => 'Mobile',
				'label' => 'Mobile',
			),
			array(
				'value' => 'Preferences',
				'label' => 'Preferences',
			),
		);

		foreach ( $list_ids as $list_id ) {
			if ( $use_cache ) {
				$cached = Cache::get( 'optn_int_moosend_fields_' . $this->account_id . '_' . $list_id );
				if ( ! empty( $cached ) ) {
					$fields        = json_decode( $cached, true );
					$unique_fields = $this->merge_unique_fields( $unique_fields, $fields );
					continue;
				}
			}

			$res = wp_remote_get(
				'https://api.moosend.com/v3/lists/' . $list_id . '/details.json?PageSize=1000&apiKey=' . $settings['apiKey'],
				array(
					'timeout' => 45,
					'headers' => $this->get_headers(),
				)
			);

			if ( ! is_wp_error( $res ) && 200 == wp_remote_retrieve_response_code( $res ) ) { // phpcs:ignore
				$body = wp_remote_retrieve_body( $res );
				$body = json_decode( $body, true );

				if ( isset( $body['Context']['CustomFieldsDefinition'] ) && is_array( $body['Context']['CustomFieldsDefinition'] ) ) {
					$fields = array();
					foreach ( $body['Context']['CustomFieldsDefinition'] as $d ) {
						$fields[] = array(
							'value' => $d['Name'],
							'label' => $d['Name'],
						);
					}

					// Cache the fields.
					Cache::set( 'optn_int_moosend_fields_' . $this->account_id . '_' . $list_id, wp_json_encode( $fields ) );

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
	 * Gets list for moosend
	 *
	 * @param array $args receive optional data as array.
	 * @return array
	 *
	 * @link https://docs.moosend.com/developers/api-documentation/en/index-en.html#UUID-0e1c5a07-da45-a495-2bd0-a1093a45a806
	 */
	public function get_lists( $args = array() ): array {

		if ( ! empty( $args['list_ids'] ) ) {
			return array();
		}
		if ( $args['use_cache'] ) {
			$res = $this->get_cached_lists();

			if ( ! empty( $res ) ) {
				return $res;
			}
		}
		$settings = $this->get_settings();

		$url = add_query_arg(
			array(
				'apiKey'   => $settings['apiKey'],
				'PageSize' => 1000,
			),
			'https://api.moosend.com/v3/lists.json'
		);

		$res = wp_remote_get(
			$url,
			array(
				'timeout' => 45,
				'headers' => $this->get_headers(),
			)
		);

		if ( ! is_wp_error( $res ) && 200 == wp_remote_retrieve_response_code( $res ) ) { // phpcs:ignore
			$body = wp_remote_retrieve_body( $res );
			$body = json_decode( $body, true );

			if ( isset( $body['Context']['MailingLists'] ) && is_array( $body['Context']['MailingLists'] ) ) {

				$ret = array();

				foreach ( $body['Context']['MailingLists'] as $d ) {
					$ret[] = array(
						'value' => $d['ID'],
						'label' => $d['Name'],
					);
				}

				$this->set_lists_cache( $ret );

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

		return array(
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
		);
	}
}
