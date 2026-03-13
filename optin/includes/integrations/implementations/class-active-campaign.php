<?php // phpcs:ignore

namespace OPTN\Includes\Integrations\Implementations;

use OPTN\Includes\Integrations\Base\BaseMailIntegration;
use OPTN\Includes\Utils\Cache;
use OPTN\Includes\Utils\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Active Campaign Integration class.
 *
 * @link https://developers.activecampaign.com/reference/overview
 */
class ActiveCampaign extends BaseMailIntegration {

	/**
	 * Get name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'activecampaign';
	}

	/**
	 * Adds a contact
	 *
	 * @param array $data data.
	 * @return bool
	 *
	 * @link https://developers.activecampaign.com/reference/create-a-new-contact, https://developers.activecampaign.com/reference/create-contact-tag, https://developers.activecampaign.com/reference/update-list-status-for-contact
	 */
	public function add_subscriber( $data ): bool {

		if ( ! isset( $data['fields'] ) ) {
			return false;
		}

		$tags  = $data['tags'] ?? array();
		$lists = $data['lists'] ?? array();

		$body = array(
			'allowNullEmail' => true,
		);

		$fixed_fields = array(
			'email',
			'firstName',
			'lastName',
			'phone',
		);

		foreach ( $data['fields'] as $field => $value ) {
			if ( in_array( $field, $fixed_fields, true ) ) {
				$body[ $field ] = $value;
			} else {
				if ( ! isset( $body['fieldValues'] ) ) {
					$body['fieldValues'] = array();
				}

				$body['fieldValues'][] = array(
					'field' => strval( $field ),
					'value' => $value,
				);
			}
		}

		$settings = $this->get_settings();
		$url      = rtrim( $settings['url'], '/' ) . '/api/3/contacts';

		$body = array(
			'contact' => $body,
		);

		$res = wp_remote_post(
			$url,
			array(
				'body'    => wp_json_encode( $body ),
				'timeout' => 45,
				'headers' => $this->get_headers(),
			)
		);

		$resp_body  = json_decode( wp_remote_retrieve_body( $res ), true );
		$contact_id = $resp_body['contact']['id'] ?? null;

		if ( is_wp_error( $res ) || 201 !== wp_remote_retrieve_response_code( $res ) || empty( $contact_id ) ) {

			// Check for duplicate contact.
			$err_body = json_decode( wp_remote_retrieve_body( $res ), true );
			$err_code = $err_body['errors'][0]['code'] ?? null;

			if ( 'duplicate' === $err_code ) {
				return true;
			}

			return false;
		}

		if ( ! empty( $tags ) ) {
			// TODO @samin: Implement parallel requests.
			$url = rtrim( $settings['url'], '/' ) . '/api/3/contactTags';
			foreach ( $tags as $tag ) {
				$res = wp_remote_post(
					$url,
					array(
						'body'     => wp_json_encode(
							array(
								'contactTag' => array(
									'contact' => $contact_id,
									'tag'     => $tag,
								),
							)
						),
						'timeout'  => 45,
						'headers'  => $this->get_headers(),
						'blocking' => true,
					)
				);

				if ( is_wp_error( $res ) || 201 !== wp_remote_retrieve_response_code( $res ) ) {
					return false;
				}
			}
		}

		if ( ! empty( $lists ) ) {
			// TODO @samin: Implement parallel requests.
			$url = rtrim( $settings['url'], '/' ) . '/api/3/contactLists';
			foreach ( $lists as $list ) {
				$res = wp_remote_post(
					$url,
					array(
						'body'     => wp_json_encode(
							array(
								'contactList' => array(
									'list'    => $list,
									'contact' => $contact_id,
									'status'  => '1',
								),
							)
						),
						'timeout'  => 45,
						'headers'  => $this->get_headers(),
						'blocking' => true,
					)
				);

				if ( is_wp_error( $res ) || 201 !== wp_remote_retrieve_response_code( $res ) ) {
					return false;
				}
			}
		}

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

		$fields = Utils::sanitize_json_array( $data['fields'] );
		$ip     = Utils::get_user_ip();

		$leads_data = array(
			'email'       => isset( $fields['email'] ) ? sanitize_email( $fields['email'] ) : ( sanitize_text_field( $fields['phone'] ?? '' ) ),
			'name'        => trim( ( $fields['firstName'] ?? '' ) . ' ' . ( $fields['lastName'] ?? '' ) ),
			'conv_id'     => intval( $data['conv_id'] ),
			'data'        => array_merge(
				$fields,
				array(
					'tags'  => $data['tags'],
					'lists' => $data['lists'],
					'ip'    => $ip,
				)
			),
			'integration' => 'activecampaign',
		);

		$this->db->add_lead( $leads_data );
	}


