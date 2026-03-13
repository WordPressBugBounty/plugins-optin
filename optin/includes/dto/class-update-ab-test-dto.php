<?php // phpcs:ignore

namespace OPTN\Includes\Dto;

/**
 * Option DTO
 */
class UpdateAbTestDto {

	/**
	 * ID
	 *
	 * @var int
	 */
	public $id;

	/**
	 * Title
	 *
	 * @var string
	 */
	public $title;


	/**
	 * Type
	 *
	 * @var string
	 */
	public $type;

	/**
	 * Type
	 *
	 * @var string
	 */
	public $status;

	/**
	 * Metric
	 *
	 * @var string|null
	 */
	public $metric;

	/**
	 * Duration
	 *
	 * @var int|null
	 */
	public $duration;

	/**
	 * Update duration
	 *
	 * @var boolean
	 */
	public $update_duration;

	/**
	 * Optin IDs
	 *
	 * @var array
	 */
	public $optins;

	/**
	 * Started at
	 *
	 * @var string
	 */
	public $started_at;

	/**
	 * Constructor
	 *
	 * @param int         $id ID.
	 * @param string      $title Title.
	 * @param string      $type Type.
	 * @param string      $status Status.
	 * @param string|null $metric Metric.
	 * @param int|null    $duration Duration.
	 * @param boolean     $update_duration Update Duration.
	 * @param array       $optins optin target distribution.
	 */
	public function __construct( $id, $title, $type, $status, $metric, $duration, $update_duration, $optins ) {
		$this->id              = $id;
		$this->title           = $title;
		$this->type            = $type;
		$this->status          = $status;
		$this->metric          = $metric;
		$this->duration        = $duration;
		$this->update_duration = $update_duration;
		$this->optins          = $optins;

		if ( $this->update_duration ) {
			$this->started_at = current_time( 'mysql' );
		}
	}
}
