<?php // phpcs:ignore

namespace OPTN\Includes\Utils;

/**
 * Country decoder class
 */
class CountryDecoder {

	/**
	 * Country map
	 *
	 * @var array $country_map country map
	 */
	private static $country_map = null;

	/**
	 * Reads the country data from json and returns it
	 *
	 * @return array
	 */
	private static function get_country_map() {
		if ( empty( self::$country_map ) ) {
			self::$country_map = wp_json_file_decode( plugin_dir_path( __FILE__ ) . '/country.json', array( 'associative' => true ) );
		}

		return self::$country_map;
	}

	/**
	 * Decodes country code to actual country
	 *
	 * @param string $code country code.
	 * @return string|null
	 */
	public static function decode( $code ) {
		$code = strtoupper( $code );

		$country_map = self::get_country_map();

		return isset( $country_map[ $code ] ) ? $country_map[ $code ] : null;
	}


	/**
	 * Get total World Domination >:()
	 *
	 * @param int $count count.
	 * @return float
	 */
	public static function get_dom_pct( $count ) {
		$country_map = self::get_country_map();

		$country_count = count( $country_map );

		return 0 === $country_count ? 0 : min( round( ( $count / $country_count ) * 100, 2 ), 100 );
	}
}
