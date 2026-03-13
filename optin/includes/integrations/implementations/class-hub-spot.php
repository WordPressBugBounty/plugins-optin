<?php // phpcs:ignore

namespace OPTN\Includes\Integrations\Implementations;

use OPTN\Includes\Integrations\Base\BaseMailIntegration;
use OPTN\Includes\Utils\Cache;
use OPTN\Includes\Utils\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * HubSpot Integration class.
 *
 * @link https://developers.hubspot.com/docs/reference/api
 */
class HubSpot extends BaseMailIntegration {

	/**
	 * Get name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'hubspot';
	}

	/**
	 * Adds a subscribe to Hub Spot
	 *
	 * @param array $data data.
	 * @return bool
	 *
	 * @link https://developers.hubspot.com/docs/reference/api/crm/objects/contacts/v1#create-a-new-contact
	 */
	public function add_subscriber( $data ): bool {
		if ( ! isset( $data['fields'] ) || ! isset( $data['fields']['email'] ) ) {
			return false;
		}

		$email = $data['fields']['email'];
		unset( $data['fields']['email'] );

		$properties = array(
			array(
				'property' => 'email',
				'value'    => $email,
			),
		);
		foreach ( $data['fields'] as $field_name => $field_value ) {
			if ( ! empty( $field_name ) && ! empty( $field_value ) ) {
				$properties[] = array(
					'property' => $field_name,
					'value'    => $field_value,
				);
			}
		}

		$body = array(
			'properties' => $properties,
		);

		$res = wp_remote_post(
			'https://api.hubapi.com/contacts/v1/contact',
			array(
				'body'    => wp_json_encode( $body ),
				'timeout' => 45,
				'headers' => $this->get_headers(),
			)
		);

		// Response code should be either 200 or 201.
		if ( ! is_wp_error( $res ) && in_array( wp_remote_retrieve_response_code( $res ), array( 200, 201 ) ) ) { // phpcs:ignore
			$data['fields']['email'] = $email;
			$contact_data            = json_decode( wp_remote_retrieve_body( $res ), true );
			if ( ! empty( $data['list'] ) ) {
				$this->add_to_hub_sopt_lists( $data['list'], $contact_data['vid'] );
			}
			$this->add_lead( $data );
			return true;
		}

		return false;
	}

	/**
	 * Adds concat to Hub Spot list
	 *
	 * @param array $list_ids list ids.
	 * @param mixed $contact_vid contact vid.
	 * @return void
	 *
	 * @link https://developers.hubspot.com/docs/reference/api/crm/lists/v1-contacts#add-existing-contacts-to-a-list
	 */
	private function add_to_hub_sopt_lists( $list_ids, $contact_vid ): void {
		foreach ( $list_ids as $list_id ) {
			wp_remote_post(
				"https://api.hubapi.com/contacts/v1/lists/{$list_id}/add",
				array(
					'timeout' => 45,
					'headers' => $this->get_headers(),
					'body'    => wp_json_encode(
						array(
							'vids' => array( $contact_vid ),
						)
					),
				)
			);
		}
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
			'name'        => trim( ( $fields['firstname'] ?? '' ) . ' ' . ( $fields['lastname'] ?? '' ) ),
			'conv_id'     => intval( $data['conv_id'] ),
			'data'        => $fields,
			'integration' => 'hubspot',
		);

		$this->db->add_lead( $leads_data );
	}


	/**
	 * Gets the fields for Hub Spot
	 *
	 * @param array $args receive data as array.
	 * @return array
	 *
	 * @link https://developers.hubspot.com/docs/reference/api/crm/properties/v1-contacts#get-all-contact-properties
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
			'https://api.hubapi.com/properties/v1/contacts/properties',
			array(
				'timeout' => 45,
				'headers' => $this->get_headers(),
			)
		);

		if ( ! is_wp_error( $res ) && 200 == wp_remote_retrieve_response_code( $res ) ) { // phpcs:ignore
			$body = wp_remote_retrieve_body( $res );
			$data = json_decode( $body, true );

			if ( is_array( $data ) ) {

				$ret = array();

				foreach ( $data as $d ) {
					$ret[] = array(
						'value' => $d['name'],
						'label' => $d['label'],
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
	 * Gets lists for Hub Spot
	 *
	 * @param array $args receive data as array.
	 * @return array
	 *
	 * @link https://developers.hubspot.com/docs/reference/api/crm/lists/v1-contacts#get-all-contact-lists
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
			'https://api.hubapi.com/contacts/v1/lists',
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
						'value' => $d['listId'],
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
