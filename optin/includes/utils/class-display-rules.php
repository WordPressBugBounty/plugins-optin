<?php // phpcs:ignore

namespace OPTN\Includes\Utils;

use OPTN\Includes\Utils\Utils;

/**
 * Display Rules Class
 */
class DisplayRules {

	/**
	 * Default post types
	 */
	const DEFAULT_POST_TYPES = array(
		'post',
		'page',
		'attachment',
		'nav_menu_item',
		'wp_template',
		'wp_template_part',
	);

	/**
	 * Is valid display rule
	 *
	 * @param object $rule rule.
	 * @return boolean
	 */
	public static function is_valid_display_rule( $rule ) {
		$type = $rule->type;

		if ( str_starts_with( $type, 'woo' ) ) {
			return self::is_valid_woo_display_rule( $rule );
		}

		if ( str_starts_with( $type, 'edd' ) ) {
			return self::is_valid_edd_display_rule( $rule );
		}

		return self::is_valid_general_display_rule( $rule );
	}

	/**
	 * Validate WooCommerce Display Rules.
	 *
	 * @param object $rule rule.
	 * @return boolean
	 */
	private static function is_valid_woo_display_rule( $rule ) {

		if ( ! function_exists( 'WC' ) ) {
			return false;
		}

		$type   = $rule->type;
		$values = $rule->values;
		$cond   = $rule->cond;

		// Woo Cart Page.
		if ( 'woo_cart_nocond' === $type ) {
			return function_exists( 'is_cart' ) && is_cart();
		}

		// Woo Thank You Page.
		if ( 'woo_thank_nocond' === $type ) {
			return is_order_received_page();
		}

		// Woo Product Page.
		if ( 'woo_single_product' === $type ) {
			if ( is_product() ) {
				$product = wc_get_product();

				if ( ! empty( $product ) ) {
					return Utils::check_values(
						strval( $product->get_id() ),
						$values,
						$cond
					);
				}
			} elseif ( in_array( $cond, array( 'not_any', 'not_all' ), true ) ) {
				return true;
			}
		}

		// Woo Product Categories.
		if ( 'woo_cats' === $type ) {
			if ( ! is_product() ) {
				return false;
			}

			$product = wc_get_product();

			if ( ! empty( $product ) ) {
				return Utils::check_values(
					$product->get_category_ids(),
					$values,
					$cond
				);
			}
		}

		// Woo Product Tags.
		if ( 'woo_tags' === $type ) {
			if ( ! is_product() ) {
				return false;
			}

			$product = wc_get_product();

			if ( ! empty( $product ) ) {
				return Utils::check_values(
					$product->get_tag_ids(),
					$values,
					$cond
				);
			}
		}

		// Woo Products in Cart.
		if ( 'woo_cart_products' === $type ) {
			if ( ! empty( WC()->cart ) ) {
				$products = array_values(
					array_map(
						function ( $cart_item ) {
							return strval( $cart_item['product_id'] );
						},
						WC()->cart->get_cart() ?? array()
					)
				);

				$args = array(
					'status'       => 'publish',
					'stock_status' => 'instock',
					'return'       => 'ids',
					'limit'        => -1,
				);

				$all_count = count( wc_get_products( $args ) ); // TODO @samin: Use count posts.

				return Utils::check_values(
					$products,
					$values,
					$cond,
					$all_count,
				);
			}
		}

		// Woo Number of products in Cart.
		if ( 'woo_cart_products_num' === $type ) {
			if ( ! empty( WC()->cart ) ) {
				$num_of_products = WC()->cart->get_cart_contents_count();
				return Utils::check_math(
					$num_of_products,
					$values,
					$cond,
				);
			}
		}

		// Woo Cart Total Value.
		if ( 'woo_cart_value$' === $type ) {
			if ( ! empty( WC()->cart ) ) {
				$total = WC()->cart->get_cart_contents_total();
				return Utils::check_math(
					$total,
					$values,
					$cond,
				);
			}
		}

		return false;
	}

