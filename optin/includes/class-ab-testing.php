<?php // phpcs:ignore

namespace OPTN\Includes;

use OPTN\Includes\Dto\CreateAbTestDto;
use OPTN\Includes\Dto\UpdateAbTestDto;
use OPTN\Includes\Utils\Utils;

/**
 * AB Testing
 */
class AbTesting {

	/**
	 * Unique prefix for our cron hooks to avoid collisions.
	 */
	const CRON_HOOK_PREFIX = 'optn_abt_';

	/**
	 * The specific cron hook for winner selection.
	 */
	const WINNER_SELECTION_HOOK = self::CRON_HOOK_PREFIX . 'select_winner';

	const DRAFT_STATUS  = 0;
	const ACTIVE_STATUS = 1;
	const END_STATUS    = 2;

	/**
	 * Map of status values to their integer representations.
	 *
	 * @var array
	 */
	public static $status_map = array(
		'draft'  => self::DRAFT_STATUS,
		'active' => self::ACTIVE_STATUS,
		'end'    => self::END_STATUS,
	);

	/**
	 * Map of status values to their integer representations.
	 *
	 * @var array
	 */
	private static $status_map_flip;

	/**
	 * Map of type values to their integer representations.
	 *
	 * @var array
	 */
	private static $type_map = array(
		'manual'    => 0,
		'automatic' => 1,
	);

	/**
	 * Map of type values to their integer representations.
	 *
	 * @var array
	 */
	private static $type_map_flip;

	/**
	 * Map of metrics to their integer representations.
	 *
	 * @var array
	 */
	private static $metrics_map = array(
		'conv'  => 0,
		'cr'    => 1,
		'leads' => 2,
		'rev'   => 3,
	);

	/**
	 * Db
	 *
	 * @var \OPTN\Includes\Db
	 */
	private $db;

	/**
	 * Map of metrics to their integer representations.
	 *
	 * @var array
	 */
	private static $metrics_map_flip;

	/**
	 * Constructor. Sets up the WordPress action for our cron hook.
	 */
	public function __construct() {

		$this->db = Db::get_instance();

		add_action( self::WINNER_SELECTION_HOOK, array( $this, 'handle_scheduled_winner_selection' ), 10, 1 );

		add_action( 'optn_abt_created', array( $this, 'test_created' ), 10, 2 );
		add_action( 'optn_abt_updated', array( $this, 'test_updated' ), 10, 1 );
		add_action( 'optn_abt_before_delete', array( $this, 'test_deleted' ), 10, 1 );
	}

	/**
	 * Action for when a test is created.
	 *
	 * @param int             $id Test ID.
	 * @param CreateAbTestDto $dto Test data.
	 * @return void
	 */
	public function test_created( $id, CreateAbTestDto $dto ) {
		if ( 'automatic' === $dto->type && 'active' === $dto->status ) {
			$this->schedule_winner_selection( $id, $dto->started_at, $dto->duration );
		}

		if ( 'active' === $dto->status ) {
			self::create_variant_view_history( $id );
		}
	}

	/**
	 * Action for when a test is updated.
	 *
	 * @param UpdateAbTestDto $dto Test data.
	 * @return void
	 */
	public function test_updated( UpdateAbTestDto $dto ) {
		// Reschedule if duration is updated.
		if (
			'automatic' === $dto->type &&
			$dto->update_duration &&
			'active' === $dto->status
		) {
			$this->schedule_winner_selection( $dto->id, $dto->started_at, $dto->duration );
		}

		// Unschedule if type is changed to manual.
		elseif ( 'manual' === $dto->type ) {
			$this->unschedule_winner_selection( $dto->id );
		}
	}

	/**
	 * Action for when a test is deleted.
	 *
	 * @param int $id Test ID.
	 * @return void
	 */
	public function test_deleted( $id ) {
		$this->unschedule_winner_selection( $id );
		self::delete_variant_view_history( $id );
	}

	/**
	 * Handles manual winner selection.
	 *
	 * @param int $test_id A/B Test id.
	 * @param int $optin_id optin id.
	 * @return boolean True on success, false on failure.
	 */
	public static function handle_manual_winner_selection( int $test_id, int $optin_id ) {
		/**
		 * DB Instance
		 *
		 * @var \OPTN\Includes\Db $db
		 */
		$db = Db::get_instance();

		$abtest = $db->get_ab_test( $test_id );

		// Do nothing if the test does not exist.
		if ( empty( $abtest ) ) {
			return false;
		}

		// Do nothing if the test is not active.
		if ( ! isset( $abtest['status'] ) || 'active' !== $abtest['status'] ) {
			return false;
		}

		// Do nothing if the optins list is empty.
		if ( ! isset( $abtest['optins'] ) || count( $abtest['optins'] ) < 1 ) {
			return false;
		}

		$optin_ids = array_map(
			function ( $optin ) {
				return $optin['id'];
			},
			$abtest['optins']
		);

		if ( ! in_array( $optin_id, $optin_ids ) ) { // phpcs:ignore
			return false;
		}

		self::handle_end_actions( (int) $abtest['id'], (int) $optin_id, $optin_ids );

		return true;
	}

