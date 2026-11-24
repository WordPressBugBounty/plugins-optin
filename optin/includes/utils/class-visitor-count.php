<?php
/**
 * Counts eligible front-end page views and unique weekly visitors.
 *
 * @package Optin
 */

namespace OPTN\Includes\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Stores current and previous weekly visitor and page-view totals.
 * Public requests update the current totals; a weekly event rotates those totals
 * so analytics can display both counts and their percentage changes.
 */
class VisitorCount {

	use \OPTN\Includes\Traits\Singleton;

	/**
	 * Option key storing page-view and visitor counts for the current week.
	 *
	 * @var string
	 */
	private $current_week_option = 'optn_visitor_stats_current_week';
	/**
	 * Option key storing the preceding week's page-view and visitor counts.
	 *
	 * @var string
	 */
	private $previous_week_option = 'optn_visitor_stats_previous_week';

	const DEF_VALUE = array(
		'visitors'  => 0,
		'pageviews' => 0,
	);

	/**
	 * Schedules the weekly stats rotation and connects its event callback.
	 * The event is created only when no rotation is already scheduled.
	 *
	 * @return void
	 */
	public function schedule_rotation() {
		if ( ! wp_next_scheduled( 'opnt_rotate_visitor_stats' ) ) {
			wp_schedule_event( strtotime( 'next monday' ), 'weekly', 'opnt_rotate_visitor_stats' );
		}

		add_action( 'opnt_rotate_visitor_stats', array( $this, 'rotate_weekly_stats' ) );
	}

	/**
	 * Counts eligible singular, front-page, and posts-page requests.
	 * Admin, JSON, AJAX, cron, and crawler requests are ignored. Page views count
	 * once per request, while the weekly cookie limits visitor counts to one per
	 * browser until the next Monday.
	 *
	 * @return void
	 */
	public function track_visit() {
		if ( is_admin() ||
			wp_is_json_request() ||
			wp_doing_ajax() ||
			wp_doing_cron() ) {
			return;
		}

		if ( preg_match( '/bot|crawl|slurp|spider/i', $_SERVER['HTTP_USER_AGENT'] ?? '' ) ) { // phpcs:ignore
			return;
		}

		if ( is_singular() || is_front_page() || is_home() ) {

			$current_stats = $this->get_current_week_stats();

			static $counted = false;
			if ( ! $counted ) {
				++$current_stats['pageviews'];
				$counted = true;
			}

			if ( $this->is_unique_visitor() ) {
				++$current_stats['visitors'];
			}

			update_option( $this->current_week_option, $current_stats );
		}
	}

	/**
	 * Builds display-ready visitor and page-view totals with week-over-week change.
	 * Totals use the shared number formatter; differences compare current counts
	 * with the previous week's stored counts.
	 *
	 * @return array{visitors: array{total: string, diff: int|float}, pageviews: array{total: string, diff: int|float}} Weekly analytics summary.
	 */
	public function get_stats() {
		$current_stats  = $this->get_current_week_stats();
		$previous_stats = $this->get_previous_week_stats();

		return array(
			'visitors'  => array(
				'total' => Utils::format_number( $current_stats['visitors'] ),
				'diff'  => Utils::get_diff_pct( $previous_stats['visitors'], $current_stats['visitors'] ),
			),
			'pageviews' => array(
				'total' => Utils::format_number( $current_stats['pageviews'] ),
				'diff'  => Utils::get_diff_pct( $previous_stats['pageviews'], $current_stats['pageviews'] ),
			),
		);
	}

	/**
	 * Copies current totals to the previous-week option and resets current totals.
	 * This is the callback scheduled for the weekly rotation event.
	 *
	 * @return void
	 */
	public function rotate_weekly_stats() {
		// Move current week's stats to previous week.
		$current_stats = $this->get_current_week_stats();
		update_option( $this->previous_week_option, $current_stats );

		// Reset current week's stats.
		$new_stats = array(
			'visitors'  => 0,
			'pageviews' => 0,
		);
		update_option( $this->current_week_option, $new_stats );
	}

	/**
	 * Checks the weekly visitor cookie and sets it for a first visit this week.
	 * A missing cookie counts as a unique visitor and expires at the next Monday.
	 *
	 * @return bool Whether this browser has not yet been counted this week.
	 */
	private function is_unique_visitor() {
		$cookie_name = 'opnt_visitor_tracked_weekly';
		if ( ! isset( $_COOKIE[ $cookie_name ] ) ) { // phpcs:ignore
			$expiry = strtotime( 'next monday' ) - time();
			setcookie( $cookie_name, '1', time() + $expiry, COOKIEPATH );
			return true;
		}
		return false;
	}

	/**
	 * Loads current-week totals, initializing both counters when no option exists.
	 *
	 * @return array{visitors: int, pageviews: int} Current weekly counters.
	 */
	private function get_current_week_stats() {
		$stats = get_option( $this->current_week_option );
		if ( ! $stats ) {
			$stats = self::DEF_VALUE;
			update_option( $this->current_week_option, $stats );
		}
		return $stats;
	}

	/**
	 * Loads the previous week's counters or the zero-value defaults.
	 *
	 * @return array{visitors: int, pageviews: int} Previous weekly counters.
	 */
	private function get_previous_week_stats() {
		return get_option(
			$this->previous_week_option,
			self::DEF_VALUE
		);
	}
}
