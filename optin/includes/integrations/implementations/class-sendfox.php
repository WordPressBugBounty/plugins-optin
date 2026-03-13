<?php // phpcs:ignore

namespace OPTN\Includes\Integrations\Implementations;

use OPTN\Includes\Integrations\Base\BaseMailIntegration;
use OPTN\Includes\Utils\Cache;
use OPTN\Includes\Utils\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Sendfox Integration class.
 *
 * @link https://help.sendfox.com/category/270-api-integrations
 */
class Sendfox extends BaseMailIntegration {

	/**
	 * Get name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'sendfox';
	}

	/**
	 * Adds a subscribe to Sendfox
	 *
	 * @param array $data data.
	 * @return bool
	 *
	 * @link https://help.sendfox.com/article/278-endpoints
	 */
	public function add_subscriber( $data ): bool {

		if ( ! isset( $data['fields'] ) || ! isset( $data['fields']['email'] ) ) {
			return false;
		}

		if ( isset( $data['fields']['lists'] ) ) {
			$data['fields']['lists'] = array_map( 'intval', $data['fields']['lists'] );
		}

		$res = wp_remote_post(
			'https://api.sendfox.com/contacts',
			array(
				'body'    => wp_json_encode( $data['fields'] ),
				'timeout' => 45,
				'headers' => $this->get_headers(),
			)
		);

		// Response code should be either 200 or 201.
		if ( ! is_wp_error( $res ) && in_array( wp_remote_retrieve_response_code( $res ), array( 200, 201 ) ) ) { // phpcs:ignore
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
			'name'        => trim( ( $fields['first_name'] ?? '' ) . ' ' . ( $fields['last_name'] ?? '' ) ),
			'conv_id'     => intval( $data['conv_id'] ),
			'data'        => $fields,
			'integration' => 'sendfox',
		);

		$this->db->add_lead( $leads_data );
	}


	/**
	 * Gets the fields for Sendfox
	 *
	 * @param array $args receive data as array.
	 * @return array
	 *
	 * @link https://help.sendfox.com/article/278-endpoints
	 */
	public function get_fields( $args = array() ): array {
		return array(
			array(
				'value' => 'email',
				'label' => 'Email',
			),
			array(
				'value' => 'first_name',
				'label' => 'First Name',
			),
			array(
				'value' => 'last_name',
				'label' => 'Last Name',
			),
		);
	}

	/**
	 * Gets lists for sendfox
	 *
	 * @param array $args receive data as array.
	 * @return array
	 *
	 * @link https://help.sendfox.com/article/278-endpoints
	 */
	public function get_lists( $args = array() ): array {
		$use_cache = $args['use_cache'];

		if ( $use_cache ) {
			$res = $this->get_cached_lists();

			if ( ! empty( $res ) ) {
				return $res;
			}
		}

		$max_pages = 100;
		$curr_page = 1;
		$ret       = array();

		while ( true ) {
			$res = wp_remote_get(
				'https://api.sendfox.com/lists?page=' . $curr_page,
				array(
					'timeout' => 45,
					'headers' => $this->get_headers(),
				)
			);

			if ( is_wp_error( $res ) || 200 != wp_remote_retrieve_response_code( $res ) ) { // phpcs:ignore
				break;
			}

			$body = wp_remote_retrieve_body( $res );
			$body = json_decode( $body, true );

			if ( empty( $body['data'] ) ) {
				break;
			}

			foreach ( $body['data'] as $d ) {
				$ret[] = array(
					'value' => $d['id'],
					'label' => $d['name'],
				);
			}

			++$curr_page;

			if ( $curr_page > $max_pages ) {
				break;
			}
		}

		$this->set_lists_cache( $ret );

		return $ret;
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
			'Authorization' => 'Bearer ' . $settings['apiKey'],
		);
	}
}
