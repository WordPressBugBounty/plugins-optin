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

		if ( empty( $data['link'] ) || empty( $data['reqType'] ) ) {
			return false;
		}

		$url = wp_http_validate_url( esc_url_raw( $data['link'] ) );

		if ( false === $url ) {
			return false;
		}

		$res = null;

		if ( 'GET' === $data['reqType'] ) {
			$url = add_query_arg( is_array( $data['fields'] ) ? $data['fields'] : array(), $url );

			if ( false === wp_http_validate_url( $url ) ) {
				return false;
			}

			$res = wp_safe_remote_get(
				$url,
				array(
					'timeout' => 45,
				)
			);
		} elseif ( 'POST' === $data['reqType'] ) {
			$res = wp_safe_remote_post(
				$url,
				array(
					'method'  => 'POST',
					'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
					'body'    => wp_json_encode( isset( $data['fields'] ) && is_array( $data['fields'] ) ? $data['fields'] : array() ),
					'timeout' => 45,
				)
			);
		} else {
			return false;
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
