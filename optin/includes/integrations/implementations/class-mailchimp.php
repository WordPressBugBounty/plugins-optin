<?php // phpcs:ignore

namespace OPTN\Includes\Integrations\Implementations;

use http\Url;
use OPTN\Includes\Integrations\Base\BaseMailIntegration;
use OPTN\Includes\Utils\Cache;
use OPTN\Includes\Utils\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Mailchimp Integration class.
 *
 * @link https://mailchimp.com/developer/marketing/docs/fundamentals/
 */
class Mailchimp extends BaseMailIntegration {

	/**
	 * Endpoint for Mailchimp
	 *
	 * @var Url $end_point
	 */
	private $end_point;

	/**
	 * Get name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'mailchimp';
	}

	/**
	 * Constructor
	 *
	 * @param int|null $account_id account id.
	 */
	public function __construct( $account_id ) {
		parent::__construct( $account_id );
		$settings        = $this->get_settings();
		$data            = explode( '-', $settings['apiKey'] );
		$data_center     = end( $data );
		$this->end_point = 'https://' . $data_center . '.api.mailchimp.com/3.0';
	}

	/**
	 * Adds a subscribe to Mailchimp by list id
	 *
	 * @param array $data data.
	 * @return bool
	 *
	 * @link https://mailchimp.com/developer/marketing/api/list-members/add-member-to-list/
	 */
	public function add_subscriber( $data ): bool {

		if ( ! isset( $data['fields'] ) || ! isset( $data['fields']['email'] ) ) {
			return false;
		}

		$email = $data['fields']['email'];
		unset( $data['fields']['email'] );

		$body_template = array(
			'email_address' => $email,
			'status'        => ! empty( $data['doubleOpt'] ) ? 'pending' : 'subscribed',
			'ip_opt'        => Utils::get_user_ip(),
		);

		if ( ! empty( $data['fields'] ) ) {
			$body_template['merge_fields'] = $data['fields'];
		}

		$data['fields']['email'] = $email;

		$success = false;

		foreach ( $data['list'] as $list_id ) {
			$body = $body_template;

			$res = wp_remote_post(
				$this->end_point . '/lists/' . $list_id . '/members',
				array(
					'body'    => wp_json_encode( $body ),
					'timeout' => 45,
					'headers' => $this->get_headers(),
				)
			);

			// Response code should be either 200 or 201.
			if ( ! is_wp_error( $res ) && in_array( wp_remote_retrieve_response_code( $res ), array( 200, 201 ) ) ) { // phpcs:ignore
				$success = true;
			}

			$resp_body = wp_remote_retrieve_body( $res );
			$resp_body = json_decode( $resp_body, true );

			// Duplicate email case.
			if ( 'Member Exists' === ( $resp_body['title'] ?? '' ) ) {
				$success = true;
			}
		}

		if ( $success ) {
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
			'integration' => 'mailchimp',
		);

		$this->db->add_lead( $leads_data );
	}


	/**
	 * Gets the fields for Mailchimp by list id
	 *
	 * @param array $args receive data as array.
	 * @return array
	 * @link https://mailchimp.com/developer/marketing/api/list-merges/list-merge-fields/
	 */
	public function get_fields( $args = array() ): array {

		if ( empty( $args['list_id'] ) ) {
			return array();
		}
		$use_cache = $args['use_cache'];

		$list_ids      = is_array( $args['list_id'] ) ? $args['list_id'] : array( $args['list_id'] );
		$unique_fields = array();

		foreach ( $list_ids as $list_id ) {
			if ( $use_cache ) {
				$cached = Cache::get( 'optn_int_mailchimp_fields_' . $this->account_id . '_' . $list_id );
				if ( ! empty( $cached ) ) {
					$fields        = json_decode( $cached, true );
					$unique_fields = $this->merge_unique_fields( $unique_fields, $fields );
					continue;
				}
			}

			$res = wp_remote_get(
				$this->end_point . '/lists/' . $list_id . '/merge-fields',
				array(
					'timeout' => 45,
					'headers' => $this->get_headers(),
				)
			);

			if ( ! is_wp_error( $res ) && 200 == wp_remote_retrieve_response_code( $res ) ) { // phpcs:ignore
				$body = wp_remote_retrieve_body( $res );
				$body = json_decode( $body, true );

				if ( isset( $body['merge_fields'] ) && is_array( $body['merge_fields'] ) ) {
					$fields = array();

					foreach ( $body['merge_fields'] as $d ) {
						$fields[] = array(
							'value' => $d['tag'],
							'label' => $d['name'],
						);
					}

					$fields[] = array(
						'value' => 'email',
						'label' => 'Email',
					);

					// Cache the fields.
					Cache::set( 'optn_int_mailchimp_fields_' . $this->account_id . '_' . $list_id, wp_json_encode( $fields ) );

					$unique_fields = $this->merge_unique_fields( $unique_fields, $fields );
				}
			}
		}

		return $unique_fields;
	}

	/**
	 * Merge fields for multiple list
	 *
	 * @param array $existing_fields existing fields.
	 * @param array $new_fields new fields.
	 * @return array
	 */
	private function merge_unique_fields( array $existing_fields, array $new_fields ): array {
		foreach ( $new_fields as $field ) {
			if ( ! array_key_exists( $field['value'], array_column( $existing_fields, 'value' ) ) ) {
				$existing_fields[] = $field;
			}
		}

		return $existing_fields;
	}

	/**
	 * Gets list for mailchimp
	 *
	 * @param array $args receive optional data as array.
	 * @return array
	 *
	 * @link https://mailchimp.com/developer/marketing/api/lists/
	 */
	public function get_lists( $args = array() ): array {
		if ( ! empty( $args['list_ids'] ) ) {
			return array();
		}
		$use_cache = $args['use_cache'];
		if ( $use_cache ) {
			$res = Cache::get( 'optn_int_mailchimp_lists_' . $this->account_id );

			if ( ! empty( $res ) ) {
				return json_decode( $res );
			}
		}

		$res = wp_remote_get(
			$this->end_point . '/lists',
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

				Cache::set( 'optn_int_mailchimp_lists_' . $this->account_id, wp_json_encode( $ret ) );

				return $ret;
			}
		}

		return array();
	}
}
