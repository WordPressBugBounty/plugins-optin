<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- Matches the plugin autoloader.
/**
 * Copies WPXPO campaign media into the site's Media Library.
 *
 * @package Optin
 */

namespace OPTN\Includes\Utils;

defined( 'ABSPATH' ) || exit;

/** Localizes template assets when editing and suppresses remaining remote URLs when rendering. */
class TemplateMedia {

	private const UPLOAD_URL = '~(?<![\w./:%+-])(?:https?:)?//((?:apps\.|www\.)?wowoptin\.com|(?:www\.)?wpxpo\.com)(?::(?:80|443))?(/wp-content/uploads/[^\s"\'<>()\\\,#?]+)(?:[?#][^\s"\'<>()\\,]*)?~i';
	private const MAX_BYTES  = 8 * 1024 * 1024;
	private const MAX_PIXELS = 25 * 1000 * 1000;

	/**
	 * Attachment results reused across a campaign or recipe.
	 *
	 * @var array<string, array|null>
	 */
	private $attachments = array();

	/**
	 * Latest time to start or wait for a download in this import.
	 *
	 * @var float
	 */
	private $deadline;

	/** Share a download deadline across the import. */
	public function __construct() {
		$this->deadline = microtime( true ) + 20;
	}

	/**
	 * Copy eligible images in validated campaign JSON and replace successful URLs.
	 *
	 * @param string $json Validated campaign JSON.
	 * @return string Campaign JSON with Media Library references where available.
	 */
	public function localize_json( $json ) {
		$data    = json_decode( $json );
		$changed = self::rewrite( $data, array( $this, 'attachment' ) );
		return $changed ? wp_json_encode( $data ) : $json;
	}

	/**
	 * Remove vendor media references from a rendering copy without downloading files.
	 *
	 * @param object $data Decoded campaign data for output.
	 * @return void
	 */
	public static function hide_remote( $data ) {
		self::rewrite(
			$data,
			static function () {
				return array(
					'id'  => 0,
					'url' => '',
				);
			}
		);
	}

	/**
	 * Replace vendor upload URLs in structured media fields, generated CSS and HTML.
	 *
	 * @param mixed       $data      Campaign data to update.
	 * @param callable    $resolve   Resolves a source to an attachment or an empty URL.
	 * @param string|null $site_host Current site hostname, loaded on the first matching URL.
	 * @return bool Whether any data changed.
	 */
	private static function rewrite( &$data, $resolve, &$site_host = null ) {
		$changed = false;
		foreach ( $data as $key => &$value ) {
			if ( is_array( $value ) || is_object( $value ) ) {
				$changed = self::rewrite( $value, $resolve, $site_host ) || $changed;
			} elseif ( is_string( $value ) && false !== stripos( $value, '/wp-content/uploads/' ) ) {
				$media = 'url' === $key && is_object( $data ) && property_exists( $data, 'id' ) ? $data : null;
				$value = preg_replace_callback(
					self::UPLOAD_URL,
					static function ( $parts ) use ( $value, $media, $resolve, &$changed, &$site_host ) {
						$site_host = $site_host ?? strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
						$host      = strtolower( $parts[1] );
						if ( $site_host === $host ) {
							return $parts[0];
						}
						$attachment = $resolve( 'https://' . $host . $parts[2] );
						if ( null === $attachment ) {
							return $parts[0];
						}
						if ( $media && $value === $parts[0] ) {
							$media->id = $attachment['id'];
						}
						$changed = true;
						return $attachment['url'];
					},
					$value
				);
			}
		}
		unset( $value );
		return $changed;
	}

	/**
	 * Reuse a prior Media Library copy or fetch one image within this import's limits.
	 *
	 * @param string $url Canonical WPXPO upload URL.
	 * @return array|null Attachment ID and URL, or null if unavailable.
	 */
	private function attachment( $url ) {
		if ( array_key_exists( $url, $this->attachments ) ) {
			return $this->attachments[ $url ];
		}
		if ( count( $this->attachments ) >= 16 ) {
			return null;
		}
		$this->attachments[ $url ] = null;

		$path = rawurldecode( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		if ( ! preg_match( '~\.(?:avif|gif|jpe?g|png|webp)$~iD', $path ) || preg_match( '~(?:^|/)\.{1,2}(?:/|$)|[\x00-\x1f\x7f\\\\?#]~', $path ) ) {
			return null;
		}
		$ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'post_status'    => 'inherit',
				'fields'         => 'ids',
				'numberposts'    => 1,
				'meta_key'       => '_source_url', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Reuse WordPress's attachment source marker.
				'meta_value'     => $url, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Indexed by meta key and bounded per import.
			)
		);
		$id  = $ids ? $ids[0] : $this->download( $url, sanitize_file_name( basename( $path ) ) );
		$src = $id ? wp_get_attachment_url( $id ) : false;
		$src = $src ? esc_url_raw( $src, array( 'http', 'https' ) ) : '';
		if ( $src && ! preg_match( self::UPLOAD_URL, $src ) ) {
			// Attachment URLs can be used in both HTML attributes and quoted or unquoted CSS URLs.
			$src                       = strtr(
				$src,
				array(
					"'" => '%27',
					'(' => '%28',
					')' => '%29',
					',' => '%2C',
				)
			);
			$this->attachments[ $url ] = array(
				'id'  => (int) $id,
				'url' => $src,
			);
		}
		return $this->attachments[ $url ];
	}

	/**
	 * Download a bounded raster image and let WordPress create its attachment.
	 *
	 * @param string $url  Canonical URL on an allowed template media host.
	 * @param string $name Local file name.
	 * @return int Attachment ID, or zero on failure.
	 */
	private function download( $url, $name ) {
		$remaining = $this->deadline - microtime( true );
		if ( $remaining < 1 || ! current_user_can( 'upload_files' ) ) {
			return 0;
		}
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}
		if ( ! function_exists( 'wp_read_image_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$temp = wp_tempnam( $name );
		if ( ! $temp ) {
			return 0;
		}
		try {
			$response = wp_safe_remote_get(
				$url,
				array(
					'timeout'             => min( 5, $remaining ),
					'redirection'         => 0,
					'user-agent'          => 'WowOptin',
					'stream'              => true,
					'filename'            => $temp,
					'limit_response_size' => self::MAX_BYTES + 1,
				)
			);
			clearstatcache( true, $temp );
			$size = is_file( $temp ) ? filesize( $temp ) : 0;
			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) || ! $size || $size > self::MAX_BYTES ) {
				return 0;
			}
			$image = wp_getimagesize( $temp );
			if ( ! $image || $image[0] * $image[1] > self::MAX_PIXELS || ! in_array( $image['mime'], array( 'image/avif', 'image/gif', 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
				return 0;
			}

			$id = media_handle_sideload(
				array(
					'name'     => $name,
					'tmp_name' => $temp,
				),
				0,
				null,
				array( 'meta_input' => array( '_source_url' => $url ) )
			);
			return is_wp_error( $id ) ? 0 : (int) $id;
		} finally {
			if ( is_file( $temp ) ) {
				wp_delete_file( $temp );
			}
		}
	}
}
