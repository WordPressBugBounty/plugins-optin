<?php // phpcs:ignore

namespace OPTN\Includes\Integrations\Implementations;

use OPTN\Includes\Integrations\Base\BaseMailIntegration;
use OPTN\Includes\Utils\Cache;
use OPTN\Includes\Utils\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * MailerLite Integration class.
 *
 * @link https://developers.mailerlite.com/docs/
 */
class MailerLite extends BaseMailIntegration {

	/**
	 * Get name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'mailerlite';
	}

	/**
	 * Adds a subscribe to MailerLite
	 *
	 * @param array $data data.
	 * @return bool
	 *
	 * @link https://developers.mailerlite.com/docs/subscribers.html#create-upsert-subscriber
	 */
	public function add_subscriber( $data ): bool {

		if ( ! isset( $data['fields'] ) || ! isset( $data['fields']['email'] ) ) {
			return false;
		}

		$email = $data['fields']['email'];
		unset( $data['fields']['email'] );

		$body = array(
			'email'      => $email,
			'fields'     => $data['fields'],
			'groups'     => $data['group'],
			'status'     => 'active',
			'ip_address' => Utils::get_user_ip(),
		);

		$res = wp_remote_post(
			'https://connect.mailerlite.com/api/subscribers',
			array(
				'body'    => wp_json_encode( $body ),
				'timeout' => 45,
				'headers' => $this->get_headers(),
			)
		);

		// Response code should be either 200 or 201.
		if ( ! is_wp_error( $res ) && in_array( wp_remote_retrieve_response_code( $res ), array( 200, 201 ) ) ) { // phpcs:ignore
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
			'name'        => Utils::extract_name( $fields ),
			'conv_id'     => intval( $data['conv_id'] ),
			'data'        => $fields,
			'integration' => 'mailerlite',
		);

		$this->db->add_lead( $leads_data );
	}


	/**
	 * Gets the fields for Mailer Lite
	 *
	 * @param array $args receive data as array.
	 * @return array
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
			'https://connect.mailerlite.com/api/fields',
			array(
				'timeout' => 45,
				'headers' => $this->get_headers(),
			)
		);

		if ( ! is_wp_error( $res ) && 200 == wp_remote_retrieve_response_code( $res ) ) { // phpcs:ignore
			$body = wp_remote_retrieve_body( $res );
			$body = json_decode( $body, true );

			if ( isset( $body['data'] ) && is_array( $body['data'] ) ) {

				$ret = array();

				foreach ( $body['data'] as $d ) {
					$ret[] = array(
						'value' => $d['key'],
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
	 * Gets groups for mailer lite
	 *
	 * @param array $args receive data as array.
	 * @return array
	 *
	 * @link https://developers.mailerlite.com/docs/groups.html#list-all-groups
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
			'https://connect.mailerlite.com/api/groups',
			array(
				'timeout' => 45,
				'headers' => $this->get_headers(),
			)
		);

		if ( ! is_wp_error( $res ) && 200 == wp_remote_retrieve_response_code( $res ) ) { // phpcs:ignore
			$body = wp_remote_retrieve_body( $res );
			$body = json_decode( $body, true );

			if ( isset( $body['data'] ) && is_array( $body['data'] ) ) {

				$ret = array();

				foreach ( $body['data'] as $d ) {
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
}
