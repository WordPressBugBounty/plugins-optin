<?php // phpcs:ignore

namespace OPTN\Includes\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Class for counting visitors.
 */
class VisitorCount {

	use \OPTN\Includes\Traits\Singleton;

	private $current_week_option  = 'optn_visitor_stats_current_week';
	private $previous_week_option = 'optn_visitor_stats_previous_week';

	const DEF_VALUE = array(
		'visitors'  => 0,
		'pageviews' => 0,
	);

	public function schedule_rotation() {
		if ( ! wp_next_scheduled( 'opnt_rotate_visitor_stats' ) ) {
			wp_schedule_event( strtotime( 'next monday' ), 'weekly', 'opnt_rotate_visitor_stats' );
		}

		add_action( 'opnt_rotate_visitor_stats', array( $this, 'rotate_weekly_stats' ) );
	}

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

	public function rotate_weekly_stats() {
		// Move current week's stats to previous week
		$current_stats = $this->get_current_week_stats();
		update_option( $this->previous_week_option, $current_stats );

		// Reset current week's stats
		$new_stats = array(
			'visitors'  => 0,
			'pageviews' => 0,
		);
		update_option( $this->current_week_option, $new_stats );
	}

	private function is_unique_visitor() {
		$cookie_name = 'opnt_visitor_tracked_weekly';
		if ( ! isset( $_COOKIE[ $cookie_name ] ) ) { // phpcs:ignore
			$expiry = strtotime( 'next monday' ) - time();
			setcookie( $cookie_name, '1', time() + $expiry, COOKIEPATH );
			return true;
		}
		return false;
	}

	private function get_current_week_stats() {
		$stats = get_option( $this->current_week_option );
		if ( ! $stats ) {
			$stats = self::DEF_VALUE;
			update_option( $this->current_week_option, $stats );
		}
		return $stats;
	}

	private function get_previous_week_stats() {
		return get_option(
			$this->previous_week_option,
			self::DEF_VALUE
		);
	}
}
