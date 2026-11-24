<?php // phpcs:ignore

namespace OPTN\Admin;

use OPTN\Includes\Db;

defined( 'ABSPATH' ) || exit;

/**
 * Prepares captured lead records for download from the administration interface.
 *
 * Loads stored lead values, shapes them into export rows, and returns the data consumed
 * by the REST endpoint that initiates a lead export.
 */
class ExportHelper {

	/**
	 * Get lead data formatted for export.
	 *
	 * @return array
	 */
	public static function export_leads() {

		/**
		 * DB Instance.
		 *
		 * @var Db $db
		 */
		$db = Db::get_instance();

		$raw_data = $db->get_leads( -1, -1 );

		$leads = $raw_data['leads'] ?? array();

		$data = array();

		foreach ( $leads as $lead ) {
			$ip = $lead['data']['ip'] ?? '';
			unset( $lead['data']['ip'] );
			$data[] = array(
				'id'          => $lead['id'],
				'optin_id'    => $lead['conv_id'],
				'optin_title' => $lead['conv_title'],
				'email'       => $lead['email'],
				'name'        => $lead['name'],
				'integration' => $lead['integration'],
				'ip'          => $ip,
				'data'        => wp_json_encode( $lead['data'] ?? array() ),
				'created_at'  => $lead['created_at'] ?? '',
			);
		}

		return array(
			'filename' => 'wowoptin_leads_' . current_time( 'Y-m-d_H:i:s' ) . '.csv',
			'data'     => $data,
		);
	}
}
