<?php // phpcs:ignore

namespace OPTN\Includes\Dto;

/**
 * Option DTO
 */
class CreateAbTestDto {

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
	 * @param string      $title Title.
	 * @param string      $type Type.
	 * @param string      $status Status.
	 * @param string|null $metric Metric.
	 * @param int|null    $duration Duration.
	 * @param array       $optins optin target distribution.
	 */
	public function __construct( $title, $type, $status, $metric, $duration, $optins ) {
		$this->title    = $title;
		$this->type     = $type;
		$this->status   = $status;
		$this->metric   = $metric;
		$this->duration = $duration;
		$this->optins   = $optins;

		$this->started_at = current_time( 'mysql' );
	}
}
