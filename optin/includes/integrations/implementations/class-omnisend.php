<?php // phpcs:ignore

namespace OPTN\Includes\Integrations\Implementations;

use OPTN\Includes\Integrations\Base\BaseMailIntegration;
use OPTN\Includes\Utils\Cache;
use OPTN\Includes\Utils\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Omni send Integration class.
 *
 * @link https://developers.omnisend.com/docs/
 */
class Omnisend extends BaseMailIntegration {

	/**
	 * Get name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'omnisend';
	}

	/**
	 * Adds a subscribe to omni send
	 *
	 * @param array $data data.
	 * @return bool
	 *
	 * @link https://api-docs.omnisend.com/reference/post_contacts
	 */
	public function add_subscriber( $data ): bool {

		if ( ! isset( $data['fields'] ) || ! isset( $data['fields']['email'] ) ) {
			return false;
		}

		$body = array(
			'status'     => 'subscribed',
			'statusDate' => gmdate( 'Y-m-d\TH:i:s\Z' ),
		);

		if ( ! empty( $data['fields'] ) ) {
			foreach ( $data['fields'] as $key => $value ) {
				$body[ $key ] = $value;
			}
		}
		$res = wp_remote_post(
			'https://api.omnisend.com/v5/contacts',
			array(
				'body'    => wp_json_encode( $body ),
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
			'name'        => trim( ( $fields['firstName'] ?? '' ) . ' ' . ( $fields['lastName'] ?? '' ) ),
			'conv_id'     => intval( $data['conv_id'] ),
			'data'        => $fields,
			'integration' => 'omnisend',
		);

		$this->db->add_lead( $leads_data );
	}


	/**
	 * Gets the fields for omni send
	 *
	 * @param array $args receive data as array.
	 * @return array
	 */
	public function get_fields( $args = array() ): array {

		return array(
			array(
				'value' => 'email',
				'label' => 'Email',
			),
			array(
				'value' => 'address',
				'label' => 'Address',
			),
			array(
				'value' => 'birthdate',
				'label' => 'Date of Birth',
			),
			array(
				'value' => 'city',
				'label' => 'City',
			),
			array(
				'value' => 'state',
				'label' => 'State',
			),
			array(
				'value' => 'country',
				'label' => 'Country',
			),
			array(
				'value' => 'countryCode',
				'label' => 'Country Code',
			),
			array(
				'value' => 'firstName',
				'label' => 'First Name',
			),
			array(
				'value' => 'lastName',
				'label' => 'Last Name',
			),
			array(
				'value' => 'gender',
				'label' => 'Gender',
			),
			array(
				'value' => 'postalCode',
				'label' => 'Postal Code',
			),
		);
	}

	/**
	 * Gets groups for omni send
	 *
	 * @param array $args receive data as array.
	 * @return array
	 */
	public function get_lists( $args = array() ): array {
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
			'X-API-KEY'    => $settings['apiKey'],
		);
	}
}
