<?php // phpcs:ignore

namespace OPTN\Includes\Integrations\Implementations;

use OPTN\Includes\Integrations\Base\BaseMailIntegration;
use OPTN\Includes\Utils\Cache;
use OPTN\Includes\Utils\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Acumbamail Integration class.
 *
 * @link https://acumbamail.com/en/apidoc/
 */
class Acumbamail extends BaseMailIntegration {

	/**
	 * Get name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'acumbamail';
	}

	/**
	 * Adds a subscriber to acumbamail by list id
	 *
	 * @param array $data data.
	 * @return bool
	 *
	 * @link https://acumbamail.com/en/apidoc/function/addSubscriber/
	 */
	public function add_subscriber( $data ): bool {

		if ( ! isset( $data['fields'] ) || ! isset( $data['list'] ) ) {
			return false;
		}

		$settings = $this->get_settings();

		$body = array(
			'auth_token'        => $settings['apiKey'],
			'list_id'           => $data['list'],
			'merge_fields'      => $data['fields'],
			'double_optin'      => ! empty( $data['doubleOpt'] ) ? 1 : 0,
			'update_subscriber' => ! empty( $data['updateExisting'] ) ? 1 : 0,
			'complete_json'     => 1,
		);

		$res = wp_remote_post(
			'https://acumbamail.com/api/1/addSubscriber',
			array(
				'timeout' => 45,
				'headers' => $this->get_headers(),
				'body'    => $body,
			)
		);

		// Response code should be either 200 or 201.
		if ( ! is_wp_error( $res ) && in_array( wp_remote_retrieve_response_code( $res ), array( 200, 201 ) ) ) { // phpcs:ignore
			$email                   = json_decode( wp_remote_retrieve_body( $res ), true )['email'] ?? $data['fields']['email'] ?? '';
			$data['fields']['email'] = $email;
			$this->add_lead(
				$data
			);
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
			'name'        => '',
			'conv_id'     => intval( $data['conv_id'] ),
			'data'        => $fields,
			'integration' => 'acumbamail',
		);

		$this->db->add_lead( $leads_data );
	}


	/**
	 * Gets fields for a list
	 *
	 * @param array $args receive data as array.
	 * @return array
	 * @link https://acumbamail.com/en/apidoc/function/getFields/
	 */
	public function get_fields( $args = array() ): array {

		$list_id = $args['list_id'] ?? null;

		if ( empty( $list_id ) ) {
			return array();
		}

		$settings = $this->get_settings();

		$use_cache = $args['use_cache'] ?? false;

		if ( $use_cache ) {
			$cached = Cache::get( 'optn_int_acumbamail_fields_' . $this->account_id . '_' . $list_id );
			if ( ! empty( $cached ) ) {
				$fields = json_decode( $cached, true );
				return is_array( $fields ) ? $fields : array();
			}
		}

		$url = add_query_arg(
			array(
				'auth_token' => $settings['apiKey'],
				'list_id'    => $list_id,
			),
			'https://acumbamail.com/api/1/getListFields'
		);

		$res = wp_remote_get(
			$url,
			array(
				'timeout' => 45,
			)
		);

		$fields = array();

		if ( ! is_wp_error( $res ) && 200 === wp_remote_retrieve_response_code( $res ) ) {
			$body = wp_remote_retrieve_body( $res );
			$body = json_decode( $body, true );

			if ( ! empty( $body['fields'] ) && is_array( $body['fields'] ) ) {
				foreach ( $body['fields'] as $fd ) {
					$fields[] = array(
						'value' => $fd['tag'],
						'label' => $fd['label'],
					);
				}
			}

			Cache::set( 'optn_int_acumbamail_fields_' . $this->account_id . '_' . $list_id, wp_json_encode( $fields ) );
		}

		return $fields;
	}

	/**
	 * Gets list for acumbamail
	 *
	 * @param array $args receive optional data as array.
	 * @return array
	 *
	 * @link https://acumbamail.com/en/apidoc/function/getLists/
	 */
	public function get_lists( $args = array() ): array {

		if ( $args['use_cache'] ) {
			$res = $this->get_cached_lists();

			if ( ! empty( $res ) ) {
				return $res;
			}
		}

		$settings = $this->get_settings();

		$url = add_query_arg(
			array(
				'auth_token' => $settings['apiKey'],
			),
			'https://acumbamail.com/api/1/getLists'
		);

		$res = wp_remote_get(
			$url,
			array(
				'timeout' => 45,
			)
		);

		$lists = array();

		if ( ! is_wp_error( $res ) && 200 === wp_remote_retrieve_response_code( $res ) ) {
			$body = wp_remote_retrieve_body( $res );
			$body = json_decode( $body, true );

			if ( is_array( $body ) ) {
				foreach ( $body as $list_id => $list_data ) {
					$lists[] = array(
						'value' => $list_id,
						'label' => $list_data['name'] ?? $list_id,
					);
				}
			}

			$this->set_lists_cache( $lists );
		}

		return $lists;
	}

	/**
	 * Gets headers with authorization token
	 *
	 * @return array
	 */
	protected function get_headers() {
		return array(
			'Content-Type' => 'application/x-www-form-urlencoded',
		);
	}
}
