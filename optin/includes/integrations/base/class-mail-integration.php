<?php // phpcs:ignore
/**
 * Mail Integration Interface
 *
 * @package optin
 * @subpackage optin/intergrtions
 */

namespace OPTN\Includes\Integrations\Base;

interface MailIntegration {

	/**
	 * Constructor
	 *
	 * @param int $account_id account id.
	 */
	public function __construct( $account_id );

	/**
	 * Adds a subscribe.
	 *
	 * @param array $data data.
	 * @return bool
	 */
	public function add_subscriber( $data ): bool;


	/**
	 * Add a lead to leads table.
	 *
	 * @param array $data data.
	 * @return void
	 */
	public function add_lead( $data );

	/**
	 * Gets all the default and custom fields
	 *
	 * @param array $args receive data as array.
	 * @return array
	 */
	public function get_fields( $args = array() ): array;


	/**
	 * Gets all the lists or groups or tags
	 *
	 * @param array $args receive data as array.
	 * @return array
	 */
	public function get_lists( $args = array() ): array;
}