	/**
	 * Handles End action after a winner has been selected.
	 *
	 * @param int   $test_id A/B Test id.
	 * @param int   $winner_id winner optin id.
	 * @param array $optin_ids all optin ids in the test.
	 */
	public static function handle_end_actions( $test_id, $winner_id, $optin_ids ) {
		/**
		 * DB Instance
		 *
		 * @var \OPTN\Includes\Db $db
		 */
		$db = Db::get_instance();

		global $wpdb;

		// Enabling winner optin and removing it from trash.
		$wpdb->update(
			$db->optins_table,
			array(
				'enabled'    => true,
				'deleted_at' => null,
			),
			array( 'id' => $winner_id )
		);

		// Disable loser optins, but not the ones that are in other ACTIVE A/B tests.
		$loser_ids = array_filter(
			$optin_ids,
			function ( $id ) use ( $winner_id ) {
				return intval( $id ) !== $winner_id;
			}
		);

		$loser_ids_str = implode( ',', $loser_ids );

		$draft_sql =
			"UPDATE {$db->optins_table} c
				LEFT JOIN {$db->ab_test_variants_table} v
					ON v.optin_id = c.id AND v.test_id != {$test_id}
				LEFT JOIN {$db->ab_tests_table} t
					ON t.id = v.test_id
			SET c.enabled = 0
			WHERE 
				c.id IN ( {$loser_ids_str} ) AND 
				(
					v.id IS NULL OR 
					t.status != " . self::ACTIVE_STATUS . '
				)';

		$wpdb->query( $draft_sql ); // phpcs:ignore

		// Generating stats and saving them.
		$res              = $db->get_optin_stats_by_ids( $optin_ids );
		$res['winner_id'] = $winner_id;
		$res['optin_ids'] = $optin_ids;

		$wpdb->update(
			$db->ab_tests_table,
			array(
				'status' => self::END_STATUS,
				'result' => wp_json_encode( $res ),
			),
			array( 'id' => $test_id )
		);
	}

	/**
	 * Schedules the winner selection event for a specific A/B test.
	 *
	 * @param int    $test_id The ID of the A/B test.
	 * @param string $start_date The start date of the test.
	 * @param int    $duration_days The number of days after which the winner should be selected.
	 * @return bool True on success, false on failure.
	 */
	public function schedule_winner_selection( $test_id, $start_date, $duration_days ) {
		if ( ! is_numeric( $test_id ) || $test_id <= 0 ) {
			return false;
		}

		if ( ! is_numeric( $duration_days ) || $duration_days <= 0 ) {
			return false;
		}

		$this->unschedule_winner_selection( $test_id );

		$timestamp = strtotime( "+{$duration_days} days", strtotime( $start_date ) );
		$args      = array( 'test_id' => (int) $test_id );

		return true === wp_schedule_single_event( $timestamp, self::WINNER_SELECTION_HOOK, $args );
	}

	/**
	 * Unschedules the winner selection event for a specific A/B test.
	 * Useful if a test is manually stopped, deleted, or its duration is changed.
	 *
	 * @param int $test_id The ID of the A/B test.
	 */
	public function unschedule_winner_selection( $test_id ) {
		if ( ! is_numeric( $test_id ) || $test_id <= 0 ) {
			return;
		}
		$args = array( 'test_id' => (int) $test_id );
		wp_clear_scheduled_hook( self::WINNER_SELECTION_HOOK, $args );
	}

	/**
	 * Handles the cron event when it's time to select a winner.
	 * This is the callback function for the WP-Cron job.
	 *
	 * @param int $test_id A/B Test id.
	 */
	public function handle_scheduled_winner_selection( int $test_id ) {
		if ( ! is_numeric( $test_id ) ) {
			return;
		}

		$test_id = (int) $test_id;

		$abtest = $this->db->get_ab_test( $test_id );

		// Do nothing if the test does not exist.
		if ( empty( $abtest ) ) {
			return;
		}

		// Do nothing if the test is not active.
		if ( ! isset( $abtest['status'] ) || 'active' !== $abtest['status'] ) {
			return;
		}

		// Do nothing if the test is not automatic.
		if ( ! isset( $abtest['type'] ) || 'automatic' !== $abtest['type'] ) {
			return;
		}

		// Do nothing if the optins list is empty.
		if ( ! isset( $abtest['optins'] ) || count( $abtest['optins'] ) < 1 ) {
			return;
		}

		$this->perform_winner_selection_logic( $abtest );
	}

