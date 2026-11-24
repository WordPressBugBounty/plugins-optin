<?php
/**
 * Registers and handles administrative REST endpoints for opt-ins and their data.
 *
 * The handlers validate request values, delegate persistence and lookup work to the
 * database or supporting services, and format results for the WordPress REST API.
 *
 * @package Optin
 */

namespace OPTN\Admin;

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

/**
 * Provides the authenticated REST API used by the opt-in administration screens.
 *
 * Owns endpoint registration and request handling for saved opt-ins, leads, display
 * rules, templates, settings, products, archive terms, and rendered shortcode previews.
 */
class RestAdmin {

	/**
	 * Database instance
	 *
	 * @var Db $db DB instance.
	 */
	private $db;


	/**
	 * Load the database service and register this class's administration routes.
	 *
	 * Route registration happens when the REST admin controller is constructed, so
	 * WordPress can dispatch matching requests to the handlers below.
	 */
	public function __construct() {
		$this->db = Db::get_instance();
		$this->register_routes();
	}

	/**
	 * Register the authenticated REST endpoints provided by the admin controller.
	 *
	 * Also initializes analytics and integration routes, then binds each opt-in admin
	 * route to its handler and shared capability check.
	 *
	 * @return void
	 */
	public function register_routes() {
		Analytics::get_instance()->register_admin_routes();
		new RestIntegration();

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
				'permission_callback' => array( $this, 'permission_callback' ),
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
				'permission_callback' => array( $this, 'settings_permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/settings',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_update_settings' ),
				'permission_callback' => array( $this, 'settings_permission_callback' ),
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
			'/export/leads',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_export_leads' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);
	}

	/**
	 * Build the exported lead dataset for the current administration request.
	 *
	 * Delegates formatting and file-data preparation to ExportHelper, then wraps the
	 * resulting payload in the standard REST response format.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_export_leads() {
		$data = ExportHelper::export_leads();
		return rest_ensure_response( $data );
	}

	/**
	 * Return stock image choices for a search query and requested page.
	 *
	 * Sanitizes search and source values, applies pagination defaults, requires a
	 * non-empty query for the stock source, and allows extensions to supply results.
	 *
	 * @param WP_REST_Request $req Request containing optional `s`, `page`, `limit`, and `src` values.
	 * @return WP_REST_Response Image choices or a validation response.
	 */
	public function handle_get_image_assets( $req ) {
		$s     = isset( $req['s'] ) ? sanitize_text_field( $req['s'] ) : '';
		$page  = isset( $req['page'] ) ? intval( $req['page'] ) : 0;
		$limit = isset( $req['limit'] ) ? intval( $req['limit'] ) : 10;
		$src   = isset( $req['src'] ) ? sanitize_text_field( $req['src'] ) : 'stock';

		if ( 'stock' === $src && empty( $s ) ) {
			return rest_ensure_response( new WP_REST_Response( array(), 400 ) );
		}

		$res = apply_filters( 'optn_image_assets', null, $s, $page, $limit, $src );
		if ( $res === null ) {
			$res = Assets::get_images( $s, $page, $limit );
		}

		return rest_ensure_response( $res );
	}

	/**
	 * Return the current global plugin settings for the administration interface.
	 *
	 * Reads settings through the Settings service and wraps the configuration in a
	 * WordPress HTTP response for REST serialization.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_get_settings() {
		return rest_ensure_response( new WP_HTTP_Response( Settings::get_global_settings() ) );
	}

	/**
	 * Validate and persist global plugin settings submitted by an administrator.
	 *
	 * Requires an array in the `settings` request field and delegates sanitization and
	 * persistence to the Settings service before returning a created response.
	 *
	 * @param WP_REST_Request $req Request containing the settings array to update.
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
	 * Retrieve one saved opt-in record by its database ID.
	 *
	 * Reads the optional ID from the request, asks the database service for that saved
	 * record, and converts the result to a WordPress REST response.
	 *
	 * @param WP_REST_Request $req Request containing the saved opt-in `id`.
	 * @return WP_REST_Response REST response containing the saved record or lookup result.
	 */
	public function handle_get_post( WP_REST_Request $req ) {
		$id = isset( $req['id'] ) ? intval( $req['id'] ) : null;

		$res = $this->db->get_saved_post( $id );

		return rest_ensure_response( $res );
	}

	/**
	 * Create, retrieve, or update a saved opt-in based on the requested operation.
	 *
	 * Sanitizes scalar fields, accepts serialized data only when it is valid JSON, and
	 * dispatches `create`, `get`, or `update` operations to the database service. Database
	 * errors pass through; operation errors are returned as a bad-request REST response.
	 *
	 * @param WP_REST_Request $req Request containing the operation and opt-in fields.
	 * @return WP_REST_Response|WP_Error REST result, or a database error.
	 */
	public function handle_save_post( WP_REST_Request $req ) {
		$req_type = isset( $req['req_type'] ) ? sanitize_text_field( $req['req_type'] ) : null;

		$title       = isset( $req['title'] ) ? sanitize_text_field( $req['title'] ) : null;
		$data        = isset( $req['data'] ) && Utils::is_valid_json( $req['data'] ) ? $req['data'] : null; // phpcs:ignore
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

		if ( is_wp_error( $res ) ) {
			return $res; }
		if ( 'create' === $req_type && ! empty( $res['id'] ) ) {
			// Imported templates and copies need the same support status as reopened optins.
			$saved                       = $this->db->get_saved_post( $res['id'] );
			$res['unsupported_features'] = $saved['unsupported_features'] ?? array();
		}
		if ( isset( $res['error'] ) ) {
			return rest_ensure_response(
				new WP_REST_Response( array( 'error' => $res['error'] ), 400 )
			);
		}

			return rest_ensure_response( $res );
	}

	/**
	 * Return display-rule choices filtered by rule type, search term, and exclusions.
	 *
	 * Sanitizes the type and search text, converts excluded identifiers to positive
	 * integers, and delegates option lookup to the display-rule service.
	 *
	 * @param WP_REST_Request $req Request containing optional `type`, `search`, and `exclude` values.
	 * @return WP_REST_Response REST response containing matching rule values.
	 */
	public function handle_get_rule_values( WP_REST_Request $req ) {
		$type    = isset( $req['type'] ) ? sanitize_text_field( $req['type'] ) : null;
		$search  = isset( $req['search'] ) ? sanitize_text_field( $req['search'] ) : '';
		$exclude = isset( $req['exclude'] ) && is_array( $req['exclude'] ) ? array_map( 'absint', $req['exclude'] ) : array();

		$res = DisplayRules::get_values( $type, $search, $exclude );

		return rest_ensure_response( $res );
	}

	/**
	 * Return the available display-rule options for a requested rule type.
	 *
	 * Sanitizes the optional type value and asks the display-rule service to build the
	 * corresponding option list for the administration interface.
	 *
	 * @param WP_REST_Request $req Request containing the optional `type` value.
	 * @return WP_REST_Response REST response containing the rule options.
	 */
	public function handle_get_rule_options( WP_REST_Request $req ) {
		$type = isset( $req['type'] ) ? sanitize_text_field( $req['type'] ) : null;

		$res = DisplayRules::get_options( $type );

		return rest_ensure_response( $res );
	}

	/**
	 * Return a filtered and sorted page of saved opt-ins.
	 *
	 * Reads status, pagination, search, filters, and sort preferences from the request,
	 * normalizes those values, and returns the database query result as REST data.
	 *
	 * @param WP_REST_Request $req Request containing optional list and sorting criteria.
	 * @return WP_REST_Response REST response containing the requested opt-in page.
	 */
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

	/**
	 * Soft-delete or permanently delete the opt-ins identified in a request.
	 *
	 * Requires an array of IDs and an explicit `soft` or `hard` deletion mode, converts
	 * IDs to positive integers, and returns a validation error or database result.
	 *
	 * @param WP_REST_Request $req Request containing `ids` and deletion `type`.
	 * @return WP_REST_Response REST response containing the deletion result or validation error.
	 */
	public function handle_delete_post( WP_REST_Request $req ) {

		// Validate the submitted ID list before converting its entries.
		$ids = isset( $req['ids'] ) ? $req['ids'] : null;
		if ( ! is_array( $ids ) ) {
			return rest_ensure_response( new WP_Error( 'optin_invalid_request', __( 'Post ID(s) is required', 'optin' ), array( 'status' => 400 ) ) );
		}
		$ids = array_map( 'absint', $ids );

		// Accept only the supported soft or hard deletion modes.
		$type = isset( $req['type'] ) ? sanitize_text_field( $req['type'] ) : null;
		if ( $type !== 'soft' && $type !== 'hard' ) {
			return rest_ensure_response( new WP_Error( 'optin_invalid_request', __( 'Invalid Delete Type', 'optin' ), array( 'status' => 400 ) ) );
		}

		$res = $this->db->delete_post( $ids, $type );

		return rest_ensure_response( $res );
	}


	/**
	 * Restore one or more soft-deleted opt-ins.
	 *
	 * Requires an array of opt-in IDs, converts each ID to a positive integer, and
	 * delegates restoration to the database service.
	 *
	 * @param WP_REST_Request $req Request containing the opt-in `ids` to restore.
	 * @return WP_REST_Response REST response containing the restoration result or validation error.
	 */
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
	 * Reset analytics statistics for one or more saved opt-ins.
	 *
	 * Requires an array of opt-in IDs, normalizes each to a positive integer, and returns
	 * the database reset result in a status response.
	 *
	 * @param WP_REST_Request $req Request containing the opt-in `ids` to reset.
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

	/**
	 * Return a paginated list of captured leads.
	 *
	 * Reads the requested page size and page number, normalizes invalid page numbers to
	 * the first page, and returns the database lead query result as REST data.
	 *
	 * @param WP_REST_Request $req Request containing optional `limit` and `page` values.
	 * @return WP_REST_Response REST response containing lead records and paging data.
	 */
	public function handle_get_leads( WP_REST_Request $req ) {
		// $status = isset( $req['status'] ) ? sanitize_text_field( $req['status'] ) : 'all';
		$limit = isset( $req['limit'] ) ? intval( $req['limit'] ) : 12;
		$page  = isset( $req['page'] ) ? ( intval( $req['page'] ) > 0 ? intval( $req['page'] ) : 1 ) : 1;
		// $search = isset( $req['search'] ) ? sanitize_text_field( $req['search'] ) : '';

		return rest_ensure_response( $this->db->get_leads( $limit, $page ) );
	}

	/**
	 * Delete captured leads selected by their database IDs.
	 *
	 * Validates that the request contains an ID list, normalizes each ID to a positive
	 * integer, and returns either the database error or a success response.
	 *
	 * @param WP_REST_Request $req Request containing lead `ids` to delete.
	 * @return WP_REST_Response REST response describing the deletion result.
	 */
	public function handle_delete_leads( WP_REST_Request $req ) {

		// Validate the submitted lead ID list before converting its entries.
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


	/**
	 * Return published product choices from WooCommerce and Easy Digital Downloads.
	 *
	 * Includes WooCommerce products when its product API is available, always queries
	 * published EDD downloads, and reports whether WooCommerce was active in the result.
	 *
	 * @return WP_REST_Response REST response containing product choices and WooCommerce state.
	 */
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

	/**
	 * Return public taxonomy terms as archive-target choices for display rules.
	 *
	 * Iterates public post types and their taxonomies, excludes the plugin's internal
	 * conversion-popup type, and formats each term as a labeled archive choice.
	 *
	 * @return WP_REST_Response REST response containing archive page choices.
	 */
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
	 * Create a copy of the saved opt-in identified by the route ID.
	 *
	 * Rejects a missing or invalid ID, delegates copying to the database service, and
	 * reports whether the new record was created along with the copy result.
	 *
	 * @param WP_REST_Request $req REST request containing the route's opt-in `id`.
	 * @return WP_REST_Response
	 */
	public function handle_duplicate_optin( WP_REST_Request $req ) {
		$id = isset( $req['id'] ) ? intval( $req['id'] ) : null;

		if ( empty( $id ) ) {
			return new WP_REST_Response( 'Invalid ID', 400 );
		}

		$res = $this->db->copy_post( $id );
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$success = ! is_null( $res['id'] );

		return rest_ensure_response(
			array(
				'success' => $success,
				'data'    => $res,
			)
		);
	}


	/**
	 * Return the available opt-in templates for the administration interface.
	 *
	 * Delegates template discovery to the template service and wraps its result as REST data.
	 *
	 * @return WP_REST_Response REST response containing available templates.
	 */
	public function handle_get_templates() {
		return rest_ensure_response( Templates::get_templates() );
	}

	/**
	 * Return the available campaign presets for the administration interface.
	 *
	 * Delegates preset retrieval to the template service and wraps the catalog as REST data.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_get_presets() {
		return rest_ensure_response( Templates::get_presets() );
	}

	/**
	 * Return one preset's saved configuration by its route ID.
	 *
	 * Converts the ID to an integer, rejects an empty identifier, and asks the template
	 * service to fetch the matching preset.
	 *
	 * @param WP_REST_Request $req REST request containing the preset `id`.
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
	 * Apply a recipe to an opt-in with optional status and disable-others settings.
	 *
	 * Reads the route ID and optional activation flags, then delegates recipe activation
	 * and related campaign updates to the database service.
	 *
	 * @param WP_REST_Request $req Request containing recipe ID and optional activation flags.
	 * @return WP_REST_Response REST response containing the activation result.
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
	 * Render shortcode content with page header and footer assets for a preview.
	 *
	 * Executes the submitted shortcode, resolves its associated page, captures head and
	 * footer output, and returns a complete HTML document when that context exists.
	 *
	 * @param WP_REST_Request $req Request containing the shortcode string to render.
	 * @return array{data: string} Rendered preview document or an empty string without page context.
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

			$res  = '<!DOCTYPE html><html lang="en"><head>';
			$res .= $header;
			$res .= '</head>';
			$res .= '<body style="overflow: hidden !important; background-color: transparent !important;">' . $content . '</body>';
			$res .= '<footer>' . $footer . '</footer>';
			$res .= '</html>';
		}

		return array(
			'data' => $res,
		);
	}

	/**
	 * Resolve the page context associated with a shortcode for preview rendering.
	 *
	 * Extracts an explicit shortcode ID when present; otherwise determines the configured
	 * front page or posts page and enables WPForms header asset loading when required.
	 *
	 * @param string $shortcode Shortcode markup whose page context should be resolved.
	 * @return array{id: int|string|false, is_front: bool} Resolved page ID and front-page flag.
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

	/**
	 * Require site administration authority for global settings.
	 *
	 * @return bool Whether the current user may manage site-wide settings.
	 */
	public function settings_permission_callback() {
		return current_user_can( 'manage_options' );
	}
}
