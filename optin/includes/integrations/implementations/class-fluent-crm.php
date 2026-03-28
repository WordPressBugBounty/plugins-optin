<?php // phpcs:ignore

namespace OPTN\Includes\Integrations\Implementations;

use OPTN\Includes\Integrations\Base\BaseMailIntegration;
use OPTN\Includes\Utils\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * FluentCRM Integration class.
 *
 * @link https://developers.mailerlite.com/docs/
 */
class FluentCrm extends BaseMailIntegration {

	/**
	 * Get name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'fluentcrm';
	}

	/**
	 * FluentCRM Fields
	 *
	 * @var array
	 */
	private $fluent_crm_fields = array(
		array(
			'value' => 'prefix',
			'label' => 'Name Prefix',
		),
		array(
			'value' => 'first_name',
			'label' => 'First Name',
		),
		array(
			'value' => 'last_name',
			'label' => 'Last Name',
		),
		array(
			'value' => 'full_name',
			'label' => 'Full Name',
		),
		array(
			'value' => 'email',
			'label' => 'Email',
		),
		array(
			'value' => 'timezone',
			'label' => 'Timezone',
		),
		array(
			'value' => 'address_line_1',
			'label' => 'Address Line 1',
		),
		array(
			'value' => 'address_line_2',
			'label' => 'Address Line 2',
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
			'value' => 'postal_code',
			'label' => 'Postal Code',
		),
		array(
			'value' => 'country',
			'label' => 'Country',
		),
		array(
			'value' => 'phone',
			'label' => 'Phone',
		),
		array(
			'value' => 'source',
			'label' => 'Source',
		),
		array(
			'value' => 'date_of_birth',
			'label' => 'Date of Birth',
		),
	);

	/**
	 * Adds a subscribe to FluentCRM
	 *
	 * @param array $data data.
	 * @return bool
	 */
	public function add_subscriber( $data ): bool {
		$fluent_data       = $data['fields'];
		$fluent_data['ip'] = Utils::get_user_info()['ip'];

		if ( isset( $fluent_data['doubleOpt'] ) && $fluent_data['doubleOpt'] ) {
			$fluent_data['status'] = 'pending';
		} else {
			$fluent_data['status'] = 'subscribed';
		}

		unset( $fluent_data['doubleOpt'] );

		if ( function_exists( 'FluentCrmApi' ) ) {
			$contact_api = FluentCrmApi( 'contacts' );
			$contact     = $contact_api->createOrUpdate( $fluent_data );

			if ( empty( $contact ) ) {
				return false;
			}

			if ( $contact && 'pending' === $contact->status ) {
				$contact->sendDoubleOptinEmail();
			}

			$this->add_lead( $data );

			return true;
		}

		return false;
	}


	/**
	 * Adds lead to the leads table
	 *
	 * @param array $data data.
	 * @return void
	 */
	public function add_lead( $data ) {
		if ( isset( $data['conv_id'] ) && isset( $data['fields'] ) ) {
			$fluent_data       = $data['fields'];
			$fluent_data['ip'] = Utils::get_user_ip();

			$leads_data = array();

			$leads_data['conv_id'] = $data['conv_id'];

			$leads_data['email'] = isset( $fluent_data['email'] ) ? sanitize_email( $fluent_data['email'] ) : null;

			$name = null;

			if ( isset( $fluent_data['name'] ) ) {
				$name = sanitize_text_field( $fluent_data['name'] );
			} elseif ( isset( $fluent_data['first_name'] ) || isset( $fluent_data['last_name'] ) ) {
				$first_name = ! empty( $fluent_data['first_name'] ) ? $fluent_data['first_name'] : '';
				$last_name  = ! empty( $fluent_data['last_name'] ) ? $fluent_data['last_name'] : '';
				$name       = sanitize_text_field( trim( $first_name . ' ' . $last_name ) );
				$name       = empty( $name ) ? null : $name;
			}

			$leads_data['name'] = $name;

			$leads_data['integration'] = 'fluentcrm';

			$leads_data['data'] = Utils::sanitize_json_array( $fluent_data );

			$this->db->add_lead( $leads_data );
		}
	}


	/**
	 * Gets the fields for Mailer Lite
	 *
	 * @param array $args receive data as array.
	 * @return array
	 */
	public function get_fields( $args = array() ): array {

		if ( function_exists( 'fluentcrm_get_custom_contact_fields' ) ) {

			$fields = fluentcrm_get_custom_contact_fields();

			$fields = array_map(
				function ( $item ) {
					return array(
						'value' => $item['slug'],
						'label' => $item['label'],
					);
				},
				$fields
			);

			$fields = array_merge( $this->fluent_crm_fields, $fields );

			return $fields;
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
		if ( function_exists( 'FluentCrmApi' ) ) {
			$lists = FluentCrmApi( 'lists' )->get()->toArray();
			$tags  = FluentCrmApi( 'tags' )->get()->toArray();

			$lists = array_map(
				function ( $flist ) {
					return array(
						'value' => $flist['id'],
						'label' => $flist['title'],
					);
				},
				$lists
			);

			$tags = array_map(
				function ( $tag ) {
					return array(
						'value' => $tag['id'],
						'label' => $tag['title'],
					);
				},
				$tags
			);

			return array(
				'lists' => $lists,
				'tags'  => $tags,
			);
		}

		return array(
			'lists' => array(),
			'tags'  => array(),
		);
	}
}