	/**
	 * Validate EDD Display Rules.
	 *
	 * @param object $rule rule.
	 * @return boolean
	 */
	private static function is_valid_edd_display_rule( $rule ) {
		$type   = $rule->type;
		$values = $rule->values;
		$cond   = $rule->cond;

		// EDD Cart Page.
		if ( 'edd_cart_nocond' === $type ) {
			$edd_options = get_option( 'edd_settings', array() );
			return isset( $edd_options['purchase_page'] ) && get_the_ID() == $edd_options['purchase_page']; // phpcs:ignore
		}

		// EDD Thank You Page.
		if ( 'edd_thank_nocond' === $type ) {
			if ( function_exists( 'edd_is_success_page' ) ) {
				return edd_is_success_page();
			}
		}

		// EDD Product Page.
		if ( 'edd_single_product' === $type ) {
			if ( self::is_edd_product() ) {
				$product_id = get_the_ID();
				return Utils::check_values(
					$product_id,
					$values,
					$cond,
				);
			} elseif ( in_array( $cond, array( 'not_any', 'not_all' ), true ) ) {
				return true;
			}
		}

		// EDD Product Categories.
		if ( 'edd_cats' === $type ) {
			if ( self::is_edd_product() ) {
				$product_id    = get_the_ID();
				$download_cats = get_the_terms( $product_id, 'download_category' ); // Meow >:3.

				if ( is_array( $download_cats ) ) {
					$product_cats = array_map(
						function ( $item ) {
							return $item->term_id;
						},
						$download_cats
					);

					return Utils::check_values(
						$product_cats,
						$values,
						$cond,
					);
				}
			}
		}

		if ( 'edd_tags' === $type ) {
			if ( self::is_edd_product() ) {
				$product_id    = get_the_ID();
				$download_tags = get_the_terms( $product_id, 'download_tag' );

				if ( is_array( $download_tags ) ) {
					$product_tags = array_map(
						function ( $item ) {
							return $item->term_id;
						},
						$download_tags
					);
				}

				return Utils::check_values(
					$product_tags,
					$values,
					$cond,
				);
			}
		}

		// EDD Products in Cart.
		if ( 'edd_cart_products' === $type ) {
			if ( function_exists( 'edd_get_cart_contents' ) ) {
				$products = array_map(
					function ( $cart_item ) {
						return strval( $cart_item['id'] );
					},
					edd_get_cart_contents()
				);

				$args = array(
					'post_type'      => 'download',
					'post_status'    => 'publish',
					'fields'         => 'ids',
					'posts_per_page' => -1,
				);

				$query = new \WP_Query( $args );

				$all_count = $query->found_posts; // TODO @samin: Use count posts.

				return Utils::check_values(
					$products,
					$values,
					$cond,
					$all_count,
				);
			}
		}

		// EDD Number of products in Cart.
		if ( 'edd_cart_products_num' === $type ) {
			if ( function_exists( 'edd_get_cart_quantity' ) ) {
				return Utils::check_math( edd_get_cart_quantity(), $values, $cond );
			}
		}

		// EDD Total Cart Value.
		if ( 'edd_cart_value$' === $type ) {
			if ( function_exists( 'edd_get_cart_total' ) ) {
				$total = edd_get_cart_total();
				return Utils::check_math( $total, $values, $cond );
			}
		}

		return false;
	}

