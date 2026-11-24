<?php
/**
 * Defines the defaults and persistence helpers for plugin settings.
 *
 * @package Optin
 */

namespace OPTN\Includes;

/**
 * Provides defaults, validation, and persistence for plugin configuration.
 *
 * Keeps global settings and integration options in WordPress options and exposes the
 * normalized values consumed by administration, tracking, and frontend services.
 *
 * @since      1.0.0
 * @package    optin
 * @subpackage optin/includes
 */
class Settings {

	/**
	 * Default settings.
	 *
	 * @var array
	 */
	private static $default_settings = array(

		// Integrations.
		'int_enable_webhook'         => true,


		// Global.
		'global_google_fonts'        => false,
		'global_ip_tracking'         => false,
		'global_alt_render_strategy' => false,
	);

	/**
	 * Init settings
	 *
	 * @param string $version settings version.
	 * @return void
	 */
	public static function init_settings( $version ) {

		$settings = self::get_settings();
		$defaults = apply_filters( 'optn_settings_defaults', self::$default_settings );

		if ( empty( $settings ) ) {
			$settings            = $defaults;
			$settings['version'] = $version;
			update_option( 'optn_settings', $settings, true );
		} else {
			foreach ( $defaults as $key => $value ) {
				if ( ! isset( $settings[ $key ] ) ) {
					$settings[ $key ] = $value;
				}
			}

			if ( ! isset( $settings['version'] ) ) {
				$settings['version'] = $version;
			}

			update_option( 'optn_settings', $settings, true );
		}
	}

	/**
	 * Set Optin settings
	 *
	 * @param string $key key of setting.
	 * @param mixed  $value Setting value; supported settings may be scalar, array, or structured data.
	 * @return void
	 */
	public static function set_settings( $key, $value ) {
		$settings         = self::get_settings();
		$settings[ $key ] = $value;
		update_option( 'optn_settings', $settings, true );
	}

	/**
	 * Get Optin Settings
	 *
	 * @param string|null $key key of setting.
	 * @return array
	 */
	public static function get_settings( $key = null ) {
		$settings = get_option( 'optn_settings', array() );

		if ( ! empty( $key ) ) {
			return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
		}

		return $settings;
	}

	/** Normalize the persisted switch without changing an existing value. */
	public static function is_google_fonts_enabled(): bool {
		return in_array( self::get_settings( 'global_google_fonts' ), array( true, 1, '1', 'true' ), true );
	}

	/** Normalize the persisted switch without changing an existing value. */
	public static function is_ip_tracking_enabled(): bool {
		return in_array( self::get_settings( 'global_ip_tracking' ), array( true, 1, '1', 'true' ), true );
	}

	/**
	 * Set Settings
	 *
	 * @param array $new_settings new settings.
	 * @return void
	 */
	public static function update_settings( $new_settings ) {

		$curr_settings = self::get_settings();
		$allowed       = apply_filters( 'optn_settings_defaults', self::$default_settings );

		foreach ( $new_settings as $key => $value ) {
			if ( array_key_exists( $key, $allowed ) && isset( $curr_settings[ $key ] ) ) {

				if ( is_string( $value ) || is_numeric( $value ) || is_bool( $value ) ) {

					if ( is_string( $value ) ) {
						$value = sanitize_key( $value );
					}

					$curr_settings[ $key ] = $value;
				}
			}
		}

		update_option( 'optn_settings', $curr_settings, true );
	}

	/**
	 * Get all the global settings
	 *
	 * @return array
	 */
	public static function get_global_settings() {

		$settings         = self::get_settings();
		$filterd_settings = array();

		foreach ( $settings as $key => $value ) {
			if ( strpos( $key, 'global_' ) === 0 ) {
				$filterd_settings[ $key ] = $value;
			}
		}

		return $filterd_settings;
	}

	/**
	 * Get all the settings related to integration
	 *
	 * @return array
	 */
	public static function get_integration_settings() {
		return apply_filters( 'optn_integration_settings', array( 'int_enable_webhook' => self::get_settings( 'int_enable_webhook' ) ) );
	}
}
