<?php

namespace OPTN\Includes\Utils;

class Device {

	/**
	 * Cache, keyed by user agent + detection mode.
	 *
	 * The mode is part of the key because `$ua_only` changes the answer: a lookup that
	 * passes an explicit UA must not reuse (or poison) a lookup that is allowed to read
	 * the current request's client hints.
	 *
	 * @var array
	 */
	private static $device = array();

	/**
	 * Patterns that identify a phone.
	 *
	 * Evaluated before the tablet list. A UA carrying an explicit phone marker is a phone
	 * even when it also matches a tablet pattern - every Android phone contains the string
	 * "Android", and every iPhone contains "Mobile".
	 *
	 * @var string
	 */
	protected static $phone_devices = 'iPhone|iPod|Windows Phone|IEMobile|Opera Mini|Opera Mobi|BlackBerry|BB10|Android.*\bMobile\b';

	/**
	 * List of tablet devices.
	 *
	 * @var array
	 */
	protected static $tablet_devices = array(
		'iPad'          => 'iPad|iPad.*Mobile',
		'GenericTablet' => array(
			'Android.*\b97D\b|Tablet(?!.*PC)|BNTV250A|MID-WCDMA|LogicPD Zoom2|\bA7EB\b|CatNova8|A1_07|CT704|CT1002',
			'\bM721\b|rk30sdk|\bEVOTAB\b|M758A|ET904|ALUMIUM10|Smartfren Tab|Endeavour 1010|Tablet-PC-4|Tagi Tab',
			'\bM6pro\b|CT1020W|arc 10HD|\bTP750\b|\bQTAQZ3\b|WVT101|TM1088|KT107',
			'Android(?!.*\bMobile\b)|Android 3\.0|xoom|sch-i800|playbook|kindle|\bNexus (?:7|9|10)\b',
		),
	);

	/**
	 * Whether the user agent identifies a phone.
	 *
	 * @param string $ua user agent.
	 * @return boolean
	 */
	private static function is_phone( $ua ) {
		return self::match( self::$phone_devices, $ua );
	}

	/**
	 * Whether the user agent identifies a tablet.
	 *
	 * @param string $ua user agent.
	 * @return boolean
	 */
	private static function is_tablet( $ua ) {
		foreach ( self::$tablet_devices as $_regex ) {
			$regexString = $_regex;
			if ( is_array( $_regex ) ) {
				$regexString = implode( '|', $_regex );
			}
			if ( self::match( $regexString, $ua ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the browser itself declares this request as coming from a phone.
	 *
	 * Chromium sends `Sec-CH-UA-Mobile: ?1` only for phones - Android tablets and desktops
	 * both get `?0` - so it is a stronger signal than any user agent pattern and is checked
	 * first. Treated as a positive signal only: `?0` never suppresses the checks below,
	 * because most non-Chromium browsers omit the header entirely.
	 *
	 * @param boolean $ua_only ignore the current request's client hints.
	 * @return boolean
	 */
	private static function is_phone_by_client_hint( $ua_only = false ) {
		if ( $ua_only || ! isset( $_SERVER['HTTP_SEC_CH_UA_MOBILE'] ) ) {
			return false;
		}

		return '?1' === sanitize_text_field( wp_unslash( $_SERVER['HTTP_SEC_CH_UA_MOBILE'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	/**
	 * Fallback mobile check for user agents that matched neither the phone nor the
	 * tablet list.
	 *
	 * @param string $ua user agent.
	 * @return boolean
	 */
	private static function is_mobile( $ua ) {
		return str_contains( $ua, 'Mobile' )
			|| str_contains( $ua, 'Android' )
			|| str_contains( $ua, 'Silk/' )
			|| str_contains( $ua, 'Kindle' )
			|| str_contains( $ua, 'BlackBerry' )
			|| str_contains( $ua, 'Opera Mini' )
			|| str_contains( $ua, 'Opera Mobi' );
	}

	/**
	 * Resolve the device breakpoint for a user agent.
	 *
	 * @param string $ua user agent. Defaults to the current request's.
	 * @return string one of 'lg' (desktop), 'sm' (tablet), 'xs' (phone).
	 */
	public static function get_device( $ua = '' ) {
		$ua_only = false;

		if ( ! empty( $ua ) ) {
			$ua_only = true;
		} else {
			$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		if ( empty( $ua ) ) {
			return 'lg';
		}

		$cache_key = $ua . '|' . ( $ua_only ? '1' : '0' );

		// Check cache
		if ( isset( self::$device[ $cache_key ] ) ) {
			return self::$device[ $cache_key ];
		}

		// Order matters. The client hint is authoritative where present, and an explicit
		// phone marker outranks the tablet list, which is deliberately broad enough to
		// match phones too (see `Android(?!.*Mobile)`). The generic mobile check is last
		// because it is the loosest.
		if ( self::is_phone_by_client_hint( $ua_only ) || self::is_phone( $ua ) ) {
			$device = 'xs';
		} elseif ( self::is_tablet( $ua ) ) {
			$device = 'sm';
		} elseif ( self::is_mobile( $ua ) ) {
			$device = 'xs';
		} else {
			$device = 'lg';
		}

		self::$device[ $cache_key ] = $device;

		return $device;
	}

	/**
	 *
	 * @param string $regex
	 *
	 * @return boolean
	 */
	private static function match( $regex, $ua ) {
		if ( empty( $ua ) ) {
			return false;
		}

		return (bool) preg_match( sprintf( '#%s#is', $regex ), $ua, $matches );
	}
}