	/**
	 * Check General Display Rules.
	 *
	 * @param object $rule rule.
	 * @return boolean
	 */
	private static function is_valid_general_display_rule( $rule ) {
		$type   = $rule->type;
		$values = $rule->values;
		$cond   = $rule->cond;

		// Entire Page.
		if ( str_starts_with( $type, 'entire' ) ) {
			return true;
		}

		// Front page.
		if ( str_starts_with( $type, 'front' ) ) {
			return is_front_page();
		}

		// URL Path.
		if ( 'url_path' === $type ) {
			return self::match_url_path( $values, $cond );
		}

		// Query params.
		if ( 'custom_param_url' === $type ) {
			$params = Utils::get_sanitized_query_parameters();
			return Utils::validate_query_params( $values, $params );
		}

		// Cookie.
		if ( 'cookie' === $type ) {
			return self::validate_cookie( $values );
		}

		// Post.
		if ( 'post_posts' === $type ||
						'page' === $type ||
						str_starts_with( $type, 'cpt_post:' )
		) {

			$post_id = strval( get_the_ID() );
			return Utils::check_values( $post_id, $values, $cond );
		}

		// Parent post.
		if ( 'parent_page' === $type ) {
			$parent_post_id = wp_get_post_parent_id( get_the_ID() );
			return Utils::check_values( $parent_post_id, $values, $cond );
		}

		// Taxonomy (with custom taxonomy).
		if (
			'post_cat' === $type ||
			'post_tags' === $type ||
			str_starts_with( $type, 'cpt_tax:' )
		) {
			$term_ids = Utils::get_terms_by_id( get_the_ID() );
			return Utils::check_values( $term_ids, $values, $cond );
		}

		// Post Types.
		if ( 'post_type' === $type ) {
			$post_type = get_post_type( get_the_ID() );
			return Utils::check_values( $post_type, $values, $cond );
		}

		// Author.
		if ( 'author' === $type ) {
			$author_id = strval( get_post_field( 'post_author', get_the_ID() ) );
			return Utils::check_values( $author_id, $values, $cond );
		}

		return false;
	}

