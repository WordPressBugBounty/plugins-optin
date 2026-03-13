<?php // phpcs:ignore

namespace OPTN\Includes;

/**
 * DB Class.
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
