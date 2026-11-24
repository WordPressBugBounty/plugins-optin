<?php // phpcs:ignore

namespace OPTN\Includes\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Block Utils Class
 */
class BlockUtils {
	/** General icon IDs select only these bundled files. */
	private const ICON_FILES = array(
		'icon_1'  => 'icon_1.svg',
		'icon_2'  => 'icon_2.svg',
		'icon_3'  => 'icon_3.svg',
		'icon_4'  => 'icon_4.svg',
		'icon_5'  => 'icon_5.svg',
		'icon_6'  => 'icon_6.svg',
		'icon_7'  => 'icon_7.svg',
		'icon_8'  => 'icon_8.svg',
		'icon_9'  => 'icon_9.svg',
		'icon_10' => 'icon_10.svg',
		'icon_11' => 'icon_11.svg',
		'icon_12' => 'icon_12.svg',
		'icon_13' => 'icon_13.svg',
		'icon_14' => 'icon_14.svg',
		'icon_15' => 'icon_15.svg',
		'icon_16' => 'icon_16.svg',
		'icon_17' => 'icon_17.svg',
		'icon_18' => 'icon_18.svg',
		'icon_19' => 'icon_19.svg',
		'icon_20' => 'icon_20.svg',
		'icon_21' => 'icon_21.svg',
		'icon_22' => 'icon_22.svg',
		'icon_23' => 'icon_23.svg',
		'icon_24' => 'icon_24.svg',
		'icon_25' => 'icon_25.svg',
		'icon_26' => 'icon_26.svg',
		'icon_27' => 'icon_27.svg',
		'icon_28' => 'icon_28.svg',
		'icon_29' => 'icon_29.svg',
		'icon_30' => 'icon_30.svg',
		'icon_31' => 'icon_31.svg',
		'icon_32' => 'icon_32.svg',
		'icon_33' => 'icon_33.svg',
		'icon_34' => 'icon_34.svg',
	);

	/**
	 * Add google font to the list of fonts to be loaded.
	 *
	 * @param string $font_name font name.
	 * @param array  $weights font weights.
	 * @return void
	 */
	public static function add_google_font( $font_name, $weights ) {
		add_filter(
			'optn_google_fonts',
			function ( $font_data ) use ( $font_name, $weights ) {

				$sanitized_font_name = str_replace( ' ', '+', sanitize_text_field( $font_name ) );

				$sanitized_weights = array();

				$weights = array_unique( $weights );

				foreach ( $weights as $weigth ) {
					$weigth = intval( $weigth );
					if ( $weigth >= 100 && $weigth <= 900 ) {
						$sanitized_weights[] = $weigth;
					}
				}

				if ( isset( $font_data[ $sanitized_font_name ] ) && is_array( $font_data[ $sanitized_font_name ] ) ) {
					$sanitized_weights = array_unique( array_merge( $font_data[ $sanitized_font_name ], $sanitized_weights ) );
				}

				$font_data[ $sanitized_font_name ] = $sanitized_weights;

				return $font_data;
			}
		);
	}

	/**
	 * Icon HTML
	 *
	 * @param string $icon_name icon name.
	 * @return string
	 */
	public static function get_icon_html( $icon_name ) {
		$html = '';

		if ( ! is_string( $icon_name ) || ! isset( self::ICON_FILES[ $icon_name ] ) ) {
			return $html;
		}

		$path = OPTN_DIR . '/assets/images/icon-library/' . self::ICON_FILES[ $icon_name ];

		if ( is_readable( $path ) ) {
			$html = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}

		return false === $html ? '' : $html;
	}

	/**
	 * Get link opening tag html
	 *
	 * @param \stdClass $link Decoded link settings containing URL, target, and rel behavior.
	 * @param string    $attr extra attributes.
	 * @return string
	 */
	public static function get_link_opening_tag_html( $link, $attr ) {
		$html = '<a  href="' . esc_url( $link->url ) . '" target="' . esc_attr( $link->target ) . '" ' . $attr . ' ';

		if ( ! $link->doFollow ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$html .= 'rel="nofollow" ';
		} elseif ( $link->sponsored ) {
			$html .= 'rel="sponsored" ';
		}

		if ( $link->download ) {
			$html .= 'download ';
		}

		$html .= ' >';

		return $html;
	}

	/**
	 * Class for hiding elements responsively
	 *
	 * @param \stdClass $attr Decoded block attributes containing responsive visibility settings.
	 * @return string
	 */
	public static function get_resp_hide_classes( &$attr ) {
		$cls = array();

		if ( isset( $attr->hideDesktop ) && $attr->hideDesktop ) { // phpcs:ignore
			$cls[] = 'optn-hide-desktop';
		}

		if ( isset( $attr->hideTab ) && $attr->hideTab ) { // phpcs:ignore
			$cls[] = 'optn-hide-tablet';
		}

		if ( isset( $attr->hideMobile ) && $attr->hideMobile ) { // phpcs:ignore
			$cls[] = 'optn-hide-mobile';
		}

		return count( $cls ) < 1 ? '' : ' ' . implode( ' ', $cls ) . ' ';
	}

	/**
	 * Watermark HTML
	 *
	 * @return string
	 */
	public static function get_watermark_html() {
		$html = '';

		$path = OPTN_DIR . '/assets/images/watermark.svg';

		if ( file_exists( $path ) ) {
			$html .= '<a href="https://www.wowoptin.com" target="_blank" style="text-decoration:none;position:absolute;bottom:0px;right:0px;display:flex;align-items:center;justify-content:center;">';
			$html .= file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$html .= '</a>';

		}

		return $html;
	}

	/**
	 * Undocumented function
	 *
	 * @param array $attr_array attributes array.
	 * @return string
	 */
	public static function build_html_attrs( $attr_array ) {
		$res = '';
		foreach ( $attr_array as $key => $value ) {
			if ( ! preg_match( '/^[a-zA-Z][a-zA-Z0-9:_-]*$/D', $key ) || preg_match( '/^on|^srcdoc$/i', $key ) ) {
				continue; }
			if ( is_array( $value ) ) {
				$value = implode( ' ', array_filter( $value, 'is_scalar' ) ); }
			if ( ! is_scalar( $value ) ) {
				continue; }
			if ( in_array( strtolower( $key ), array( 'href', 'src', 'action', 'xlink:href', 'formaction' ), true ) ) {
				$value = esc_url( $value ); }
			$res .= $key . '="' . esc_attr( $value ) . '" ';
		}

		return $res;
	}
}
