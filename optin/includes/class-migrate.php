<?php // phpcs:ignore

namespace OPTN\Includes;

/**
 * Coordinates versioned database migrations for existing plugin installations.
 *
 * Uses the database service to compare the stored schema version with the current
 * plugin version and applies the required upgrade steps.
 */
class Migrate {
	use \OPTN\Includes\Traits\Singleton;

	/**
	 * DB instance
	 *
	 * @var Db
	 */
	private $db;

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->db = Db::get_instance();
	}
}
