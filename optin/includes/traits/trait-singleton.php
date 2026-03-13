<?php

namespace OPTN\Includes\Traits;

trait Singleton {
	private static $instance = null;

	/**
	 * Constructor
	 *
	 * @return void
	 */
	protected function __construct() {}

	final public static function get_instance() {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}
}
