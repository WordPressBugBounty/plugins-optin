<?php // phpcs:ignore

namespace OPTN\Includes\Utils;

use WP_Query;

/**
 * Assets class
 */
class Assets {

	const IMAGE_ASSET_URL = 'https://apps.wowoptin.com/wp-json/wmedia/v1/images';
	const VIDEO_ASSET_URL = 'https://apps.wowoptin.com/wp-json/wmedia/v1/videos';

	/**
	 * Undocumented function
	 *
	 * @param string $s     search string.
	 * @param int    $page  page number.
	 * @param int    $limit limit.
	 * @return array
	 */
	public static function get_videos( $s, $page, $limit ) {
		$url = add_query_arg(
			array(
				'license' => Utils::get_license_key(),
				'is_dev'  => defined( 'OPTN_DEV_MODE' ) && OPTN_DEV_MODE ? '1' : '0',
				'version' => defined( 'OPTN_VERSION' ) ? OPTN_VERSION : '0.0.0',
				's'       => $s,
				'page'    => $page,
				'limit'   => $limit,
			),
			self::VIDEO_ASSET_URL
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			Utils::log_to_file( $response );
			return array();
		}

		$data = json_decode( $response['body'] );

		return $data;
	}

	/**
	 * Undocumented function
	 *
	 * @param string $s     search string.
	 * @param int    $page  page number.
	 * @param int    $limit limit.
	 * @param string $src src.
	 * @return array
	 */
	public static function get_images( $s, $page, $limit, $src ) {
		if ( 'stock' === $src ) {
			return self::get_stock_images( $s, $page, $limit );
		} elseif ( 'own' === $src ) {
			return self::get_own_images( $s, $page, $limit );
		}
	}

	/**
	 * Undocumented function
	 *
	 * @param string $s     search string.
	 * @param int    $page  page number.
	 * @param int    $limit limit.
	 * @return array
	 */
	private static function get_stock_images( $s, $page, $limit ) {
		$url = add_query_arg(
			array(
				'license' => Utils::get_license_key(),
				'is_dev'  => defined( 'OPTN_DEV_MODE' ) && OPTN_DEV_MODE ? '1' : '0',
				'version' => defined( 'OPTN_VERSION' ) ? OPTN_VERSION : '0.0.0',
				's'       => $s,
				'page'    => $page,
				'limit'   => $limit,
			),
			self::IMAGE_ASSET_URL
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			Utils::log_to_file( $response );
			return array();
		}

		$data = json_decode( $response['body'] );

		return $data;
	}

	/**
	 * Undocumented function
	 *
	 * @param string $url     image url.
	 * @return array
	 */
	public static function get_image( $url ) {
		$url = add_query_arg(
			array(
				'license'   => Utils::get_license_key(),
				'is_dev'    => defined( 'OPTN_DEV_MODE' ) && OPTN_DEV_MODE ? '1' : '0',
				'version'   => defined( 'OPTN_VERSION' ) ? OPTN_VERSION : '0.0.0',
				'image_url' => $url,
			),
			self::IMAGE_ASSET_URL . '/download'
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			Utils::log_to_file( $response );
			return null;
		}

		$data = json_decode( $response['body'] );

		return isset( $data->download_url ) ? $data->download_url : null;
	}

	/**
	 * Get Image file name from url
	 *
	 * @param string $image_url image url.
	 * @return string|null
	 */
	public static function get_image_filename( $image_url ) {
		$parsed_url = wp_parse_url( $image_url );

		if ( false === $parsed_url ) {
			return null;
		}

		$original_filename = null;

		// Special Case for unsplash images.
		if ( str_starts_with( $image_url, 'https://api.unsplash.com/photos/' ) ) {
			$paths             = explode( '/', $parsed_url['path'] );
			$original_filename = isset( $paths[2] ) ? 'unsplash-' . $paths[2] : null;
		} else {
			$original_filename = basename( $parsed_url['path'] );
		}

		if ( empty( $original_filename ) ) {
			return null;
		}

		return 'wowoptin-' . sanitize_file_name( $original_filename );
	}

	/**
	 * Undocumented function
	 *
	 * @param string $s     search string.
	 * @param int    $page  page number.
	 * @param int    $limit limit.
	 * @return array
	 */
	private static function get_own_images( $s, $page, $limit ) {
		$data = array();

		$args = array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'published',
			'posts_per_page' => $limit,
			'paged'          => $page + 1,
		);

		if ( ! empty( $s ) ) {
			$args['s'] = $s;
		}

		$query = new WP_Query( $args );
		$posts = $query->posts;

		foreach ( $posts as $post ) {
			$image = wp_get_attachment_image_src( $post->ID, 'full' );
			// $author     = get_the_author_meta( 'display_name', $post->post_author );
			// $author_url = get_the_author_meta( 'user_url', $post->post_author );
			$data[] = array(
				'id'         => $post->ID,
				'width'      => $image[1],
				'height'     => $image[2],
				'full'       => $image[0],
				'thumb'      => wp_get_attachment_image_url( $post->ID, 'thumb' ),
				'author'     => '',
				'author_url' => '',
			);
		}

		// $provider     = get_bloginfo( 'name' );
		// $provider_url = get_bloginfo( 'url' );

		return array(
			'data'         => array(
				'page'  => $page,
				'limit' => $limit,
				'total' => $query->found_posts,
				'data'  => $data,
			),

			'provider'     => 'own',
			'provider_url' => '',
		);
	}
}
