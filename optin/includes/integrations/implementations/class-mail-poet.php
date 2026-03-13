<?php // phpcs:ignore

namespace OPTN\Includes\Integrations\Implementations;

use http\Url;
use OPTN\Includes\Integrations\Base\BaseMailIntegration;
use OPTN\Includes\Utils\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * MailPoet Integration class.
 *
 * @link https://github.com/mailpoet/mailpoet/tree/trunk/doc
 */
class MailPoet extends BaseMailIntegration {

	/**
	 * Endpoint for Mail Poet
	 *
	 * @var Url $end_point
	 */
	private $mail_poet;

		/**
		 * Get name
		 *
		 * @return string
		 */
	public function get_name() {
		return 'mailpoet';
	}

	/**
	 * Constructor
	 *
	 * @param int|null $account_id account id.
	 */
	public function __construct( $account_id ) {
		parent::__construct( $account_id );
		if ( class_exists( \MailPoet\API\API::class ) ) {
			$this->mail_poet = \MailPoet\API\API::MP( 'v1' );
		}
	}

	/**
	 * Adds a subscribe to mail poet
	 *
	 * @param array $data data.
	 * @return bool
	 *
	 * @link https://github.com/mailpoet/mailpoet/blob/trunk/doc/api_methods/AddSubscriber.md
	 */
	public function add_subscriber( $data ): bool {

		if ( ! isset( $data['fields'] ) || ! isset( $data['fields']['email'] ) || ! $this->check_installed() ) {
			return false;
		}

		$list_ids = ! empty( $data['list'] ) ? $data['list'] : array();

		try {
			$save_data = $this->mail_poet->addSubscriber( $data['fields'], $list_ids );

			if ( $save_data ) {
				$this->add_lead( $data );
				return true;
			}

			return false;
		} catch ( \MailPoet\API\MP\v1\APIException $e ) {
			// If the email is already subscribed, the API will return a 12 error code. In that case, we just return true.
			if ( $e->getCode() === 12 ) {
				return true;
			}

			// Otherwise, its a valid error, so we need to log it and return false.
			Utils::log_error( $e->getMessage() );
			return false;
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
			'name'        => trim( ( $fields['first_name'] ?? '' ) . ' ' . ( $fields['last_name'] ?? '' ) ),
			'conv_id'     => intval( $data['conv_id'] ),
			'data'        => $fields,
			'integration' => 'mailpoet',
		);

		$this->db->add_lead( $leads_data );
	}

	/**
	 * Gets the fields for mail poet
	 *
	 * @param array $args receive data as array.
	 * @return array
	 *
	 * @link https://github.com/mailpoet/mailpoet/blob/trunk/doc/api_methods/GetSubscriberFields.md
	 */
	public function get_fields( $args = array() ): array {

		if ( ! $this->check_installed() ) {
			return array();
		}

		$data = $this->mail_poet->getSubscriberFields();
		if ( ! empty( $data ) ) {

			$ret = array();

			foreach ( $data as $d ) {
				$ret[] = array(
					'value' => $d['id'],
					'label' => $d['name'],
				);
			}

			return $ret;
		}

		return array();
	}

	/**
	 * Gets groups for mail poet
	 *
	 * @param array $args receive data as array.
	 * @return array
	 *
	 * @link https://github.com/mailpoet/mailpoet/blob/trunk/doc/api_methods/GetLists.md
	 */
	public function get_lists( $args = array() ): array {

		if ( ! $this->check_installed() ) {
			return array();
		}

		$data = $this->mail_poet->getLists();
		if ( ! empty( $data ) ) {

			$ret = array();

			foreach ( $data as $d ) {
				$ret[] = array(
					'value' => $d['id'],
					'label' => $d['name'],
				);
			}

			return $ret;
		}

		return array();
	}

	/**
	 * Check if MailPoet API class exists
	 *
	 * @return bool
	 */
	private function check_installed() {
		return class_exists( '\MailPoet\API\API' );
	}
}
