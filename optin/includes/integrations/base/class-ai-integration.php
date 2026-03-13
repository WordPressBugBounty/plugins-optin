<?php // phpcs:ignore
/**
 * AI Integration Interface
 *
 * @package optin
 * @subpackage optin/intergrtions
 */

namespace OPTN\Includes\Integrations\Base;

use OPTN\Includes\Dto\ModelInput;

interface AiIntegration {

	/**
	 * Constructor
	 *
	 * @param int $account_id account id.
	 */
	public function __construct( $account_id );

	/**
	 * Generate text.
	 *
	 * @param ModelInput $input model input.
	 * @return string|WP_Error
	 */
	public function generate_text( ModelInput $input );

	/**
	 * Get models.
	 *
	 * @return array
	 */
	public static function get_models();
}