	/**
	 * Winner selection logic.
	 *
	 * @param array $abtest data.
	 */
	private function perform_winner_selection_logic( $abtest ) {
		$ids = array_map(
			function ( $optin ) {
				return intval( $optin['id'] );
			},
			$abtest['optins']
		);

		sort( $ids );

		$winner_id = null;

		// Determining winner.
		switch ( $abtest['metric'] ) {
			case 'leads':
				$winner_id = $this->get_optin_id_by_leads( $ids );
				break;
			case 'cr':
				$winner_id = $this->get_optin_id_by_cr( $ids );
				break;
			case 'conv':
				$winner_id = $this->get_optin_id_by_conversions( $ids );
				break;
			case 'rev':
				$winner_id = $this->get_optin_id_by_revenue( $ids );
				break;
		}

		if ( empty( $winner_id ) ) {
			$winner_id = $ids[0];
		}

		self::handle_end_actions( (int) $abtest['id'], (int) $winner_id, $ids );
	}

	/**
	 * Get the ID of the optin with highest total revenue for a given set of optins.
	 *
	 * @param array $ids optin ids.
	 * @return int
	 */
	private function get_optin_id_by_revenue( $ids ) {
		global $wpdb;

		$ids_str = implode( ',', $ids );

		$sql =
			"SELECT 
				i.conv_id as `id`,
				i.order_type,
				i.order_id
			FROM {$this->db->interactions_table} i
			WHERE 
				i.conv_id IN ( {$ids_str} ) AND
				i.order_id IS NOT NULL AND
				i.order_type IS NOT NULL";

		$data = $wpdb->get_results( $sql ); // phpcs:ignore

		$winner_id   = null;
		$max_revenue = PHP_INT_MIN;

		foreach ( $data as $d ) {
			$rev = Utils::get_revenue( $d->order_type, $d->order_id );
			if ( $max_revenue < $rev ) {
				$max_revenue = $rev;
				$winner_id   = $d->id;
			}
		}

		return intval( $winner_id );
	}

	/**
	 * Get the ID of the optin with highest total conversions for a given set of optins.
	 *
	 * @param array $ids optin ids.
	 * @return int
	 */
	private function get_optin_id_by_conversions( $ids ) {
		global $wpdb;

		$ids_str = implode( ',', $ids );

		$sql =
			"SELECT 
				i.conv_id as `id`,
				COUNT(*) AS `views`,
				SUM((i.click = TRUE) + (i.order_id IS NOT NULL)) AS `conversions`
			FROM {$this->db->interactions_table} i
			WHERE i.conv_id IN ( {$ids_str} )
			GROUP BY i.conv_id
			ORDER BY `conversions` DESC, `views` ASC, `id` ASC
			LIMIT 1";

		$winner_id = $wpdb->get_var( $sql ); // phpcs:ignore

		return intval( $winner_id );
	}

	/**
	 * Get the ID of the optin with highest leads collected for a given set of optins.
	 *
	 * @param array $ids optin ids.
	 * @return int
	 */
	private function get_optin_id_by_leads( $ids ) {
		global $wpdb;

		$ids_str = implode( ',', $ids );

		$sql =
			"SELECT 
				l.conv_id as `id`,
				COUNT(*) AS `leads`
			FROM {$this->db->leads_table} l
			WHERE l.conv_id IN ({$ids_str})
			GROUP BY l.conv_id
			ORDER BY `leads` DESC, `id` ASC
			LIMIT 1";

		$winner_id = $wpdb->get_var( $sql ); // phpcs:ignore

		return intval( $winner_id );
	}

