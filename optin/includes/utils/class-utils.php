<?php
/**
 * Provides shared formatting, validation, visitor, and request helpers.
 *
 * @package Optin
 */

namespace OPTN\Includes\Utils;

use OPTN\Includes\Dto\Option;
use WP_Error;

	/**
	 * Shared helpers used by plugin settings, rendering, analytics, and integration code.
	 * Methods in this class normalize values and expose WordPress or commerce data
	 * through small reusable operations.
	 *
	 * @since      1.0.1
	 *
	 * @package    optin
	 * @subpackage optin/public
	 */
class Utils {


	/**
	 * Class constructor
	 */
	private function __construct() {}

	/**
	 * Logs via Query Monitor
	 *
	 * @param mixed $output Any diagnostic value Query Monitor can display.
	 * @return void
	 */
	public static function log( $output ) {
		if ( class_exists( '\QM' ) && wp_get_environment_type() === 'local' ) {
			\QM::debug( $output );
		}
	}

	/**
	 * Checks if in debug mode. For Development purposes only.
	 *
	 * @since 1.0.3
	 * @return bool
	 */
	public static function is_debug_mode() {
		return wp_get_environment_type() === 'local' &&
		defined( 'WP_DEBUG' ) &&
		WP_DEBUG === true;
	}


	/**
	 * Logs via debug.log
	 *
	 * @param mixed $output Any diagnostic value to serialize to the local debug log.
	 * @return void
	 */
	public static function log_to_file( $output ) {
		if ( wp_get_environment_type() === 'local' &&
		defined( 'WP_DEBUG' ) &&
		defined( 'WP_DEBUG_LOG' ) &&
		WP_DEBUG === true &&
		WP_DEBUG_LOG === true
		) {
			error_log( print_r( $output, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r
		}
	}

	/**
	 * Logs via debug.log
	 *
	 * @param mixed $output Any error context value to prefix and write to the local debug log.
	 * @return void
	 */
	public static function log_error( $output ) {
		self::log_to_file( '[OPTN_ERROR] ' . $output );
	}

	/**
	 * Checks if prefix exists
	 *
	 * @param string  $value value to check.
	 * @param string  $prefix prefix.
	 * @param boolean $remove Removes the prefix if found.
	 * @return boolean
	 */
	public static function check_prefix( &$value, $prefix, $remove = false ) {
		$i = strpos( $value, $prefix );

		if ( false !== $i ) {
			if ( $remove ) {
				$value = substr( $value, strlen( $prefix ), strlen( $value ) );
			}
			return true;
		}

		return false;
	}


	/**
	 * Converts a binary array to decimal. Array's last element will be LSB
	 *
	 * @param array<int, bool|int> $arr Binary values ordered with the least significant bit last.
	 * @return int
	 */
	public static function bin_array_to_dec( $arr ) {
		$bin = '';
		foreach ( $arr as $a ) {
			$bin .= $a ? '1' : '0';
		}
		return bindec( $bin );
	}

	/**
	 * Get percentage difference between two values.
	 *
	 * @param int|float $old_value old value.
	 * @param int|float $new_value new value.
	 * @return int|float
	 */
	public static function get_diff_pct( $old_value, $new_value ) {
		if ( 0 == $old_value ) { // phpcs:ignore
			return 0 != $new_value ? 100 : 0; // phpcs:ignore
		}
		return ceil( ( abs( $new_value - $old_value ) / abs( $old_value ) ) * 100 );
	}

	/**
	 * Format an already-scaled percentage value for display.
	 *
	 * Rounds *up* at the last kept decimal, so a rate is never shown lower than
	 * it actually is ( 1.341 => "1.35", 1.445 => "1.45" ), and always renders
	 * the full number of decimals ( 1.5 => "1.50" ).
	 *
	 * The inner round() absorbs binary floating point error before the ceil():
	 * without it 1.11 * 100 lands on 111.00000000000001 and ceils up to 1.12.
	 *
	 * @param int|float $value    Percentage value, already scaled to 0-100.
	 * @param int       $decimals Decimal places to keep.
	 * @return string
	 */
	public static function format_percentage( $value, $decimals = 2 ) {

		if ( ! is_numeric( $value ) ) {
			return number_format( 0, $decimals, '.', '' );
		}

		$factor  = pow( 10, $decimals );
		$rounded = ceil( round( floatval( $value ) * $factor, 6 ) ) / $factor;

		return number_format( $rounded, $decimals, '.', '' );
	}

	/**
	 * Abbreviates numeric values with thousand, million, billion, or trillion units.
	 * Non-numeric input passes through unchanged; numeric values are rounded to one
	 * decimal place before the matching unit suffix is appended.
	 *
	 * @param int|float|string $num Numeric value to abbreviate.
	 * @return int|float|string Abbreviated value, or the original non-numeric input.
	 */
	public static function format_number( $num ) {

		if ( ! is_numeric( $num ) ) {
			return $num ?? '';
		}

		$units = array( '', 'K', 'M', 'B', 'T' );
		for ( $i = 0; $num >= 1000; $i++ ) {
			$num /= 1000;
		}
		return round( $num, 1 ) . $units[ $i ];
	}

	/**
	 * Reads the order subtotal from WooCommerce or Easy Digital Downloads.
	 * Order type 0 uses WooCommerce; type 1 uses an EDD payment. Unsupported types,
	 * missing commerce APIs, and missing orders produce a zero subtotal.
	 *
	 * @param int $order_type Commerce source identifier: 0 for WooCommerce or 1 for EDD.
	 * @param int $order_id   Order or payment identifier to retrieve.
	 * @return int|float Order subtotal, or zero when no supported order is available.
	 */
	public static function get_revenue( $order_type, $order_id ) {

		if ( $order_type == 0 ) {
			if ( function_exists( 'wc_get_order' ) ) {
				$order = wc_get_order( $order_id );
				if ( $order && method_exists( $order, 'get_subtotal' ) ) {
					return $order->get_subtotal() ?? 0;
				}
				return 0;
			}
			return 0;
		} elseif ( $order_type == 1 ) {
			if ( class_exists( 'EDD_Payment' ) ) {
				$payment = new \EDD_Payment( $order_id );
				return $payment->subtotal ?? 0;
			}
			return 0;
		}

		return 0;
	}

	/**
	 * Builds date strings for today and the requested number of preceding days.
	 * The current date is the first item and every value uses the Y-m-d format.
	 *
	 * @param int $days Number of dates to return, including today.
	 * @return array<int, string> Calendar dates in newest-to-oldest order.
	 */
	public static function get_past_dates( $days = 7 ) {
		$dates = array();
		$today = new \DateTime();

		for ( $i = 0; $i < $days; $i++ ) {
			$date = clone $today;
			$date->modify( "-$i day" );
			$dates[] = $date->format( 'Y-m-d' );
		}

		return $dates;
	}

	/**
	 * Checks whether a string can be decoded as valid JSON.
	 *
	 * @param string $string JSON text to validate.
	 * @return bool Whether JSON decoding completed without an error.
	 */
	public static function is_valid_json( $string ) {
		json_decode( $string );
		return json_last_error() === JSON_ERROR_NONE;
	}








	/**
	 * Collects term IDs from every taxonomy attached to a post.
	 * Returns an empty array when the ID has no post type or assigned terms.
	 *
	 * @param int $id Post ID whose taxonomy terms should be collected.
	 * @return array<int, int> Term IDs assigned to the post.
	 */
	public static function get_terms_by_id( $id ) {
		$terms = array();
		$pt    = get_post_type( $id );
		if ( $pt ) {
			$taxes = get_object_taxonomies( $pt, 'objects' );
			if ( ! empty( $taxes ) ) {
				foreach ( $taxes as $tax ) {
					$the_terms = get_the_terms( $id, $tax->name );
					if ( ! empty( $the_terms ) ) {
						foreach ( $the_terms as $term ) {
							$terms[] = $term->term_id;
						}
					}
				}
			}
		}
		return $terms;
	}


	/**
	 * Returns the visitor's login state and new-or-returning classification.
	 * The second value is determined by the visitor cookie helper.
	 *
	 * @return array<int, string> Logged-in state followed by visitor history state.
	 */
	public static function get_visitor_types() {
		$roles   = array();
		$roles[] = is_user_logged_in() ? 'logged_in' : 'logged_out';
		$roles[] = self::detect_new_or_returning_visitor();
		return $roles;
	}

	/**
	 * Classifies a visitor from the plugin's one-year tracking cookie.
	 * A missing cookie marks the visitor as new and sets the cookie for later visits.
	 *
	 * @return string Either 'new' or 'returning'.
	 */
	private static function detect_new_or_returning_visitor() {
		$cookie_name    = 'optn_visitor_type';
		$is_new_visitor = false;

		if ( ! isset( $_COOKIE[ $cookie_name ] ) ) {
			$is_new_visitor = true;
			setcookie( $cookie_name, 'returning', intval( time() + YEAR_IN_SECONDS ), '/' );
		}

		return $is_new_visitor ? 'new' : 'returning';
	}

	/**
	 * Compares two scalar values with an equality, greater-than, or less-than rule.
	 * Unsupported condition names do not match.
	 *
	 * @param int|float|string $curr_value Current value being evaluated.
	 * @param int|float|string $rule_value Value supplied by the display rule.
	 * @param string           $cond       Comparison operator: eq, gt, or lt.
	 * @return bool Whether the selected comparison passes.
	 */
	public static function check_math( $curr_value, $rule_value, $cond ) {
		switch ( $cond ) {
			case 'eq':
				return $curr_value == $rule_value;
			case 'gt':
				return $curr_value > $rule_value;
			case 'lt':
				return $curr_value < $rule_value;
			default:
				return false;
		}
	}

	/**
	 * Evaluates any, all, not-any, and not-all conditions for scalar or list values.
	 * Special rule values beginning with 'all' use collection counts instead of
	 * intersecting the configured values; other lists are normalized first.
	 *
	 * @param array|string|int|float|bool|null $curr_values Current scalar or list of visitor values.
	 * @param array|string                     $rule_values Configured rule values, including special 'all' markers.
	 * @param string                           $cond        Membership condition to evaluate.
	 * @param int                              $all_count   Required count used by special 'all' rules.
	 * @return bool Whether the current values satisfy the condition.
	 */
	public static function check_values( $curr_values, $rule_values, $cond, $all_count = 0 ) {
		if ( isset( $rule_values[0] ) && 0 === strpos( $rule_values[0], 'all' ) ) {

			if ( is_array( $curr_values ) ) {
				switch ( $cond ) {
					case 'any':
						return count( $curr_values ) > 0;
					case 'all':
						return $all_count <= count( $curr_values );
					case 'not_all':
						return $all_count > count( $curr_values );
					case 'not_any':
					default:
						return count( $curr_values ) < 1;
				}
			}

			switch ( $cond ) {
				case 'any':
					return true;
				case 'not_any':
				case 'all':
				case 'not_all':
				default:
					return false;
			}
		}

		if ( is_array( $curr_values ) ) {
			$intersect = array_intersect( $curr_values, self::clean_values( $rule_values ) );

			switch ( $cond ) {
				case 'any':
					return ! empty( $intersect );
				case 'all':
					return count( $intersect ) === count( $rule_values );
				case 'not_all':
					return count( $intersect ) !== count( $rule_values );
				case 'not_any':
					return empty( $intersect );
				default:
					return false;
			}
		}

		$inc = in_array( $curr_values, self::clean_values( $rule_values ) );

		if ( 'any' === $cond || 'all' === $cond ) {
			return $inc;
		}

		if ( 'not_any' === $cond || 'not_all' === $cond ) {
			return ! $inc;
		}

		return false;
	}

	/**
	 * Normalizes list values before display-rule comparisons.
	 * Array entries lose any provider suffix after '###' and become strings;
	 * non-string scalar values are cast to strings.
	 *
	 * @param array|string|int|float|bool|null $values Array or scalar values to normalize.
	 * @return array|string|null Normalized values, or null for an unchanged string scalar.
	 */
	public static function clean_values( $values ) {
		if ( is_array( $values ) ) {
			return array_map(
				function ( $value ) {
					if ( strpos( $value, '###' ) !== false ) {
						return explode( '###', $value )[0];
					}
					return strval( $value );
				},
				$values
			);
		}

		if ( ! is_string( $values ) ) {
			return strval( $values );
		}
	}

	/**
	 * Removes a leading prefix from a string when it is present.
	 * The input is updated by reference only on a match.
	 *
	 * @param string $string String to inspect and possibly modify.
	 * @param string $prefix Prefix that must appear at the start of the string.
	 * @return bool Whether the prefix was found and removed.
	 */
	public static function maybe_remove_prefix( &$string, $prefix ) {
		if ( strpos( $string, $prefix ) === 0 ) {
			$string = substr( $string, strlen( $prefix ) );
			return true;
		}
		return false;
	}

	/**
	 * Returns the trackable public IP and a matching cached country code.
	 * Tracking settings control whether IP data is read; a country is accepted only
	 * from the visitor cookie when its IP matches and the code has two uppercase letters.
	 *
	 * @return array{ip: string|null, country: string|null} Visitor IP and cached country.
	 */
	public static function get_user_info() {
		if ( ! \OPTN\Includes\Settings::is_ip_tracking_enabled() ) {
			return array(
				'ip'      => null,
				'country' => null,
			);
		}
		$ip      = self::get_ip_from_request();
		$country = null;
		$info    = isset( $_COOKIE['optn_user_info'] ) && is_string( $_COOKIE['optn_user_info'] ) ? json_decode( sanitize_text_field( wp_unslash( $_COOKIE['optn_user_info'] ) ) ) : null;
		if ( $ip && is_object( $info ) && ( $info->ip ?? null ) === $ip && is_string( $info->country ?? null ) && preg_match( '/^[A-Z]{2}$/D', $info->country ) ) {
			$country = $info->country;
		}
		return array(
			'ip'      => $ip,
			'country' => $country,
		);
	}

	/**
	 * Reads REMOTE_ADDR and rejects private, reserved, or invalid IP addresses.
	 *
	 * @return string|null Public client IP address, or null when it is not trackable.
	 */
	public static function get_ip_from_request() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ? $ip : null;
	}

	/**
	 * Gets the public IP value returned by the tracking-aware visitor lookup.
	 *
	 * @return string|null Trackable visitor IP address, or null when unavailable.
	 */
	public static function get_user_ip() {
		$info = self::get_user_info();
		return $info['ip'];
	}

	/**
	 * Converts a saved embed position into its WordPress hook and placement metadata.
	 * Only built-in positions can resolve to HTML hooks. Unsupported and legacy
	 * custom settings keep a null hook.
	 *
	 * @param mixed $opt Saved embed position.
	 * @return array{hook: string|null, pos: string|null, single?: bool, type?: string} Hook, placement, context, and callback kind.
	 */
	public static function get_embed_hook_and_pos( $opt ) {
		$invalid = array( 'hook' => null, 'pos' => null );
		if ( ! is_string( $opt ) ) {
			return $invalid;
		}

		$positions = array(
			'before_body'         => array(
				'hook'   => 'wp_body_open',
				'pos'    => 'before',
				'single' => false,
				'type'   => 'action',
			),
			'after_body'          => array(
				'hook'   => 'wp_footer',
				'pos'    => 'after',
				'single' => false,
				'type'   => 'action',
			),
			'before_title'        => array(
				'hook'   => 'the_title',
				'pos'    => 'before',
				'single' => true,
				'type'   => 'filter',
			),
			'after_title'         => array(
				'hook'   => 'the_title',
				'pos'    => 'after',
				'single' => true,
				'type'   => 'filter',
			),
			'before_content'      => array(
				'hook'   => 'the_content',
				'pos'    => 'before',
				'single' => true,
				'type'   => 'filter',
			),
			'after_content'       => array(
				'hook'   => 'the_content',
				'pos'    => 'after',
				'single' => true,
				'type'   => 'filter',
			),
			'before_comment_form' => array(
				'hook'   => 'comment_form_before',
				'pos'    => 'before',
				'single' => true,
				'type'   => 'action',
			),
			'after_comment_form'  => array(
				'hook'   => 'comment_form_after',
				'pos'    => 'after',
				'single' => true,
				'type'   => 'action',
			),
			'before_footer'       => array(
				'hook'   => 'wp_footer',
				'pos'    => 'before',
				'single' => false,
				'type'   => 'action',
			),
			'after_footer'        => array(
				'hook'   => 'wp_footer',
				'pos'    => 'after',
				'single' => false,
				'type'   => 'action',
			),
		);

		return $positions[ $opt ] ?? $invalid;
	}

	/**
	 * Sanitizes a JSON array for safe use in WordPress
	 *
	 * @param string|array $input Raw JSON string or PHP array to sanitize.
	 * @param array        $allowed_types Array of allowed data types ('string', 'int', 'float', 'bool', 'array').
	 * @param bool         $strict_checking Whether to error on unexpected fields/types.
	 * @return array|WP_Error
	 */
	public static function sanitize_json_array( $input, $allowed_types = array( 'string', 'int', 'float', 'bool', 'array', 'NULL' ), $strict_checking = false ) {
		// If input is JSON string, decode it first.
		if ( is_string( $input ) ) {
			$decoded = json_decode( $input, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return new \WP_Error(
					'invalid_json',
					'Invalid JSON format: ' . json_last_error_msg()
				);
			}
			$input = $decoded;
		}

		// Verify we have an array.
		if ( ! is_array( $input ) ) {
			return new \WP_Error(
				'invalid_input',
				'Input must be a JSON string or array'
			);
		}

		// Recursive function to sanitize array values.
		$sanitize_value = function ( $value, $key = '' ) use ( &$sanitize_value, $allowed_types, $strict_checking ) {
			$type = gettype( $value );

			// Handle nested arrays recursively.
			if ( is_array( $value ) ) {
				if ( ! in_array( 'array', $allowed_types, true ) ) {
					return new \WP_Error(
						'invalid_type',
						sprintf( 'Array type not allowed for key "%s"', $key )
					);
				}
				$sanitized = array();
				foreach ( $value as $k => $v ) {
					// Sanitize array keys.
					// Keep key sanitization disabled until the saved schema defines the allowed key format.

					$result = $sanitize_value( $v, $k );
					if ( is_wp_error( $result ) ) {
						return $result;
					}

					if ( null !== $result ) {
						$sanitized[ $k ] = $result;
					}
				}
				return $sanitized;
			}

			// Handle scalar values.
			switch ( $type ) {
				case 'string':
					if ( ! in_array( 'string', $allowed_types, true ) ) {
						return new \WP_Error(
							'invalid_type',
							sprintf( 'String type not allowed for key "%s"', $key )
						);
					}
					return sanitize_text_field( $value );

				case 'integer':
					if ( ! in_array( 'int', $allowed_types, true ) ) {
						return new \WP_Error(
							'invalid_type',
							sprintf( 'Integer type not allowed for key "%s"', $key )
						);
					}
					return intval( $value );

				case 'double':
					if ( ! in_array( 'float', $allowed_types, true ) ) {
						return new \WP_Error(
							'invalid_type',
							sprintf( 'Float type not allowed for key "%s"', $key )
						);
					}
					return floatval( $value );

				case 'boolean':
					if ( ! in_array( 'bool', $allowed_types, true ) ) {
						return new \WP_Error(
							'invalid_type',
							sprintf( 'Boolean type not allowed for key "%s"', $key )
						);
					}
					return (bool) $value;
				case 'NULL':
					if ( ! in_array( 'NULL', $allowed_types, true ) ) {
						return new \WP_Error(
							'invalid_type',
							sprintf( 'Null type not allowed for key "%s"', $key )
						);
					}
					return null;

				default:
					if ( $strict_checking ) {
						return new \WP_Error(
							'invalid_type',
							sprintf( 'Unsupported type "%s" for key "%s"', $type, $key )
						);
					}
					// In non-strict mode, convert to sanitized string.
					return sanitize_text_field( (string) $value );
			}
		};

		$result = $sanitize_value( $input );

		if ( is_wp_error( $result ) ) {
			self::log_to_file( $result );
			return null;
		}

		return $result;
	}

	/**
	 * Get the user hash
	 *
	 * @return string
	 */
	public static function get_user_hash() {
		if ( isset( $_COOKIE['optn_analytics_id'] ) ) {
			return sanitize_text_field( wp_unslash( $_COOKIE['optn_analytics_id'] ) );
		}

		return null;
	}

	/**
	 * Get preview id
	 *
	 * @return int|null
	 */
	public static function get_optin_preview_id() {
		if ( isset( $_GET['optn_preview'] ) && current_user_can( OPTN_MIN_CAPABILITY ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return intval( $_GET['optn_preview'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		return null;
	}


	/**
	 * Build attribute string for HTML Elements. Expected to be escaped.
	 *
	 * @param array $attr attributes array.
	 * @return string
	 */
	public static function build_attr_str( $attr ) {
		$res = '';

		foreach ( $attr as $key => $value ) {
			if ( false === $value ) {
				continue;
			}
			$res .= " {$key}";
			$v    = is_string( $value ) ? $value : strval( $value );
			if ( '' !== $v ) {
				$res .= '="' . $v . '" ';
			}
		}

		return $res;
	}

	/**
	 * Is preview mode
	 *
	 * @return bool
	 */
	public static function is_preview_mode() {
		return apply_filters(
			'optn_is_preview_mode',
			(
			isset( $_POST['et_fb_preview'] ) || // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only builder preview flag; capability checked below.
			isset( $_GET['optn_preview'] ) ) && // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only preview flag; capability checked below.
			current_user_can( OPTN_MIN_CAPABILITY )
		);
	}

	/**
	 * Get Audio files
	 *
	 * @return array
	 */
	public static function get_audio_urls() {
		$res = array();
		$dir = OPTN_DIR . '/assets/sounds/';

		$files = scandir( $dir );

		foreach ( $files as $file ) {
			if ( '.' !== $file && '..' !== $file ) {
				$filename         = pathinfo( $file, PATHINFO_FILENAME );
				$res[ $filename ] = OPTN_URL . '/assets/sounds/' . $file;
			}
		}

		return $res;
	}




	/**
	 * Get currency symbol.
	 *
	 * @return string
	 */
	public static function get_currency_symbol() {
		if ( function_exists( 'get_woocommerce_currency_symbol' ) ) {
			return trim( html_entity_decode( get_woocommerce_currency_symbol() ) );
		}

		if ( function_exists( 'edd_currency_symbol ' ) ) {
			return edd_currency_symbol();
		}

		return '$';
	}


	/**
	 * Process REST response with error checking
	 *
	 * @param array|WP_Error $resp Remote response.
	 * @param array          $status_codes HTTP status codes to consider as success.
	 * @return \OPTN\Includes\Dto\Option
	 */
	public static function get_remote_req_body( $resp, $status_codes = array( 200 ) ) {
		$option = new Option();

		if ( is_wp_error( $resp ) ) {

			$option->data  = null;
			$option->error = $resp->get_error_message();

			return $option;
		}

		if ( ! in_array( wp_remote_retrieve_response_code( $resp ), $status_codes ) ) { // phpcs:ignore

			$option->data  = null;
			$option->error = wp_remote_retrieve_response_message( $resp );

			return $option;
		}

		$body = wp_remote_retrieve_body( $resp );
		$body = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {

			$option->data  = null;
			$option->error = json_last_error_msg();

			return $option;
		}

		$option->data  = $body;
		$option->error = null;

		return $option;
	}

	/**
	 * Checks if given string is a valid version number
	 *
	 * @param string $version version string.
	 * @return boolean
	 */
	public static function is_valid_version_number( $version ) {
		if ( ! is_string( $version ) ) {
			return false;
		}

		$regex = '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?(?:\+([0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$/';

		$is_match = preg_match( $regex, $version );

		return boolval( $is_match );
	}

	/**
	 * Extracts name from array.
	 *
	 * @param array $fields Array of fields.
	 * @return string
	 */
	public static function extract_name( $fields ) {
		$res = '';

		$common_keys = array(
			'name',
			array( 'first_name', 'last_name' ),
			array( 'firstname', 'lastname' ),
			array( 'fname', 'lname' ),
			array( 'f_name', 'l_name' ),
		);

		$std_fields = array();
		foreach ( $fields as $key => $value ) {
			$std_fields[ strtolower( $key ) ] = $value;
		}

		foreach ( $std_fields as $key => $value ) {
			foreach ( $common_keys as $common_key ) {
				if ( is_array( $common_key ) ) {
					if ( in_array( $key, $common_key, true ) ) {
						$res = ( $fields[ $common_key[0] ] ?? '' ) . ' ' . ( $fields[ $common_key[1] ] ?? '' );
						break;
					}
				} elseif ( $key === $common_key ) {
					$res = $value;
					break;
				}
			}

			if ( ! empty( $res ) ) {
				break;
			}
		}

		return trim( $res );
	}
}
