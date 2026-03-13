<?php // phpcs:ignore

namespace OPTN\Includes\Integrations\Implementations;

use OPTN\Includes\Integrations\Base\BaseMailIntegration;
use OPTN\Includes\Utils\Cache;
use OPTN\Includes\Utils\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Brevo Integration class.
 *
 * @link https://developers.brevo.com/reference/getting-started-1
 */
class Brevo extends BaseMailIntegration {

	/**
	 * Get name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'brevo';
	}

	/**
	 * Adds a subscribe to Brevo
	 *
	 * @param array $data data.
	 * @return bool
	 *
	 * @link https://developers.brevo.com/reference/createcontact
	 */
	public function add_subscriber( $data ): bool {

		if ( ! isset( $data['fields'] ) || ! isset( $data['fields']['email'] ) ) {
			return false;
		}

		$email = $data['fields']['email'];
		unset( $data['fields']['email'] );

		$body = array(
			'email'         => $email,
			'listIds'       => $data['list'],
			'updateEnabled' => true,
		);

		// Sending empty attributes will fail the request.
		if ( ! empty( $data['fields'] ) ) {
			$body['attributes'] = $data['fields'];
		}

		$res = wp_remote_post(
			'https://api.brevo.com/v3/contacts',
			array(
				'body'    => wp_json_encode( $body ),
				'timeout' => 45,
				'headers' => $this->get_headers(),
			)
		);

		$code = wp_remote_retrieve_response_code( $res );

		// Brevo returns 400 if email already exists.
		if ( 400 === $code ) {
			$body       = wp_remote_retrieve_body( $res );
			$body       = json_decode( $body, true );
			$error_code = $body['code'] ?? '';

			if ( 'duplicate_parameter' === $error_code ) {
				return true;
			}
		}

		if ( ! is_wp_error( $res ) && in_array( $code, array( 200, 201, 204 ) ) ) { // phpcs:ignore
			$data['fields']['email'] = $email;
			$this->add_lead( $data );
			return true;
		}

		return false;
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
			'integration' => 'brevo',
		);

		$this->db->add_lead( $leads_data );
	}


	/**
	 * Gets the fields for brevo
	 *
	 * @param array $args receive data as array.
	 * @return array
	 *
	 * @link https://developers.brevo.com/reference/getattributes-1
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
			'https://api.brevo.com/v3/contacts/attributes',
			array(
				'timeout' => 45,
				'headers' => $this->get_headers(),
			)
		);

		if ( ! is_wp_error( $res ) && 200 == wp_remote_retrieve_response_code( $res ) ) { // phpcs:ignore
			$body = wp_remote_retrieve_body( $res );
			$body = json_decode( $body, true );

			if ( isset( $body['attributes'] ) && is_array( $body['attributes'] ) ) {

				$ret = array();

				foreach ( $body['attributes'] as $d ) {
					$ret[] = array(
						'value' => $d['name'],
						'label' => $d['name'],
					);
				}

				$ret[] = array(
					'value' => 'email',
					'label' => 'Email',
				);

				$this->set_fields_cache( $ret );

				return $ret;
			}
		}

		return array();
	}

	/**
	 * Gets groups for brevo
	 *
	 * @param array $args receive data as array.
	 * @return array
	 *
	 * @link https://developers.brevo.com/reference/getlists-1
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
			'https://api.brevo.com/v3/contacts/lists',
			array(
				'timeout' => 45,
				'headers' => $this->get_headers(),
			)
		);

		if ( ! is_wp_error( $res ) && 200 == wp_remote_retrieve_response_code( $res ) ) { // phpcs:ignore
			$body = wp_remote_retrieve_body( $res );
			$body = json_decode( $body, true );

			if ( isset( $body['lists'] ) && is_array( $body['lists'] ) ) {

				$ret = array();

				foreach ( $body['lists'] as $d ) {
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
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
			'api-key'      => $settings['apiKey'],
		);
	}
}
