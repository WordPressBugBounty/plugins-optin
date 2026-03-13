<?php // phpcs:ignore

namespace OPTN\Includes\Integrations\Implementations;

use OPTN\Includes\Integrations\Base\BaseMailIntegration;
use OPTN\Includes\Utils\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * MailerLite Integration class.
 *
 * @link https://developers.mailerlite.com/docs/
 */
class Webhook extends BaseMailIntegration {

	/**
	 * Get name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'webhook';
	}

	/**
	 * Adds a subscribe to MailerLite
	 *
	 * @param array $data data.
	 * @return bool
	 */
	public function add_subscriber( $data ): bool {

		$res = null;

		if ( 'GET' === $data['reqType'] ) {
			$url = add_query_arg( $data['fields'], $data['link'] );
			$res = wp_remote_get(
				$url,
				array(
					'timeout' => 45,
				)
			);
		} elseif ( 'POST' === $data['reqType'] ) {
			$res = wp_remote_post(
				$data['link'],
				array(
					'method'  => 'POST',
					'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
					'body'    => wp_json_encode( $data['fields'] ),
					'timeout' => 45,
				)
			);
		}

		if ( ! is_null( $res ) && ! is_wp_error( $res ) ) {
			$this->add_lead( $data );
			return true;
		}

		return false;
	}

	/**
	 * Adds to the leads table
	 *
	 * @param array $data data.
	 * @return void
	 */
	public function add_lead( $data ) {
		if ( isset( $data['conv_id'] ) && isset( $data['fields'] ) ) {
			$fields = $data['fields'];

			$leads_data = array(
				'conv_id'     => intval( $data['conv_id'] ),
				'name'        => Utils::extract_name( $fields ),
				'email'       => isset( $fields['email'] ) ? sanitize_email( $fields['email'] ) : null,
				'data'        => Utils::sanitize_json_array( $fields ),
				'integration' => 'webhook',
			);

			$this->db->add_lead( $leads_data );
		}
	}

	/**
	 * Gets the fields
	 *
	 * @param array $args receive data as array.
	 * @return array
	 */
	public function get_fields( $args = array() ): array {
		return array();
	}

	/**
	 * Gets list
	 *
	 * @param array $args receive data as array.
	 * @return array
	 */
	public function get_lists( $args = array() ): array {
		return array();
	}
}
