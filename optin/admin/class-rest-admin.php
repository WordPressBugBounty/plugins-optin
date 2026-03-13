<?php // phpcs:ignore

namespace OPTN\Admin;

use OPTN\Admin\Rest\RestAbTesting;
use OPTN\Admin\Rest\RestIntegration;
use OPTN\Includes\Analytics;
use OPTN\Includes\Db;
use OPTN\Includes\Settings;
use OPTN\Includes\Utils\Assets;
use OPTN\Includes\Utils\DisplayRules;
use OPTN\Includes\Utils\Utils;
use OPTN\Includes\Utils\Templates;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;

class RestAdmin {

	/**
	 * Database instance
	 *
	 * @var Db $db DB instance.
	 */
	private $db;


	public function __construct() {
		$this->db = Db::get_instance();
		$this->register_routes();
	}

	public function register_routes() {
		Analytics::get_instance()->register_admin_routes();
		new RestIntegration();
		new RestAbTesting();

		register_rest_route(
			'optn/v1',
			'/optin/(?P<id>\d+)/duplicate',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_duplicate_optin' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/recipes/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_activate_recipe' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'optn/v1',
			'/get-post',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_get_post' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/save-post',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_save_post' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/delete-post',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'handle_delete_post' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/restore-post',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_restore_post' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/reset-post',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_reset_post' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/get-posts',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_get_posts' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/get-leads',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_get_leads' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/delete-leads',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'handle_delete_leads' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/get-products',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_get_products' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/get-archives',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_get_archive_pages' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/get-rule-options',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_get_rule_options' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/get-rule-values',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_get_rule_values' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/get-templates',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_get_templates' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/presets',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_get_presets' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/presets/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_get_preset' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/settings',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_get_settings' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/settings',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_update_settings' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/download-assets',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_download_assets' ),
				'permission_callback' => array( $this, 'permission_callback' ),
				'args'                => array(
					'image_url' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return filter_var( $param, FILTER_VALIDATE_URL );
						},
					),
				),
			)
		);

		register_rest_route(
			'optn/v1',
			'/shortcode-content',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'shortcode_content' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/assets/images',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_get_image_assets' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/assets/videos',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_get_video_assets' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/export/leads',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_export_leads' ),
				'permission_callback' => array( $this, 'permission_callback' ),
				// 'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Export leads
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_export_leads() {
		$data = ExportHelper::export_leads();
		return rest_ensure_response( $data );
	}

	/**
	 * Get Stock Images.
	 *
	 * @param WP_REST_Request $req request.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_get_image_assets( $req ) {
		$s     = isset( $req['s'] ) ? sanitize_text_field( $req['s'] ) : '';
		$page  = isset( $req['page'] ) ? intval( $req['page'] ) : 0;
		$limit = isset( $req['limit'] ) ? intval( $req['limit'] ) : 10;
		$src   = isset( $req['src'] ) ? sanitize_text_field( $req['src'] ) : 'stock';

		if ( 'stock' === $src && empty( $s ) ) {
			return rest_ensure_response( new WP_REST_Response( array(), 400 ) );
		}

		$res = Assets::get_images( $s, $page, $limit, $src );

		return rest_ensure_response( $res );
	}

	/**
	 * Get Stock Videos.
	 *
	 * @param WP_REST_Request $req request.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_get_video_assets( $req ) {
		$s     = isset( $req['s'] ) ? sanitize_text_field( $req['s'] ) : '';
		$page  = isset( $req['page'] ) ? intval( $req['page'] ) : 0;
		$limit = isset( $req['limit'] ) ? intval( $req['limit'] ) : 10;

		if ( empty( $s ) ) {
			return rest_ensure_response( new WP_REST_Response( array(), 400 ) );
		}

		$res = Assets::get_videos( $s, $page, $limit );

		return rest_ensure_response( $res );
	}

	/**
	 * Get settings
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_get_settings() {
		return rest_ensure_response( new WP_HTTP_Response( Settings::get_global_settings() ) );
	}

	/**
	 * Get settings
	 *
	 * @param WP_REST_Request $req request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_update_settings( WP_REST_Request $req ) {

		$settings = isset( $req['settings'] ) ? $req['settings'] : null;

		if ( ! is_array( $settings ) ) {
			return rest_ensure_response( new WP_Error( 'optin_invalid_request', __( 'Settings is required', 'optin' ), array( 'status' => 400 ) ) );
		}

		// Validate and update settings.
		Settings::update_settings( $settings );

		return rest_ensure_response( new WP_REST_Response( array(), 201 ) );
	}

	/**
	 * Download assets from our hosted server
	 *
	 * @param WP_REST_Request $req Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_download_assets( WP_REST_Request $req ) {
		$image_url = esc_url_raw( $req->get_param( 'image_url' ) );

		$def_value = array(
			'id'  => null,
			'url' => '',
		);

		$prefixed_filename = Assets::get_image_filename( $image_url );

		if ( empty( $prefixed_filename ) ) {
			return rest_ensure_response( $def_value );
		}

		global $wpdb;
		$query = $wpdb->prepare(
			"SELECT ID
			FROM {$wpdb->posts}
			WHERE post_type = 'attachment'
			AND post_title = %s
			LIMIT 1",
			sanitize_file_name( pathinfo( $prefixed_filename, PATHINFO_FILENAME ) )
		);

		$existing_attachment_id = $wpdb->get_var( $query ); // phpcs:ignore

		if ( $existing_attachment_id ) {
			$existing_image_url = wp_get_attachment_url( $existing_attachment_id );
			return rest_ensure_response(
				array(
					'id'  => $existing_attachment_id,
					'url' => $existing_image_url,
				)
			);
		}

		// Download and Save image.

		// If image is from Unsplash, then we can't directly download it.
		// We must use our proxy.
		if ( str_starts_with( $image_url, 'https://api.unsplash.com/photos/' ) ) {
			$image_url = Assets::get_image( $image_url );

			// Allow downloading from localhost.
			if ( Utils::is_debug_mode() ) {
				add_filter( 'http_request_host_is_external', '__return_true' );
			}

			if ( empty( $image_url ) ) {
				return rest_ensure_response( $def_value );
			}
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp_attachment_id = media_sideload_image( $image_url, 0, $prefixed_filename, 'id' );

		if ( is_wp_error( $tmp_attachment_id ) ) {
			return rest_ensure_response( $def_value );
		}

		$attachment_post             = get_post( $tmp_attachment_id );
		$attachment_post->post_title = sanitize_file_name( pathinfo( $prefixed_filename, PATHINFO_FILENAME ) );
		wp_update_post( $attachment_post );

		$image_url = wp_get_attachment_url( $tmp_attachment_id );

		return rest_ensure_response(
			array(
				'id'  => $tmp_attachment_id,
				'url' => $image_url,
			)
		);
	}

	public function handle_get_post( WP_REST_Request $req ) {
		$id = isset( $req['id'] ) ? intval( $req['id'] ) : null;

		$res = $this->db->get_saved_post( $id );

		return rest_ensure_response( $res );
	}

	public function handle_save_post( WP_REST_Request $req ) {
		$req_type = isset( $req['req_type'] ) ? sanitize_text_field( $req['req_type'] ) : null;

		$title       = isset( $req['title'] ) ? sanitize_text_field( $req['title'] ) : null;
		$data   = isset( $req['data'] ) && Utils::is_valid_json( $req['data'] ) ? $req['data'] : null; // phpcs:ignore
		$type        = isset( $req['type'] ) ? sanitize_text_field( $req['type'] ) : null;
		$id          = isset( $req['id'] ) ? intval( $req['id'] ) : null;
		$template_id = isset( $req['templateId'] ) ? intval( $req['templateId'] ) : null;
		$status      = isset( $req['status'] ) ? ( sanitize_text_field( $req['status'] ) === 'publish' || true === $req['status'] || 1 === $req['status'] ? true : false ) : false;

		$res = null;
		if ( $req_type === 'create' ) {
			$res = $this->db->create_new_post( $type, $id, $title, $template_id );
		} elseif ( $req_type === 'get' ) {
			$res = $this->db->get_saved_post( $id );
		} elseif ( $req_type === 'update' ) {
			$res = $this->db->update_saved_post( $id, $title, $status, $data, $type );
		}

		if ( isset( $res['error'] ) ) {
			return rest_ensure_response(
				new WP_REST_Response( array( 'error' => $res['error'] ), 400 )
			);
		}

		return rest_ensure_response( $res );
	}

	public function handle_get_rule_values( WP_REST_Request $req ) {
		$type    = isset( $req['type'] ) ? sanitize_text_field( $req['type'] ) : null;
		$search  = isset( $req['search'] ) ? sanitize_text_field( $req['search'] ) : '';
		$exclude = isset( $req['exclude'] ) && is_array( $req['exclude'] ) ? array_map( 'absint', $req['exclude'] ) : array();

		$res = DisplayRules::get_values( $type, $search, $exclude );

		return rest_ensure_response( $res );
	}

	public function handle_get_rule_options( WP_REST_Request $req ) {
		$type = isset( $req['type'] ) ? sanitize_text_field( $req['type'] ) : null;

		$res = DisplayRules::get_options( $type );

		return rest_ensure_response( $res );
	}

	public function handle_get_posts( WP_REST_Request $req ) {
		// phpcs:disable Generic.Formatting.MultipleStatementAlignment.NotSameWarning
		$status         = isset( $req['status'] ) ? sanitize_text_field( $req['status'] ) : 'all';
		$limit          = isset( $req['limit'] ) ? intval( $req['limit'] ) : 12;
		$page           = isset( $req['page'] ) ? ( intval( $req['page'] ) > 0 ? intval( $req['page'] ) : 1 ) : 1;
		$search         = isset( $req['search'] ) ? sanitize_text_field( $req['search'] ) : '';
		$filter         = isset( $req['filter'] ) && is_array( $req['filter'] ) ? $req['filter'] : array();
		$sort_by        = isset( $req['sortBy'] ) ? sanitize_key( $req['sortBy'] ) : 'id';
		$sort_order_raw = isset( $req['sortOrder'] ) ? strtolower( sanitize_text_field( $req['sortOrder'] ) ) : 'desc';
		$sort_order     = 'asc' === $sort_order_raw ? 'ASC' : 'DESC';
		// phpcs:enable Generic.Formatting.MultipleStatementAlignment.NotSameWarning

		$sort = array(
			'by'    => $sort_by,
			'order' => $sort_order,
		);

		return rest_ensure_response( $this->db->get_posts( $status, $limit, $page, $search, $filter, $sort ) );
	}

	public function handle_delete_post( WP_REST_Request $req ) {

		// Validation/Sanitization
		$ids = isset( $req['ids'] ) ? $req['ids'] : null;
		if ( ! is_array( $ids ) ) {
			return rest_ensure_response( new WP_Error( 'optin_invalid_request', __( 'Post ID(s) is required', 'optin' ), array( 'status' => 400 ) ) );
		}
		$ids = array_map( 'absint', $ids );

		// Validation/Sanitization
		$type = isset( $req['type'] ) ? sanitize_text_field( $req['type'] ) : null;
		if ( $type !== 'soft' && $type !== 'hard' ) {
			return rest_ensure_response( new WP_Error( 'optin_invalid_request', __( 'Invalid Delete Type', 'optin' ), array( 'status' => 400 ) ) );
		}

		$res = $this->db->delete_post( $ids, $type );

		return rest_ensure_response( $res );
	}


	public function handle_restore_post( WP_REST_Request $req ) {
		// Validation/Sanitization.
		$ids = isset( $req['ids'] ) ? $req['ids'] : null;
		if ( ! is_array( $ids ) ) {
			return rest_ensure_response( new WP_Error( 'optin_invalid_request', __( 'Post ID(s) is required', 'optin' ), array( 'status' => 400 ) ) );
		}
		$ids = array_map( 'absint', $ids );

		$res = $this->db->restore_post( $ids );

		return rest_ensure_response( $res );
	}


	/**
	 * Resets a post stats
	 *
	 * @param WP_REST_Request $req request.
	 * @return WP_REST_Response
	 */
	public function handle_reset_post( WP_REST_Request $req ) {
		$ids = isset( $req['ids'] ) ? $req['ids'] : null;
		if ( ! is_array( $ids ) ) {
			return rest_ensure_response( new WP_Error( 'optin_invalid_request', __( 'Post ID(s) is required', 'optin' ), array( 'status' => 400 ) ) );
		}
		$ids = array_map( 'absint', $ids );

		$res = $this->db->reset_post( $ids );

		return rest_ensure_response( new WP_REST_Response( array( 'success' => $res ), 200 ) );
	}

	public function handle_get_leads( WP_REST_Request $req ) {
		// $status = isset( $req['status'] ) ? sanitize_text_field( $req['status'] ) : 'all';
		$limit = isset( $req['limit'] ) ? intval( $req['limit'] ) : 12;
		$page  = isset( $req['page'] ) ? ( intval( $req['page'] ) > 0 ? intval( $req['page'] ) : 1 ) : 1;
		// $search = isset( $req['search'] ) ? sanitize_text_field( $req['search'] ) : '';

		return rest_ensure_response( $this->db->get_leads( $limit, $page ) );
	}

	public function handle_delete_leads( WP_REST_Request $req ) {

		// Validation/Sanitization
		$ids = isset( $req['ids'] ) ? $req['ids'] : null;
		if ( ! is_array( $ids ) ) {
			return rest_ensure_response( new WP_Error( 'optin_invalid_request', __( 'Lead ID(s) is required', 'optin' ), array( 'status' => 400 ) ) );
		}
		$ids = array_map( 'absint', $ids );

		$res = $this->db->delete_leads( $ids );

		if ( isset( $res['error'] ) ) {
			return rest_ensure_response( new WP_REST_Response( 'error', $res['error'] ) );
		}

		return rest_ensure_response( new WP_REST_Response( 'success', 200 ) );
	}


	public function handle_get_products() {
		$active   = false;
		$products = array();

		if ( function_exists( 'wc_get_products' ) ) {
			$active = true;

			$products = wc_get_products(
				array(
					'limit'  => -1,
					'status' => 'publish',
				)
			);

			$products = array_map(
				function ( $product ) {
					return array(
						'value' => 'P' . $product->get_id(),
						'label' => '[WOO] ' . $product->get_name() . ' | ID: ' . $product->get_id(),
					);
				},
				$products
			);

		}

		$edd_products = get_posts(
			array(
				'post_type'      => 'download',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			)
		);

		$edd_products = array_map(
			function ( $product ) {
				return array(
					'value' => 'P' . $product->ID,
					'label' => '[EDD] ' . $product->post_title . ' | ID: ' . $product->ID,
				);
			},
			$edd_products
		);

		$products = array_merge( $products, $edd_products );

		return new \WP_REST_Response(
			array(
				'products' => $products,
				'active'   => $active,
			),
			200
		);
	}

	public function handle_get_archive_pages() {

		$archive_data = array();

		$post_type = get_post_types( array( 'public' => true ), 'objects' );

		foreach ( $post_type as $key => $type ) {

			if ( 'conversion_popup' === $type->name ) {
				continue;
			}

			// Taxonomy.
			$taxonomy = get_object_taxonomies( $type->name, 'objects' );
			if ( ! empty( $taxonomy ) ) {
				foreach ( $taxonomy as $tax ) {
					$args         = array(
						'taxonomy'   => $tax->name,
						'fields'     => 'all',
						'hide_empty' => false,
					);
					$post_results = get_terms( $args );
					if ( ! empty( $post_results ) ) {
						foreach ( $post_results as $key => $val ) {
							$archive_data[] = array(
								'value' => 'A' . $val->term_id,
								'label' => '[' . $type->label . '] ' . $val->name . ' | Term ID: ' . $val->term_id,
							);
						}
					}
				}
			}
		}

		return new \WP_REST_Response(
			array(
				'archive_pages' => $archive_data,
			),
			200
		);
	}

	/**
	 * Duplicate post by id
	 *
	 * @param WP_REST_Request $req request object.
	 * @return WP_REST_Response
	 */
	public function handle_duplicate_optin( WP_REST_Request $req ) {
		$id = isset( $req['id'] ) ? intval( $req['id'] ) : null;

		if ( empty( $id ) ) {
			return new WP_REST_Response( 'Invalid ID', 400 );
		}

		$res = $this->db->copy_post( $id );

		$success = ! is_null( $res['id'] );

		return rest_ensure_response(
			array(
				'success' => $success,
				'data'    => $res,
			)
		);
	}


	public function handle_get_templates() {
		return rest_ensure_response( Templates::get_templates() );
	}

	/**
	 * Get presets
	 *
	 * @return WP_REST_Response
	 */
	public function handle_get_presets() {
		return rest_ensure_response( Templates::get_presets() );
	}

	/**
	 * Get preset by ID
	 *
	 * @param WP_REST_Request $req request object.
	 * @return WP_REST_Response
	 */
	public function handle_get_preset( WP_REST_Request $req ) {
		$id = isset( $req['id'] ) ? intval( $req['id'] ) : null;

		if ( empty( $id ) ) {
			return new WP_REST_Response( null, 400 );
		}

		return rest_ensure_response( Templates::fetch_single_preset( $id ) );
	}

		/**
		 * Get preset by ID
		 *
		 * @param WP_REST_Request $req request object.
		 * @return WP_REST_Response
		 */
	public function handle_activate_recipe( WP_REST_Request $req ) {
		$id = isset( $req['id'] ) ? intval( $req['id'] ) : null;

		$args = array();

		if ( isset( $req['status'] ) ) {
			$args['status'] = boolval( $req['status'] );
		}

		if ( isset( $req['disable_others'] ) ) {
			$args['disable_others'] = boolval( $req['disable_others'] );
		}

		return rest_ensure_response( $this->db->activate_recipe( $id, $args ) );
	}

	/**
	 * Render actual content from shortcode
	 *
	 * @param WP_REST_Request $req request object.
	 * @return array
	 */
	public function shortcode_content( WP_REST_Request $req ) {
		$params    = $req->get_params();
		$shortcode = sanitize_text_field( $params['shortCode'] );
		$code_data = $this->shortcode_support( $shortcode );
		$res       = '';

		$content = do_shortcode( wp_unslash( $shortcode ) );

		if ( ( $code_data['id'] || $code_data['is_front'] ) && ! empty( $content ) ) {
			$post = get_post( $code_data['id'] );

			setup_postdata( $post );
			ob_start();
				setup_postdata( $post );
				wp_head();
			$header = ob_get_clean();
			ob_start();
				wp_footer();
			$footer = ob_get_clean();
			if ( preg_match_all( '/<script\b[^>]*>(.*?)<\/script>/is', $footer, $matches ) ) {
				$footer = implode( "\n", $matches[0] );
			}

			wp_reset_postdata();

			$styles = '
				<style>
					body {
						overflow: hidden !important;
						background-color: transparent !important;
					}
				</style>
			';

			$res  = '<!DOCTYPE html><html lang="en"><head>';
			$res .= $header;
			$res .= $styles . '</head>';
			$res .= '<body>' . $content . '</body>';
			$res .= '<footer>' . $footer . '</footer>';
			$res .= '</html>';
		}

		return array(
			'data' => $res,
		);
	}

	/**
	 * Shortcode support for get post id or home page id
	 *
	 * @param string $shortcode shortcode.
	 * @return array
	 */
	public function shortcode_support( $shortcode ) {
		$pattern  = get_shortcode_regex();
		$id       = '';
		$is_front = false;
		if ( preg_match( '/' . $pattern . '/s', $shortcode, $matches ) ) {
			$attributes = shortcode_parse_atts( $matches[3] );
			if ( isset( $attributes['id'] ) ) {
				$id = $attributes['id'];
			} else {
				if ( 'page' === get_option( 'show_on_front' ) ) {
					$id = get_option( 'page_on_front' );
				} else {
					$id = get_option( 'page_for_posts' );
				}
				$is_front = true;
			}
		}

		// Support third part plugin if any manual condition.
		if ( ( $id || $is_front ) && strpos( $shortcode, 'wpforms' ) !== false ) {
			add_filter( 'wpforms_frontend_assets_header_force_load', '__return_true' );
		}
		return array(
			'id'       => $id,
			'is_front' => $is_front,
		);
	}


	/**
	 * Permission callback
	 *
	 * @return boolean
	 */
	public function permission_callback() {
		return current_user_can( OPTN_MIN_CAPABILITY );
	}
}
