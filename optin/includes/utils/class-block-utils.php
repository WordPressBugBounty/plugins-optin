<?php // phpcs:ignore

namespace OPTN\Includes\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Block Utils Class
 */
class BlockUtils {

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

		if ( empty( $icon_name ) ) {
			return $html;
		}

		$path = OPTN_DIR . '/assets/images/icon-library/' . $icon_name . '.svg';

		if ( file_exists( $path ) ) {
			$html = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}

		return $html;
	}

	/**
	 * Get link opening tag html
	 *
	 * @param object $link link attribute.
	 * @param string $attr extra attributes.
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
	 * @param object $attr attributes.
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
			if ( is_array( $value ) ) {
				$attrs_str = implode( ' ', $value );
				$res      .= $key . '="' . $attrs_str . '" ';
			} else {
				$res .= $key . '="' . $value . '" ';
			}
		}

		return $res;
	}
}
