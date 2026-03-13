<?php //phpcs:ignore

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase, Squiz.Commenting.BlockComment.HasEmptyLineBefore

namespace OPTN\Includes;

use OPTN\Includes\Dto\CreateAbTestDto;
use OPTN\Includes\Utils\Os;
use OPTN\Includes\Utils\Device;
use OPTN\Includes\Utils\TrafficChannel;
use OPTN\Includes\Utils\CountryDecoder;
use OPTN\Includes\Utils\Utils;
use OPTN\Includes\Utils\Templates;
use OPTN\Includes\AbTesting;
use OPTN\Includes\Dto\UpdateAbTestDto;

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

/**
 * DB Class.
 */
class Db {
	use \OPTN\Includes\Traits\Singleton;

	public $optins_table; // phpcs:ignore
	public $leads_table; // phpcs:ignore
	public $interactions_table; // phpcs:ignore
	public $integrations_table; // phpcs:ignore
	public $ab_tests_table; // phpcs:ignore
	public $ab_test_variants_table; // phpcs:ignore
	public $install_callbacks; // phpcs:ignore
	private $prefix         = 'optn_'; // phpcs:ignore

	/**
	 * Constructor
	 */
	private function __construct() {
		global $wpdb;

		$this->optins_table  = $wpdb->prefix . $this->prefix . 'conversions'; // phpcs:ignore
		$this->interactions_table = $wpdb->prefix . $this->prefix . 'interactions'; // phpcs:ignore
		$this->leads_table        = $wpdb->prefix . $this->prefix . 'leads'; // phpcs:ignore
		$this->integrations_table        = $wpdb->prefix . $this->prefix . 'integrations'; // phpcs:ignore
		$this->ab_tests_table        = $wpdb->prefix . $this->prefix . 'ab_tests'; // phpcs:ignore
		$this->ab_test_variants_table        = $wpdb->prefix . $this->prefix . 'ab_test_variants'; // phpcs:ignore

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
		$needs_update = ! empty( array_key_last( $this->install_callbacks ) ) && array_key_last( $this->install_callbacks ) !== $this->get_db_version();

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
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$res = true;

		$res &= maybe_create_table(
			$this->ab_tests_table,
			"CREATE TABLE {$this->ab_tests_table} (
				`id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
				`title`      VARCHAR(255) NOT NULL,
				`type`       TINYINT UNSIGNED NOT NULL,
				`status`     TINYINT UNSIGNED NOT NULL,
				`metric`     TINYINT UNSIGNED DEFAULT NULL,
				`result`     LONGTEXT DEFAULT NULL,
				`started_at` DATETIME DEFAULT NULL,
				`duration`   INT UNSIGNED DEFAULT NULL,

				PRIMARY KEY  (id)
			) $charset_collate;"
		);

		$res &= maybe_create_table(
			$this->ab_test_variants_table,
			"CREATE TABLE {$this->ab_test_variants_table} (
				`id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
				`test_id`  INT UNSIGNED NOT NULL,
				`optin_id` INT UNSIGNED NOT NULL,
				`t_dist`   DECIMAL(5,2) UNSIGNED NOT NULL,

				PRIMARY KEY  (id),

				FOREIGN KEY (`test_id`) 
					REFERENCES {$this->ab_tests_table} (`id`) 
					ON DELETE CASCADE,

				FOREIGN KEY (`optin_id`) 
					REFERENCES {$this->optins_table} (`id`) 
					ON DELETE CASCADE

			) $charset_collate;"
		);

