<?php // phpcs:ignore

namespace OPTN\Includes\Integrations\Base;

use OPTN\Includes\Db;
use OPTN\Includes\Utils\Cache;

defined( 'ABSPATH' ) || exit;

/**
 * Parent MailService class.
 */
abstract class BaseMailIntegration implements MailIntegration {

	/**
	 * Db instance
	 *
	 * @var Db $db Db variable
	 */
	protected $db;


	/**
	 * Account id
	 *
	 * @var int $account_id account id.
	 */
	protected $account_id;

	/**
	 * Settings for current account
	 *
	 * @var array $settings
	 */
	protected $settings;

	/**
	 * Constructor
	 *
	 * @param int|null $account_id account id.
	 */
	public function __construct( $account_id ) {
		$this->db         = Db::get_instance();
		$this->account_id = $account_id;
		$this->settings   = null;
	}

	/**
	 * Get name
	 *
	 * @return string
	 */
	abstract public function get_name();

	/**
	 * Adds subscriber
	 *
	 * @param array $data data.
	 * @return boolean
	 */
	abstract public function add_subscriber( $data ): bool;

	/**
	 * Add leads to lead table
	 *
	 * @param array $data user form input data.
	 * @return void
	 */
	abstract public function add_lead( $data );

	/**
	 * Gets all the fields
	 *
	 * @param array $args receive data as array.
	 * @return array
	 */
	abstract public function get_fields( $args = array() ): array;


	/**
	 * Gets all the lists
	 *
	 * @param array $args receive data as array.
	 * @return array
	 */
	abstract public function get_lists( $args = array() ): array;

	/**
	 * Gets user settings
	 *
	 * @return array
	 */
	protected function get_settings() {

		if ( is_null( $this->settings ) && $this->account_id ) {

			$res            = $this->db->get_integration( $this->account_id, false );
			$this->settings = $res['data'];
		}

		return $this->settings;
	}

	/**
	 * Set lists cache
	 *
	 * @param array $value value.
	 * @return void
	 */
	protected function set_lists_cache( $value ) {
		$key = 'optn_int_' . $this->get_name() . '_lists_' . $this->account_id;
		Cache::set( $key, wp_json_encode( $value ) );
	}

	/**
	 * Set fields cache
	 *
	 * @param array $value value.
	 * @return void
	 */
	protected function set_fields_cache( $value ) {
		$key = 'optn_int_' . $this->get_name() . '_fields_' . $this->account_id;
		Cache::set( $key, wp_json_encode( $value ) );
	}

	/**
	 * Get lists cache
	 *
	 * @return mixed
	 */
	protected function get_cached_lists() {
		$key   = 'optn_int_' . $this->get_name() . '_lists_' . $this->account_id;
		$value = Cache::get( $key );
		return $value ? json_decode( $value, true ) : null;
	}

	/**
	 * Get lists cache
	 *
	 * @return mixed
	 */
	protected function get_cached_fields() {
		$key   = 'optn_int_' . $this->get_name() . '_fields_' . $this->account_id;
		$value = Cache::get( $key );
		return $value ? json_decode( $value, true ) : null;
	}

	/**
	 * Gets headers with authorization token
	 *
	 * @return array
	 */
	protected function get_headers() {

		$settings = $this->get_settings();

		return array(
			'User-Agent'    => 'WowOptin/' . OPTN_VERSION,
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $settings['apiKey'],
		);
	}
}
