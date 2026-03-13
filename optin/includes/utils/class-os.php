<?php

namespace OPTN\Includes\Utils;

use OPTN\Includes\Utils\Device;

class Os {

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

	private static $mobile_oses =
		array(
			'/android/i'    => 'Android',
			'/blackberry/i' => 'Android',
			'/webos/i'      => 'Android',
			'/iphone/i'     => 'iOS',
			'/ipod/i'       => 'iOS',
			'/ipad/i'       => 'iOS',
		);

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
