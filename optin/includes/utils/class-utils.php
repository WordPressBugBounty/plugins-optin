<?php // phpcs:ignore

namespace OPTN\Includes\Utils;

use OPTN\Includes\Dto\Option;
use WP_Error;

	/**
	 * Utils class for public
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
	 * @param mixed $output Output to log.
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
	 * @param mixed $output Output to log.
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
	 * @param mixed $output Output to log.
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
	 * @param array Binary array
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

	public static function is_valid_json( $string ) {
		json_decode( $string );
		return json_last_error() === JSON_ERROR_NONE;
	}

	public static function get_license_info() {
		$lic_data = get_option( 'edd_optin_license_data', array() );

		$expiration = '';
		$type       = '';

		if ( isset( $lic_data['expires'] ) ) {
			$raw_expiration = $lic_data['expires'];

			if ( strtotime( $raw_expiration ) ) {
				$expiration = date( 'F j, Y, g:i A', strtotime( $raw_expiration ) ); // phpcs:ignore
			} else {
				$expiration = ucfirst( $raw_expiration );
			}
		}

		if ( isset( $lic_data['license_limit'] ) ) {
			$raw_limit = intval( $lic_data['license_limit'] );

			if ( 0 === $raw_limit ) {
				$type = 'Unlimited';
			} else {
				$type = $raw_limit . ' Site(s)';
			}
		}

		$data = array(
			'is_active'  => defined( 'OPTN_PRO_VERSION' ) && isset( $lic_data['license'] ) && 'valid' === $lic_data['license'],
			'expiration' => $expiration,
			'type'       => $type,
		);

		return $data;
	}

	public static function get_license_key() {
		if ( ! defined( 'OPTN_PRO_VERSION' ) ) {
			return null;
		}

		return get_option( 'edd_optin_license_key', null );
	}

	/**
	 * Check if Pro user
	 *
	 * @return boolean
	 */
	public static function is_pro() {
		if ( ! defined( 'OPTN_PRO_VERSION' ) ) {
			return false;
		}
		$lic_data = get_option( 'edd_optin_license_data', array() );

		return isset( $lic_data['license'] ) && 'valid' === $lic_data['license'];
	}

	public static function get_sanitized_query_parameters() {
		if ( isset( $_GET ) && is_array( $_GET ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			$ret = array();

			foreach ( $_GET as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$ret[ $key ] = $value;
			}

			return $ret;
		}

		return array();
	}


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


	public static function get_visitor_types() {
		$roles   = array();
		$roles[] = is_user_logged_in() ? 'logged_in' : 'logged_out';
		$roles[] = self::detect_new_or_returning_visitor();
		return $roles;
	}

	private static function detect_new_or_returning_visitor() {
		$cookie_name    = 'optn_visitor_type';
		$is_new_visitor = false;

		if ( ! isset( $_COOKIE[ $cookie_name ] ) ) {
			$is_new_visitor = true;
			setcookie( $cookie_name, 'returning', intval( time() + YEAR_IN_SECONDS ), '/' );
		}

		return $is_new_visitor ? 'new' : 'returning';
	}

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

	public static function check_values( $curr_values, $rule_values, $cond, $all_count = 0 ) {
		if ( isset( $rule_values[0] ) && str_starts_with( $rule_values[0], 'all' ) ) {

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

	public static function maybe_remove_prefix( &$string, $prefix ) {
		if ( strpos( $string, $prefix ) === 0 ) {
			$string = substr( $string, strlen( $prefix ) );
			return true;
		}
		return false;
	}

	/**
	 * Validates user parameters against a string of key-value pairs
	 *
	 * @param string $values     Query string format of key-value pairs to validate
	 * @param array  $params     Current params from $_GET
	 * @return bool             Returns true if all parameters match, false otherwise
	 */
	public static function validate_query_params( string $values, array $params ): bool {
		if ( empty( $values ) || ! is_string( $values ) ) {
			return false;
		}

		$param_pairs = explode( '&', $values );

		foreach ( $param_pairs as $pair ) {
			$parts = explode( '=', $pair );

			// Skip invalid pairs
			if ( count( $parts ) !== 2 || empty( $parts[0] ) || empty( $parts[1] ) ) {
				return false;
			}

			[$key, $value] = $parts;

			// Check if parameter exists and matches
			if ( ! isset( $params[ $key ] ) || $params[ $key ] !== $value ) {
				return false;
			}
		}

		return true;
	}

	public static function get_user_info() {

		$user_info = array(
			'ip'      => null,
			'country' => null,
		);

		if ( isset( $_COOKIE['optn_user_info'] ) ) {
			$info                 = json_decode( stripslashes( $_COOKIE['optn_user_info'] ) ); // phpcs:ignore
			$user_info['ip']      = isset( $info->ip ) ? sanitize_text_field( $info->ip ) : null;
			$user_info['country'] = isset( $info->country ) ? sanitize_text_field( $info->country ) : null;
		} else {
			$user_info['ip'] = self::get_ip_from_request();
		}

		return $user_info;
	}

	public static function get_ip_from_request() {
		foreach ( array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR' ) as $key ) {
			if ( array_key_exists( $key, $_SERVER ) === true ) {
				foreach ( array_map( 'trim', explode( ',', $_SERVER[ $key ] ) ) as $ip ) { // phpcs:ignore
					if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
						return $ip;
					}
				}
			}
		}

		return null;
	}

	public static function get_user_ip() {
		$info = self::get_user_info();
		return $info['ip'];
	}

	public static function get_embed_hook_and_pos( $opt ) {
		$res = array( 'pos' => null );

		if ( self::maybe_remove_prefix( $opt, 'before_' ) ) {
			$res['pos'] = 'before';
		} elseif ( self::maybe_remove_prefix( $opt, 'after_' ) ) {
			$res['pos'] = 'after';
		} elseif ( self::maybe_remove_prefix( $opt, 'custom###' ) ) {

			if ( self::maybe_remove_prefix( $opt, 'before###' ) ) {
				$res['pos']  = 'before';
				$res['hook'] = $opt;
			} elseif ( self::maybe_remove_prefix( $opt, 'after###' ) ) {
				$res['pos']  = 'after';
				$res['hook'] = $opt;
			}

			return $res;
		}

		switch ( $opt ) {
			case 'body':
				$res['hook']   = 'wp_body_open';
				$res['single'] = false;
				break;
			case 'title':
				$res['hook']   = 'the_title';
				$res['single'] = true;
				break;
			case 'content':
				$res['hook']   = 'the_content';
				$res['single'] = true;
				break;
			case 'comment_form':
				if ( 'before' === $res['pos'] ) {
					$res['hook']   = 'comment_form_before';
					$res['single'] = true;
				} elseif ( 'after' === $res['pos'] ) {
					$res['hook']   = 'comment_form_after';
					$res['single'] = true;
				}
				break;
			case 'footer':
				$res['hook']   = 'the_footer';
				$res['single'] = false;
				break;
			default:
				$res['hook'] = null;
		}

		return $res;
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
					// $k = sanitize_key( $k );

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
	 * Get template preview id
	 *
	 * @return int|null
	 */
	public static function get_template_preview_id() {
		if ( isset( $_GET['optn_template_preview'] ) && current_user_can( OPTN_MIN_CAPABILITY ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return intval( $_GET['optn_template_preview'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		return null;
	}

	/**
	 * Is preview mode
	 *
	 * @return bool
	 */
	public static function is_preview_mode() {
		return (
			isset( $_POST['et_fb_preview'] ) || // phpcs:ignore
			isset( $_GET['optn_template_preview'] ) ||
			isset( $_GET['optn_preview'] ) ) &&
			current_user_can( OPTN_MIN_CAPABILITY ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
	 * Is license page visible
	 *
	 * @return boolean
	 */
	public static function is_show_license_page() {
		return defined( 'OPTN_PRO_VERSION' );
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
