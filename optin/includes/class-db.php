<?php //phpcs:ignore

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase, Squiz.Commenting.BlockComment.HasEmptyLineBefore

// Suppress database lint findings in this persistence layer as requested; query behavior is unchanged.
// phpcs:disable PluginCheck.Security.DirectDB, WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery

namespace OPTN\Includes;

use OPTN\Includes\Utils\Os;
use OPTN\Includes\Utils\Device;
use OPTN\Includes\Utils\CountryDecoder;
use OPTN\Includes\Utils\Utils;
use OPTN\Includes\Utils\Templates;

defined( 'ABSPATH' ) || exit;
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

/**
 * Owns plugin database tables and persistence operations for campaigns and analytics.
 *
 * Creates and queries opt-in, lead, interaction, and integration records, and translates
 * stored campaign settings into the forms consumed by the admin and frontend layers.
 */
class Db {
	use \OPTN\Includes\Traits\Singleton;

	public $optins_table; // phpcs:ignore
	public $leads_table; // phpcs:ignore
	public $interactions_table; // phpcs:ignore
	public $integrations_table; // phpcs:ignore
	public $install_callbacks; // phpcs:ignore
	private $prefix = 'optn_'; // phpcs:ignore

	/**
	 * Constructor
	 */
	private function __construct() {
		global $wpdb;

		$this->optins_table       = $wpdb->prefix . $this->prefix . 'conversions'; // phpcs:ignore
		$this->interactions_table = $wpdb->prefix . $this->prefix . 'interactions'; // phpcs:ignore
		$this->leads_table        = $wpdb->prefix . $this->prefix . 'leads'; // phpcs:ignore
		$this->integrations_table = $wpdb->prefix . $this->prefix . 'integrations'; // phpcs:ignore

		$this->install_callbacks = array(
			'1.0.0' => 'migrate_1_0_0',
			'1.4.0' => 'migrate_1_4_0',
			'1.4.2' => 'migrate_1_4_2',
			'1.4.8' => 'migrate_1_4_8',
		);

		uasort( $this->install_callbacks, 'version_compare' );
	}

	/**
	 * Installs plugin
	 *
	 * @return void
	 */
	public function maybe_install() {
		$needs_update = ! empty( array_key_last( $this->install_callbacks ) ) && version_compare( (string) $this->get_db_version(), array_key_last( $this->install_callbacks ), '<' );

		$is_updating = get_option( 'optn_updating_db' );

		if ( ! $needs_update || $is_updating ) {
			return;
		}

		update_option( 'optn_updating_db', true, true );

		try {
			$this->install();
		} catch ( \Exception $e ) {
			Utils::log_error( $e->getMessage() );
		} finally {
			update_option( 'optn_updating_db', false );
		}
	}

	/**
	 * Installs plugin
	 *
	 * @return void
	 */
	public function install() {

		$callbacks = $this->get_update_callbacks();

		$latest_db_version = null;
		$success           = true;

		foreach ( $callbacks as $version => $callback ) {

			if ( true !== call_user_func( array( $this, $callback ) ) ) {
				$success = false;
				Utils::log_error( 'Update callback failed: ' . $callback );
				break;
			}

			$latest_db_version = $version;
		}

		if ( $success && ! empty( $latest_db_version ) ) {
			$this->set_db_version( $latest_db_version );
		}
	}


	/**
	 * Creates all DB table
	 *
	 * @return boolean is success.
	 */
	public function migrate_1_0_0() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$res = true;

		// Back-compat gates for TIMESTAMP behavior and JSON support.
		$optins_ts = "\n\t\t\t\t`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n";
		if ( $this->supports_multi_ts_current() ) {
			$optins_ts .= "\t\t\t\t`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n";
		} else {
			$optins_ts .= "\t\t\t\t`updated_at` TIMESTAMP NULL DEFAULT NULL,\n";
		}
		$optins_ts .= "\t\t\t\t`deleted_at` TIMESTAMP NULL DEFAULT NULL,\n";

		$leads_data_col = $this->supports_json_type() ? '`data` JSON DEFAULT NULL,' : '`data` LONGTEXT DEFAULT NULL,';

