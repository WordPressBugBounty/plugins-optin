<?php // phpcs:ignore

namespace OPTN\Includes\Utils;

/**
 * Template class
 */
class Templates {

	const TEMPLATE_URL = 'https://apps.wowoptin.com/wp-json/otmp/v1/templates';
	const PRESET_URL   = 'https://apps.wowoptin.com/wp-json/otmp/v1/presets';
	const RECIPE_URL   = 'https://apps.wowoptin.com/wp-json/otmp/v1/recipes';

	/**
	 * Get metadata of all templates
	 *
	 * @return array|null
	 */
	public static function get_templates() {
		$key = get_option( 'optn_temp_metadata_key', '' );

		$cached_data = self::get_from_cache( 'templates' );

		$key = ! empty( $cached_data ) ? $key : '';

		$url = add_query_arg(
			array(
				'key'     => $key,
				'is_dev'  => defined( 'OPTN_DEV_MODE' ) && OPTN_DEV_MODE ? '1' : '0',
				'version' => defined( 'OPTN_VERSION' ) ? OPTN_VERSION : '0.0.0',
			),
			self::TEMPLATE_URL
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

		if ( isset( $data->data ) && 'valid' === $data->data ) {
			return $cached_data;
		}

		if ( ! isset( $data->data ) || ! isset( $data->key ) ) {
			return null;
		}

		update_option( 'optn_temp_metadata_key', $data->key );
		self::store_in_cache( 'templates', $data->data );

		return $data->data;
	}

	/**
	 * Get metadata of all element presets
	 *
	 * @return array|null
	 */
	public static function get_presets() {
		$key = get_option( 'optn_temp_preset_key', '' );

		$cached_data = self::get_from_cache( 'presets' );

		$key = ! empty( $cached_data ) ? $key : '';

		$url = add_query_arg(
			array(
				'key'     => $key,
				'is_dev'  => defined( 'OPTN_DEV_MODE' ) && OPTN_DEV_MODE ? '1' : '0',
				'version' => defined( 'OPTN_VERSION' ) ? OPTN_VERSION : '0.0.0',
			),
			self::PRESET_URL
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

		if ( isset( $data->data ) && 'valid' === $data->data ) {
			return $cached_data;
		}

		if ( ! isset( $data->data ) || ! isset( $data->key ) ) {
			return null;
		}

		update_option( 'optn_temp_preset_key', $data->key );
		self::store_in_cache( 'presets', $data->data );

		return $data->data;
	}

	/**
	 * Get Template post
	 *
	 * @param int $id template id.
	 * @return array|null
	 */
	public static function fetch_template_post( $id ) {

		$cache_data = Cache::get( 'optn_template_post' );

		if ( ! empty( $cache_data ) && isset( $cache_data[ $id ] ) ) {
			return $cache_data[ $id ];
		}

		$url = add_query_arg(
			array(
				'license' => Utils::get_license_key(),
				'is_dev'  => defined( 'OPTN_DEV_MODE' ) && OPTN_DEV_MODE ? '1' : '0',
				'version' => defined( 'OPTN_VERSION' ) ? OPTN_VERSION : '0.0.0',
			),
			self::TEMPLATE_URL . '/preview/' . $id
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			Utils::log_to_file( $response );

			$body = wp_remote_retrieve_body( $response );
			$body = json_decode( $body, true );
			if ( isset( $body['message'] ) && 'Update required' === $body['message'] ) {
				return 'update_required';
			}

			return null;
		}

		$data = json_decode( $response['body'] );

		if ( ! empty( $data ) ) {
			Cache::set( 'optn_template_post', array( $id => $data ), 3 );
		}

		return $data;
	}


	/**
	 * Get template data by ID
	 *
	 * @param int $id template id.
	 * @return array|null
	 */
	public static function fetch_single_template( $id ) {

		$url = add_query_arg(
			array(
				'license' => Utils::get_license_key(),
				'is_dev'  => defined( 'OPTN_DEV_MODE' ) && OPTN_DEV_MODE ? '1' : '0',
				'version' => defined( 'OPTN_VERSION' ) ? OPTN_VERSION : '0.0.0',
			),
			self::TEMPLATE_URL . '/' . $id
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			Utils::log_to_file( $response );

			$body = wp_remote_retrieve_body( $response );
			$body = json_decode( $body, true );
			if ( isset( $body['message'] ) && 'Update required' === $body['message'] ) {
				return 'update_required';
			}

			return null;
		}

		$data = json_decode( $response['body'] );

		return $data;
	}


	/**
	 * Get recipe data by ID
	 *
	 * @param int $id template id.
	 * @return array|null
	 */
	public static function fetch_single_recipe( $id ) {

		$url = add_query_arg(
			array(
				'license' => Utils::get_license_key(),
				'is_dev'  => defined( 'OPTN_DEV_MODE' ) && OPTN_DEV_MODE ? '1' : '0',
				'version' => defined( 'OPTN_VERSION' ) ? OPTN_VERSION : '0.0.0',
			),
			self::RECIPE_URL . '/' . $id
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			Utils::log_to_file( $response );

			$body = wp_remote_retrieve_body( $response );
			$body = json_decode( $body, true );
			if ( isset( $body['message'] ) && 'Update required' === $body['message'] ) {
				return 'update_required';
			}

			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ) );

		return $data;
	}

	/**
	 * Get Preset data by ID
	 *
	 * @param int $id preset id.
	 * @return array|null
	 */
	public static function fetch_single_preset( $id ) {

		$url = add_query_arg(
			array(
				'license' => Utils::get_license_key(),
				'is_dev'  => defined( 'OPTN_DEV_MODE' ) && OPTN_DEV_MODE ? '1' : '0',
				'version' => defined( 'OPTN_VERSION' ) ? OPTN_VERSION : '0.0.0',
			),
			self::PRESET_URL . '/' . $id
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			Utils::log_to_file( $response );

			$body = wp_remote_retrieve_body( $response );
			$body = json_decode( $body, true );
			if ( isset( $body['message'] ) && 'Update required' === $body['message'] ) {
				return 'update_required';
			}

			return null;
		}

		$data = json_decode( $response['body'] );

		return $data;
	}

	/**
	 * Get Cache root dir
	 *
	 * @return string
	 */
	private static function get_path() {
		$upload_dir = wp_upload_dir();
		return trailingslashit( $upload_dir['basedir'] ) . 'optn';
	}

	/**
	 * Get value from cache
	 *
	 * @param string $key key.
	 * @return string|null
	 */
	private static function get_from_cache( $key ) {
		$path = self::get_path() . "/{$key}.json";

		if ( file_exists( $path ) ) {
			return wp_json_file_decode( $path );
		}

		return null;
	}

	/**
	 * Delete value from cache
	 *
	 * @param string $key key.
	 * @return void
	 */
	private static function delete_cache( $key ) {
		$path = self::get_path() . "/{$key}.json";
		wp_delete_file( $path );
	}

	/**
	 * Store value in cache
	 *
	 * @param string $key key.
	 * @param string $content content.
	 * @return void
	 */
	private static function store_in_cache( $key, $content ) {
		if ( ! function_exists( 'wp_filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		global $wp_filesystem;
		WP_Filesystem();

		$dir = self::get_path();

		if ( ! $wp_filesystem->is_dir( $dir ) ) {
			$wp_filesystem->mkdir( $dir );
		}

		$path = trailingslashit( $dir ) . "{$key}.json";

		if ( ! $wp_filesystem->put_contents( $path, wp_json_encode( $content ), FS_CHMOD_FILE ) ) {
			Utils::log( 'Failed to write to the cache file: ' . $path );
		}
	}
}
