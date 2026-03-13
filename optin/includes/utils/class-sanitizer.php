<?php // phpcs:ignore

/**
 * Sanitizer class
 *
 * @package opitn/inludes/uitls
 */

namespace OPTN\Includes\Utils;

/**
 * Optin HTML Content Sanitizer
 */
class Sanitizer {

	/**
	 * Common attributes
	 *
	 * @var array
	 */
	private static $common_attributes = array(
		'class'                 => true,
		'id'                    => true,
		'style'                 => array(
			'transform' => array(),
		),
		'title'                 => true,
		'role'                  => true,
		'tabindex'              => true,
		'aria-hidden'           => true,
		'data-*'                => true,
		'data-optn'             => true,
		'data-optn-post-id'     => true,
		'data-optn-post-type'   => true,
		'data-optn-updated-at'  => true,
		'data-optn-block-index' => true,
		'data-optn-autoplay'    => true,
		'data-step-id'          => true,
		'aria-description'      => true,
	);

	/**
	 * Allow list
	 *
	 * @return array
	 */
	private static function get_allowed_html() {
		return array(

			// HTML.
			'a'              => array_merge(
				self::$common_attributes,
				array(
					'href'       => true,
					'title'      => true,
					'rel'        => true,
					'target'     => true,
					'noreferrer' => true,
					'download'   => true,
				)
			),
			'b'              => self::$common_attributes,
			'strong'         => self::$common_attributes,
			'i'              => self::$common_attributes,
			'em'             => self::$common_attributes,
			'del'            => self::$common_attributes,
			'sub'            => self::$common_attributes,
			'sup'            => self::$common_attributes,
			'u'              => self::$common_attributes,
			'br'             => self::$common_attributes,
			'p'              => self::$common_attributes,
			'div'            => self::$common_attributes,
			'span'           => self::$common_attributes,
			'h1'             => self::$common_attributes,
			'h2'             => self::$common_attributes,
			'h3'             => self::$common_attributes,
			'h4'             => self::$common_attributes,
			'h5'             => self::$common_attributes,
			'h6'             => self::$common_attributes,
			'img'            => array_merge(
				self::$common_attributes,
				array(
					'src'     => true,
					'alt'     => true,
					'width'   => true,
					'height'  => true,
					'loading' => true,
				)
			),
			'video'          => array_merge(
				self::$common_attributes,
				array(
					'src'         => true,
					'poster'      => true,
					'preload'     => true,
					'controls'    => true,
					'loading'     => true,
					'muted'       => true,
					'autoplay'    => true,
					'height'      => true,
					'loop'        => true,
					'width'       => true,
					'playsinline' => true,

				)
			),
			'ul'             => self::$common_attributes,
			'ol'             => self::$common_attributes,
			'li'             => self::$common_attributes,
			'table'          => array_merge(
				self::$common_attributes,
				array(
					'border'      => true,
					'cellpadding' => true,
					'cellspacing' => true,
				)
			),
			'thead'          => self::$common_attributes,
			'tbody'          => self::$common_attributes,
			'tfoot'          => self::$common_attributes,
			'tr'             => self::$common_attributes,
			'th'             => array_merge(
				self::$common_attributes,
				array(
					'colspan' => true,
					'rowspan' => true,
				)
			),
			'td'             => array_merge(
				self::$common_attributes,
				array(
					'colspan' => true,
					'rowspan' => true,
				)
			),
			'form'           => array_merge(
				self::$common_attributes,
				array(
					'action'  => true,
					'method'  => true,
					'enctype' => true,
				)
			),
			'input'          => array_merge(
				self::$common_attributes,
				array(
					'type'        => true,
					'name'        => true,
					'value'       => true,
					'placeholder' => true,
					'checked'     => true,
					'disabled'    => true,
					'readonly'    => true,
					'required'    => true,
					'min'         => true,
					'max'         => true,
					'step'        => true,
					'maxlength'   => true,
					'size'        => true,
				)
			),
			'textarea'       => array_merge(
				self::$common_attributes,
				array(
					'name'        => true,
					'rows'        => true,
					'cols'        => true,
					'placeholder' => true,
					'maxlength'   => true,
					'readonly'    => true,
					'required'    => true,
				)
			),
			'button'         => array_merge(
				self::$common_attributes,
				array(
					'type'     => true,
					'name'     => true,
					'value'    => true,
					'disabled' => true,
				)
			),
			'select'         => array_merge(
				self::$common_attributes,
				array(
					'name'     => true,
					'multiple' => true,
					'size'     => true,
					'disabled' => true,
					'required' => true,
				)
			),
			'option'         => array(
				'value'    => true,
				'selected' => true,
			),
			'label'          => array_merge(
				self::$common_attributes,
				array(
					'for' => true,
				)
			),
			'fieldset'       => self::$common_attributes,
			'figure'         => self::$common_attributes,
			'figcaption'     => self::$common_attributes,
			'blockquote'     => array_merge(
				self::$common_attributes,
				array(
					'cite' => true,
				)
			),
			'pre'            => self::$common_attributes,
			'code'           => self::$common_attributes,
			'hr'             => self::$common_attributes,
			'kbd'            => self::$common_attributes,
			'iframe'         => array_merge(
				self::$common_attributes,
				array(
					'src'               => true,
					'srcdoc'            => true,
					'name'              => true,
					'width'             => true,
					'height'            => true,
					'frameborder'       => true,
					'allow'             => true,
					'allowfullscreen'   => true,
					'loading'           => true,
					'referrerpolicy'    => true,
					'sandbox'           => true,
					'scrolling'         => true,
					'style'             => true,
					'class'             => true,
					'id'                => true,
					'title'             => true,
					'aria-hidden'       => true,
					'allowtransparency' => true,
				)
			),

			// SVG.
			'svg'            => array(
				'xmlns'        => true,
				'xmlns:xlink'  => true,
				'version'      => true,
				'id'           => true,
				'viewBox'      => true,
				'viewbox'      => true, // Must be lowercase B otherwise wp's stupid regex will remove it.
				'width'        => true,
				'height'       => true,
				'class'        => true,
				'style'        => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
			'clippath'       => array( // Must be in lowercase.
				'id' => true,
			),
			'g'              => array(
				'id'        => true,
				'class'     => true,
				'transform' => true,
				'clip-path' => true,
			),
			'path'           => array(
				'id'           => true,
				'd'            => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
				'class'        => true,
			),
			'rect'           => array(
				'x'      => true,
				'y'      => true,
				'width'  => true,
				'height' => true,
				'fill'   => true,
				'class'  => true,
			),
			'circle'         => array(
				'cx'    => true,
				'cy'    => true,
				'r'     => true,
				'fill'  => true,
				'class' => true,
			),
			'ellipse'        => array(
				'cx'    => true,
				'cy'    => true,
				'rx'    => true,
				'ry'    => true,
				'fill'  => true,
				'class' => true,
			),
			'line'           => array(
				'x1'     => true,
				'y1'     => true,
				'x2'     => true,
				'y2'     => true,
				'stroke' => true,
				'class'  => true,
			),
			'polyline'       => array(
				'points' => true,
				'fill'   => true,
				'stroke' => true,
				'class'  => true,
			),
			'polygon'        => array(
				'points' => true,
				'fill'   => true,
				'stroke' => true,
				'class'  => true,
			),
			'text'           => array(
				'x'           => true,
				'y'           => true,
				'fill'        => true,
				'font-family' => true,
				'font-size'   => true,
			),
			'textpath'       => array(
				'href'              => true,
				'class'             => true,
				'xlink:href'        => true,
				'text-anchor'       => true,
				'startoffset'       => true,
				'dominant-baseline' => true,
			),
			'tspan'          => array(
				'x'           => true,
				'y'           => true,
				'fill'        => true,
				'font-family' => true,
				'font-size'   => true,
			),
			'use'            => array(
				'xlink:href' => true,
				'x'          => true,
				'y'          => true,
			),
			'defs'           => true,
			'symbol'         => array( 'id' => true ),
			'linearGradient' => array(
				'id' => true,
				'x1' => true,
				'y1' => true,
				'x2' => true,
				'y2' => true,
			),
			'stop'           => array(
				'offset'     => true,
				'stop-color' => true,
			),
			'title'          => array(),
			'desc'           => array(),
			'script'         => array(),
			'style'          => array(),
		);
	}


	/**
	 * Allowed protocols
	 *
	 * @var array
	 */
	private static $allowed_protocols = array(
		'http',
		'https',
		'ftp',
		'ftps',
		'mailto',
		'tel',
		'fax',
	);

	/**
	 * Allowed styles
	 *
	 * @var array
	 */
	private static $allowed_styles = array(
		'stroke-dashoffset',
		'stroke-linejoin',
		'display',
		'background-clip',
		'color',
		'transform',
	);

	/**
	 * Sanitizes html content
	 *
	 * @param string $content html content.
	 * @return string
	 */
	public static function sanitize( $content ) {

		add_filter( 'optn_is_sanitizing_content', '__return_true' );

		$sanitized_content = wp_kses( $content, self::get_allowed_html(), self::$allowed_protocols );

		add_filter( 'optn_is_sanitizing_content', '__return_false' );

		return $sanitized_content;
	}

		/**
		 * Sanitizes and echos html content
		 *
		 * @param string $content html content.
		 * @return void
		 */
	public static function e_sanitize( $content ) {
		echo self::sanitize( $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Adds styles to allowlist
	 *
	 * @param array $styles style array.
	 * @return array
	 */
	public static function add_to_style_allowlist( $styles ) {
		return array_merge( $styles, self::$allowed_styles );
	}

	/**
	 * Allow Style attribute value
	 *
	 * @param boolean $allow_css allow css.
	 * @param string  $css_str css string.
	 * @return boolean
	 */
	public static function allow_style_attrs( $allow_css, $css_str ) {

		// Check if we are sanitizing optin content.
		$is_sanitizing_optin_content = apply_filters( 'optn_is_sanitizing_content', false );

		if ( $is_sanitizing_optin_content ) {
			$allowed = array( 'rgb', 'skew' );

			foreach ( $allowed as $str ) {
				if ( strpos( $css_str, $str ) !== false ) {
					return true;
				}
			}
		}

		return $allow_css;
	}

	/**
	 * Adds tags to allowlist
	 *
	 * @param array  $tags tags array.
	 * @param string $context context.
	 * @return array
	 */
	public static function add_to_tags_allowlist( $tags, $context ) {
		if ( 'post' === $context ) {
			$tags['video']  = array(
				'autoplay' => true,
				'controls' => true,
				'height'   => true,
				'loop'     => true,
				'muted'    => true,
				'poster'   => true,
				'preload'  => true,
				'src'      => true,
				'width'    => true,
			);
			$tags['source'] = array(
				'src'  => true,
				'type' => true,
			);
		}
		return $tags;
	}
}
