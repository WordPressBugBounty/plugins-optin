<?php // phpcs:ignore

namespace OPTN\Includes\Utils;

use OPTN\Includes\Utils\Utils;

/**
 * Validates display-rule definitions and resolves their selectable values.
 *
 * Evaluates supported targeting rules against the current request and supplies the
 * post, taxonomy, author, commerce, and visitor options shown by the campaign editor.
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
	 * @param \stdClass $rule Decoded display-rule record containing its type and configured values.
	 * @return boolean
	 */
	public static function is_valid_display_rule( $rule ) {
		if ( ! is_object( $rule ) || ! is_string( $rule->type ?? null ) ) {
			return false;
		}
		/** Null declines a rule; absent or unknown handlers keep unsupported rules false. */
		$result = apply_filters( 'optn_display_rule_result', null, $rule );
		if ( null !== $result ) {
			return true === $result;
		}
		if ( ! in_array( $rule->type ?? '', array( 'entire_nocond', 'front_nocond', 'page', 'parent_page', 'post_posts', 'post_cat', 'post_tags', 'post_type', 'author' ), true ) ) {
			return false;
		}
		return self::is_valid_general_display_rule( $rule );
	}

	/**
	 * Check General Display Rules.
	 *
	 * @param \stdClass $rule Decoded display-rule record passed to the general evaluator.
	 * @return boolean
	 */
	private static function is_valid_general_display_rule( $rule ) {
		$type   = $rule->type;
		$values = $rule->values;
		$cond   = $rule->cond;

		// Entire Page.
		if ( 0 === strpos( $type, 'entire' ) ) {
			return true;
		}

		// Front page.
		if ( 0 === strpos( $type, 'front' ) ) {
			return is_front_page();
		}

		// URL Path.

		// Query params.

		// Cookie.

		// Post.
		if ( 'post_posts' === $type ||
						'page' === $type
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
			'post_tags' === $type
		) {
			$term_ids = Utils::get_terms_by_id( get_the_ID() );
			return Utils::check_values( $term_ids, $values, $cond );
		}

		// Post Types.
		if ( 'post_type' === $type ) {
			$post_type = get_post_type( get_the_ID() );
			if ( ! in_array( $post_type, self::DEFAULT_POST_TYPES, true ) ) {
				return false; }
			foreach ( (array) $values as $value ) {
				if ( ! is_string( $value ) || ! in_array( explode( '###', $value )[0], self::DEFAULT_POST_TYPES, true ) ) {
					return false; }
			}
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

		return apply_filters(
			'optn_display_rule_options',
			array_map(
				function ( $option ) {
					return ! empty( $option['pro'] ) ? array_intersect_key( $option, array_flip( array( 'value', 'label', 'pro', 'isHeader', 'isChild' ) ) ) : $option;
				},
				$res
			)
		);
	}

	/**
	 * Get values for a rule.
	 *
	 * @param string          $type rule type.
	 * @param string          $search search query.
	 * @param array<int, int> $exclude Term, post, or user IDs omitted from the result.
	 * @return array
	 */
	public static function get_values( $type, $search, $exclude ) {
		/** Null declines lookup; extension results must retain the shared array response shape. */
		$result = apply_filters( 'optn_display_rule_values', null, $type, $search, $exclude );
		if ( null !== $result ) {
			return is_array( $result ) ? $result : array();
		}
		switch ( $type ) {
			case 'post_posts':
				return self::get_posts( 'post', $search, $exclude );
			case 'post_cat':
				return self::get_terms( 'category', $search, $exclude );
			case 'post_tags':
			case 'post_tag':
				return self::get_terms( 'post_tag', $search, $exclude );
			case 'page':
			case 'parent_page':
				return self::get_posts( 'page', $search, $exclude );
			case 'post_type':
				return self::get_post_types( $search, $exclude );
			case 'author':
				return self::get_users( $search, $exclude );
			default:
				return array();
		}
	}

	/**
	 * Get terms
	 *
	 * @param string          $tax taxonomy type.
	 * @param string          $search search query.
	 * @param array<int, int> $exclude Term, post, or user IDs omitted from the result.
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
	 * @param string          $post_type post type.
	 * @param string          $search search query.
	 * @param array<int, int> $exclude Term, post, or user IDs omitted from the result.
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
	 * @param string          $search search query.
	 * @param array<int, int> $exclude Term, post, or user IDs omitted from the result.
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
	 * @param string          $search search query.
	 * @param array<int, int> $exclude Term, post, or user IDs omitted from the result.
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
}
