<?php
/**
 * Provides lazy, shared instance access for plugin service classes.
 *
 * @package Optin
 */

namespace OPTN\Includes\Traits;

trait Singleton {
	/**
	 * The sole instance created for the consuming class.
	 *
	 * @var static|null
	 */
	private static $instance = null;

	/**
	 * Constructor
	 *
	 * @return void
	 */
	protected function __construct() {}

	/**
	 * Return the existing service instance or create it on first access.
	 *
	 * Uses late static binding so each class that adopts this trait keeps its own
	 * singleton instance while sharing the same initialization behavior.
	 *
	 * @return static Shared instance of the consuming class.
	 */
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