	/**
	 * Get the ID of the optin with highest conversion rate for a given set of optins.
	 *
	 * @param array $ids optin ids.
	 * @return int
	 */
	private function get_optin_id_by_cr( $ids ) {
		global $wpdb;

		$ids_str = implode( ',', $ids );

		$sql =
			"SELECT 
				i.conv_id as `id`,
				COUNT(*) AS `views`,
				SUM((i.click = TRUE) + (i.order_id IS NOT NULL)) / COUNT(*) AS `cr`
			FROM {$this->db->interactions_table} i
			WHERE i.conv_id IN ({$ids_str})
			GROUP BY i.conv_id";

		$results = $wpdb->get_results( $sql ); // phpcs:ignore

		if ( empty( $results ) ) {
			return 0;
		}

		$scored = array();
		$k      = 100; // Smoothing factor.

		foreach ( $results as $row ) {
			$cr    = floatval( $row->cr );
			$views = intval( $row->views );

			$score = ( $cr * $views ) / ( $views + $k );

			$scored[] = array(
				'id'    => $row->id,
				'views' => $views,
				'cr'    => $cr,
				'score' => $score,
			);
		}

		usort(
			$scored,
			function ( $a, $b ) {
				$res = $b['score'] <=> $a['score'];

				if ( 0 === $res ) {
					return intval( $a['id'] ) <=> intval( $b['id'] );
				}

				return $res;
			}
		);

		return $scored[0]['id'];
	}

	/**
	 * Process ab test
	 *
	 * @param object $posts posts.
	 * @return array
	 */
	public static function check_ab_test( $posts ) {
		$groups    = array();
		$ungrouped = array();

		foreach ( $posts as $post ) {
			if ( isset( $post->test_id ) && ! empty( $post->test_id ) ) {
				$groups[ $post->test_id ][] = $post;
			} else {
				$ungrouped[] = $post;
			}
		}

		$selected_posts = array();

		// Picking a single post from each test group.
		foreach ( $groups as $test_id => $g_posts ) {

			// If the user has previously viewed a variant, we show it again without random selection.
			// This is to make sure no bias is introduced in the winner selection.
			$variant = self::get_previously_viewed_variant( $test_id, $g_posts );

			// If no previous view data found, we select a random variant.
			if ( empty( $variant ) ) {
				$variant = self::get_weighted_rand_post( $g_posts, (int) $test_id );
			}

			if ( ! empty( $variant ) ) {
				self::set_viewed_variant_id( $test_id, $variant->id );
				$selected_posts[] = $variant;
			}
		}

		return array_merge( $ungrouped, $selected_posts );
	}

	/**
	 * Get previously viewed variant by current user
	 *
	 * @param int   $test_id ab test id.
	 * @param array $variants variants.
	 * @return object|null
	 */
	private static function get_previously_viewed_variant( $test_id, $variants ) {
		$user_hash = Utils::get_user_hash();

		if ( empty( $user_hash ) ) {
			return null;
		}

		$key          = 'optn_abt_view_history_' . $test_id;
		$view_history = get_option( $key, array() );

		$var_id = isset( $view_history[ $user_hash ] ) ? $view_history[ $user_hash ] : null;

		if ( empty( $var_id ) ) {
			return null;
		}

		foreach ( $variants as $variant ) {
			if ( (int) $variant->id === (int) $var_id ) {
				return $variant;
			}
		}

		return null;
	}

	/**
	 * Set viewed variant id for a user
	 *
	 * @param int $test_id ab test id.
	 * @param int $optin_id optin id.
	 * @return void
	 */
	private static function set_viewed_variant_id( $test_id, $optin_id ) {
		$key          = 'optn_abt_view_history_' . $test_id;
		$view_history = get_option( $key, array() );

		$user_hash = Utils::get_user_hash();

		if ( empty( $user_hash ) ) {
			return;
		}

		$view_history[ $user_hash ] = (int) $optin_id;

		update_option( $key, $view_history );
	}

	/**
	 * Create variant view history
	 *
	 * @param [type] $test_id ab test id.
	 * @return void
	 */
	private static function create_variant_view_history( $test_id ) {
		$key              = 'optn_abt_view_history_' . $test_id;
		$existing_history = get_option( $key, array() );
		update_option( $key, $existing_history );
	}

	/**
	 * Delete variant view history
	 *
	 * @param string $test_id ab test id.
	 * @return void
	 */
	private static function delete_variant_view_history( $test_id ) {
		$key = 'optn_abt_view_history_' . $test_id;
		delete_option( $key );
	}

	/**
	 * Get weighted random post
	 *
	 * @param array    $posts posts.
	 * @param int|null $test_id ab test id.
	 * @return object|null
	 */
	private static function get_weighted_rand_post( $posts, $test_id = null ) {
		if ( empty( $posts ) ) {
			return null;
		}

		$weights      = array();
		$total_weight = 0.0;

		foreach ( $posts as $index => $post ) {
			$weight            = isset( $post->t_dist ) ? floatval( $post->t_dist ) : 0.0;
			$weight            = max( 0.0, $weight );
			$weights[ $index ] = $weight;
			$total_weight     += $weight;
		}

		// If no valid weights are provided, use unbiased uniform selection.
		if ( $total_weight <= 0 ) {
			return self::get_uniform_rand_post( $posts, $test_id );
		}

		$fraction = self::get_seeded_fraction( $test_id );
		if ( null === $fraction ) {
			$fraction = wp_rand( 0, 1000000 ) / 1000000;
		}

		$threshold  = $fraction * $total_weight;
		$cumulative = 0.0;

		foreach ( $posts as $index => $post ) {
			$weight = isset( $weights[ $index ] ) ? $weights[ $index ] : 0.0;
			if ( $weight <= 0 ) {
				continue;
			}

			$cumulative += $weight;
			if ( $threshold < $cumulative ) {
				return $post;
			}
		}

		return self::get_uniform_rand_post( $posts, $test_id );
	}