	/**
	 * Gets the fields.
	 *
	 * @param array $args receive data as array.
	 * @return array
	 *
	 * @link https://developers.activecampaign.com/reference/retrieve-fields
	 */
	public function get_fields( $args = array() ): array {
		$use_cache = $args['use_cache'];

		if ( $use_cache ) {
			$res = $this->get_cached_fields();

			if ( ! empty( $res ) ) {
				return $res;
			}
		}

		$settings = $this->get_settings();
		$url      = rtrim( $settings['url'], '/' ) . '/api/3/fields';
		$url      = add_query_arg(
			array(
				'limit' => 9999,
			),
			$url
		);

		$res = wp_remote_get(
			$url,
			array(
				'timeout' => 45,
				'headers' => $this->get_headers(),
			)
		);

		$default_fields = array(
			array(
				'value' => 'email',
				'label' => 'Email',
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
				'value' => 'phone',
				'label' => 'Phone',
			),
		);

		if ( ! is_wp_error( $res ) && 200 == wp_remote_retrieve_response_code( $res ) ) { // phpcs:ignore
			$body = wp_remote_retrieve_body( $res );
			$body = json_decode( $body, true );

			if ( isset( $body['fields'] ) && is_array( $body['fields'] ) ) {

				$ret = array();

				foreach ( $body['fields'] as $d ) {
					$ret[] = array(
						'value' => $d['id'],
						'label' => $d['title'],
					);
				}

				$ret = array_merge( $default_fields, $ret );

				$this->set_fields_cache( $ret );

				return $ret;
			}
		}

		return array();
	}

	/**
	 * Gets tags
	 *
	 * @param array $args receive data as array.
	 * @return array
	 *
	 * @link https://developers.activecampaign.com/reference/retrieve-all-tags, https://developers.activecampaign.com/reference/retrieve-all-lists
	 */
	public function get_lists( $args = array() ): array {
		$use_cache = $args['use_cache'];

		if ( $use_cache ) {
			$res = $this->get_cached_lists();

			if ( ! empty( $res ) ) {
				return $res;
			}
		}

		$data = array(
			'lists' => array(),
			'tags'  => array(),
		);

		// Tags.
		$settings = $this->get_settings();
		$url      = rtrim( $settings['url'], '/' ) . '/api/3/tags';

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
				foreach ( $body['tags'] as $d ) {
					$data['tags'][] = array(
						'value' => $d['id'],
						'label' => $d['tag'],
					);
				}
			}
		}

		// Lists.
		$url = rtrim( $settings['url'], '/' ) . '/api/3/lists';
		$url = add_query_arg(
			array(
				'limit' => 9999,
			),
			$url
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

			if ( isset( $body['lists'] ) && is_array( $body['lists'] ) ) {
				foreach ( $body['lists'] as $d ) {
					$data['lists'][] = array(
						'value' => $d['id'],
						'label' => $d['name'],
					);
				}
			}
		}

		$this->set_lists_cache( $data );

		return $data;
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
			'Api-Token'    => $settings['apiKey'],
		);
	}
}
