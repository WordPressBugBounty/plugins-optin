<?php
/**
 * Initializes shared plugin services and registers their WordPress hooks.
 *
 * @package Optin
 */

namespace OPTN\Includes;

use OPTN\Includes\Utils\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Core class for managing plugin actions and integrations.
 */
class Xpo {

	/**
	 * Retrieve a fresh option value without retaining it in the object cache.
	 *
	 * @param string  $option Option Name.
	 * @param boolean $default_value option default value.
	 * @return mixed
	 */
	public static function get_option_without_cache( $option, $default_value = false ) {
		return self::without_option_cache(
			array( $option ),
			static function () use ( $option, $default_value ) {
				return get_option( $option, $default_value );
			}
		);
	}

	/**
	 * Add an option without reading or retaining stale object-cache entries.
	 *
	 * @since v.1.0.7
	 * @param string      $option option name.
	 * @param mixed       $value Any serializable option value accepted by WordPress.
	 * @param bool|string $autoload whether to load WordPress startup.
	 * @return bool
	 */
	public static function add_option_without_cache( $option, $value = '', $autoload = 'yes' ) {
		return self::without_option_cache(
			array( $option ),
			static function () use ( $option, $value, $autoload ) {
				return add_option( $option, $value, '', $autoload );
			}
		);
	}

	/**
	 * Retrieve a transient from the database, bypassing the object cache.
	 *
	 * @since v.1.0.7
	 * @param string $transient Transient Name.
	 * @return mixed
	 */
	public static function get_transient_without_cache( $transient ) {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Preserve the WordPress core Transients API hook.
		$pre = apply_filters( "pre_transient_{$transient}", false, $transient );
		if ( false !== $pre ) {
			return $pre;
		}

		$value = self::without_option_cache(
			array( '_transient_' . $transient, '_transient_timeout_' . $transient ),
			static function () use ( $transient ) {
				$option  = '_transient_' . $transient;
				$timeout = '_transient_timeout_' . $transient;
				$expires = get_option( $timeout );

				if ( false !== $expires && $expires < time() ) {
					delete_option( $option );
					delete_option( $timeout );
					return false;
				}

				return get_option( $option );
			}
		);

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Preserve the WordPress core Transients API hook.
		return apply_filters( "transient_{$transient}", $value, $transient );
	}

	/**
	 * Store a transient in the database without retaining object-cache entries.
	 *
	 * Database-backed helpers leave external-cache transients with the same name
	 * unchanged. Use the matching uncached reader for values stored here.
	 *
	 * @since v.1.0.7
	 * @param string  $transient Transient Name.
	 * @param mixed   $value Any value that the WordPress Transients API can store and return.
	 * @param integer $expiration Time until expiration in seconds.
	 * @return bool
	 */
	public static function set_transient_without_cache( $transient, $value, $expiration = 0 ) {
		$expiration = (int) $expiration;
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Preserve the WordPress core Transients API hook.
		$value = apply_filters( "pre_set_transient_{$transient}", $value, $expiration, $transient );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Preserve the WordPress core Transients API hook.
		$expiration = apply_filters( "expiration_of_transient_{$transient}", $expiration, $value, $transient );

		$result = self::without_option_cache(
			array( '_transient_' . $transient, '_transient_timeout_' . $transient ),
			static function () use ( $transient, $value, $expiration ) {
				$option  = '_transient_' . $transient;
				$timeout = '_transient_timeout_' . $transient;

				if ( false === get_option( $option ) ) {
					if ( $expiration ) {
						add_option( $timeout, time() + $expiration, '', false );
					}
					return add_option( $option, $value, '', ! $expiration );
				}

				if ( $expiration ) {
					if ( false === get_option( $timeout ) ) {
						delete_option( $option );
						add_option( $timeout, time() + $expiration, '', false );
						return add_option( $option, $value, '', false );
					}
					update_option( $timeout, time() + $expiration );
				}

				return update_option( $option, $value );
			}
		);

		if ( $result ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Preserve the WordPress core Transients API hook.
			do_action( "set_transient_{$transient}", $value, $expiration, $transient );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Preserve the WordPress core Transients API hook.
			do_action( 'set_transient', $transient, $value, $expiration );
			do_action_deprecated( 'setted_transient', array( $transient, $value, $expiration ), '6.8.0', 'set_transient' );
		}

		return $result;
	}

	/**
	 * Run a WordPress API operation against fresh database-backed values.
	 *
	 * Clear option caches before and after the operation, including when a hook
	 * throws. The request-wide cache backend and transient cache are not changed.
	 *
	 * @param string[] $options Option names involved in the operation.
	 * @param callable $callback WordPress API operation.
	 * @return mixed
	 */
	private static function without_option_cache( $options, $callback ) {
		$options = array_map( 'trim', $options );
		$clear   = static function () use ( $options ) {
			foreach ( $options as $option ) {
				wp_cache_delete( $option, 'options' );
			}
			wp_cache_delete( 'alloptions', 'options' );
			wp_cache_delete( 'notoptions', 'options' );
		};

		$clear();

		try {
			return $callback();
		} finally {
			$clear();
		}
	}

	/**
	 * Generate UTM Link.
	 *
	 * @param array $params params.
	 * @return string
	 */
	public static function generate_utm_link( $params = array() ) {
		$default_config = array(
			'summer' => array(
				'source'   => 'db-wowoptin-notice',
				'medium'   => 'summer-sale',
				'campaign' => 'wowoptin-dashboard',
			),
		);

		// Step 1: Get parameters.
		$base_url      = $params['url'] ?? 'https://www.wowoptin.com';
		$utm_key       = $params['utmKey'] ?? null;
		$affiliate     = $params['affiliate'] ?? apply_filters( 'optn_affiliate_id', '' );
		$hash          = $params['hash'] ?? '#pricing';
		$custom_config = $params['config'] ?? null;

		$parsed_url = wp_parse_url( $base_url );
		$scheme     = $parsed_url['scheme'] ?? 'https';
		$host       = $parsed_url['host'] ?? '';
		$path       = $parsed_url['path'] ?? '';
		$query      = array();

		// Step 3: Extract existing query params if present.
		if ( isset( $parsed_url['query'] ) ) {
			parse_str( $parsed_url['query'], $query );
		}

		// Step 4: Determine config.
		$utm_config = $custom_config ?? ( $utm_key && isset( $default_config[ $utm_key ] ) ? $default_config[ $utm_key ] : array() );

		// Step 5: Add UTM parameters.
		if ( ! empty( $utm_config ) ) {
			$query = array_merge(
				$query,
				array(
					'utm_source'   => $utm_config['source'],
					'utm_medium'   => $utm_config['medium'],
					'utm_campaign' => $utm_config['campaign'],
				)
			);
		}

		// Step 6: Add affiliate if present.
		if ( $affiliate ) {
			$query['ref'] = $affiliate;
		}

		// Step 7: Reconstruct URL.
		$final_url = $scheme . '://' . $host . $path;

		if ( ! empty( $query ) ) {
			$final_url .= '?' . http_build_query( $query );
		}

		if ( $hash ) {
			$final_url .= '#' . ltrim( $hash, '#' );
		}

		return $final_url;
	}
}
