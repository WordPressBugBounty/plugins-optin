<?php // phpcs:ignore

namespace OPTN\Includes\Integrations\Implementations;

use OPTN\Includes\Integrations\Base\BaseMailIntegration;
use OPTN\Includes\Utils\Cache;
use OPTN\Includes\Utils\Utils;
use WpOrg\Requests\Requests;

defined( 'ABSPATH' ) || exit;

/**
 * Convert Kit Integration class.
 *
 * @link https://developers.kit.com/v4
 */
class ConvertKit extends BaseMailIntegration {

	/**
	 * Get name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'convertkit';
	}

	/**
	 * Adds a subscribe to convert kit
	 *
	 * @param array $data data.
	 * @return bool
	 *
	 * @link https://developers.kit.com/api-reference/subscribers/create-a-subscriber
	 * @link https://developers.kit.com/v4#tag-a-subscriber
	 */
	public function add_subscriber( $data ): bool {

		if ( ! isset( $data['fields'] ) || ! isset( $data['fields']['email'] ) ) {
			return false;
		}

		$email = $data['fields']['email'];
		unset( $data['fields']['email'] );
		$body = array(
			'email_address' => $email,
		);
		if ( ! empty( $data['fields']['first_name'] ) ) {
			$body['first_name'] = $data['fields']['first_name'];
		}
		$fields = array();

		if ( ! empty( $data['fields'] ) ) {
			foreach ( $data['fields'] as $key => $value ) {
				$fields[ $key ] = $value;
			}
			$body['fields'] = $fields;
		}

		$sub_res = Utils::get_remote_req_body(
			wp_remote_post(
				'https://api.kit.com/v4/subscribers',
				array(
					'body'    => wp_json_encode( $body ),
					'timeout' => 45,
					'headers' => $this->get_headers(),
				)
			),
			array( 200, 201 ) // Response code should be either 200 or 201.
		);

		if ( ! empty( $sub_res->error ) ) {
			return false;
		}

		$sub_id = isset( $sub_res->data['subscriber']['id'] ) ? $sub_res->data['subscriber']['id'] : null;

		if ( isset( $data['list'] ) && is_array( $data['list'] ) && ! empty( $sub_id ) ) {

			// Convert Kit doesn't support bulk with OAuth keys. So we need to make multiple requests.
			foreach ( $data['list'] as $list_id ) {
				$url = "https://api.kit.com/v4/tags/{$list_id}/subscribers/${sub_id}";

				$requests[ $list_id ] = array(
					'url'     => $url,
					'type'    => 'POST',
					'headers' => $this->get_headers(),
					'data'    => array(),
					'options' => array(
						'timeout' => 45,
					),
				);
			}

			$responses = Requests::request_multiple( $requests );

			foreach ( $responses as $list_id => $response ) {
				if ( is_a( $response, 'Requests_Exception' ) ) {
					return false;
				}

				if ( ! in_array( $response->status_code, array( 200, 201 ) ) ) { // phpcs:ignore
					return false;
				}
			}
		}

		$data['fields']['email'] = $email;
		$this->add_lead( $data );
		return true;
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
			'name'        => $fields['first_name'] ?? null,
			'conv_id'     => intval( $data['conv_id'] ),
			'data'        => $fields,
			'integration' => 'convertkit',
		);

		$this->db->add_lead( $leads_data );
	}


	/**
	 * Gets the fields for convert kit
	 *
	 * @param array $args receive data as array.
	 * @return array
	 *
	 * @link https://developers.kit.com/v4#list-custom-fields
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
			'https://api.kit.com/v4/custom_fields',
			array(
				'timeout' => 45,
				'headers' => $this->get_headers(),
			)
		);

		if ( ! is_wp_error( $res ) && 200 == wp_remote_retrieve_response_code( $res ) ) { // phpcs:ignore
			$body = wp_remote_retrieve_body( $res );
			$body = json_decode( $body, true );

			if ( isset( $body['custom_fields'] ) && is_array( $body['custom_fields'] ) ) {

				$ret = array(
					array(
						'value' => 'email',
						'label' => 'Email',
					),
					array(
						'value' => 'first_name',
						'label' => 'First Name',
					),
				);
				foreach ( $body['custom_fields'] as $d ) {
					$ret[] = array(
						'value' => $d['key'],
						'label' => $d['label'],
					);
				}

				$this->set_fields_cache( $ret );

				return $ret;
			}
		}

		return array();
	}

	/**
	 * Gets list for convert kit
	 *
	 * @link https://developers.kit.com/v4#list-tags
	 * @param array $args receive data as array.
	 * @return array
	 */
	public function get_lists( $args = array() ): array {
		$use_cache = $args['use_cache'];

		if ( $use_cache ) {
			$res = $this->get_cached_lists();

			if ( ! empty( $res ) ) {
				return $res;
			}
		}

		$url = add_query_arg(
			array(
				'per_page' => 1000,
			),
			'https://api.kit.com/v4/tags',
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

			if ( isset( $body['tags'] ) && is_array( $body['tags'] ) ) {

				$ret = array();

				foreach ( $body['tags'] as $d ) {
					$ret[] = array(
						'value' => $d['id'],
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
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
			'X-Kit-Api-Key' => $settings['apiKey'],
		);
	}
}
