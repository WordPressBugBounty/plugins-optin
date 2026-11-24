<?php
/**
 * Detects a visitor's operating-system family from the request user agent.
 *
 * @package Optin
 */

namespace OPTN\Includes\Utils;

use OPTN\Includes\Utils\Device;

/**
 * Classifies the visitor's operating system from its user agent.
 * Desktop and mobile devices use separate pattern lists; Device supplies the
 * device category that selects which list is checked.
 */
class Os {

	/**
	 * User-agent patterns that identify desktop operating-system families.
	 *
	 * @var array<string, string>
	 */
	private static $desktop_oses =
		array(
			'/windows nt 10/i'      => 'Windows',
			'/windows nt 6.3/i'     => 'Windows',
			'/windows nt 6.2/i'     => 'Windows',
			'/windows nt 6.1/i'     => 'Windows',
			'/windows nt 6.0/i'     => 'Windows',
			'/windows nt 5.2/i'     => 'Windows',
			'/windows nt 5.1/i'     => 'Windows',
			'/windows xp/i'         => 'Windows',
			'/windows nt 5.0/i'     => 'Windows',
			'/windows me/i'         => 'Windows',
			'/win98/i'              => 'Windows',
			'/win95/i'              => 'Windows',
			'/win16/i'              => 'Windows',
			'/macintosh|mac os x/i' => 'Mac',
			'/mac_powerpc/i'        => 'Mac',
			'/linux/i'              => 'Linux',
			'/ubuntu/i'             => 'Linux',
		);

	/**
	 * User-agent patterns that identify mobile operating-system families.
	 *
	 * @var array<string, string>
	 */
	private static $mobile_oses =
		array(
			'/android/i'    => 'Android',
			'/blackberry/i' => 'Android',
			'/webos/i'      => 'Android',
			'/iphone/i'     => 'iOS',
			'/ipod/i'       => 'iOS',
			'/ipad/i'       => 'iOS',
		);

	/**
	 * Detects a desktop or mobile operating system from HTTP_USER_AGENT.
	 * Missing or unrecognized desktop agents default to Windows, while unrecognized
	 * mobile agents default to Android.
	 *
	 * @return string Detected platform family: Windows, Mac, Linux, Android, or iOS.
	 */
	public static function getOS() {

		if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return 'Windows';
		}

		$user_agent = sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ); // phpcs:ignore

		$device = Device::get_device( $user_agent );

		$os_list = $device === 'lg' ? self::$desktop_oses : self::$mobile_oses;

		foreach ( $os_list as $regex => $value ) {
			if ( preg_match( $regex, $user_agent ) ) {
				return $value;
			}
		}

		return $device === 'lg' ? 'Windows' : 'Android';
	}
}
