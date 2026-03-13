<?php // phpcs:ignore

/**
 * Video class
 *
 * @package opitn/inludes/uitls
 */

namespace OPTN\Includes\Utils;

/**
 * Video
 */
class VideoHelper {

	/**
	 * Youtube type
	 *
	 * @var string
	 */
	public static $youtube = 'youtube';

	/**
	 * Vimeo type
	 *
	 * @var string
	 */
	public static $vimeo = 'vime0';


	/**
	 * Other type
	 *
	 * @var string
	 */
	public static $other = 'other';

	/**
	 * Check if the given URL is a YouTube link.
	 *
	 * @param string $url url.
	 * @return bool
	 */
	private static function is_youtube_url( $url ) {
		return preg_match( '/(?:youtube\.com|youtu\.be)/', $url ) === 1;
	}

	/**
	 * Check if the given URL is a Vimeo link.
	 *
	 * @param string $url url.
	 * @return bool
	 */
	private static function is_vimeo_url( $url ) {
		return preg_match( '/vimeo\.com/', $url ) === 1;
	}

	/**
	 * Extract the YouTube video ID from a URL.
	 *
	 * @param string $url url.
	 * @return string|null
	 */
	private static function extract_youtube_id( $url ) {
		$pattern = '/(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/';
		if ( preg_match( $pattern, $url, $matches ) ) {
			return $matches[1];
		}
		return null;
	}

	/**
	 * Extract the Vimeo video ID from a URL.
	 *
	 * @param string $url url.
	 * @return string|null
	 */
	private static function extract_vimeo_id( $url ) {
		$pattern = '/vimeo\.com\/(?:.*?\/video\/|.*?\/|)(\d+)/';
		if ( preg_match( $pattern, $url, $matches ) ) {
			return $matches[1];
		}
		return null;
	}


	/**
	 * Get the type of video src url.
	 *
	 * @param string $url url.
	 * @return string
	 */
	private static function get_video_url_type( $url ) {
		if ( self::is_youtube_url( $url ) ) {
			return self::$youtube;
		}

		if ( self::is_vimeo_url( $url ) ) {
			return self::$vimeo;
		}

		return self::$other;
	}

	/**
	 * Wraps content with thumbnail
	 *
	 * @param string $content content.
	 * @param object $attr attributes.
	 * @param bool   $is_bg is background.
	 * @return string
	 */
	private static function wrap( $content, $attr, $is_bg ) {

		if ( $is_bg ) {
			$html  = '<div class="optn-bg-video-thirdparty">';
			$html .= '<div class="optn-bg-video-iframe-wrapper">';

			$html .= $content;

			$html .= '</div></div>';
		} else {
			$html = '<div class="optn-block-video-thirdparty">';

            $src = isset( $attr->thumbSrc->id ) ? wp_get_attachment_image_url( $attr->thumbSrc->id, 'full' ) : $attr->thumbSrc->url; // phpcs:ignore

			if ( ! empty( $src ) && ! $attr->autoplay ) {
				ob_start();
				?>
			<button
				style="background:black url('<?php echo esc_url( $src ); ?>') no-repeat center;background-size:cover;"
				class="optn-block-video-thumbnail"
				aria-label="Play Video"
				aria-title="Play Video"
			>
			</button>
				<?php
				$html .= ob_get_clean();
			}

			$html .= $content;
			$html .= '</div>';
		}

		return $html;
	}

	/**
	 * Generate youtube html
	 *
	 * @param object $attr attributes.
	 * @param bool   $is_bg is background.
	 * @param array  $extra_attrs extra attributes.
	 * @return string
	 */
	private static function get_youtube_html( &$attr, $is_bg, $extra_attrs ) {

		$vid_id = self::extract_youtube_id( $attr->url );
		$tags   = array();

		$params = array(
			'enablejsapi' => 1,
			'loop'        => (int) ( 'repeat' === $attr->repeat ),
			'playlist'    => $vid_id,
		);

		if ( $is_bg ) {
			$params['autoplay']       = 1;
			$params['mute']           = 1;
			$params['disablekb']      = 1;
			$params['playsinline']    = 1;
			$params['rel']            = 0;
			$params['iv_load_policy'] = 3;
			$params['cc_load_policy'] = 0;
			$params['controls']       = 0;
		} else {
			$params['mute']     = (int) $attr->autoplay;
			$params['controls'] = (int) $attr->controls;
		}

		$tags['src'] = esc_url(
			add_query_arg(
				$params,
				'https://www.youtube.com/embed/' . $vid_id
			)
		);

		$tags['title']           = isset( $attr->desc ) ? esc_attr( $attr->desc ) : 'Youtube';
		$tags['frameborder']     = '0';
		$tags['allow']           = 'accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture;web-share';
		$tags['referrerpolicy']  = 'strict-origin-when-cross-origin';
		$tags['allowfullscreen'] = '';

		if ( $is_bg ) {
			$tags['data-optn-youtube-autoplay'] = 'true';
		} else {
			$tags['width']                      = '100%';
			$tags['height']                     = '100%';
			$tags['data-optn-youtube-autoplay'] = $attr->autoplay ? 'true' : 'false';
		}

		foreach ( $extra_attrs as $key => $value ) {
			$tags[ $key ] = $value;
		}

		$attributes = Utils::build_attr_str( $tags );

		return self::wrap( "<iframe {$attributes}></iframe>", $attr, $is_bg );
	}

