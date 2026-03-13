<?php // phpcs:ignore

namespace OPTN\Includes\Utils;

use OPTN\Includes\Traits\Singleton;

defined( 'ABSPATH' ) || exit;


/**
 * Cache class
 */
class Cache {
	use Singleton;

	/**
	 * Gets value from cache by key
	 *
	 * @param string $key key of the value.
	 * @param mixed  $default_value default value if value in not in cache.
	 * @return mixed
	 */
	public static function get( $key, $default_value = null ) {
		$res = get_transient( $key );
		return $res ? $res : $default_value;
	}

	/**
	 * Deletes value from cache by kea
	 *
	 * @param string $key key of the value.
	 * @return bool
	 */
	public static function remove( $key ) {
		return delete_transient( $key );
	}

	/**
	 * Sets a value in cache
	 *
	 * @param string   $key key of the value.
	 * @param mixed    $value value to store in cache.
	 * @param int|null $days cache expiration in days.
	 * @return bool
	 */
	public static function set( $key, $value, $days = null ) {
		$d = empty( $days ) ? OPTN_CACHE_DAY : $days;
		return set_transient( $key, $value, DAY_IN_SECONDS * $d );
	}
}
