<?php

namespace OPTN\Includes\Utils;

class Device {

	/**
	 * Cache
	 *
	 * @var array
	 */
	private static $device = array();

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
			'ipad|android|android 3.0|xoom|sch-i800|playbook|tablet|kindle|nexus',
		),
	);


	private static function is_tablet( $ua ) {
		foreach ( self::$tablet_devices as $_regex ) {
			$regexString = $_regex;
			if ( is_array( $_regex ) ) {
				$regexString = implode( '|', $_regex );
			}
			if ( self::match( $regexString, $ua ) ) {
				self::$device[ $ua ] = 'sm';
				return true;
			}
		}

		return false;
	}

	private static function is_mobile( $ua, $ua_only = false ) {
		$is_mobile = false;

		if ( isset( $_SERVER['HTTP_SEC_CH_UA_MOBILE'] ) && ! $ua_only ) {
			$is_mobile = ( '?1' === sanitize_text_field( wp_unslash( $_SERVER['HTTP_SEC_CH_UA_MOBILE'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		} elseif ( str_contains( $ua, 'Mobile' )
			|| str_contains( $ua, 'Android' )
			|| str_contains( $ua, 'Silk/' )
			|| str_contains( $ua, 'Kindle' )
			|| str_contains( $ua, 'BlackBerry' )
			|| str_contains( $ua, 'Opera Mini' )
			|| str_contains( $ua, 'Opera Mobi' ) ) {
				$is_mobile = true;
		}

		if ( $is_mobile ) {
			self::$device[ $ua ] = 'xs';
			return true;
		}

		return false;
	}

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

		// Check cache
		if ( isset( self::$device[ $ua ] ) ) {
			return self::$device[ $ua ];
		}

		if ( self::is_tablet( $ua ) ) {
			return 'sm';
		}

		if ( self::is_mobile( $ua, $ua_only ) ) {
			return 'xs';
		}

		self::$device[ $ua ] = 'lg';
		return 'lg';
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
