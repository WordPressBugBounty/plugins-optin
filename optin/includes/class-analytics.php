<?php
/**
 * Registers REST endpoints and collects opt-in and visitor analytics.
 *
 * @package Optin
 */

namespace OPTN\Includes;

use WP_REST_Request;
use OPTN\Includes\Db;
use OPTN\Includes\Utils\VisitorCount;
use OPTN\Includes\Utils\Utils;
use OPTN\Includes\Utils\AnalyticsToken;
use OPTN\Includes\Utils\CampaignSupport;

/**
 * Coordinates analytics event recording and reporting for opt-in campaigns.
 *
 * Registers public event and IP lookup routes alongside capability-protected dashboard
 * routes, then delegates metric queries and event persistence to the database service.
 */
class Analytics {
	use \OPTN\Includes\Traits\Singleton;

	/**
	 * DB class instance
	 *
	 * @var \OPTN\Includes\Db $db db class
	 */
	private $db;

	/**
	 * VisitorCount class instance
	 *
	 * @var \OPTN\Includes\Utils\VisitorCount $visitor_count
	 */
	private $visitor_count;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->db            = Db::get_instance();
		$this->visitor_count = VisitorCount::get_instance();
	}

	/**
	 * Register public routes
	 *
	 * @return void
	 */
	public function register_public_routes() {
		register_rest_route(
			'optn/v1',
			'/update-analytics/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_update_analytics' ),
				'permission_callback' => array( $this, 'event_permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/get-ipinfo/',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_get_ipinfo' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Register admin routes
	 *
	 * @return void
	 */
	public function register_admin_routes() {
		register_rest_route(
			'optn/v1',
			'/get-quick-view-data/',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_get_quick_view_data' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/get-valid-convs/',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_get_valid_convs' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/get-impressions/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_get_impression' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/get-impressions-device/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_get_impression_device' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/get-conversions/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_get_conversion' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/get-sales/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_get_sales' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/get-pop-optins/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_get_pop_optins' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			'optn/v1',
			'/get-geo-view/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_get_geo_view' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);
	}

	/**
	 * Return visitor counts grouped by location for the requested reporting window.
	 *
	 * Reads the number of days from the REST request, defaults to the latest seven days,
	 * and wraps the database aggregation in a REST response for the analytics dashboard.
	 *
	 * @param WP_REST_Request $req Request containing the optional `days` reporting window.
	 * @return WP_REST_Response REST response containing geographic view totals.
	 */
	public function handle_get_geo_view( $req ) {
		$days = isset( $req['days'] ) ? intval( $req['days'] ) : 7;

		$res = $this->db->get_geo_view_data( $days );

		return rest_ensure_response( $res );
	}

	/**
	 * Return the most viewed opt-ins for a paginated analytics list.
	 *
	 * Uses request values for the number of days, page size, and page number, defaulting
	 * to seven days, six rows, and the first page before querying the database.
	 *
	 * @param WP_REST_Request $req Request containing optional `days`, `limit`, and `page` values.
	 * @return WP_REST_Response REST response containing the popular opt-ins and paging data.
	 */
	public function handle_get_pop_optins( $req ) {
		$days  = isset( $req['days'] ) ? intval( $req['days'] ) : 7;
		$limit = isset( $req['limit'] ) ? intval( $req['limit'] ) : 6;
		$page  = isset( $req['page'] ) ? intval( $req['page'] ) : 1;

		$res = $this->db->get_pop_optin( $days, $limit, $page );

		return rest_ensure_response( $res );
	}

	/**
	 * Return sales totals for an optional opt-in and reporting window.
	 *
	 * Converts the request's opt-in ID and day count to integers, defaults the window to
	 * seven days, and returns the database sales aggregation as a REST response.
	 *
	 * @param WP_REST_Request $req Request containing optional `id` and `days` values.
	 * @return WP_REST_Response REST response containing sales totals.
	 */
	public function handle_get_sales( WP_REST_Request $req ) {
		$id   = isset( $req['id'] ) ? intval( $req['id'] ) : null;
		$days = isset( $req['days'] ) ? intval( $req['days'] ) : 7;

		$res = $this->db->get_sales( $id, $days );
		return rest_ensure_response( $res );
	}

	/**
	 * Return conversion totals for an optional opt-in and reporting window.
	 *
	 * Converts the request's opt-in ID and day count to integers, defaults the window to
	 * seven days, and returns the database conversion aggregation as a REST response.
	 *
	 * @param WP_REST_Request $req Request containing optional `id` and `days` values.
	 * @return WP_REST_Response REST response containing conversion totals.
	 */
	public function handle_get_conversion( WP_REST_Request $req ) {
		$id   = isset( $req['id'] ) ? intval( $req['id'] ) : null;
		$days = isset( $req['days'] ) ? intval( $req['days'] ) : 7;

		$res = $this->db->get_conversions( $id, $days );
		return rest_ensure_response( $res );
	}

	/**
	 * Return impression totals grouped by visitor device.
	 *
	 * Applies the optional opt-in ID and reporting window to the database query, using
	 * seven days when no window is supplied, then wraps the grouped totals for REST.
	 *
	 * @param WP_REST_Request $req Request containing optional `id` and `days` values.
	 * @return WP_REST_Response REST response containing impression totals by device.
	 */
	public function handle_get_impression_device( WP_REST_Request $req ) {
		$id   = isset( $req['id'] ) ? intval( $req['id'] ) : null;
		$days = isset( $req['days'] ) ? intval( $req['days'] ) : 7;

		$res = $this->db->get_impressions_by_device( $id, $days );
		return rest_ensure_response( $res );
	}

	/**
	 * Return impression totals for an optional opt-in and reporting window.
	 *
	 * Reads the optional unique-visitor flag along with the opt-in ID and day count,
	 * defaults to all visitors over seven days, and returns the database totals.
	 *
	 * @param WP_REST_Request $req Request containing optional `id`, `days`, and `unique` values.
	 * @return WP_REST_Response REST response containing impression totals.
	 */
	public function handle_get_impression( WP_REST_Request $req ) {
		$id     = isset( $req['id'] ) ? intval( $req['id'] ) : null;
		$days   = isset( $req['days'] ) ? intval( $req['days'] ) : 7;
		$unique = isset( $req['unique'] ) ? boolval( $req['unique'] ) : false;

		$res = $this->db->get_impressions( $id, $days, $unique );
		return rest_ensure_response( $res );
	}

	/**
	 * Return published content options available for conversion goal selection.
	 *
	 * Queries the database for the post choices used by the analytics interface and
	 * returns that list directly to the REST server for serialization.
	 *
	 * @param WP_REST_Request $req REST request; this handler does not read request fields.
	 * @return array<int, array<string, mixed>> Selectable content records.
	 */
	public function handle_get_valid_convs( WP_REST_Request $req ) {
		$res = $this->db->get_post_for_select();
		return $res;
	}

	/**
	 * Return summary metrics and visitor counts for the analytics dashboard overview.
	 *
	 * Queries opt-in metrics for the requested ID and reporting window, combines those
	 * values with the current and previous visitor statistics, and returns one REST result.
	 *
	 * @param WP_REST_Request $req Request containing optional `id` and `days` values.
	 * @return WP_REST_Response REST response containing dashboard and visitor metrics.
	 */
	public function handle_get_quick_view_data( WP_REST_Request $req ) {
		$id   = isset( $req['id'] ) ? intval( $req['id'] ) : null;
		$days = isset( $req['days'] ) ? intval( $req['days'] ) : 7;

		$db_quick_view = $this->db->get_quick_view_data( $id, $days );

		$visitors = $this->visitor_count->get_stats();

		$res = array_merge(
			$db_quick_view,
			$visitors,
		);

		return rest_ensure_response( $res );
	}

	/**
	 * Record a supported public analytics event for an opt-in.
	 *
	 * Validates the public event and its interaction credential before dispatching a
	 * supported event. Only a new view can issue an interaction credential.
	 *
	 * @param WP_REST_Request $request Request containing the event type and its data.
	 * @return \WP_REST_Response|\WP_Error Event result or explicit validation error.
	 */
	public function handle_update_analytics( WP_REST_Request $request ) {
		$event = $this->validate_event( $request );
		if ( is_wp_error( $event ) ) {
			return $event;
		}
		$id = (int) $request['id'];
		switch ( $request['type'] ) {
			case 'view':
				$limit = $this->check_view_limit( $id );
				if ( is_wp_error( $limit ) ) {
					return $limit;
				}
				$res = $this->db->add_view( $id );
				if ( empty( $res['id'] ) ) {
					return new \WP_Error( 'optn_analytics_write_failed', __( 'Unable to record this event.', 'optin' ), array( 'status' => 500 ) );
				}
				$res['token'] = AnalyticsToken::issue( (int) $res['id'], $id );
				break;
			case 'goal':
				$path = $request['path'] ?? '/';
				$path = '/' === $path ? '/' : rtrim( $path, '/' );
				$res  = $this->db->add_goal( $id, 'click', null, $path );
				if ( ! empty( $res['success'] ) ) {
					$res['purchase_token'] = AnalyticsToken::issue( $id, (int) $event->conv_id, 'purchase' );
				}
				break;
			case 'social':
				$res = $this->db->add_social_click( $id, $request['goal_type'] );
				break;
		}

		$response = rest_ensure_response( $res );
		$response->header( 'Cache-Control', 'no-store, private' );
		return $response;
	}

	/**
	 * Allows public views and requires an interaction credential for updates.
	 *
	 * @param WP_REST_Request $request Submitted event.
	 * @return bool|\WP_Error Whether this operation is authorized.
	 */
	public function event_permission_callback( WP_REST_Request $request ) {
		$result = $this->validate_event( $request );
		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * Validates event shape before looking up or changing any interaction.
	 *
	 * @param WP_REST_Request $request Submitted event.
	 * @return \stdClass|bool|\WP_Error Interaction for updates, true for views, or error.
	 */
	private function validate_event( WP_REST_Request $request ) {
		$id      = $request['id'];
		$type    = $request['type'];
		$invalid = new \WP_Error( 'optn_invalid_analytics_event', __( 'Invalid analytics event.', 'optin' ), array( 'status' => 400 ) );
		if ( ! ( is_int( $id ) || is_string( $id ) ) || ! preg_match( '/^[1-9][0-9]{0,9}$/D', (string) $id ) || (float) $id > 4294967295 || ! is_string( $type ) || ! in_array( $type, array( 'view', 'goal', 'social' ), true ) ) {
			return $invalid;
		}
		if ( 'view' === $type ) {
			$campaign = $this->db->get_campaign_for_analytics( (int) $id );
			if ( ! $campaign || CampaignSupport::get_unsupported_features( $campaign ) ) {
				return new \WP_Error( 'optn_analytics_campaign_unavailable', __( 'Campaign unavailable.', 'optin' ), array( 'status' => 404 ) );
			}
			return true;
		}
		if ( 'goal' === $type ) {
			$path = $request['path'] ?? '/';
			if ( 'click' !== $request['goal_type'] || ! is_string( $path ) || strlen( $path ) > 255 || ! preg_match( '#^/(?!/)[^\x00-\x20\x7f]*$#D', $path ) ) {
				return $invalid;
			}
		} elseif ( ! is_string( $request['goal_type'] ) || ! in_array( $request['goal_type'], array( 'facebook', 'x', 'instagram', 'youtube', 'whatsapp', 'tiktok', 'bluesky', 'discord', 'twitch', 'telegram', 'reddit', 'tumblr', 'snapchat', 'threads', 'linkedin' ), true ) ) {
			return $invalid;
		}
		$token       = $request['token'];
		$interaction = is_string( $token ) && strlen( $token ) <= 80 ? $this->db->get_interaction_by_id( (int) $id ) : null;
		if ( ! $interaction || ! AnalyticsToken::verify( $token, (int) $id, (int) $interaction->conv_id ) ) {
			return new \WP_Error( 'optn_analytics_forbidden', __( 'Invalid or expired analytics credential.', 'optin' ), array( 'status' => 403 ) );
		}
		return $interaction;
	}

	/**
	 * Applies a generous, best-effort view budget per campaign and direct client IP.
	 * The short-lived key stores only an HMAC, never the IP or a new visitor cookie.
	 * This bounds ordinary abuse; it does not establish that a visitor is human.
	 *
	 * @param int $campaign_id Campaign receiving the view.
	 * @return bool|\WP_Error Whether the view may be recorded.
	 */
	private function check_view_limit( int $campaign_id ) {
		$address = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( ! is_string( $address ) || ! filter_var( $address, FILTER_VALIDATE_IP ) ) {
			return true;
		}
		$key   = 'optn_av_' . hash_hmac( 'sha256', $campaign_id . '|' . $address . '|' . (int) floor( time() / MINUTE_IN_SECONDS ), wp_salt( 'auth' ) );
		$count = (int) get_transient( $key );
		if ( $count >= 300 ) {
			return new \WP_Error( 'optn_analytics_rate_limited', __( 'Too many analytics events. Please try again later.', 'optin' ), array( 'status' => 429 ) );
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS + 5 );
		return true;
	}

	/**
	 * Look up the visitor's country through IPinfo when IP tracking is enabled.
	 *
	 * Requires a valid public visitor IP, requests its IPinfo JSON record, and verifies
	 * the upstream status, returned IP, and two-letter country code before exposing data.
	 * Returns a client or upstream WP_Error when tracking or lookup requirements fail.
	 *
	 * @return array<string, mixed>|\WP_Error Country lookup result or a REST error.
	 */
	public function handle_get_ipinfo() {

		if ( ! Settings::is_ip_tracking_enabled() ) {
			return new \WP_Error( 'optn_tracking_disabled', 'IP tracking disabled', array( 'status' => 403 ) );
		}
		$ip = Utils::get_ip_from_request();
		if ( ! $ip ) {
			return new \WP_Error( 'optn_visitor_ip', 'Valid public visitor IP required', array( 'status' => 400 ) );
		}
		$response = wp_remote_get( 'https://ipinfo.io/' . rawurlencode( $ip ) . '/json', array( 'timeout' => 10 ) );
		$data     = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) || ! is_array( $data ) || ( $data['ip'] ?? '' ) !== $ip || ! is_string( $data['country'] ?? null ) || ! preg_match( '/^[A-Z]{2}$/D', $data['country'] ) ) {
			return new \WP_Error( 'optn_ipinfo_upstream', 'IP lookup failed', array( 'status' => 502 ) ); }
		return array(
			'success' => true,
			'data'    => array(
				'ip'      => $ip,
				'country' => $data['country'],
			),
		);
	}


	/**
	 * Check whether the current user may access analytics administration routes.
	 *
	 * Uses the plugin's minimum administration capability constant as the route gate.
	 *
	 * @return bool Whether the current user has the required capability.
	 */
	public function permission_callback() {
		return current_user_can( OPTN_MIN_CAPABILITY );
	}
}