		return (bool) $res;
	}

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

		return false === $res;
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

		return false === $res;
	}

	/**
	 * Get AB tests
	 *
	 * @param string $status Status.
	 *
	 * @param string $search Search.
	 * @param int    $limit limit.
	 * @param int    $page page.
	 * @return array
	 */
	public function get_ab_tests( $status, $search, $limit = 12, $page = 1 ) {

		global $wpdb;

		$conds = array();

		if ( ! empty( $status ) && 'all' !== $status ) {
			$conds[] = $wpdb->prepare( 'status = %d', AbTesting::encode_status( $status ) );
		}

		if ( ! empty( $search ) ) {
			$conds[] = $wpdb->prepare( 'title LIKE %s', '%' . $wpdb->esc_like( $search ) . '%' );
		}

		$conds = count( $conds ) > 0 ? 'WHERE ' . $this->get_cond( $conds ) : '';

		$offset = ( $page - 1 ) * $limit;
		$_limit = $wpdb->prepare( 'LIMIT %d OFFSET %d', $limit, $offset );

		$sql = "SELECT * 
				FROM {$this->ab_tests_table} 
				{$conds}
				ORDER BY `id` DESC
				{$_limit}";

		$data = $wpdb->get_results( $sql ); // phpcs:ignore

		$res = array();

		foreach ( $data as $d ) {
			$res[] = array(
				'id'         => $d->id,
				'title'      => $d->title,
				'type'       => AbTesting::decode_type( $d->type ),
				'status'     => AbTesting::decode_status( $d->status ),
				'metric'     => AbTesting::decode_metric( $d->metric ),
				'result'     => ! empty( $d->result ) ? json_decode( $d->result, true ) : null,
				'started_at' => $d->started_at ? $this->get_formatted_date( $d->started_at ) : null,
				'duration'   => $d->duration,
			);
		}

		$status_cond = ! empty( $search ) ? $wpdb->prepare( 'WHERE `title` LIKE %s', '%' . $wpdb->esc_like( $search ) . '%' ) : '';

		$status_sql =
			"SELECT `status`, COUNT(*) AS `count`
			FROM {$this->ab_tests_table}
			{$status_cond}
			GROUP BY `status`
			";

		// Status counts.
		$status_data = $wpdb->get_results( $status_sql ); // phpcs:ignore

		$status_counts = array();
		$all_count     = 0;

		foreach ( $status_data as $sd ) {
			$status_counts[ AbTesting::decode_status( $sd->status ) ] = intval( $sd->count );
			$all_count += intval( $sd->count );
		}

		foreach ( AbTesting::$status_map as $key => $val ) {
			if ( ! isset( $status_counts[ $key ] ) ) {
				$status_counts[ $key ] = 0;
			}
		}

		$status_counts['all'] = $all_count;

		$total_pages = intval( ceil( $all_count / $limit ) );

		return array(
			'posts'        => $res,
			'total_pages'  => $total_pages,
			'current_page' => $page,
			'count'        => $status_counts,
		);
	}

	/**
	 * Get AB test
	 *
	 * @param int $id test id.
	 * @return array
	 */
	public function get_ab_test( $id ) {

		global $wpdb;

		$sql = "SELECT abt.*, v.optin_id, v.t_dist, c.title as `optin_title`, c.type as `optin_type`
				FROM 
					{$this->ab_tests_table} abt
				LEFT JOIN 
					{$this->ab_test_variants_table} v ON v.test_id = abt.id
				LEFT JOIN 
					{$this->optins_table} c ON c.id = v.optin_id
				WHERE 
					abt.id = %d";

		$abtest = $wpdb->get_results( $wpdb->prepare($sql, $id ) ); // phpcs:ignore

		$res = array();

		if ( isset( $abtest[0] ) ) {
			$d = $abtest[0];

			$res = array(
				'id'         => $d->id,
				'title'      => $d->title,
				'type'       => AbTesting::decode_type( $d->type ),
				'status'     => AbTesting::decode_status( $d->status ),
				'metric'     => AbTesting::decode_metric( $d->metric ),
				'result'     => ! empty( $d->result ) ? json_decode( $d->result, true ) : null,
				'started_at' => $d->started_at ? $this->get_formatted_date( $d->started_at ) : null,
				'duration'   => is_numeric( $d->duration ) ? intval( $d->duration ) : null,
				'optins'     => array(),
			);

			foreach ( $abtest as $d ) {
				if ( ! empty( $d->optin_id ) ) {
					$res['optins'][] = array(
						'id'     => $d->optin_id,
						'title'  => $d->optin_title,
						'type'   => $this->get_decoded_conv_type2( $d->optin_type ),
						't_dist' => $d->t_dist,
					);
				}
			}

			if ( count( $res['optins'] ) < 1 ) {
				$res['optins'] = array(
					array(
						'id'     => null,
						'title'  => '',
						'type'   => null,
						't_dist' => 50,
					),
					array(
						'id'     => null,
						'title'  => '',
						'type'   => null,
						't_dist' => 50,
					),
				);
			}
		}

		return $res;
	}

		/**
		 * Get AB test Stats
		 *
		 * @param int $id test id.
		 * @return array
		 */
	public function get_ab_test_stats( $id ) {

		global $wpdb;

		$abtest = $this->get_ab_test( $id );

		if ( empty( $abtest ) ) {
			return array();
		}

		$has_ended = 'end' === $abtest['status'];

		$id_str = '';
		$stats  = null;

		if ( $has_ended ) {
			$stats = $abtest['result'];

			if ( empty( $stats ) ) {
				return array();
			}

			$id_str = implode( ',', $stats['optin_ids'] );
		} else {
			$ids = array_map(
				function ( $optin ) {
					return $optin['id'];
				},
				$abtest['optins']
			);

			$stats = $this->get_optin_stats_by_ids( $ids );

			$id_str = implode( ',', $ids );
		}

		$sql =
			"SELECT 
				o.id AS `id`, 
				o.title AS `title`,
				o.type AS `type`,
				v.t_dist AS `t_dist`
			FROM {$this->optins_table} o
			LEFT JOIN {$this->ab_test_variants_table} v ON v.optin_id = o.id
			WHERE o.id IN ( {$id_str} ) AND v.test_id = {$abtest['id']}";

		$optin_data = (array) $wpdb->get_results( $sql, OBJECT_K ); // phpcs:ignore

		foreach ( $stats['stats'] as &$stat ) {
			$od = isset( $optin_data[ $stat['id'] ] ) ? (array) $optin_data[ $stat['id'] ] : false;

			// Default values.
			$stat['title']      = null;
			$stat['t_dist']     = null;
			$stat['optin_type'] = null;

			if ( $od ) {
				$stat['title']      = $od['title'];
				$stat['t_dist']     = $od['t_dist'];
				$stat['optin_type'] = $this->get_decoded_conv_type2( $od['type'] );
			} else {
				$stat['deleted'] = true;
			}

			if ( $has_ended ) {
				$stat['winner'] = $stat['id'] == $stats['winner_id']; // phpcs:ignore
			}
		}

		return array(
			'total' => $stats['total'],
			'stats' => $stats['stats'],
		);
	}

	/**
	 * Adds an AB test
	 *
	 * @param CreateAbTestDto $dto create ab test dto.
	 * @return boolean
	 */
	public function add_ab_test( CreateAbTestDto $dto ) {

		global $wpdb;

		$data = array(
			'title'      => $dto->title,
			'type'       => AbTesting::encode_type( $dto->type ),
			'status'     => AbTesting::encode_status( $dto->status ),
			'metric'     => AbTesting::encode_metric( $dto->metric ),
			'started_at' => $dto->started_at,
			'duration'   => $dto->duration,
		);

		$wpdb->insert( $this->ab_tests_table, $data );

		$id = $wpdb->insert_id;

		if ( empty( $id ) ) {
			return false;
		}

		$rows      = array();
		$optin_ids = array();

		foreach ( $dto->optins as $optin ) {
			$rows[]      = "({$id}, {$optin['id']}, {$optin['t_dist']})";
			$optin_ids[] = $optin['id'];
		}

		$sql = "INSERT INTO {$this->ab_test_variants_table} 
				(`test_id`,`optin_id`,`t_dist`) VALUES "
				. implode( ', ', $rows );

		$success = $wpdb->query($sql); // phpcs:ignore

		if ( ! $success ) {
			$wpdb->delete( $this->ab_tests_table, array( 'id' => $id ) );
			return false;
		}

		// Enabling optins.
		$ids_str = implode(
			',',
			$optin_ids
		);
		if ( $this->supports_multi_ts_current() ) {
			$sql =
				"UPDATE {$this->optins_table} 
			SET `enabled` = 1,
				`deleted_at` = NULL
			WHERE `id` IN ( {$ids_str} )";
		} else {
			$sql =
				"UPDATE {$this->optins_table} 
			SET `enabled` = 1,
				`deleted_at` = NULL,
				`updated_at` = NOW()
			WHERE `id` IN ( {$ids_str} )";
		}

		$wpdb->query( $sql ); // phpcs:ignore

		do_action( 'optn_abt_created', $id, $dto );
		do_action( 'optn_compat_purge_cache' );

		return true;
	}

	/**
	 * Adds an AB test
	 *
	 * @param UpdateAbTestDto $dto create ab test dto.
	 * @return boolean
	 */
	public function update_ab_test( UpdateAbTestDto $dto ) {

		global $wpdb;

		$data = array(
			'title'  => $dto->title,
			'type'   => AbTesting::encode_type( $dto->type ),
			'status' => AbTesting::encode_status( $dto->status ),
			'metric' => AbTesting::encode_metric( $dto->metric ),
		);

		if ( $dto->update_duration ) {
			$data['duration']   = $dto->duration;
			$data['started_at'] = $dto->started_at;
		}

		if ( 'draft' === $dto->status || 'manual' === $dto->type ) {
			$data['started_at'] = null;
			$data['duration']   = null;
		}

		$res = $wpdb->update( $this->ab_tests_table, $data, array( 'id' => $dto->id ), null, array( '%d' ) );

		if ( false === $res ) {
			return false;
		}

		$wpdb->delete( $this->ab_test_variants_table, array( 'test_id' => $dto->id ), array( '%d' ) );

		$rows = array();

		foreach ( $dto->optins as $optin ) {
			$rows[] = "({$dto->id}, {$optin['id']}, {$optin['t_dist']})";
		}

		$sql = "INSERT INTO {$this->ab_test_variants_table} 
				(`test_id`,`optin_id`,`t_dist`) VALUES "
				. implode( ', ', $rows );

		$res = (bool) $wpdb->query($sql); // phpcs:ignore

		if ( $res ) {
			do_action( 'optn_abt_updated', $dto );
		}

		do_action( 'optn_compat_purge_cache' );

		return $res;
	}

	/**
	 * Deletes an AB test
	 *
	 * @param int $id id.
	 * @return boolean
	 */
	public function delete_ab_test( $id ) {

		global $wpdb;

		do_action( 'optn_abt_before_delete', $id );

		// Disabling all the variants.
		$sql         = "SELECT v.optin_id
				FROM {$this->ab_tests_table} t
				LEFT JOIN {$this->ab_test_variants_table} v ON v.test_id = t.id
				WHERE t.id = %d";
		$variant_ids = $wpdb->get_col( $wpdb->prepare( $sql, $id ) ); // phpcs:ignore
		$variant_ids = join( ',', $variant_ids );

		if ( $this->supports_multi_ts_current() ) {
			$sql = "UPDATE {$this->optins_table}
				SET `enabled` = 0
				WHERE id IN ( {$variant_ids} )";
		} else {
			$sql = "UPDATE {$this->optins_table}
				SET `enabled` = 0, `updated_at` = NOW()
				WHERE id IN ( {$variant_ids} )";
		}

		$wpdb->query( $sql ); // phpcs:ignore

		// Deleting the test.
		$res = $wpdb->delete( $this->ab_tests_table, array( 'id' => $id ), array( '%d' ) );

		do_action( 'optn_compat_purge_cache' );

		return (bool) $res;
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

		$res = $wpdb->get_results( $sql, ARRAY_A); // phpcs:ignore

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

		$res = $wpdb->get_results( $sql, ARRAY_A); // phpcs:ignore

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

		$raw_data = $wpdb->get_results($sql); // phpcs:ignore

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

		$count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->leads_table}"); // phpcs:ignore

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

		$raw_sales_data = $wpdb->get_results( $wpdb->prepare( $raw_sales_sql, $days )); //phpcs:ignore

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
			$cr            = isset( $conv[ $c->id ] ) ? round( ( $conv[ $c->id ] / $c->views ) * 100, 2 ) : 0;
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
				SUM((i.click = TRUE) + (i.order_id IS NOT NULL)) AS `conversions`,
				IFNULL(SUM((i.click = TRUE) + (i.order_id IS NOT NULL)) / NULLIF(COUNT(*), 0), 0) AS `cr`
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

				$o_data['views']       = Utils::format_number( intval( $od['views'] ) );
				$o_data['conversions'] = Utils::format_number( intval( $od['conversions'] ) );
				$o_data['cr']          = ( round( floatval( $od['cr'] ), 2 ) * 100 ) . '%';
				$o_data['leads']       = Utils::format_number( $leads );
				$o_data['revenue']     = Utils::get_currency_symbol() . Utils::format_number( $revenue );
				$o_data['optin_type']  = $this->get_decoded_conv_type2( $od['type'] );

				$total['views']       += $od['views'];
				$total['conversions'] += $od['conversions'];
				$total['leads']       += $leads;
				$total['revenue']     += $revenue;
			}

			$optin_stats[] = $o_data;
		}

		$total['cr']          = $total['views'] > 0 ? round( $total['conversions'] / $total['views'] * 100, 2 ) . '%' : '0.00%';
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

		if ( $res ) {
			do_action(
				'optn_viewed_optin',
				array(
					'conv_id' => $id,
				)
			);
		}

		$curr_id = $wpdb->insert_id;

		return array(
			'id' => $curr_id,
		);
	}

	/**
	 * Add goal tracking
	 *
	 * @param int    $id        Interaction ID.
	 * @param string $goal_type Goal type.
	 * @param mixed  $value     Goal value.
	 * @param string $path      Goal path.
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
				'id' => $id,
			)
		);

		if ( $res ) {
			do_action(
				'optn_converted',
				array( 'id' => $id ),
			);
		}

		return array(
			'success' => is_int( $res ) && $res > 0 ? 1 : 0,
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
				'id'      => $id,
				'conv_id' => $conv_id,
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

		return array(
			'success' => is_int( $res ) && $res > 0 ? 1 : 0,
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

		$sql = "SELECT `data`
				FROM {$this->interactions_table} i 
				WHERE i.id = %d";

		$data = $wpdb->get_var( $wpdb->prepare( $sql, $id ) ); // phpcs:ignore

		$data = empty( $data ) ? array() : json_decode( $data, true );

		$data['social']           = ! empty( $data['social'] ) ? $data['social'] : array();
		$data['social'][ $value ] = true;

		$res = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->interactions_table, // phpcs:ignore
			array(
				'click'      => true,
				'data'       => wp_json_encode( $data ),
				'updated_at' => current_time( 'mysql' ),
			),
			array(
				'id' => $id,
			)
		);

		if ( $res ) {
			do_action(
				'optn_converted',
				array( 'id' => $id ),
			);
		}

		return array(
			'success' => is_int( $res ) && $res > 0 ? 1 : 0,
		);
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

		$res['rate']['total'] = strval( round( $curr_rate, 2 ) ) . '%';
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
	 * @param object $post_data Post data object.
	 * @param string $type      Optin type.
	 * @return array Processed data array for database.
	 */
	private function get_processed_data_for_db( $post_data, $type ) {

		$data = array();

		if ( 'src' === $post_data->audience->tSrc ) {
			$traffic_url         = isset( $post_data->audience->tUrl ) ? $post_data->audience->tUrl : null;
			$data['traffic_url'] = $traffic_url;
		} else {
			$data['traffic_url'] = null;
		}

		if ( 'custom' === $post_data->schedule->endType && ! empty( $post_data->schedule->endDate ) && ! empty( $post_data->schedule->endTime ) ) {
			$end_datetime         = gmdate( 'Y-m-d H:i:s', strtotime( $post_data->schedule->endDate . ' ' . $post_data->schedule->endTime ) );
			$data['end_datetime'] = $end_datetime;
		} else {
			$data['end_datetime'] = null;
		}

		$data['device']          = $this->get_encoded_device( $post_data->audience );
		$data['os']              = $this->get_encoded_os( $post_data->audience );
		$data['traffic_channel'] = $this->get_encoded_traffic_channel( $post_data->audience );
		$data['visitor']         = $this->get_encoded_visitor( $post_data->audience );

		$data['embed'] = null;

		if ( 'inline' === $type && 'embed' === $post_data->design->pos->inlineType && ! empty( $post_data->design->pos->embedPosition ) ) {
			$hook = sanitize_key( $post_data->design->pos->embedPosition );

			if ( 'custom' === $hook ) {
				$hook = 'custom###' .
					sanitize_key( $post_data->design->pos->customHookPos ) . '###' .
					sanitize_key( $post_data->design->pos->customHookName );
			}

			$data['embed'] = esc_sql( $hook );
		}

		return $data;
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

		$post_data = empty( $data ) ? null : json_decode( $data );

		if ( ! empty( $post_data ) ) {
			$updated_data         = $this->get_processed_data_for_db( $post_data, $type );
			$updated_data['data'] = $data;
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
			return array(
				'title'  => $conversion->title,
				'data'   => isset( $conversion->data ) ? $conversion->data : null,
				'status' => $conversion->enabled ? 'publish' : 'draft',
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
	 * Copy an existing post
	 *
	 * @param int $id Optin ID to copy.
	 * @return array New post data.
	 */
	public function copy_post( $id ) {
		global $wpdb;

		$original = $this->get_conv_by_id( $id );

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

		$enabled = isset( $args['status'] ) ? boolval( $args['status'] ) : true;

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

		foreach ( $data as $d ) {
			$title = sanitize_text_field( $d->title );

			$enc_data = wp_json_encode( $d->data );

			$db_data            = $this->get_processed_data_for_db( $d->data, $d->type );
			$db_data['title']   = $title;
			$db_data['enabled'] = $enabled;
			$db_data['data']    = $enc_data;
			$db_data['type']    = $this->get_encoded_conv_type( $d->type );

			$success = (bool) $wpdb->insert( $this->optins_table, $db_data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			if ( $success ) {
				$res[] = array(
					'id'    => $wpdb->insert_id,
					'title' => $title,
					'type'  => $d->type,
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

			$db_data            = $this->get_processed_data_for_db( $data, $type );
			$db_data['title']   = sanitize_text_field( $title );
			$db_data['enabled'] = false;
			$db_data['data']    = $enc_data;
			$db_data['type']    = $this->get_encoded_conv_type( $type );

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
				'traffic_channel' => $this->get_encoded_traffic_channel( 'default' ),
				'visitor'         => $this->get_encoded_visitor( 'default' ),
				'device'          => $this->get_encoded_device( 'default' ),
				'os'              => $this->get_encoded_os( 'default' ),
			);

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
		$device           = $this->get_encoded_device( Device::get_device() );
		$os               = $this->get_encoded_os( Os::getOS() );
		$visitor_types    = $this->get_encoded_visitor( Utils::get_visitor_types() );
		$traffic_channels = $this->get_encoded_traffic_channel( TrafficChannel::get_traffic_channels() );

		global $wpdb;

		$id_cond = '';

		if ( ! empty( $id ) ) {
			$id_cond .= $wpdb->prepare( ' AND c.id = %d ', $id );
		}

		if ( ! empty( $embed ) ) {
			$id_cond .= ' AND c.embed IS NOT NULL ';
		} else {
			$id_cond .= ' AND c.embed IS NULL ';
		}

		if ( $is_inline ) {
			$id_cond .= ' AND c.type = 3 ';
		} else {
			$id_cond .= ' AND c.type != 3 ';
		}

		$posts = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				' SELECT c.*, v.test_id, v.t_dist ' .
				" FROM {$this->optins_table} c " . // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				" LEFT JOIN {$this->ab_test_variants_table} v ON v.optin_id = c.id " . // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				" WHERE
					c.enabled = 1 AND
					c.device & %d > 0 AND
					c.os & %d > 0 AND
					c.visitor & %d > 0 AND
					c.traffic_channel & %d > 0 AND
					( c.end_datetime IS NULL OR c.end_datetime > NOW() ) AND
					( c.traffic_url IS NULL OR c.traffic_url = %s )
					{$id_cond}", // phpcs:ignore
				$device,
				$os,
				$visitor_types,
				$traffic_channels,
				isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : ''
			)
		);

		return $posts;
	}

	/**
	 * Clear interaction table of old data.
	 *
	 * @param int $days_older_than Delete interactions older than this many days.
	 * @return bool
	 */
	public function clear_interactions( $days_older_than ) {
		global $wpdb;

		$summary_option = 'optn_interactions_summary';

		$days_older_than = intval( $days_older_than );

		if ( $days_older_than < 1 ) {
			return false;
		}

		$where = $wpdb->prepare(
			"i.`created_at` < (NOW() - INTERVAL %d DAY) AND NOT EXISTS (SELECT 1 FROM {$this->ab_test_variants_table} v INNER JOIN {$this->ab_tests_table} t ON t.id = v.test_id WHERE v.optin_id = i.conv_id AND t.status = %d)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$days_older_than,
			AbTesting::ACTIVE_STATUS
		);

		// Aggregate the data that is about to be deleted.
		$impressions_total = intval(
			$wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$this->interactions_table} i WHERE {$where}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			)
		);

		$impressions_by_device_rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT COALESCE(i.`device`, -1) AS `device`, COUNT(*) AS `count` FROM {$this->interactions_table} i WHERE {$where} GROUP BY COALESCE(i.`device`, -1)" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$impressions_by_country_rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT LOWER(COALESCE(i.`country`, 'unknown')) AS `country`, COUNT(*) AS `count` FROM {$this->interactions_table} i WHERE {$where} GROUP BY LOWER(COALESCE(i.`country`, 'unknown'))" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$conversions_total = intval(
			$wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT IFNULL(SUM((i.click = TRUE) + (i.order_id IS NOT NULL)), 0) FROM {$this->interactions_table} i WHERE {$where}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			)
		);

		$sales_rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT i.`order_type`, i.`order_id` FROM {$this->interactions_table} i WHERE {$where} AND i.`order_id` IS NOT NULL AND i.`order_type` IS NOT NULL" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$revenue_total = 0.0;
		foreach ( $sales_rows as $sale ) {
			$revenue_total += floatval( Utils::get_revenue( $sale->order_type, $sale->order_id ) );
		}

		$impressions_by_device = array();
		foreach ( (array) $impressions_by_device_rows as $row ) {
			$key = strval( intval( $row->device ) );
			if ( -1 === intval( $row->device ) ) {
				$key = 'unknown';
			}
			$impressions_by_device[ $key ] = intval( $row->count );
		}

		$impressions_by_country = array();
		foreach ( (array) $impressions_by_country_rows as $row ) {
			$key = sanitize_key( $row->country );
			if ( empty( $key ) ) {
				$key = 'unknown';
			}
			$impressions_by_country[ $key ] = intval( $row->count );
		}

		$run_summary = array(
			'last_run_at'            => current_time( 'mysql' ),
			'days_older_than'        => $days_older_than,
			'impressions_total'      => $impressions_total,
			'impressions_by_device'  => $impressions_by_device,
			'impressions_by_country' => $impressions_by_country,
			'conversions_total'      => $conversions_total,
			'revenue_total'          => $revenue_total,
		);

		$sql = $wpdb->prepare(
			"DELETE i FROM {$this->interactions_table} i WHERE i.`created_at` < (NOW() - INTERVAL %d DAY) AND NOT EXISTS (SELECT 1 FROM {$this->ab_test_variants_table} v INNER JOIN {$this->ab_tests_table} t ON t.id = v.test_id WHERE v.optin_id = i.conv_id AND t.status = %d)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$days_older_than,
			AbTesting::ACTIVE_STATUS
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$res = $wpdb->query( $sql );

		if ( false === $res ) {
			return false;
		}

		// Persist the summary only after a successful deletion.
		if ( $run_summary['impressions_total'] > 0 || $run_summary['conversions_total'] > 0 || $run_summary['revenue_total'] > 0 ) {
			$prev_summary = get_option( $summary_option );
			$prev_summary = is_array( $prev_summary ) ? $prev_summary : array();

			$merged = array(
				'purge_runs'             => isset( $prev_summary['purge_runs'] ) ? intval( $prev_summary['purge_runs'] ) + 1 : 1,
				'last_run_at'            => $run_summary['last_run_at'],
				'last_deleted_rows'      => intval( $res ),
				'days_older_than'        => $days_older_than,
				'impressions_total'      => ( isset( $prev_summary['impressions_total'] ) ? intval( $prev_summary['impressions_total'] ) : 0 ) + $run_summary['impressions_total'],
				'conversions_total'      => ( isset( $prev_summary['conversions_total'] ) ? intval( $prev_summary['conversions_total'] ) : 0 ) + $run_summary['conversions_total'],
				'revenue_total'          => ( isset( $prev_summary['revenue_total'] ) ? floatval( $prev_summary['revenue_total'] ) : 0.0 ) + $run_summary['revenue_total'],
				'impressions_by_device'  => array(),
				'impressions_by_country' => array(),
			);

			$prev_device  = isset( $prev_summary['impressions_by_device'] ) && is_array( $prev_summary['impressions_by_device'] ) ? $prev_summary['impressions_by_device'] : array();
			$prev_country = isset( $prev_summary['impressions_by_country'] ) && is_array( $prev_summary['impressions_by_country'] ) ? $prev_summary['impressions_by_country'] : array();

			$device_keys = array_unique( array_merge( array_keys( $prev_device ), array_keys( $run_summary['impressions_by_device'] ) ) );
			foreach ( $device_keys as $key ) {
				$merged['impressions_by_device'][ $key ] = intval( $prev_device[ $key ] ?? 0 ) + intval( $run_summary['impressions_by_device'][ $key ] ?? 0 );
			}

			$country_keys = array_unique( array_merge( array_keys( $prev_country ), array_keys( $run_summary['impressions_by_country'] ) ) );
			foreach ( $country_keys as $key ) {
				$merged['impressions_by_country'][ $key ] = intval( $prev_country[ $key ] ?? 0 ) + intval( $run_summary['impressions_by_country'][ $key ] ?? 0 );
			}

			update_option( $summary_option, $merged, false );
		}

		do_action( 'optn_compat_purge_cache' );

		return true;
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
			case 'zapier':
				return 'Zapier';
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
			default:
				return __( 'Unknown', 'optin' );
		}
	}

	/**
	 * Get encoded traffic channel
	 *
	 * @param mixed $data data.
	 * @return string|null
	 */
	private function get_encoded_traffic_channel( $data ) {

		/*
		Bit mapping:
		---------------

			0 0 0 0
			^ ^ ^ ^
			| | | |
			| | | -- org
			| | ---- paid
			| ------ social
			-------- direct
		*/

		if ( 'default' === $data || ( isset( $data->tSrc ) && 'all' === $data->tSrc ) ) {
			return 15;
		}

		if ( is_array( $data ) ) {

			$channel_lookup = array(
				'direct' => 8,
				'social' => 4,
				'paid'   => 2,
				'org'    => 1,
			);

			$curr_channel = 0;

			foreach ( $data as $channel ) {
				if ( isset( $channel_lookup[ $channel ] ) ) {
					$curr_channel |= $channel_lookup[ $channel ];
				}
			}

			return 0 !== $curr_channel ? $curr_channel : 15;
		}

		if ( 'channel' === $data->tSrc ) {
			return Utils::bin_array_to_dec(
				array(
					$data->tDirect,
					$data->tSocial,
					$data->tPaidSearch,
					$data->tOrgSearch,
				)
			);
		}

		return null;
	}

	/**
	 * Get encoded visitor type.
	 *
	 * @param mixed $data Visitor data.
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
	 * Get encoded device type.
	 *
	 * @param mixed $data Device data.
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
	 * Get encoded OS type.
	 *
	 * @param mixed $data OS data.
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