	/**
	 * Validate cookie display rule.
	 *
	 * @param object $values cookie settings values.
	 * @return bool
	 */
	private static function validate_cookie( $values ) {
		$key_type     = $values->keyType; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$key          = sanitize_text_field( $values->keyValue ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$value_type   = $values->valueType; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$value        = sanitize_text_field( $values->valueValue ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$cookie_value = sanitize_text_field( wp_unslash( $_COOKIE[ $key ] ?? '' ) );

		switch ( $key_type ) {
			case 'exists':
				return isset( $_COOKIE[ $key ] );
			case 'not_exists':
				return ! isset( $_COOKIE[ $key ] );
		}

		switch ( $value_type ) {
			case 'empty':
				return empty( $cookie_value );
			case 'not_empty':
				return ! empty( $cookie_value );
			case 'equals':
				return $cookie_value == $value; // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
			case 'not_equals':
				return $cookie_value != $value; // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual
			case 'contains':
				return strpos( $cookie_value, $value ) !== false;
			case 'not_contains':
				return strpos( $cookie_value, $value ) === false;
		}

		return false;
	}

	/**
	 * Get display rules options for dropdown
	 *
	 * @return array
	 */
	public static function get_options() {
		$res = array(
			array(
				'isHeader' => true,
				'label'    => __( 'General', 'optin' ),
				'value'    => 'general_header',
			),
			array(
				'isChild'    => true,
				'value'      => 'entire_nocond',
				'label'      => __( 'Entire Site', 'optin' ),
				'condition'  => 'none',
				'valueField' => 'none',
			),
			array(
				'isChild'    => true,
				'value'      => 'front_nocond',
				'label'      => __( 'Home Page', 'optin' ),
				'condition'  => 'none',
				'valueField' => 'none',
			),
			array(
				'isChild'   => true,
				'value'     => 'page',
				'label'     => __( 'Page', 'optin' ),
				'condition' => 'single',
			),
			array(
				'isChild'   => true,
				'value'     => 'parent_page',
				'label'     => __( 'Parent Page', 'optin' ),
				'condition' => 'single',
			),
			array(
				'isChild'   => true,
				'value'     => 'author',
				'label'     => __( 'Author', 'optin' ),
				'condition' => 'single',
			),
			array(
				'isChild'   => true,
				'value'     => 'post_type',
				'label'     => __( 'Post Type', 'optin' ),
				'condition' => 'single',
			),
			array(
				'isChild'      => true,
				'value'        => 'cookie',
				'label'        => __( 'Cookie', 'optin' ),
				'pro'          => true,
				'Component'    => 'cookie',
				'defaultValue' => array(
					'keyType'    => 'equals',
					'keyValue'   => '',
					'valueType'  => 'equals',
					'valueValue' => '',
				),
			),
			array(
				'isChild'    => true,
				'value'      => 'url_path',
				'label'      => __( 'URL Path', 'optin' ),
				'pro'        => true,
				'condition'  => 'string',
				'valueField' => 'text',
				'props'      => array(
					'placeholder' => __( 'Ex', 'optin' ) . ': /products/electronics/televisions',
				),
			),
			array(
				'isChild'    => true,
				'value'      => 'custom_param_url',
				'label'      => __( 'URL Parameter', 'optin' ),
				'pro'        => true,
				'condition'  => 'contains',
				'valueField' => 'text',
				'props'      => array(
					'placeholder' => __( 'Ex', 'optin' ) . ': param1=value1&param2=value2',
				),
			),

			array(
				'isHeader' => true,
				'label'    => __( 'Post', 'optin' ),
				'value'    => 'post_header',
			),
			array(
				'isChild'   => true,
				'value'     => 'post_posts',
				'label'     => __( 'Posts', 'optin' ),
				'condition' => 'single',
			),
			array(
				'isChild' => true,
				'value'   => 'post_cat',
				'label'   => __( 'Post Category', 'optin' ),
			),
			array(
				'isChild' => true,
				'value'   => 'post_tags',
				'label'   => __( 'Post Tag', 'optin' ),
			),
		);

		$post_types = get_post_types(
			array(
				'public'   => true,
				'_builtin' => false,
			),
			'objects'
		);

		$exclude = array( 'product', 'download', 'attachment' );

		if ( ! empty( $post_types ) ) {
			foreach ( $post_types as $post_type ) {

				if ( in_array( $post_type->name, $exclude ) ) { // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
					continue;
				}

				$res[] = array(
					'isHeader' => true,
					'label'    => ucwords( $post_type->labels->singular_name ),
					'value'    => $post_type->label,
					'pro'      => true,
				);

				$res[] = array(
					'value'     => 'cpt_post:' . $post_type->name,
					'isChild'   => true,
					'label'     => $post_type->label,
					'pro'       => true,
					'condition' => 'single',
				);

				$taxes = get_object_taxonomies( $post_type->name, 'objects' );

				if ( ! empty( $taxes ) ) {
					foreach ( $taxes as $tax ) {
						$res[] = array(
							'value'   => 'cpt_tax:' . $post_type->name . '###' . $tax->name,
							'isChild' => true,
							'label'   => $tax->label,
							'pro'     => true,
						);
					}
				}
			}
		}

		array_push(
			$res,
			array(
				'isHeader' => true,
				'label'    => __( 'WooCommerce', 'optin' ),
				'value'    => 'woocom',
				'pro'      => true,
			),
			array(
				'value'     => 'woo_single_product',
				'label'     => __( 'Product Single Page', 'optin' ),
				'isChild'   => true,
				'pro'       => true,
				'condition' => 'single',
			),
			array(
				'value'   => 'woo_cats',
				'label'   => __( 'Product Single Page with Category', 'optin' ),
				'isChild' => true,
				'pro'     => true,
			),
			array(
				'value'   => 'woo_tags',
				'label'   => __( 'Product Single Page with Tag', 'optin' ),
				'isChild' => true,
				'pro'     => true,
			),
			array(
				'value'   => 'woo_cart_products',
				'label'   => __( 'Products in Cart', 'optin' ),
				'isChild' => true,
				'pro'     => true,
			),
			array(
				'value'      => 'woo_cart_products_num',
				'label'      => __( 'Number of Products in Cart', 'optin' ),
				'isChild'    => true,
				'pro'        => true,
				'condition'  => 'math',
				'valueField' => 'math',
			),
			array(
				'value'      => 'woo_cart_value$',
				'label'      => __( 'Total Cart Amount', 'optin' ),
				'isChild'    => true,
				'pro'        => true,
				'isCurrency' => true,
				'valueField' => 'math',
			),
			array(
				'value'     => 'woo_cart_nocond',
				'label'     => __( 'Cart Page', 'optin' ),
				'isChild'   => true,
				'pro'       => true,
				'condition' => 'none',
			),
			array(
				'value'     => 'woo_thank_nocond',
				'label'     => __( 'Thank You Page', 'optin' ),
				'isChild'   => true,
				'pro'       => true,
				'condition' => 'none',
			),
			array(
				'isHeader' => true,
				'label'    => __( 'EDD', 'optin' ),
				'value'    => 'edd',
				'pro'      => true,
			),
			array(
				'value'     => 'edd_single_product',
				'label'     => __( 'Product Single Page', 'optin' ),
				'isChild'   => true,
				'pro'       => true,
				'condition' => 'single',
			),
			array(
				'value'   => 'edd_cats',
				'label'   => __( 'Product Single Page with Category', 'optin' ),
				'isChild' => true,
				'pro'     => true,
			),
			array(
				'value'   => 'edd_tags',
				'label'   => __( 'Product Single Page with Tag', 'optin' ),
				'isChild' => true,
				'pro'     => true,
			),
			array(
				'isChild' => true,
				'value'   => 'edd_cart_products',
				'label'   => __( 'Products in Cart', 'optin' ),
				'pro'     => true,
			),
			array(
				'isChild'    => true,
				'value'      => 'edd_cart_products_num',
				'label'      => __( 'Number of Products in Cart', 'optin' ),
				'pro'        => true,
				'condition'  => 'math',
				'valueField' => 'math',
			),
			array(
				'isChild'    => true,
				'value'      => 'edd_cart_value$',
				'label'      => __( 'Total Cart Amount', 'optin' ),
				'pro'        => true,
				'isCurrency' => true,
				'valueField' => 'math',
			),
			array(
				'isChild'   => true,
				'value'     => 'edd_cart_nocond',
				'label'     => __( 'Cart Page', 'optin' ),
				'pro'       => true,
				'condition' => 'none',
			),
			array(
				'isChild'   => true,
				'value'     => 'edd_thank_nocond',
				'label'     => __( 'Thank You Page', 'optin' ),
				'pro'       => true,
				'condition' => 'none',
			),
		);

		return $res;
	}

	/**
	 * Get values for a rule.
	 *
	 * @param string $type rule type.
	 * @param string $search search query.
	 * @param mixed  $exclude exclude terms.
	 * @return array
	 */
	public static function get_values( $type, $search, $exclude ) {

		if ( Utils::check_prefix( $type, 'cpt_post:', true ) ) {
			return self::get_posts( $type, $search, $exclude );
		}

		if ( Utils::check_prefix( $type, 'cpt_tax:', true ) ) {
			$v = explode( '###', $type );
			return self::get_terms( $v[1], $search, $exclude );
		}

		switch ( $type ) {

			case 'post_posts':
				return self::get_posts( 'post', $search, $exclude );

			case 'post_cat':
				return self::get_terms( 'category', $search, $exclude );

			case 'post_tag':
				return self::get_terms( 'post_tag', $search, $exclude );

			case 'page':
			case 'parent_page':
				return self::get_posts( 'page', $search, $exclude );

			case 'post_type':
				return self::get_post_types( $search, $exclude );

			case 'author':
				return self::get_users( $search, $exclude );

			case 'woo_cart_products':
			case 'woo_single_product':
				return self::get_woo_products( $search, $exclude );

			case 'woo_cats':
				return self::get_terms( 'product_cat', $search, $exclude );

			case 'woo_tags':
				return self::get_terms( 'product_tag', $search, $exclude );

			case 'edd_single_product':
			case 'edd_cart_products':
				return self::get_edd_products( $search, $exclude );

			case 'edd_cats':
				return self::get_terms( 'download_category', $search, $exclude );

			case 'edd_tags':
				return self::get_terms( 'download_tag', $search, $exclude );

			default:
				return array();
		}
	}

	/**
	 * Get WooCommerce products
	 *
	 * @param string $search search query.
	 * @param array  $exclude exclude terms.
	 * @return array
	 */
	public static function get_woo_products( $search, $exclude ) {
		$res = array();

		if ( function_exists( 'wc_get_products' ) ) {
			$products = wc_get_products(
				array(
					'exclude' => $exclude, //phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
					's'       => $search,
					'limit'   => ! empty( $search ) ? -1 : 10,
					'status'  => 'publish',
				)
			);

			if ( ! empty( $products ) ) {
				foreach ( $products as $product ) {
					$res[] = array(
						'value' => $product->get_id(),
						'label' => $product->get_name(),
					);
				}
			}
		}

		return $res;
	}

	/**
	 * Get EDD products.
	 *
	 * @param string $search search query.
	 * @param mixed  $exclude exclude terms.
	 * @return array
	 */
	public static function get_edd_products( $search, $exclude ) {
		$res = array();

		$products = get_posts(
			array(
				'post_type'      => 'download',
				'posts_per_page' => ! empty( $search ) ? -1 : 10,
				'post_status'    => 'publish',
				's'              => $search,
				'exclude'        => $exclude, //phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
			)
		);

		if ( ! empty( $products ) ) {
			foreach ( $products as $product ) {
				$res[] = array(
					'value' => $product->ID,
					'label' => $product->post_title,
				);
			}
		}

		return $res;
	}

	/**
	 * Get terms
	 *
	 * @param string $tax taxonomy type.
	 * @param string $search search query.
	 * @param mixed  $exclude exclude terms.
	 * @return array
	 */
	public static function get_terms( $tax, $search, $exclude ) {
		$res = array();

		$terms = get_terms(
			array(
				'taxonomy'   => $tax,
				'hide_empty' => false,
				'exclude'    => $exclude, //phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
				'search'     => $search,
				'number'     => ! empty( $search ) ? 0 : 10,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( ! empty( $terms ) ) {
			foreach ( $terms as $term ) {
				$res[] = array(
					'label' => $term->name,
					'value' => $term->term_id,
				);
			}
		}

		return $res;
	}

	/**
	 * Get posts by post type.
	 *
	 * @param string $post_type post type.
	 * @param string $search search query.
	 * @param mixed  $exclude exclude terms.
	 * @return array
	 */
	public static function get_posts( $post_type, $search, $exclude ) {
		$res = array();

		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				's'              => $search,
				'post__not_in'   => $exclude, //phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
				'posts_per_page' => ! empty( $search ) ? -1 : 10,
				'status'         => 'publish',
			)
		);

		if ( ! empty( $posts ) ) {
			foreach ( $posts as $post ) {
				$res[] = array(
					'label' => $post->post_title,
					'value' => $post->ID,
				);
			}
		}

		return $res;
	}

	/**
	 * Get users.
	 *
	 * @param string $search search query.
	 * @param mixed  $exclude exclude terms.
	 * @return array
	 */
	public static function get_users( $search, $exclude ) {
		$res = array();

		$users = get_users(
			array(
				'search'  => '*' . $search . '*',
				'exclude' => $exclude,
			)
		);

		if ( ! empty( $users ) ) {
			foreach ( $users as $user ) {
				$res[] = array(
					'value' => $user->ID,
					'label' => $user->data->display_name,
				);
			}
		}

		return $res;
	}

	/**
	 * Get Post types
	 *
	 * @param string $search search query.
	 * @param mixed  $exclude exclude terms.
	 * @return array
	 */
	public static function get_post_types( $search, $exclude ) {
		$res     = array();
		$exclude = array_merge(
			$exclude,
			array(
				'product',
				'download',
				'attachment',
				'nav_menu_item',
				'wp_template',
				'wp_template_part',
			)
		);

		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'objects'
		);

		if ( ! empty( $post_types ) ) {
			foreach ( $post_types as $post_type ) {
				if ( ! empty( $search ) ) {
					if ( mb_stripos( $post_type->label, $search ) === false ) {
						continue;
					}
				}

				if ( ! empty( $exclude ) ) {
					if ( in_array( $post_type->name, $exclude ) ) {
						continue;
					}
				}

				$res[] = array(
					'value' => $post_type->name,
					'label' => $post_type->label,
					'pro'   => ! in_array( $post_type->name, self::DEFAULT_POST_TYPES ),
				);
			}
		}

		return $res;
	}

	/**
	 * Check if EDD product single page
	 *
	 * @return boolean
	 */
	private static function is_edd_product() {
		return is_single() && 'download' === get_post_type();
	}

	/**
	 * Alternative version using WordPress functions for better integration
	 *
	 * @param string $target_path The path to match against.
	 * @param string $condition The matching condition.
	 * @param bool   $case_sensitive Whether the comparison should be case sensitive.
	 * @return bool
	 */
	private static function match_url_path( $target_path, $condition = 'equals', $case_sensitive = false ) {
		// Derive the current request path from the server environment to avoid
		// losing language prefixes (e.g., /en) or sub-paths added by plugins.
		// Using home_url() here can strip locale directories when filters are applied.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

		$current_path = wp_parse_url( $request_uri, PHP_URL_PATH );
		$current_path = $current_path ? $current_path : '/';

		// If the server/environment collapses language-prefixed home to '/',
		// fall back to the site's base path (e.g., '/en') for accurate matching.
		$site_base = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( '/' === $current_path && ! empty( $site_base ) && '/' !== $site_base ) {
			$current_path = $site_base;
		}

		// Build a list of path variants to match against:
		// 1) The raw request path (preserves language prefixes).
		// 2) Optionally, the path with the site's base stripped (for subdir installs).
		$paths_to_check = array( $current_path );
		if ( ! empty( $site_base ) && '/' !== $site_base && strpos( $current_path, $site_base ) === 0 ) {
			$without_base     = substr( $current_path, strlen( $site_base ) );
			$without_base     = '/' . ltrim( $without_base, '/' );
			$paths_to_check[] = $without_base;
		}

		// Note: Do not strip the site's base path using home_url() because multilingual
		// plugins often filter it to include language prefixes (e.g., /en). We want to
		// preserve the visible URL path exactly as requested.

		// Normalize paths: ensure leading slash and decode percent-encoding.
		$current_path = rawurldecode( '/' . ltrim( $current_path, '/' ) );
		$target_path  = rawurldecode( '/' . ltrim( (string) $target_path, '/' ) );

		// Remove trailing slash for consistency (except for root).
		if ( '/' !== $current_path ) {
			$current_path = rtrim( $current_path, '/' );
		}
		if ( '/' !== $target_path ) {
			$target_path = rtrim( $target_path, '/' );
		}

		if ( class_exists( '\\QM' ) ) {
			\QM::debug( 'REQUEST_URI: ' . ( isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' ) );
			\QM::debug( 'Site Base: ' . (string) $site_base );
			\QM::debug( 'Current Path Variants: ' . implode( ', ', $paths_to_check ) );
			\QM::debug( 'Target Path: ' . $target_path );
		}

		if ( ! $case_sensitive ) {
			$paths_to_check = array_map( 'strtolower', $paths_to_check );
			$target_path    = strtolower( $target_path );
		}

		// Helper comparator for a single path.
		$matches = function ( $path ) use ( $condition, $target_path ) {
			switch ( $condition ) {
				case 'equals':
					return $path === $target_path;
				case 'not_equals':
					return $path !== $target_path;
				case 'starts_with':
					return strpos( $path, $target_path ) === 0;
				case 'not_starts_with':
					return strpos( $path, $target_path ) !== 0;
				case 'ends_with':
					$target_len = strlen( $target_path );
					return substr( $path, -$target_len ) === $target_path;
				case 'not_ends_with':
					$target_len = strlen( $target_path );
					return substr( $path, -$target_len ) !== $target_path;
				case 'contains':
					return strpos( $path, $target_path ) !== false;
				case 'not_contains':
					return strpos( $path, $target_path ) === false;
				default:
					return false;
			}
		};

		// If any variant matches, we consider it a match.
		foreach ( $paths_to_check as $path_variant ) {
			// Normalize variant: ensure leading slash, remove trailing slash (except root).
			$path_variant = '/' . ltrim( $path_variant, '/' );
			if ( '/' !== $path_variant ) {
				$path_variant = rtrim( $path_variant, '/' );
			}
			if ( $matches( $path_variant ) ) {
				return true;
			}
		}

		return false;
	}
}