	/**
	 * Generate vimeo html
	 *
	 * @param object $attr attributes.
	 * @param bool   $is_bg is background.
	 * @param array  $extra_attrs extra attributes.
	 * @return string
	 */
	private static function get_vimeo_html( &$attr, $is_bg, $extra_attrs ) {
		$vid_id = self::extract_vimeo_id( $attr->url );
		$tags   = array();

		$params = array(
			'autoplay' => $is_bg ? '1' : ( (int) $attr->autoplay ),
			'muted'    => $is_bg ? '1' : ( (int) $attr->autoplay ),
			'loop'     => (int) ( 'repeat' === $attr->repeat ),
			'controls' => $is_bg ? '0' : ( (int) $attr->controls ),

		);

		$tags['src'] = esc_url(
			add_query_arg(
				$params,
				'https://player.vimeo.com/video/' . $vid_id
			)
		);

		$tags['title']           = isset( $attr->desc ) ? esc_attr( $attr->desc ) : 'Vimeo';
		$tags['frameborder']     = '0';
		$tags['allow']           = 'autoplay; fullscreen; picture-in-picture';
		$tags['allowfullscreen'] = '';

		if ( $is_bg ) {
			$tags['data-optn-vimeo-autoplay'] = 'true';
		} else {
			$tags['width']                    = '100%';
			$tags['height']                   = '100%';
			$tags['data-optn-vimeo-autoplay'] = $attr->autoplay ? 'true' : 'false';
		}

		foreach ( $extra_attrs as $key => $value ) {
			$tags[ $key ] = $value;
		}

		$attributes = Utils::build_attr_str( $tags );

		return self::wrap( "<iframe {$attributes}></iframe>", $attr, $is_bg );
	}

	/**
	 * Generate video html
	 *
	 * @param object $attr attributes.
	 * @param bool   $is_bg is background.
	 * @param array  $extra_attrs extra attributes.
	 * @return string
	 */
	private static function get_video_tag_html( &$attr, $is_bg, $extra_attrs ) {

		$tags       = array();
		$attributes = '';

		$tags['src']     = esc_url( $attr->url );
		$tags['preload'] = 'true';

		if ( 'repeat' === $attr->repeat ) {
			$tags['loop'] = 'true';
		}

		if ( $is_bg ) {
			$tags['autoplay']           = '';
			$tags['muted']              = '';
			$tags['playsinline']        = '';
			$tags['data-optn-autoplay'] = 'true';
		} else {

            $thumb_url = isset( $attr->thumbSrc->id ) ? wp_get_attachment_image_url( $attr->thumbSrc->id, 'full' ) : $attr->thumbSrc->url; // phpcs:ignore

			if ( ! empty( $thumb_url ) ) {
				$tags['poster'] = esc_url( $thumb_url );
			}

			$tags['data-optn-autoplay'] = 'false';

			if ( $attr->autoplay ) {
				$tags['autoplay']           = '';
				$tags['muted']              = '';
				$tags['data-optn-autoplay'] = 'true';
			}

			if ( $attr->controls ) {
				$tags['controls'] = '';
			}

			$tags['aria-description'] = ! empty( $attr->desc ) ? esc_attr( $attr->desc ) : false;
		}

		foreach ( $extra_attrs as $key => $value ) {
			$tags[ $key ] = $value;
		}

		$attributes = Utils::build_attr_str( $tags );

		return "<video {$attributes}></video>";
	}

	/**
	 * Extract the Vimeo video ID from a URL.
	 *
	 * @param object $attr attributes.
	 * @param bool   $is_bg is background.
	 * @param array  $extra_attrs extra attributes.
	 * @return string
	 */
	public static function get_video_html( &$attr, $is_bg = false, $extra_attrs = array() ) {
		$type = self::get_video_url_type( $attr->url );

		if ( self::$youtube === $type ) {
			return self::get_youtube_html( $attr, $is_bg, $extra_attrs );
		}

		if ( self::$vimeo === $type ) {
			return self::get_vimeo_html( $attr, $is_bg, $extra_attrs );
		}

		return self::get_video_tag_html( $attr, $is_bg, $extra_attrs );
	}
}