	/**
	 * Unbiased uniform fallback selector.
	 *
	 * @param array    $posts posts.
	 * @param int|null $test_id ab test id.
	 * @return object|null
	 */
	private static function get_uniform_rand_post( $posts, $test_id = null ) {
		$count = count( $posts );

		if ( $count < 1 ) {
			return null;
		}

		if ( 1 === $count ) {
			return $posts[0];
		}

		$index = self::get_seeded_index( $count, $test_id );

		if ( null === $index ) {
			$index = wp_rand( 0, $count - 1 );
		}

		return $posts[ $index ];
	}

	/**
	 * Returns a deterministic [0,1] fraction when a user hash exists.
	 *
	 * @param int|null $test_id ab test id.
	 * @return float|null
	 */
	private static function get_seeded_fraction( $test_id = null ) {
		$user_hash = Utils::get_user_hash();

		if ( empty( $user_hash ) ) {
			return null;
		}

		$seed     = $user_hash . '|abt|' . strval( $test_id ) . '|weighted';
		$hash     = sprintf( '%u', crc32( $seed ) );
		$max_uint = 4294967295;
		$fraction = floatval( $hash ) / $max_uint;

		return min( max( $fraction, 0.0 ), 1.0 );
	}

	/**
	 * Returns a deterministic index when a user hash exists.
	 *
	 * @param int      $count posts count.
	 * @param int|null $test_id ab test id.
	 * @return int|null
	 */
	private static function get_seeded_index( $count, $test_id = null ) {
		$user_hash = Utils::get_user_hash();

		if ( empty( $user_hash ) || $count < 1 ) {
			return null;
		}

		$seed = $user_hash . '|abt|' . strval( $test_id ) . '|uniform';
		$hash = sprintf( '%u', crc32( $seed ) );

		return intval( $hash ) % $count;
	}

	/**
	 * Encodes a status value to its integer representation.
	 *
	 * @param string $status status.
	 * @return int
	 */
	public static function encode_status( $status ) {
		return isset( self::$status_map[ $status ] ) ? self::$status_map[ $status ] : 0;
	}

	/**
	 * Decodes a status integer representation to its string value.
	 *
	 * @param int $status status.
	 * @return string
	 */
	public static function decode_status( $status ) {
		if ( ! isset( self::$status_map_flip ) ) {
			self::$status_map_flip = array_flip( self::$status_map );
		}

		return isset( self::$status_map_flip[ $status ] ) ? self::$status_map_flip[ $status ] : 'draft';
	}

	/**
	 * Encodes a type value to its integer representation.
	 *
	 * @param string $type type.
	 * @return int
	 */
	public static function encode_type( $type ) {
		return isset( self::$type_map[ $type ] ) ? self::$type_map[ $type ] : 0;
	}

	/**
	 * Decodes a type integer representation to its string value.
	 *
	 * @param int $type type.
	 * @return string
	 */
	public static function decode_type( $type ) {
		if ( ! isset( self::$type_map_flip ) ) {
			self::$type_map_flip = array_flip( self::$type_map );
		}

		return isset( self::$type_map_flip[ $type ] ) ? self::$type_map_flip[ $type ] : 'manual';
	}

	/**
	 * Encodes a metric value to its integer representation.
	 *
	 * @param string $metric metric.
	 * @return int
	 */
	public static function encode_metric( $metric ) {
		return isset( self::$metrics_map[ $metric ] ) ? self::$metrics_map[ $metric ] : 0;
	}

	/**
	 * Decodes a metric integer representation to its string value.
	 *
	 * @param int $metric metric.
	 * @return string
	 */
	public static function decode_metric( $metric ) {
		if ( ! isset( self::$metrics_map_flip ) ) {
			self::$metrics_map_flip = array_flip( self::$metrics_map );
		}

		return isset( self::$metrics_map_flip[ $metric ] ) ? self::$metrics_map_flip[ $metric ] : 'conv';
	}
}
