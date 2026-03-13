<?php // phpcs:ignore

namespace OPTN\Includes\Integrations\Implementations;

use OPTN\Includes\Integrations\Base\BaseMailIntegration;
use OPTN\Includes\Utils\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Get Response Integration class.
 *
 * @link https://apidocs.getresponse.com/v3
 */
class GetResponse extends BaseMailIntegration {

	/**
	 * Get name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'getresponse';
	}


	/**
	 * Adds a subscribe to get response
	 *
	 * @param array $data data.
	 * @return bool
	 *
	 * @link https://apireference.getresponse.com/#operation/createContact
	 */
	public function add_subscriber( $data ): bool {

		if ( ! isset( $data['fields'] ) || ! isset( $data['fields']['email'] ) ) {
			return false;
		}

		$fields = $data['fields'];

		$body = array(
			'email'     => $fields['email'],
			'ipAddress' => Utils::get_user_ip(),
		);
		if ( ! empty( $fields ) ) {

			$fields_arr = array();
			unset( $fields['email'] );
			if ( ! empty( $fields['name'] ) ) {
				$body['name'] = $fields['name'];
				unset( $fields['name'] );
			}
			foreach ( $fields as $key => $value ) {
				$fields_arr[] = array(
					'customFieldId' => $key,
					'value'         => is_array( $value ) ? $value : array( $value ),
				);
			}
			$body['customFieldValues'] = $fields_arr;

		}

		$success   = false;
		$duplicate = false;
		foreach ( $data['list'] as $list_id ) {
			$body['campaign'] = array(
				'campaignId' => $list_id,
			);

			$res = wp_remote_post(
				'https://api.getresponse.com/v3/contacts',
				array(
					'body'    => wp_json_encode( $body ),
					'timeout' => 45,
					'headers' => $this->get_headers(),
				)
			);

			if ( ! is_wp_error( $res ) ) {
				$code = (int) wp_remote_retrieve_response_code( $res );

				if ( in_array( $code, array( 200, 201, 202 ), true ) ) {
					$success = true;
				}

				// Duplicate email case.
				if ( in_array( $code, array( 409 ), true ) ) {
					$success   = true;
					$duplicate = true;
				}
			}
		}

		if ( $success && ! $duplicate ) {
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
			'name'        => isset( $fields['name'] ) ? $fields['name'] : null,
			'conv_id'     => intval( $data['conv_id'] ),
			'data'        => $fields,
			'integration' => $this->get_name(),
		);

		$this->db->add_lead( $leads_data );
	}


	/**
	 * Gets the fields for Get Response
	 *
	 * @param array $args receive data as array.
	 * @return array
	 *
	 * @link https://apireference.getresponse.com/#operation/getCustomFieldList
	 */
	public function get_fields( $args = array() ): array {

		$use_cache = $args['use_cache'];
		if ( $use_cache ) {
			$res = $this->get_cached_fields();

			if ( ! empty( $res ) ) {
				return $res;
			}
		}

		$res = wp_remote_get(
			'https://api.getresponse.com/v3/custom-fields',
			array(
				'timeout' => 45,
				'headers' => $this->get_headers(),
			)
		);

		if ( ! is_wp_error( $res ) && 200 == wp_remote_retrieve_response_code( $res ) ) { // phpcs:ignore
			$body = wp_remote_retrieve_body( $res );
			$body = json_decode( $body, true );

			if ( isset( $body ) && is_array( $body ) ) {

				$ret = array(
					array(
						'value' => 'email',
						'label' => 'Email',
					),
					array(
						'value' => 'name',
						'label' => 'Name',
					),
				);

				foreach ( $body as $d ) {
					$ret[] = array(
						'value' => $d['customFieldId'],
						'label' => $d['name'],
					);
				}

				$this->set_fields_cache( $ret );

				return $ret;
			}
		}

		return array();
	}

	/**
	 * Gets groups for get response
	 *
	 * @param array $args receive data as array.
	 * @return array
	 *
	 * @link https://apireference.getresponse.com/#operation/getCampaignList
	 */
	public function get_lists( $args = array() ): array {
		$use_cache = $args['use_cache'];

		if ( $use_cache ) {
			$res = $this->get_cached_lists();

			if ( ! empty( $res ) ) {
				return $res;
			}
		}

		$res = wp_remote_get(
			'https://api.getresponse.com/v3/campaigns',
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
						'value' => $d['campaignId'],
						'label' => $d['name'],
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

		$settings = $this->get_settings();

		return array(
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
			'X-Auth-Token' => 'api-key ' . $settings['apiKey'],
		);
	}
}
