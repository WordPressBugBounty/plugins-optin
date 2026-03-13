<?php // phcps:ignore

namespace OPTN\Includes;

/**
 * Settings
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
		'int_enable_fluentcrm' => false,
		'int_enable_mailpoet'  => false,
		'int_enable_webhook'   => true,

		'int_bg_enable_ga'     => false,
		'int_bg_enable_zapier' => false,

		// Global.
		'global_google_fonts'  => true,
		'global_ip_tracking'   => true,
	);

	/**
	 * Init settings
	 *
	 * @param string $version settings version.
	 * @return void
	 */
	public static function init_settings( $version ) {

		$settings = self::get_settings();

		if ( empty( $settings ) ) {
			$settings            = self::$default_settings;
			$settings['version'] = $version;
			update_option( 'optn_settings', $settings, true );
		} else {
			foreach ( self::$default_settings as $key => $value ) {
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
	 * @param mixed  $value value of setting.
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

	/**
	 * Set Settings
	 *
	 * @param array $new_settings new settings.
	 * @return void
	 */
	public static function update_settings( $new_settings ) {

		$curr_settings = self::get_settings();

		foreach ( $new_settings as $key => $value ) {
			if ( isset( $curr_settings[ $key ] ) ) {

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
			if ( str_starts_with( $key, 'global_' ) ) {
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

		$settings   = self::get_settings();
		$wp_plugins = array();

		foreach ( $settings as $key => $value ) {
			if ( str_starts_with( $key, 'int_enable_' ) || str_starts_with( $key, 'int_bg_enable_' ) ) {
				$wp_plugins[ $key ] = $value;
			}
		}

		return $wp_plugins;
	}
}