		$res &= maybe_create_table(
			$this->optins_table,
			"CREATE TABLE {$this->optins_table} (
				`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
				`type` TINYINT UNSIGNED NOT NULL,
				`title` VARCHAR(255) NOT NULL,
				`data` LONGTEXT DEFAULT NULL,
				`enabled` BOOLEAN NOT NULL DEFAULT false,
				`end_datetime` TIMESTAMP NULL,
				`traffic_url` VARCHAR(2048) NULL DEFAULT NULL,
				`traffic_channel` MEDIUMINT UNSIGNED DEFAULT NULL,
				`visitor` MEDIUMINT UNSIGNED NOT NULL,
				`device` SMALLINT UNSIGNED NOT NULL,
				`os` MEDIUMINT UNSIGNED NOT NULL,
				`embed` VARCHAR(255) DEFAULT NULL,

				{$optins_ts}

				PRIMARY KEY (`id`)
			) $charset_collate;"
		);

		$res &= maybe_create_table(
			$this->interactions_table,
			"CREATE TABLE {$this->interactions_table} (
				`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
				`conv_id` INT UNSIGNED NOT NULL,
				`click` BOOLEAN DEFAULT FALSE,
				`order_type` TINYINT UNSIGNED DEFAULT NULL,
				`order_id` INT UNSIGNED DEFAULT NULL,
				`user_id` INT UNSIGNED NULL,
				`ip_address` INT UNSIGNED NULL,
				`country` varchar(3) DEFAULT NULL,
				`path` varchar(255) DEFAULT NULL,
				`device` SMALLINT NULL,
				`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`updated_at` TIMESTAMP NULL,
				`purchased_at` TIMESTAMP NULL,

				PRIMARY KEY (`id`),
				FOREIGN KEY (`conv_id`) REFERENCES {$this->optins_table} (`id`) ON DELETE CASCADE,

				INDEX `idx_created_at` (`created_at`),
				INDEX `idx_updated_at` (`updated_at`)
			) $charset_collate;"
		);

		$res &= maybe_create_table(
			$this->leads_table,
			"CREATE TABLE {$this->leads_table} (
				`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
				`conv_id` INT UNSIGNED DEFAULT NULL,
				`email` VARCHAR(255) DEFAULT NULL,
				`name` VARCHAR(255) DEFAULT NULL,
				{$leads_data_col}
				`integration` VARCHAR(255) NOT NULL,
				`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

				PRIMARY KEY  (id),
				FOREIGN KEY (`conv_id`) REFERENCES {$this->optins_table} (`id`) ON DELETE SET NULL,

				INDEX `idx_integration` (`integration`),
				INDEX `idx_created_at` (`created_at`)
			) $charset_collate;"
		);

		$res &= maybe_create_table(
			$this->integrations_table,
			"CREATE TABLE {$this->integrations_table} (
				`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
				`type` VARCHAR(255) NOT NULL,
				`title` VARCHAR(255) NOT NULL,
				`data` TEXT NOT NULL,

				PRIMARY KEY (`id`),
				INDEX `type_idx` (`type`)
			) $charset_collate;"
		);

		return (bool) $res;
	}


	/**
	 * Install 1.4.0
	 *
	 * @return boolean is success.
	 */
	public function migrate_1_4_0() {
		return true; }

	/**
	 * Install 1.4.2
	 * Addes `int_type` column to integrations table
	 *
	 * @return boolean is success.
	 */
	public function migrate_1_4_2() {
		global $wpdb;

		$cols = $wpdb->get_col( "DESCRIBE {$this->integrations_table}" ); // phpcs:ignore

		if ( in_array( 'int_type', $cols, true ) ) {
			return true;
		}

		$sql = "ALTER TABLE {$this->integrations_table}
				ADD `int_type` VARCHAR(50) NOT NULL DEFAULT 'mail'
				AFTER `id`";

		$res = $wpdb->query( $sql ); // phpcs:ignore

		return false !== $res;
	}

	/**
	 * Install 1.4.8
	 * Addes `data` column to interactions table
	 *
	 * @return boolean is success.
	 */
	public function migrate_1_4_8() {
		global $wpdb;

		$cols = $wpdb->get_col( "DESCRIBE {$this->interactions_table}" ); // phpcs:ignore

		if ( in_array( 'data', $cols, true ) ) {
			return true;
		}

		$sql = "ALTER TABLE {$this->interactions_table}
				ADD `data` LONGTEXT DEFAULT NULL
				AFTER `user_id`";

		$res = $wpdb->query( $sql ); // phpcs:ignore

		return false !== $res;
	}

	/**
	 * Adds an integration
	 *
	 * @param string      $title title of the integration.
	 * @param string      $type integration type.
	 * @param array       $data integration data.
	 * @param int|null    $id id of integration to update (optional).
	 * @param string|null $int_type integration type (optional).
	 * @return boolean
	 */
	public function add_integration( $title, $type, $data, $id, $int_type = null ) {

		global $wpdb;

		$data = array(
			'title'    => $title,
			'type'     => $type,
			'int_type' => $int_type ?? 'mail',
			'data'     => wp_json_encode( $data ),
		);

		if ( ! empty( $id ) ) {
			$res = $wpdb->update( // phpcs:ignore
				$this->integrations_table, // phpcs:ignore
				$data,
				array(
					'id' => $id,
				)
			);
		} else {
			$res = $wpdb->insert( // phpcs:ignore
				$this->integrations_table, // phpcs:ignore
				$data
			);
		}

		do_action( 'optn_compat_purge_cache' );

		return boolval( $res );
	}

	/**
	 * Deletes an integration by id
	 *
	 * @param int $id id of the integration.
	 * @return boolean
	 */
	public function delete_integration( $id ) {

		global $wpdb;

		$res = $wpdb->delete( // phpcs:ignore
			$this->integrations_table, // phpcs:ignore
			array(
				'id' => $id,
			)
		);

		do_action( 'optn_compat_purge_cache' );

		return boolval( $res );
	}

	/**
	 * Gets an integration by id
	 *
	 * @param int|null    $id id of the integration.
	 * @param boolean     $hide_api truncate api key.
	 * @param string|null $type Integration type.
	 * @return array
	 */
	public function get_integration( $id, $hide_api = true, $type = null ) {
		global $wpdb;

		$sql = "SELECT * FROM {$this->integrations_table}";

		$conds = array();

		if ( ! empty( $id ) ) {
			$conds[] = $wpdb->prepare( '`id` = %d', $id );
		}

		if ( 'all' !== $type ) {
			$type    = empty( $type ) ? 'mail' : $type;
			$conds[] = $wpdb->prepare( '`int_type` = %s', $type );
		}

		if ( count( $conds ) > 0 ) {
			$sql .= ' WHERE ' . implode( ' AND ', $conds );
		}

		$res = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore

		if ( ! empty( $res ) ) {
			foreach ( $res as &$d ) {
				$d['data'] = json_decode( $d['data'], true );

				// Truncating API Key.
				if ( $hide_api && isset( $d['data']['apiKey'] ) && ! empty( $d['data']['apiKey'] ) ) {

					if ( strlen( $d['data']['apiKey'] ) < 5 ) {
						$d['data']['apiKey'] = '****';
					}

					$d['data']['apiKey'] = str_pad(
						substr( $d['data']['apiKey'], -4 ),
						strlen( $d['data']['apiKey'] ),
						'*',
						STR_PAD_LEFT
					);
				}
			}
		}

		return is_array( $res ) && ! empty( $id ) && isset( $res[0] ) ? $res[0] : $res;
	}

	/**
	 * Get integrations by type
	 *
	 * @param string $type id of the integration.
	 * @return array
	 */
	public function get_integrations_by_type( $type ) {
		global $wpdb;

		$sql = "SELECT * FROM {$this->integrations_table}";

		$sql .= $wpdb->prepare( ' WHERE `type`=%s', $type );

		$res = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore

		if ( ! empty( $res ) ) {
			foreach ( $res as &$d ) {
				$d['data'] = json_decode( $d['data'], true );
			}
		}

		return $res;
	}

	/**
	 * Add lead
	 *
	 * @param array $data lead data.
	 * @return boolean
	 */
	public function add_lead( $data ) {
		global $wpdb;

		$res = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->leads_table, // phpcs:ignore
			array(
				'conv_id'     => $data['conv_id'],
				'email'       => $data['email'],
				'name'        => $data['name'],
				'data'        => wp_json_encode( $data['data'] ),
				'integration' => $data['integration'],
			)
		);

		return boolval( $res );
	}

	/**
	 * Get leads
	 *
	 * @param int $limit limit.
	 * @param int $page page.
	 * @return array
	 */
	public function get_leads( $limit, $page ) {
		global $wpdb;

		$pagination = '';

		if ( $limit > 0 ) {
			$offset     = ( $page - 1 ) * $limit;
			$pagination = $wpdb->prepare( 'LIMIT %d OFFSET %d', $limit, $offset );
		}

		$sql =
			"SELECT l.* , c.title AS `conv_title`
			FROM {$this->leads_table} l
			LEFT JOIN {$this->optins_table} c ON c.id = l.conv_id
			ORDER BY l.created_at DESC
			{$pagination}";

		$raw_data = $wpdb->get_results( $sql ); // phpcs:ignore

		$res = array();

		foreach ( $raw_data as $d ) {
			$data['id']          = $d->id;
			$data['conv_id']     = ! empty( $d->conv_id ) ? $d->conv_id : null;
			$data['conv_title']  = ! empty( $d->conv_id ) && ! empty( $d->conv_title ) ? $d->conv_title : null;
			$data['email']       = ! empty( $d->email ) ? $d->email : null;
			$data['name']        = ! empty( $d->name ) ? $d->name : null;
			$data['created_at']  = $this->get_formatted_date( $d->created_at );
			$data['data']        = ! empty( $d->data ) ? json_decode( $d->data, true ) : null;
			$data['integration'] = $this->get_decoded_integration_type( $d->integration );

			$res[] = $data;
		}

		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->leads_table}" ); // phpcs:ignore

		$total = ceil( $count / $limit );

		return array(
			'leads' => $res,
			'total' => $total,
		);
	}

	/**
	 * Delete leads
	 *
	 * @param int[] $ids lead ids.
	 * @return int
	 */
	public function delete_leads( $ids ) {
		global $wpdb;
		$res = null;

		$ids = array_map(
			function ( $id ) {
				return esc_sql( $id );
			},
			$ids
		);

		$ids = implode( ',', $ids );

		$sql = "DELETE FROM {$this->leads_table} WHERE `id` IN ( {$ids} )";
		$res = $wpdb->query( $sql ); // phpcs:ignore

		if ( is_int( $res ) && $res < 1 ) {
			return array( 'error' => 404 );
		}

		if ( false === $res ) {
			return array( 'error' => 500 );
		}

		do_action( 'optn_compat_purge_cache' );

		return 200;
	}

	/**
	 * Get Optins
	 *
	 * @param string $status optin status.
	 * @param int    $limit limit.
	 * @param int    $page page.
	 * @param string $search search string.
	 * @param array  $filter extra filter args.
	 * @param array  $sort   sorting args.
	 * @return array
	 */
	public function get_posts( $status, $limit, $page, $search, $filter = array(), $sort = array() ) {
		global $wpdb;

		$sortable_columns = array(
			'id'           => 'c.id',
			'title'        => 'c.title',
			'type'         => 'c.type',
			'enabled'      => 'c.enabled',
			'status'       => 'c.enabled',
			'end_datetime' => 'c.end_datetime',
			'updated_at'   => 'c.updated_at',
		);

		// Stats columns that require PHP sorting.
		$stats_columns = array( 'views', 'conversions', 'cr', 'leads', 'revenue' );

		$sort_by    = isset( $sort['by'] ) ? sanitize_key( $sort['by'] ) : 'id';
		$sort_order = isset( $sort['order'] ) ? strtoupper( $sort['order'] ) : 'DESC';

		$is_stats_sort = in_array( $sort_by, $stats_columns, true );

		if ( ! isset( $sortable_columns[ $sort_by ] ) && ! $is_stats_sort ) {
			$sort_by = 'id';
		}

		$order_column = $is_stats_sort ? 'c.id' : $sortable_columns[ $sort_by ];
		$order_dir    = 'ASC' === $sort_order ? 'ASC' : 'DESC';

		$sql = "SELECT c.`id`, c.`title`, c.`type`, c.`enabled`, c.`end_datetime`, c.`updated_at`, c.`deleted_at`
				FROM {$this->optins_table} c ";

		$conds = array();

		if ( 'draft' === $status ) {
			$conds[] = 'c.enabled = 0';
		} elseif ( 'publish' === $status ) {
			$conds[] = 'c.enabled = 1';
		}

		if ( 'trash' === $status ) {
			$conds[] = 'c.deleted_at IS NOT NULL';
		} else {
			$conds[] = 'c.deleted_at IS NULL';
		}

		if ( isset( $filter['inline'] ) && false === $filter['inline'] ) {
			$conds[] = 'c.type != ' . intval( $this->get_encoded_conv_type( 'inline' ) );
		}

		if ( ! empty( $search ) ) {
			$conds[] = $wpdb->prepare(
				'c.title LIKE %s ',
				'%' . $wpdb->esc_like( $search ) . '%'
			);
		}

		if ( count( $conds ) > 0 ) {
			$sql .= ' WHERE ' . $this->get_cond( $conds );
		}

		$limit = absint( $limit );
		if ( $limit < 1 ) {
			$limit = 10;
		}

		$page = absint( $page );
		if ( $page < 1 ) {
			$page = 1;
		}

		$offset = ( $page - 1 ) * $limit;

		// For stats columns, we need to fetch all posts, sort them, then paginate.
		// For SQL columns, we can use SQL sorting and pagination.
		if ( ! $is_stats_sort ) {
			$sql .= " ORDER BY {$order_column} {$order_dir}";

			if ( 'c.id' !== $order_column ) {
				$sql .= ', c.id DESC';
			}

			$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $limit, $offset );
		} else {
			// For stats sorting, fetch all matching posts without pagination.
			$sql .= ' ORDER BY c.id DESC';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$posts = $wpdb->get_results( $sql, OBJECT_K );

		$post_ids = array_keys( (array) $posts );
		$stats    = array( 'stats' => array() );

		if ( ! empty( $post_ids ) ) {
			$stats = $this->get_optin_stats_by_ids( $post_ids );
		}

		foreach ( $posts as $post ) {
			$post->type       = $this->get_decoded_conv_type2( $post->type );
			$post->updated_at = $this->get_formatted_date( $post->updated_at );
			$post->enabled    = $post->enabled ? true : false;

			if ( ! empty( $post->end_datetime ) ) {
				$post->end_datetime = $this->get_formatted_date( $post->end_datetime );
			}

			if ( ! empty( $post->deleted_at ) ) {
				$post->deleted_at = $this->get_formatted_date( $post->deleted_at );
			}
		}

		foreach ( $stats['stats'] as $stat ) {
			if ( isset( $posts[ $stat['id'] ] ) ) {
				$posts[ $stat['id'] ]->views       = $stat['views'];
				$posts[ $stat['id'] ]->conversions = $stat['conversions'];
				$posts[ $stat['id'] ]->cr          = $stat['cr'];
				$posts[ $stat['id'] ]->leads       = $stat['leads'];
				$posts[ $stat['id'] ]->revenue     = $stat['revenue'];
			}
		}

		// Apply PHP sorting for stats columns.
		if ( $is_stats_sort ) {
			$posts_array = array_values( (array) $posts );

			usort(
				$posts_array,
				function ( $a, $b ) use ( $sort_by, $order_dir ) {
					$a_val = floatval( $a->$sort_by ?? 0 );
					$b_val = floatval( $b->$sort_by ?? 0 );

					if ( $a_val === $b_val ) {
						return 0;
					}

					if ( 'ASC' === $order_dir ) {
						return $a_val < $b_val ? -1 : 1;
					} else {
						return $a_val > $b_val ? -1 : 1;
					}
				}
			);

			// Apply pagination after sorting.
			$posts = array_slice( $posts_array, $offset, $limit );
		} else {
			$posts = array_values( (array) $posts );
		}

		$count       = $this->get_post_status_count( $search );
		$status_key  = isset( $count[ $status ] ) ? intval( $count[ $status ] ) : 0;
		$total_pages = ( 0 === $limit ) ? 0 : (int) ceil( $status_key / $limit );

		return array(
			'posts'        => $posts,
			'count'        => $count,
			'total_pages'  => $total_pages,
			'current_page' => $page,
		);
	}

	/**
	 * Get posts for select dropdown
	 *
	 * @return array List of posts with id and title.
	 */
	public function get_post_for_select() {
		global $wpdb;

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT c.`id` as `value`, c.`title` as `label`
				FROM {$this->optins_table} c", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			)
		);
	}

	/**
	 * Get impressions data
	 *
	 * @param int  $id    Optin ID.
	 * @param int  $days  Number of days.
	 * @param bool $unique Whether to get unique impressions.
	 * @return array Impressions data with total and unique views.
	 */
	public function get_impressions( $id, $days, $unique ) {
		global $wpdb;

		$sql = 'SELECT DATE(i.`created_at`) AS `date`, COUNT(*) as `t_views`,  COUNT(DISTINCT i.`ip_address`) as `u_views`';

		$sql .= " FROM {$this->interactions_table} i";

		$conds = array(
			$wpdb->prepare(
				'i.`created_at` >= (NOW() - INTERVAL %d DAY)',
				$days
			),
		);

		if ( ! empty( $id ) ) {
			$conds[] = $wpdb->prepare( 'i.`conv_id` = %d', $id );
		}

		$sql .= ' WHERE ' . $this->get_cond( $conds );

		$sql .= ' GROUP BY DATE(i.`created_at`) ORDER BY DATE(i.`created_at`) DESC';

		$raw_data = $wpdb->get_results( $wpdb->prepare( $sql ) ); // phpcs:ignore

		$data = array();

		foreach ( $raw_data as $d ) {
			$data[ $d->date ] = array(
				'total'  => $d->t_views,
				'unique' => $d->u_views,
			);
		}

		$res = array(
			'total'  => array(),
			'unique' => array(),
		);

		$periods = Utils::get_past_dates( $days );

		foreach ( $periods as $period ) {
			$res ['total'] [ $period ]  = isset( $data[ $period ]['total'] ) ? intval( $data[ $period ]['total'] ) : 0;
			$res ['unique'] [ $period ] = isset( $data[ $period ]['unique'] ) ? intval( $data[ $period ]['unique'] ) : 0;
		}

		return $res;
	}

	/**
	 * Get impressions by device type
	 *
	 * @param int $id   Optin ID.
	 * @param int $days Number of days.
	 * @return array Device-wise impressions data.
	 */
	public function get_impressions_by_device( $id, $days ) {
		/** Null keeps common analytics; extensions may provide their enriched result. */
		$result = apply_filters( 'optn_impressions_by_device_result', null, $id, $days );
		if ( null !== $result ) {
			return $result; }

		$device_map = array(
			__( 'Desktop', 'optin' ),
			__( 'Tablet', 'optin' ),
			__( 'Mobile', 'optin' ),
		);

		global $wpdb;

		$sql = 'SELECT i.`device` AS `device`, COUNT(*) AS `views`';

		$sql .= " FROM {$this->interactions_table} i";

		$conds = array(
			$wpdb->prepare(
				'i.`created_at` >= (NOW() - INTERVAL %d DAY)',
				$days
			),
		);

		if ( ! empty( $id ) ) {
			$conds[] = $wpdb->prepare( 'i.`conv_id` = %d', $id );
		}

		$sql .= ' WHERE ' . $this->get_cond( $conds );

		$sql .= ' GROUP BY i.`device` ORDER BY i.`device`';

		$data = $wpdb->get_results( $sql ); // phpcs:ignore

		$device_data = array();

		foreach ( $data as $d ) {
			$device_data[ $d->device ] = $d->views;
		}

		$res   = array();
		$total = array_sum( array_column( $data, 'views' ) );

		for ( $i = 0; $i < count( $device_map ); $i++ ) {
			$res[] = array(
				'label' => $device_map[ $i ],
				'value' => isset( $device_data[ $i ] ) ? intval( $device_data[ $i ] ) : 0,
				'pct'   => isset( $device_data[ $i ] ) ? round( ( $device_data[ $i ] / $total ) * 100, 2 ) : 0,
			);
		}

		return $res;
	}

	/**
	 * Get conversions data
	 *
	 * @param int $id   Optin ID.
	 * @param int $days Number of days.
	 * @return array Conversions data by date.
	 */
	public function get_conversions( $id, $days ) {
		global $wpdb;

		$conds = array();

		if ( ! empty( $id ) ) {
			$conds[] = $wpdb->prepare( 'i.`conv_id` = %d', $id );
		}

		$cond_str = '';

		if ( ! empty( $conds ) ) {
			$cond_str = ' AND ' . $this->get_cond( $conds );
		}

		$raw_click_sql =
			"SELECT DATE(i.`updated_at`) AS `date`, COUNT(*) AS `total` 
			FROM {$this->interactions_table} i
			WHERE i.`click` = TRUE AND
				DATE(i.`updated_at`) >= DATE(NOW() - INTERVAL %d DAY) {$cond_str}
			GROUP BY DATE(i.`updated_at`)
			ORDER BY DATE(i.`updated_at`) DESC";

		$raw_click_data = $wpdb->get_results( $wpdb->prepare( $raw_click_sql, $days ) ); // phpcs:ignore

		$raw_sales_sql =
			"SELECT DATE(i.`purchased_at`) AS `date`, COUNT(*) AS `total`
			FROM {$this->interactions_table} i
				WHERE
				i.order_id IS NOT NULL AND
				DATE(i.`purchased_at`) >= DATE(NOW() - INTERVAL %d DAY) {$cond_str}
			GROUP BY DATE(i.`purchased_at`)
			ORDER BY DATE(i.`purchased_at`) DESC";

		$raw_sales_data = $wpdb->get_results( $wpdb->prepare( $raw_sales_sql, $days ) ); //phpcs:ignore

		$click = array();
		$sales = array();

		foreach ( $raw_click_data as $c ) {
			$click[ $c->date ] = $c->total;
		}

		foreach ( $raw_sales_data as $s ) {
			$sales[ $s->date ] = $s->total;
		}

		$periods = Utils::get_past_dates( $days );
		$res     = array();

		foreach ( $periods as $period ) {
			$res[ $period ] =
				( isset( $click[ $period ] ) ? intval( $click[ $period ] ) : 0 ) +
				( isset( $sales[ $period ] ) ? intval( $sales[ $period ] ) : 0 );
		}

		return $res;
	}

	/**
	 * Get sales data
	 *
	 * @param int $id   Optin ID.
	 * @param int $days Number of days.
	 * @return array Sales revenue data by date.
	 */
	public function get_sales( $id, $days ) {
		global $wpdb;

		$conds = array();

		if ( ! empty( $id ) ) {
			$conds[] = "i.`conv_id` = {$id}";
		}

		$cond_str = '';

		if ( ! empty( $conds ) ) {
			$cond_str = ' AND ' . $this->get_cond( $conds );
		}

		$raw_data = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT DATE(i.`purchased_at`) AS `date`, i.`order_type`, i.`order_id` ' .
				" FROM {$this->interactions_table} i " . // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				" WHERE i.`order_id` IS NOT NULL AND i.`purchased_at` IS NOT NULL AND DATE(i.`purchased_at`) >= DATE(NOW() - INTERVAL %d DAY) {$cond_str} GROUP BY DATE(i.`purchased_at`) ORDER BY DATE(i.`purchased_at`) DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$days,
			)
		);

		$data = array();

		foreach ( $raw_data as $c ) {
			$data[ $c->date ] = array(
				'order_type' => $c->order_type,
				'order_id'   => $c->order_id,
			);
		}

		$periods = Utils::get_past_dates( $days );
		$res     = array();

		foreach ( $periods as $period ) {
			$res[ $period ] = isset( $data[ $period ] ) ? floatval( Utils::get_revenue( $data[ $period ]['order_type'], $data[ $period ]['order_id'] ) ) : 0.00;
		}

		return $res;
	}

	/**
	 * Get popular optins
	 *
	 * @param int $days  Number of days.
	 * @param int $limit Results per page.
	 * @param int $page  Current page number.
	 * @return array Popular optins with pagination data.
	 */
	public function get_pop_optin( $days, $limit, $page ) {
		global $wpdb;

		$offset = ( $page - 1 ) * $limit;

		$raw_data = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT c.id, c.title, COUNT(i.id) AS `views` ' .
				" FROM {$this->optins_table} c " . // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				" LEFT JOIN {$this->interactions_table} i ON " . // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					'c.id = i.conv_id AND
					DATE(i.created_at) >= DATE(NOW() - INTERVAL %d DAY)
				WHERE
					c.deleted_at IS NULL
				GROUP BY c.id
				ORDER BY `views` DESC
				LIMIT %d OFFSET %d',
				$days,
				$limit,
				$offset
			)
		);

		$raw_conv_data = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT i.conv_id, COUNT(*) AS `conv` ' .
				" FROM {$this->interactions_table} i " . // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				' WHERE
					(i.click = 1 OR i.order_id IS NOT NULL) AND
					DATE(i.updated_at) >= DATE(NOW() - INTERVAL %d DAY)
				GROUP BY i.conv_id',
				$days
			)
		);

		$conv = array();

		foreach ( $raw_conv_data as $c ) {
			$conv[ $c->conv_id ] = $c->conv;
		}

		$res = array();

		foreach ( $raw_data as $c ) {
			$cr            = isset( $conv[ $c->id ] ) ? Utils::format_percentage( ( $conv[ $c->id ] / $c->views ) * 100 ) : '0.00';
			$res['data'][] = array(
				'title' => $c->title,
				'views' => $c->views,
				'cr'    => $cr,
			);
		}

		$total = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT COUNT(*) AS `count` ' .
				" FROM {$this->optins_table} c " . // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				' WHERE c.`deleted_at` IS NULL'
			)
		);

		$res['page']['total'] = ceil( intval( $total->count ) / $limit );

		return $res;
	}

	/**
	 * Get geographic view data
	 *
	 * @param int $days Number of days.
	 * @return array Geographic data with country-wise views.
	 */
	public function get_geo_view_data( $days ) {
		/** Null keeps common analytics; extensions may provide their enriched result. */
		$result = apply_filters( 'optn_geo_view_data_result', null, $days );
		if ( null !== $result ) {
			return $result; }
		global $wpdb;
		$res = array(
			'dom' => 0,
			'map' => array(),
			'pct' => array(),
		);

		$raw_data = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT LOWER(COALESCE(i.country, 'unknown')) AS `country`, COUNT(i.country) as `value` " .
				" FROM {$this->interactions_table} i " . // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				' WHERE
					DATE(i.created_at) >= DATE(NOW() - INTERVAL %d DAY)
				GROUP BY i.country
				ORDER BY `value` DESC',
				$days
			)
		);

		$count      = count( $raw_data );
		$res['dom'] = CountryDecoder::get_dom_pct( $count );

		$res['map'] = $raw_data;

		$top5  = array_slice( $raw_data, 0, 5 );
		$total = array_sum( array_column( $top5, 'value' ) );

		$unk = 0;

		foreach ( $top5 as $d ) {
			$name = CountryDecoder::decode( $d->country );
			$pct  = ! empty( $total ) ? round( ( $d->value / $total ) * 100, 2 ) : 0;
			$unk += empty( $name ) ? $pct : 0;

			if ( ! empty( $name ) ) {
				$res['pct'][] = array(
					'name' => $name,
					'pct'  => $pct,
				);
			}
		}

		if ( $unk > 0 ) {
			$res['pct'][] = array(
				'name' => __( 'Unknown', 'optin' ),
				'pct'  => round( $unk, 2 ),
			);
		}

		return $res;
	}

	/**
	 * Get stats for optins
	 *
	 * @param array $ids optin ids.
	 * @return array
	 */
	public function get_optin_stats_by_ids( $ids ) {
		global $wpdb;

		$ids_str = implode( ',', $ids );

		$sales_sql =
			"SELECT 
				i.conv_id as `id`,
				i.order_type,
				i.order_id
			FROM {$this->interactions_table} i
			WHERE 
				i.conv_id IN ( {$ids_str} ) AND
				i.order_id IS NOT NULL AND
				i.order_type IS NOT NULL";

		$sales_data = $wpdb->get_results( $sales_sql ); // phpcs:ignore

		$sales = array();

		foreach ( $sales_data as $d ) {
			if ( ! isset( $sales[ $d->id ] ) ) {
				$sales[ $d->id ] = 0;
			}
			$sales[ $d->id ] += floatval( Utils::get_revenue( $d->order_type, $d->order_id ) );
		}

		$sql =
			"SELECT 
				i.conv_id AS `id`,
				COUNT(*) AS `views`,
				SUM((i.click = TRUE) + (i.order_id IS NOT NULL)) AS `conversions`
			FROM {$this->interactions_table} i
			WHERE i.conv_id IN ( {$ids_str} )
			GROUP BY i.conv_id";

		$data = $wpdb->get_results( $sql, OBJECT_K ); // phpcs:ignore

		$sql = "SELECT 
					l.conv_id AS `id`, 
					COUNT(*) AS `leads`
				FROM {$this->leads_table} l
				WHERE l.conv_id IN ( {$ids_str} )
				GROUP BY l.conv_id";

		$leads_data = $wpdb->get_results( $sql, OBJECT_K ); // phpcs:ignore

		$total       = array(
			'views'       => 0,
			'conversions' => 0,
			'cr'          => 0.00,
			'leads'       => 0,
			'revenue'     => 0.00,
		);
		$optin_stats = array();

		foreach ( $ids as $id ) {

			$o_data = array(
				'id'          => intval( $id ),
				'views'       => 0,
				'conversions' => 0,
				'cr'          => '0.00%',
				'leads'       => 0,
				'revenue'     => 0,
				'optin_type'  => null,
			);

			if ( isset( $data[ $id ] ) ) {

				$od      = (array) $data[ $id ];
				$revenue = isset( $sales[ $id ] ) ? $sales[ $id ] : 0;
				$leads   = isset( $leads_data[ $id ] ) ? intval( $leads_data[ $id ]->leads ) : 0;

				$views       = intval( $od['views'] );
				$conversions = intval( $od['conversions'] );

				$o_data['views']       = Utils::format_number( $views );
				$o_data['conversions'] = Utils::format_number( $conversions );
				// The rate is derived here rather than in SQL: MySQL's division caps at
				// `div_precision_increment` (4) decimals, so a 0-1 ratio reaches PHP
				// already quantised to 1/10000 and 1.1142% could only ever render as
				// 1.11%. Scaling to a percentage before formatting also matters — the
				// old code rounded the ratio to 2 decimals first, which collapsed every
				// rate below 1.5% onto a whole number ( 0.0111 => 0.01 => "1%" ).
				$o_data['cr']      = $views > 0 ? Utils::format_percentage( $conversions / $views * 100 ) . '%' : '0.00%';
				$o_data['leads']   = Utils::format_number( $leads );
				$o_data['revenue'] = Utils::get_currency_symbol() . Utils::format_number( $revenue );
				// $o_data['optin_type']  = $this->get_decoded_conv_type2( $od['type'] );

				$total['views']       += $od['views'];
				$total['conversions'] += $od['conversions'];
				$total['leads']       += $leads;
				$total['revenue']     += $revenue;
			}

			$optin_stats[] = $o_data;
		}

		$total['cr']          = $total['views'] > 0 ? Utils::format_percentage( $total['conversions'] / $total['views'] * 100 ) . '%' : '0.00%';
		$total['views']       = Utils::format_number( $total['views'] );
		$total['conversions'] = Utils::format_number( $total['conversions'] );
		$total['leads']       = Utils::format_number( $total['leads'] );
		$total['revenue']     = Utils::get_currency_symbol() . Utils::format_number( $total['revenue'] );

		return array(
			'total' => $total,
			'stats' => $optin_stats,
		);
	}

	/**
	 * Get post count by status
	 *
	 * @param string $search Search term.
	 * @return array Post counts by status (draft, publish, trash, all).
	 */
	private function get_post_status_count( $search ) {
		global $wpdb;
		$res = array();

		$cond = '';

		if ( ! empty( $search ) ) {
			$cond = $wpdb->prepare( ' AND c.title LIKE %s', '%' . $wpdb->esc_like( $search ) . '%' );
		}

		$sql = "SELECT
					CASE
						WHEN c.enabled = 0 THEN 'draft'
						WHEN c.enabled = 1 THEN 'publish'
					END as `status`,
					COUNT(*) AS `count` 
				FROM {$this->optins_table} c
				WHERE c.deleted_at IS NULL {$cond}
				GROUP BY enabled

				UNION

				SELECT
					CASE
						WHEN true THEN 'trash'
					END AS `status`, 		
					COUNT(*) AS `count` 
				FROM {$this->optins_table} c 
				WHERE c.deleted_at IS NOT NULL {$cond}
		";

		$data = $wpdb->get_results( $sql ); // phpcs:ignore

		foreach ( $data as $d ) {
			$res[ $d->status ] = $d->count;
		}

		$statues = array( 'draft', 'publish', 'trash' );
		foreach ( $statues as $status ) {
			if ( ! isset( $res[ $status ] ) ) {
				$res[ $status ] = '0';
			}
		}

		$res['all'] = strval( $res['draft'] + $res['publish'] );

		return $res;
	}

	/**
	 * Add view
	 *
	 * @param int $id optin id.
	 * @return array
	 */
	public function add_view( $id ) {
		global $wpdb;

		$curr_user_id = get_current_user_id();

		$user_info = Utils::get_user_info();
		$ip        = ! empty( $user_info['ip'] ) ? ip2long( $user_info['ip'] ) : null;

		$device = Device::get_device();
		$device = 'lg' === $device ? 0 : ( 'sm' === $device ? 1 : 2 );

		$res = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->interactions_table, // phpcs:ignore
			array(
				'conv_id'    => $id,
				'user_id'    => 0 === $curr_user_id ? null : $curr_user_id,
				'ip_address' => $ip,
				'country'    => $user_info['country'],
				'device'     => $device,
			),
		);
		// Hooks may perform their own inserts; retain the identity of this interaction.
		$curr_id = $res ? $wpdb->insert_id : 0;

		if ( $res ) {
			do_action(
				'optn_viewed_optin',
				array(
					'conv_id' => $id,
				)
			);
		}

		return array(
			'id' => $curr_id,
		);
	}

	/**
	 * Add goal tracking
	 *
	 * @param int        $id        Interaction ID.
	 * @param string     $goal_type Goal type.
	 * @param float|null $value Numeric goal value submitted with the event, when provided.
	 * @param string     $path      Goal path.
	 * @return array Success status.
	 */
	public function add_goal( $id, $goal_type, $value, $path ) {
		global $wpdb;

		$res = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->interactions_table, // phpcs:ignore
			array(
				'click'      => true,
				'path'       => $path,
				'updated_at' => current_time( 'mysql' ),
			),
			array(
				'id'    => $id,
				'click' => 0,
			)
		);

		if ( $res ) {
			do_action(
				'optn_converted',
				array( 'id' => $id ),
			);
		}

		// A retry is successful once this interaction is converted; retain its first path.
		$row = 0 === $res ? $this->get_interaction_by_id( (int) $id ) : null;
		if ( $row && $row->click && null === $row->path && null !== $path ) {
			// A social conversion has no path. Fill it once without emitting another conversion.
			$filled = $wpdb->update(
				$this->interactions_table,
				array( 'path' => $path ),
				array(
					'id'    => $id,
					'click' => 1,
					'path'  => null,
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( false === $filled ) {
				return array( 'success' => 0 );
			}
		}
		return array(
			'success' => ( is_int( $res ) && $res > 0 ) || ( $row && $row->click ) ? 1 : 0,
		);
	}

	/**
	 * Add purchase tracking
	 *
	 * @param int    $id         Interaction ID.
	 * @param int    $conv_id    Conversion ID.
	 * @param string $order_type Order type (woo, edd).
	 * @param int    $order_id   Order ID.
	 * @return array Success status.
	 */
	public function add_purchase( $id, $conv_id, $order_type, $order_id ) {
		$type = null;

		switch ( $order_type ) {
			case 'woo':
				$type = 0;
				break;
			case 'edd':
				$type = 1;
				break;
			default:
				return 0;
		}

		global $wpdb;

		$res = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->interactions_table, // phpcs:ignore
			array(
				'order_type'   => $type,
				'order_id'     => $order_id,
				'purchased_at' => current_time( 'mysql' ),
			),
			array(
				'id'           => $id,
				'conv_id'      => $conv_id,
				'purchased_at' => null,
			)
		);

		if ( $res ) {
			do_action(
				'optn_purchased',
				array(
					'conv_id' => $conv_id,
				)
			);
		}

		$row = 0 === $res ? $this->get_interaction_by_id( (int) $id ) : null;
		return array(
			'success' => ( is_int( $res ) && $res > 0 ) || ( $row && (int) $row->conv_id === (int) $conv_id && (int) $row->order_type === $type && (int) $row->order_id === (int) $order_id ) ? 1 : 0,
		);
	}

	/**
	 * Track social block clicks
	 *
	 * @param int    $id analytics id.
	 * @param string $value social type.
	 * @return array
	 */
	public function add_social_click( $id, $value ) {
		global $wpdb;
		// Compare the read state on write so parallel social events cannot lose JSON keys.
		for ( $attempt = 0; $attempt < 3; ++$attempt ) {
			$row = $this->get_interaction_by_id( (int) $id );
			if ( ! $row ) {
				break;
			}
			$data           = json_decode( $row->data ?? '', true );
			$data           = is_array( $data ) ? $data : array();
			$data['social'] = isset( $data['social'] ) && is_array( $data['social'] ) ? $data['social'] : array();
			if ( ! empty( $data['social'][ $value ] ) ) {
				return array( 'success' => 1 );
			}
			$data['social'][ $value ] = true;
			$res                      = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$this->interactions_table,
				array(
					'click'      => 1,
					'data'       => wp_json_encode( $data ),
					'updated_at' => $row->click ? $row->updated_at : current_time( 'mysql' ),
				),
				array(
					'id'    => $id,
					'click' => $row->click,
					'data'  => $row->data,
				)
			);
			if ( false === $res ) {
				break;
			}
			if ( $res > 0 ) {
				if ( ! $row->click ) {
					do_action( 'optn_converted', array( 'id' => $id ) );
				}
				return array( 'success' => 1 );
			}
		}
		return array( 'success' => 0 );
	}

	/**
	 * Reads one interaction for credential binding and idempotent event writes.
	 *
	 * @param int $id Interaction ID.
	 * @return \stdClass|null Stored interaction or null when absent.
	 */
	public function get_interaction_by_id( int $id ): ?\stdClass {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->interactions_table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}


	/**
	 * Get optin by interaction id
	 *
	 * @param int $id id.
	 * @return object
	 */
	public function get_optin_by_interaction_id( $id ) {
		global $wpdb;

		$sql = "SELECT c.* FROM {$this->interactions_table} i 
				LEFT JOIN {$this->optins_table} c ON c.`id` = i.`conv_id`";

		$sql .= $wpdb->prepare( ' WHERE i.`id`=%d', $id );

		return $wpdb->get_row( $sql ); // phpcs:ignore
	}

	/**
	 * Get quick view data
	 *
	 * @param int $id optin id.
	 * @param int $days number of days for comparison.
	 * @return array
	 */
	public function get_quick_view_data( $id, $days ) {
		global $wpdb;
		$res = array();

		$id_cond = ( ! empty( $id ) ? $wpdb->prepare( ' AND i.`conv_id` = %d ', $id ) : '' );

		// View.
		$view_sql = "SELECT 
					SUM(CASE WHEN DATE(i.created_at) >= DATE(NOW() - INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS `curr`,
					SUM(CASE WHEN DATE(i.created_at) < DATE(NOW() - INTERVAL 7 DAY) 
							AND DATE(i.created_at) >= DATE(NOW() - INTERVAL 14 DAY) THEN 1 ELSE 0 END) AS `old`
				FROM {$this->interactions_table} i
				WHERE 1=1 " . $id_cond;

		$views = $wpdb->get_row( $view_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$res['view']['total'] = Utils::format_number( intval( $views->curr ) );
		$res['view']['diff']  = Utils::get_diff_pct( intval( $views->old ), intval( $views->curr ) );

		// Conversion.
		$click_sql = "SELECT 
						SUM(CASE WHEN DATE(i.created_at) >= DATE(NOW() - INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS `curr`,
						SUM(CASE WHEN DATE(i.created_at) < DATE(NOW() - INTERVAL 7 DAY) 
								AND DATE(i.created_at) >= DATE(NOW() - INTERVAL 14 DAY) THEN 1 ELSE 0 END) AS `old`
					FROM {$this->interactions_table} i
					WHERE i.click = TRUE " . $id_cond;

		$click = $wpdb->get_row( $click_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$sales_sql = "SELECT 
						SUM(CASE WHEN DATE(i.created_at) >= DATE(NOW() - INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS `curr`,
						SUM(CASE WHEN DATE(i.created_at) < DATE(NOW() - INTERVAL 7 DAY) 
								AND DATE(i.created_at) >= DATE(NOW() - INTERVAL 14 DAY) THEN 1 ELSE 0 END) AS `old`
					FROM {$this->interactions_table} i
					WHERE i.order_id IS NOT NULL " . $id_cond;

		$sales = $wpdb->get_row( $sales_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$curr_conv = intval( $click->curr ) + intval( $sales->curr );
		$old_conv  = intval( $click->old ) + intval( $sales->old );

		$res['conv']['total'] = Utils::format_number( $curr_conv );
		$res['conv']['diff']  = Utils::get_diff_pct( $old_conv, $curr_conv );

		// Social clicks.
		$social_cond      = 'AND i.data LIKE \'%"social"%\'';
		$social_click_sql = "SELECT 
						SUM(CASE WHEN DATE(i.created_at) >= DATE(NOW() - INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS `curr`,
						SUM(CASE WHEN DATE(i.created_at) < DATE(NOW() - INTERVAL 7 DAY) 
								AND DATE(i.created_at) >= DATE(NOW() - INTERVAL 14 DAY) THEN 1 ELSE 0 END) AS `old`
					FROM {$this->interactions_table} i
					WHERE i.click = TRUE " . $id_cond . $social_cond;

		$social_click = $wpdb->get_row( $social_click_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$res['social']['total'] = Utils::format_number( $social_click->curr );
		$res['social']['diff']  = Utils::get_diff_pct( $social_click->old, $social_click->curr );

		// Social clicks platform count.
		if ( ! empty( $id ) ) {
			$social_click_data_sql = "SELECT data
						FROM {$this->interactions_table} i
						WHERE i.click = TRUE " . $id_cond . $social_cond;

			$social_click_data   = $wpdb->get_col( $social_click_data_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$social_click_count  = array();
			$total_social_clicks = 0;

			foreach ( $social_click_data as $sc ) {
				$sc = json_decode( $sc, true );
				if ( isset( $sc['social'] ) ) {
					foreach ( $sc['social'] as $key => $value ) {
						if ( $value ) {
							$social_click_count[ $key ] = ( $social_click_count[ $key ] ?? 0 ) + 1;
							++$total_social_clicks;
						}
					}
				}
			}

			$final_social_data = array();

			foreach ( $social_click_count as $key => $value ) {
				$final_social_data[] = array(
					'label' => ucwords( $key ),
					'value' => $value,
					'pct'   => $total_social_clicks > 0 ? ( $value / $total_social_clicks ) * 100 : 0,
				);
			}

			$res['social']['counts'] = $final_social_data;
		}

		// Conversion rate.
		$curr_rate = 0;
		$old_rate  = 0;

		if ( intval( $views->curr ) > 0 ) {
			$curr_rate = ( ( intval( $click->curr ) + intval( $sales->curr ) ) / intval( $views->curr ) ) * 100;
		}

		if ( intval( $views->old ) > 0 ) {
			$old_rate = ( ( intval( $click->old ) + intval( $sales->old ) ) / intval( $views->old ) ) * 100;
		}

		$res['rate']['total'] = Utils::format_percentage( $curr_rate ) . '%';
		$res['rate']['diff']  = Utils::get_diff_pct( $old_rate, $curr_rate );

		// Sales.
		$curr_sales = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT i.order_type, i.order_id ' .
				" FROM {$this->interactions_table} i " . // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				' WHERE
					i.order_id IS NOT NULL AND
					DATE(i.created_at) >= DATE(NOW() - INTERVAL 7 DAY) ' . $id_cond, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			)
		);

		$old_sales = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT i.order_type, i.order_id ' .
				" FROM {$this->interactions_table} i " . // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				' WHERE
					i.order_id IS NOT NULL AND
					DATE(i.created_at) < DATE(NOW() - INTERVAL 7 DAY) AND
					DATE(i.created_at) >= DATE(NOW() - INTERVAL 14 DAY) ' . $id_cond, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			)
		);

		$curr = 0;
		$old  = 0;

		foreach ( $curr_sales as $sale ) {
			$curr += Utils::get_revenue( $sale->order_type, $sale->order_id );
		}

		foreach ( $old_sales as $sale ) {
			$old += Utils::get_revenue( $sale->order_type, $sale->order_id );
		}

		$total = strval( Utils::format_number( $curr ) );

		if ( function_exists( 'get_woocommerce_currency_symbol' ) ) {
			$total = trim( html_entity_decode( get_woocommerce_currency_symbol() ) ) . $total;
		} elseif ( function_exists( 'edd_currency_filter' ) ) {
			$total = html_entity_decode( edd_currency_filter( $total ) );
		}

		$res['rev']['total'] = $total;
		$res['rev']['diff']  = Utils::get_diff_pct( $old, $curr );

		return $res;
	}


	/**
	 * Process post data for database storage
	 *
	 * @param \stdClass $post_data Decoded opt-in data supplied for insertion or update.
	 * @param string    $type      Optin type.
	 * @return array|\WP_Error Processed data or an unsupported embed setting error.
	 */
	private function get_processed_data_for_db( $post_data, $type ) {
		$schedule = (object) array_merge(
			array(
				'endType' => 'now',
				'endDate' => '',
				'endTime' => '',
			),
			(array) ( $post_data->schedule ?? array() )
		);
		$audience = (object) array_merge(
			array(
				'device'           => 'all',
				'osType'           => 'all',
				'visitorType'      => 'all',
				'tSrc'             => 'all',
				'tUrl'             => '',
				'xsDevice'         => true,
				'smDevice'         => true,
				'lgDevice'         => true,
				'osIos'            => true,
				'osAndroid'        => true,
				'osLinux'          => true,
				'osMac'            => true,
				'osWindows'        => true,
				'visitorNew'       => true,
				'visitorRet'       => true,
				'visitorLoggedIn'  => true,
				'visitorLoggedOut' => true,
			),
			(array) ( $post_data->audience ?? array() )
		);

		$data = array();

		$data['traffic_url'] = null;

		if ( 'custom' === $schedule->endType && ! empty( $schedule->endDate ) && ! empty( $schedule->endTime ) ) {
			$end_datetime         = gmdate( 'Y-m-d H:i:s', strtotime( $schedule->endDate . ' ' . $schedule->endTime ) );
			$data['end_datetime'] = $end_datetime;
		} else {
			$data['end_datetime'] = null;
		}

		$data['device']          = $this->get_encoded_device( $audience );
		$data['os']              = $this->get_encoded_os( $audience );
		$data['traffic_channel'] = 31;
		$data['visitor']         = $this->get_encoded_visitor( $audience );

		$data['embed'] = null;

		if ( 'inline' === $type && 'embed' === ( $post_data->design->pos->inlineType ?? '' ) && '' !== ( $post_data->design->pos->embedPosition ?? '' ) ) {
			$hook = $post_data->design->pos->embedPosition;

			if ( empty( Utils::get_embed_hook_and_pos( $hook )['hook'] ) ) {
				return new \WP_Error( 'optn_invalid_embed_hook', __( 'Choose a supported embed position.', 'optin' ), array( 'status' => 400 ) );
			}

			$data['embed'] = $hook;
		}

		/** Filter encoded storage fields; extensions own additional attribution. */
		return apply_filters( 'optn_campaign_storage_data', $data, $post_data, $type );
	}

	/**
	 * Update saved post
	 *
	 * @param int    $id     Optin ID.
	 * @param string $title  Post title.
	 * @param bool   $status Post status.
	 * @param string $data   Post data JSON.
	 * @param string $type   Post type.
	 * @return array Update result.
	 */
	public function update_saved_post( $id, $title, $status, $data, $type ) {
		if ( empty( $id ) ) {
			return array( 'error' => 'Invalid Post ID' );
		}

		global $wpdb;
		$original = $this->get_conv_by_id( $id );
		if ( $original && empty( $original->enabled ) && ( null === $original->data || '' === $original->data ) ) {
			// New blank drafts have no saved canvas yet; retain extension hold checks.
			$original       = clone $original;
			$original->data = '{"steps":[]}';
		}
		if ( $original && \OPTN\Includes\Utils\CampaignSupport::get_unsupported_features( $original ) ) {
			return new \WP_Error( 'optn_campaign_unsupported', __( 'Enable the extension that supports this campaign before editing it.', 'optin' ), array( 'status' => 409 ) );
		}

		if ( ! empty( $data ) ) {
			$data = \OPTN\Includes\Utils\CampaignSchema::sanitize_json( $data );
			if ( is_wp_error( $data ) ) {
				return $data;
			}
		}
		$post_data = empty( $data ) ? null : json_decode( $data );

		if ( ! empty( $post_data ) ) {
			$updated_data = $this->get_processed_data_for_db( $post_data, $type );
			if ( is_wp_error( $updated_data ) ) {
				return $updated_data;
			}
			$updated_data['data'] = ( new \OPTN\Includes\Utils\TemplateMedia() )->localize_json( $data );
		} else {
			$updated_data = array();
		}

		if ( ! empty( $title ) ) {
			$updated_data['title'] = $title;
		}

		$updated_data['enabled'] = is_bool( $status ) ? $status : false;

		if ( $updated_data['enabled'] ) {
			$updated_data['deleted_at'] = null;
		}

		// On older DBs without ON UPDATE CURRENT_TIMESTAMP, bump updated_at manually.
		if ( ! $this->supports_multi_ts_current() ) {
			$updated_data['updated_at'] = current_time( 'mysql' );
		}

		$res = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->optins_table, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$updated_data,
			array( 'id' => $id )
		);
		do_action( 'optn_compat_purge_cache' );

		return array( $res );
	}

	/**
	 * Get saved post data
	 *
	 * @param int $id Optin ID.
	 * @return array|null Post data or null if not found.
	 */
	public function get_saved_post( $id ) {
		$conversion = $this->get_conv_by_id( $id );

		if ( ! empty( $conversion ) ) {
			$support_data = clone $conversion;
			if ( empty( $support_data->enabled ) && ( null === $support_data->data || '' === $support_data->data ) ) {
				// Blank drafts have no saved canvas; extension holds still apply.
				$support_data->data = '{"steps":[]}';
			}
			return array(
				'title'                => $conversion->title,
				'data'                 => isset( $conversion->data ) ? $conversion->data : null,
				'status'               => $conversion->enabled ? 'publish' : 'draft',
				'unsupported_features' => \OPTN\Includes\Utils\CampaignSupport::get_unsupported_features( $support_data ),
			);
		}

		return null;
	}

	/**
	 * Get conversion by ID
	 *
	 * @param int $id Optin ID.
	 * @return object|null Conversion object or null if not found.
	 */
	public function get_conv_by_id( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->optins_table} WHERE `id` = %d", $id ) ); // phpcs:ignore
	}

	/**
	 * Finds a published analytics campaign using the same database clock as rendering.
	 *
	 * @param int $id Campaign ID.
	 * @return \stdClass|null Published, unexpired campaign, or null when unavailable.
	 */
	public function get_campaign_for_analytics( int $id ): ?\stdClass {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->optins_table} WHERE id = %d AND enabled = 1 AND deleted_at IS NULL AND (end_datetime IS NULL OR end_datetime > NOW())", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Copy an existing post
	 *
	 * @param int $id Optin ID to copy.
	 * @return array|\WP_Error New post data or an unsupported campaign error.
	 */
	public function copy_post( $id ) {
		global $wpdb;

		$original = $this->get_conv_by_id( $id );
		if ( ! $original ) {
			return new \WP_Error( 'optn_campaign_missing', 'Campaign unavailable', array( 'status' => 404 ) );
		}
		if ( isset( $original->embed ) && '' !== $original->embed && empty( Utils::get_embed_hook_and_pos( $original->embed )['hook'] ) ) {
			return new \WP_Error( 'optn_invalid_embed_hook', __( 'Choose a supported embed position.', 'optin' ), array( 'status' => 400 ) );
		}
		$original->data = \OPTN\Includes\Utils\CampaignSchema::sanitize_json( $original->data );
		if ( is_wp_error( $original->data ) ) {
			return $original->data; }

		$title = $original->title . ' - Copy';

		$data = array(
			'title'           => $title,
			'enabled'         => false,
			'data'            => $original->data,
			'type'            => $original->type,
			'end_datetime'    => $original->end_datetime,
			'traffic_url'     => $original->traffic_url,
			'traffic_channel' => $original->traffic_channel,
			'visitor'         => $original->visitor,
			'device'          => $original->device,
			'os'              => $original->os,
			'embed'           => $original->embed,
		);

		if ( isset( $data['data'] ) ) {
			$data['data'] = \OPTN\Includes\Utils\CampaignSchema::sanitize_json( $data['data'] );
			if ( is_wp_error( $data['data'] ) ) {
				return $data['data'];
			}
		}
		$res = $wpdb->insert( $this->optins_table, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$id = is_int( $res ) && $res > 0 ? $wpdb->insert_id : null;

		do_action( 'optn_compat_purge_cache' );

		return array(
			'id'     => $id,
			'type'   => $this->get_decoded_conv_type2( $original->type ),
			'title'  => $title,
			'data'   => $original->data,
			'status' => 'draft',
		);
	}

	/**
	 * Activate a recipe
	 *
	 * @param int   $id recipe id.
	 * @param array $args {
	 *     Optional extra args.
	 *
	 *     @type bool $status default status of each template. Default true.
	 *     @type bool $disable_others Disable other optins. Default false.
	 * }
	 * @return array|\WP_Error
	 */
	public function activate_recipe( $id, $args ) {
		global $wpdb;

		$data = Templates::fetch_single_recipe( $id );

		if ( empty( $data ) ) {
			return new \WP_Error( 'optin_invalid_request', __( 'Recipe not found', 'optin' ), array( 'status' => 400 ) );
		}

		if ( 'update_required' === $data ) {
			return new \WP_Error( 'optin_invalid_request', __( 'Update required', 'optin' ), array( 'status' => 400 ) );
		}

		$enabled  = isset( $args['status'] ) ? boolval( $args['status'] ) : true;
		$prepared = array();
		foreach ( $data as $d ) {
			$db_data = $this->get_processed_data_for_db( $d->data, $d->type );
			if ( is_wp_error( $db_data ) ) {
				return $db_data;
			}
			$db_data['title']   = sanitize_text_field( $d->title );
			$db_data['enabled'] = $enabled;
			$db_data['type']    = $this->get_encoded_conv_type( $d->type );
			$db_data['data']    = \OPTN\Includes\Utils\CampaignSchema::sanitize_json( wp_json_encode( $d->data ) );
			if ( is_wp_error( $db_data['data'] ) ) {
				return $db_data['data'];
			}
			$prepared[] = array( 'data' => $db_data, 'type' => $d->type );
		}

		$media = new \OPTN\Includes\Utils\TemplateMedia();
		foreach ( $prepared as &$record ) {
			$record['data']['data'] = $media->localize_json( $record['data']['data'] );
		}
		unset( $record );

		// Optionally disable all other optins before activating this recipe.
		$disable_others = isset( $args['disable_others'] ) ? boolval( $args['disable_others'] ) : false;
		if ( $disable_others ) {
			if ( $this->supports_multi_ts_current() ) {
				$sql = "UPDATE {$this->optins_table} SET `enabled` = 0 WHERE `deleted_at` IS NULL";
			} else {
				$sql = "UPDATE {$this->optins_table} SET `enabled` = 0, `updated_at` = NOW() WHERE `deleted_at` IS NULL";
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( $sql ); // phpcs:ignore
		}

		$res = array();

		foreach ( $prepared as $entry ) {
			$db_data = $entry['data'];
			$success = (bool) $wpdb->insert( $this->optins_table, $db_data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			if ( $success ) {
				$res[] = array(
					'id'    => $wpdb->insert_id,
					'title' => $db_data['title'],
					'type'  => $entry['type'],
				);
			}
		}

		return $res;
	}

	/**
	 * Create a new post
	 *
	 * @param string $type        Optin type.
	 * @param int    $copy        ID of post to copy.
	 * @param string $title       Post title.
	 * @param int    $template_id Template ID to use.
	 * @return array|null New post data or null on failure.
	 */
	public function create_new_post( $type, $copy, $title, $template_id ) {
		global $wpdb;

		if ( ! empty( $copy ) ) {
			return $this->copy_post( $copy );
		} elseif ( ! empty( $template_id ) ) {
			$data = Templates::fetch_single_template( $template_id );

			if ( 'update_required' === $data ) {
				return array( 'error' => 'update_required' );
			}

			if ( empty( $data ) ) {
				return null;
			}

			$enc_data = wp_json_encode( $data );

			$db_data = $this->get_processed_data_for_db( $data, $type );
			if ( is_wp_error( $db_data ) ) {
				return $db_data;
			}
			$db_data['title']   = sanitize_text_field( $title );
			$db_data['enabled'] = false;
			$db_data['data']    = $enc_data;
			$db_data['type']    = $this->get_encoded_conv_type( $type );

			if ( isset( $db_data['data'] ) ) {
				$db_data['data'] = \OPTN\Includes\Utils\CampaignSchema::sanitize_json( $db_data['data'] );
				if ( is_wp_error( $db_data['data'] ) ) {
					return $db_data['data'];
				}
				$enc_data       = ( new \OPTN\Includes\Utils\TemplateMedia() )->localize_json( $db_data['data'] );
				$db_data['data'] = $enc_data;
			}
			$res = $wpdb->insert( $this->optins_table, $db_data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			$id = is_int( $res ) && $res > 0 ? $wpdb->insert_id : null;

			do_action( 'optn_compat_purge_cache' );

			return array(
				'id'     => $id,
				'title'  => $title,
				'data'   => $enc_data,
				'status' => 'draft',
			);
		} else {
			$title = ! empty( $title ) ? sanitize_text_field( $title ) : __( 'New Optin', 'optin' ) . ' ' . current_time( 'Y-m-d H:i:s' );

			$data = array(
				'title'           => $title,
				'type'            => $this->get_encoded_conv_type( $type ),
				'traffic_channel' => 31,
				'visitor'         => $this->get_encoded_visitor( 'default' ),
				'device'          => $this->get_encoded_device( 'default' ),
				'os'              => $this->get_encoded_os( 'default' ),
			);

			if ( isset( $data['data'] ) ) {
				$data['data'] = \OPTN\Includes\Utils\CampaignSchema::sanitize_json( $data['data'] );
				if ( is_wp_error( $data['data'] ) ) {
					return $data['data'];
				}
			}
			$res = $wpdb->insert( $this->optins_table, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			$id = is_int( $res ) && $res > 0 ? $wpdb->insert_id : null;

			do_action( 'optn_compat_purge_cache' );

			return array(
				'id'    => $id,
				'title' => $title,
			);
		}
	}

	/**
	 * Delete posts
	 *
	 * @param int[]  $ids  Array of optin IDs to delete.
	 * @param string $type Delete type (soft, hard).
	 * @return int|array Status code or error array.
	 */
	public function delete_post( $ids, $type ) {
		global $wpdb;
		$res = null;

		$ids = array_map(
			function ( $id ) {
				return esc_sql( $id );
			},
			$ids
		);

		$ids = implode( ',', $ids );

		if ( 'soft' === $type ) {
			if ( $this->supports_multi_ts_current() ) {
				$sql = "UPDATE {$this->optins_table}  
			 	SET `deleted_at` = NOW(), `enabled` = 0
				WHERE `id` IN ( {$ids} )";
			} else {
				$sql = "UPDATE {$this->optins_table}  
			 	SET `deleted_at` = NOW(), `enabled` = 0, `updated_at` = NOW()
				WHERE `id` IN ( {$ids} )";
			}
			$res = $wpdb->query( $sql ); // phpcs:ignore
		} elseif ( 'hard' === $type ) {
			$sql = "DELETE FROM {$this->optins_table} WHERE `id` IN ( {$ids} )";
			$res = $wpdb->query( $sql ); // phpcs:ignore
		}

		if ( is_int( $res ) && $res < 1 ) {
			return array( 'error' => 404 );
		}

		if ( false === $res ) {
			return array( 'error' => 500 );
		}

		do_action( 'optn_compat_purge_cache' );

		return 200;
	}

	/**
	 * Restores a optin from trash
	 *
	 * @param int[] $ids optin ids.
	 * @return int
	 */
	public function restore_post( $ids ) {
		global $wpdb;

		$ids = array_map(
			function ( $id ) {
				return esc_sql( $id );
			},
			$ids
		);

		$ids = implode( ',', $ids );

		if ( $this->supports_multi_ts_current() ) {
			$sql = "UPDATE {$this->optins_table}
				SET `deleted_at` = NULL
				WHERE `id` IN ( {$ids} )";
		} else {
			$sql = "UPDATE {$this->optins_table}
				SET `deleted_at` = NULL, `updated_at` = NOW()
				WHERE `id` IN ( {$ids} )";
		}

		$res = $wpdb->query( $sql ); // phpcs:ignore

		do_action( 'optn_compat_purge_cache' );

		return $res;
	}

	/**
	 * Resets optins stats
	 *
	 * @param int[] $ids optin ids.
	 * @return bool
	 */
	public function reset_post( $ids ) {
		global $wpdb;

		$ids = array_map(
			function ( $id ) {
				return esc_sql( $id );
			},
			$ids
		);

		$ids = implode( ',', $ids );

		$sql = "DELETE FROM {$this->interactions_table}
				WHERE `conv_id` IN ( {$ids} )";

		$res = $wpdb->query( $sql ); // phpcs:ignore

		return (bool) $res;
	}

	/**
	 * Get conversion for render in frontend
	 *
	 * @param int     $id specific optin id.
	 * @param boolean $embed is embed.
	 * @param boolean $is_inline is inline.
	 * @return array
	 */
	public function get_conv_for_render( $id = null, $embed = false, $is_inline = false ) {
		global $wpdb;
		$device     = $this->get_encoded_device( Device::get_device() );
		$os         = $this->get_encoded_os( Os::getOS() );
		$visitor    = $this->get_encoded_visitor( Utils::get_visitor_types() );
		$conditions = $wpdb->prepare( 'enabled = 1 AND deleted_at IS NULL AND device & %d > 0 AND os & %d > 0 AND visitor & %d > 0', $device, $os, $visitor );
		if ( $id ) {
			$conditions .= $wpdb->prepare( ' AND id = %d', $id );
		}
		$conditions .= $embed ? ' AND embed IS NOT NULL' : ' AND embed IS NULL';
		$conditions .= $is_inline ? ' AND type = 3' : ' AND type != 3';
		$conditions .= ' AND (end_datetime IS NULL OR end_datetime > NOW())';
		$holds       = \OPTN\Includes\Utils\CampaignSupport::held_campaigns();
		if ( $holds ) {
			$conditions .= ' AND id NOT IN (' . implode( ',', $holds ) . ')';
		}
		/** Filter prepared rendering SQL. Extensions own any additional joins or conditions. */
		$sql   = apply_filters( 'optn_campaign_render_sql', "SELECT * FROM {$this->optins_table} WHERE " . $conditions, $id, $embed, $is_inline, $this );
		$posts = $wpdb->get_results( $sql );
		/** Filter selected rows before common support checks. */
		$posts = apply_filters( 'optn_campaign_candidates', $posts, $id, $embed, $is_inline );
		return array_values(
			array_filter(
				$posts,
				function ( $post ) {
					return ! \OPTN\Includes\Utils\CampaignSupport::get_unsupported_features( $post );
				}
			)
		);
	}

	/**
	 * Clear interaction table of old data.
	 *
	 * @param int $days_older_than Delete interactions older than this many days.
	 * @return bool
	 */
	public function clear_interactions( $days_older_than ) {
		global $wpdb;
		$holds   = \OPTN\Includes\Utils\CampaignSupport::held_campaigns();
		$exclude = $holds ? ' AND conv_id NOT IN (' . implode( ',', $holds ) . ')' : '';
		$days    = max( 1, (int) $days_older_than );
		/** Null keeps common cleanup; extensions may return their completed cleanup result. */
		$result = apply_filters( 'optn_clear_interactions_result', null, $days, $this );
		if ( null !== $result ) {
			return $result; }
		$sql = $wpdb->prepare( "DELETE FROM {$this->interactions_table} WHERE created_at < (NOW() - INTERVAL %d DAY)" . $exclude, $days );
		/** Filter prepared cleanup SQL. Extensions own their retention policy. */
		return $wpdb->query( apply_filters( 'optn_clear_interactions_sql', $sql, $days, $this ) );
	}

	/**
	 * Get encoded conversion type
	 *
	 * @param string $type Conversion type name.
	 * @return int|null Encoded type as integer.
	 */
	private function get_encoded_conv_type( $type ) {
		switch ( $type ) {
			case 'popup':
				return 0;
			case 'banner':
				return 1;
			case 'slidein':
				return 2;
			case 'inline':
				return 3;
		}

		return null;
	}

	/**
	 * Get decoded conversion type
	 *
	 * @param int $type Conversion type as integer.
	 * @return string|null Conversion type name.
	 */
	private function get_decoded_conv_type2( $type ) {
		switch ( $type ) {
			case 0:
				return 'popup';
			case 1:
				return 'banner';
			case 2:
				return 'slidein';
			case 3:
				return 'inline';
			default:
				return null;
		}
	}

	/**
	 * Get decoded conv type
	 *
	 * @deprecated v1.4.0 Use get_decoded_conv_type2() instead.
	 * @param int $type optin type in int.
	 * @return string
	 */
	private function get_decoded_conv_type( $type ) {
		switch ( $type ) {
			case 0:
				return __( 'Popup', 'optin' );
			case 1:
				return __( 'Banner', 'optin' );
			case 2:
				return __( 'Slide', 'optin' );
			case 3:
				return __( 'Inline', 'optin' );
			default:
				return null;
		}
	}

	/**
	 * Get decoded integration type
	 *
	 * @param string $type integration type.
	 * @return string
	 */
	private function get_decoded_integration_type( $type ) {
		switch ( $type ) {
			case 'fluentcrm':
				return 'FluentCRM';
			case 'webhook':
				return 'Web Hook';
			case 'mailerlite':
				return 'MailerLite';
			case 'mailchimp':
				return 'Mailchimp';
			case 'mailpoet':
				return 'MailPoet';
			case 'moosend':
				return 'Moosend';
			case 'omnisend':
				return 'Omnisend';
			case 'brevo':
				return 'Brevo';
			case 'hubspot':
				return 'HubSpot';
			case 'convertkit':
				return 'Kit';
			case 'campaignmonitor':
				return 'Campaign Monitor';
			case 'getresponse':
				return 'GetResponse';
			case 'drip':
				return 'Drip';
			case 'sendfox':
				return 'SendFox';
			case 'activecampaign':
				return 'ActiveCampaign';
			case 'acumbamail':
				return 'Acumbamail';
			default:
				return __( 'Unknown', 'optin' );
		}
	}

	/**
	 * Encode the selected visitor categories as a compact bit field for storage.
	 *
	 * Accepts the default marker, a decoded visitor configuration object, or a list of
	 * selected visitor-category identifiers and maps them to the database bit values.
	 *
	 * @param string|array|\stdClass $data Default marker, saved visitor settings, or selected visitor categories.
	 * @return int|null
	 */
	private function get_encoded_visitor( $data ) {

		/*
			Bit mapping:
			---------------

			0 0 0 0
			^ ^ ^ ^
			| | | |
			| | | -- logged_out
			| | ---- logged_in
			| ------ returning
			-------- new
		*/

		if ( 'default' === $data || ( isset( $data->visitorType ) && 'all' === $data->visitorType ) ) {
			return 15;
		}

		if ( is_array( $data ) ) {

			$visitor_type_lookup = array(
				'new'        => 8,
				'returning'  => 4,
				'logged_in'  => 2,
				'logged_out' => 1,
			);

			$curr_types = 0;

			foreach ( $data as $type ) {
				if ( isset( $visitor_type_lookup[ $type ] ) ) {
					$curr_types |= $visitor_type_lookup[ $type ];
				}
			}

			return $curr_types;
		}

		return Utils::bin_array_to_dec(
			array(
				$data->visitorNew,
				$data->visitorRet,
				$data->visitorLoggedIn,
				$data->visitorLoggedOut,
			)
		);
	}

	/**
	 * Encode the selected device categories as a compact bit field for storage.
	 *
	 * Accepts the default marker, a named device category, or a decoded device settings
	 * object and converts the selection to the database's device bit values.
	 *
	 * @param string|\stdClass $data Default marker, device category, or decoded device settings.
	 * @return int
	 */
	private function get_encoded_device( $data ) {
		/*
			Bit mapping:
			---------------

			0 0 0
			^ ^ ^
			| | |
			| | -- lg
			| - -- sm
			- - -- xs
		*/

		if ( 'default' === $data || ( isset( $data->device ) && 'all' === $data->device ) ) {
			return 7;
		}

		if ( is_string( $data ) ) {
			$device_lookup = array(
				'xs' => 4,
				'sm' => 2,
				'lg' => 1,
			);
			return $device_lookup[ $data ] ?? 7;
		}

		return Utils::bin_array_to_dec(
			array(
				$data->xsDevice,
				$data->smDevice,
				$data->lgDevice,
			)
		);
	}

	/**
	 * Encode the selected operating-system categories as a compact bit field.
	 *
	 * Accepts the default marker, one operating-system family, or a decoded OS settings
	 * object and converts the selection to the database's OS bit values.
	 *
	 * @param string|\stdClass $data Default marker, OS family, or decoded OS settings.
	 * @return int
	 */
	private function get_encoded_os( $data ) {

		/*
			Bit mapping:
			---------------

			0 0 0 0 0
			^ ^ ^ ^ ^
			| | | | |
			| | | | - Windows
			| | | --- Mac
			| | ----- Linux
			| ------- Android
			--------- iOS
		*/

		if ( 'default' === $data || ( isset( $data->osType ) && 'all' === $data->osType ) ) {
			return 31;
		}

		if ( is_string( $data ) ) {
			$os_lookup = array(
				'Windows' => 1,
				'Mac'     => 2,
				'Linux'   => 4,
				'Android' => 8,
				'iOS'     => 16,
			);

			return $os_lookup[ $data ] ?? 31;
		}

			return Utils::bin_array_to_dec(
				array(
					$data->osIos,
					$data->osAndroid,
					$data->osLinux,
					$data->osMac,
					$data->osWindows,
				)
			);
	}

		/**
		 * Get encoded integration type.
		 *
		 * @param string $integration Integration type.
		 * @return int
		 */
	private function get_encoded_integration( $integration ) {
		switch ( $integration ) {
			case 'fluentcrm':
				return 1;
			case 'webhook':
			default:
				return 0;
		}
	}

	/**
	 * Build condition string.
	 *
	 * @param array  $conds List of conditions.
	 * @param string $cond  Join type.
	 * @return string
	 */
	private function get_cond( $conds, $cond = 'and' ) {

		$c = 'and' === $cond ? 'AND' : 'OR';

		if ( count( $conds ) < 1 ) {
			return ' ';
		}

		return implode( " {$c} ", $conds ) . ' ';
	}

	/**
	 * Format date string.
	 *
	 * @param string $date_str Date string.
	 * @return string
	 */
	private function get_formatted_date( $date_str ) {
		$datetime = new \DateTime( $date_str );
		return $datetime->format( 'M j, Y' );
	}

	/**
	 * Get DB server vendor and version.
	 *
	 * @return array{vendor:string,version:string}
	 */
	private function get_server_info() {
		global $wpdb;

		// phpcs:disable Generic.Formatting.MultipleStatementAlignment.NotSameWarning
		$raw   = method_exists( $wpdb, 'db_server_info' ) ? $wpdb->db_server_info() : $wpdb->db_version(); // phpcs:ignore WordPress.DB.RestrictedFunctions
		$raw_l = strtolower( strval( $raw ) );

		$vendor  = ( false !== strpos( $raw_l, 'mariadb' ) ) ? 'mariadb' : 'mysql';
		$version = '0.0.0';
		// phpcs:enable Generic.Formatting.MultipleStatementAlignment.NotSameWarning

		if ( preg_match( '/(\d+)\.(\d+)\.(\d+)/', $raw_l, $m ) ) {
			$version = sprintf( '%d.%d.%d', $m[1], $m[2], $m[3] );
		} elseif ( preg_match( '/(\d+)\.(\d+)/', $raw_l, $m ) ) {
			$version = sprintf( '%d.%d.0', $m[1], $m[2] );
		}

		return array(
			'vendor'  => $vendor,
			'version' => $version,
		);
	}

	/**
	 * Whether the server supports multiple TIMESTAMP columns using CURRENT_TIMESTAMP
	 * as DEFAULT and/or ON UPDATE (MySQL >=5.6.5, MariaDB >=10.2.0).
	 *
	 * @return bool
	 */
	private function supports_multi_ts_current() {
		// phpcs:disable Generic.Formatting.MultipleStatementAlignment.NotSameWarning
		$info   = $this->get_server_info();
		$vendor = isset( $info['vendor'] ) ? $info['vendor'] : 'mysql';
		$ver    = $info['version'] ?? '0.0.0';
		// phpcs:enable Generic.Formatting.MultipleStatementAlignment.NotSameWarning

		if ( 'mysql' === $vendor ) {
			return version_compare( $ver, '5.6.5', '>=' );
		}

		// MariaDB.
		return version_compare( $ver, '10.2.0', '>=' );
	}

	/**
	 * Whether the server supports JSON data type (MySQL >=5.7.8, MariaDB >=10.2.7).
	 *
	 * @return bool
	 */
	private function supports_json_type() {
		// phpcs:disable Generic.Formatting.MultipleStatementAlignment.NotSameWarning
		$info   = $this->get_server_info();
		$vendor = isset( $info['vendor'] ) ? $info['vendor'] : 'mysql';
		$ver    = $info['version'] ?? '0.0.0';
		// phpcs:enable Generic.Formatting.MultipleStatementAlignment.NotSameWarning

		if ( 'mysql' === $vendor ) {
			return version_compare( $ver, '5.7.8', '>=' );
		}

		// MariaDB.
		return version_compare( $ver, '10.2.7', '>=' );
	}


	/**
	 * Get DB vesion
	 *
	 * @return string|false
	 */
	public function get_db_version() {
		$ver = get_option( 'optn_db_version', false );

		if ( ! Utils::is_valid_version_number( $ver ) ) {
			return false;
		}

		return $ver;
	}

	/**
	 * Set DB vesion
	 *
	 * @param string $ver version.
	 * @return void
	 */
	public function set_db_version( $ver ) {
		update_option( 'optn_db_version', $ver, true );
	}

	/**
	 * Get update callbacks
	 *
	 * @return array callback array.
	 */
	private function get_update_callbacks() {
		$curr_db_version = $this->get_db_version();

		if ( false === $curr_db_version ) {
			return $this->install_callbacks;
		}

		$res     = array();
		$collect = false;

		foreach ( $this->install_callbacks as $version => $callback ) {
			if ( $collect ) {
				$res[ $version ] = $callback;
			}
			if ( $version === $curr_db_version ) {
				$collect = true;
			}
		}

		return $res;
	}
}
