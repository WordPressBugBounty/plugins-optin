<?php // phpcs:ignore

namespace OPTN\Includes\Dto;

/**
 * Model Input DTO
 */
class ModelInput {

	/**
	 * User prompt
	 *
	 * @var string
	 */
	public $user_prompt;

	/**
	 * Developer Prompt
	 *
	 * @var string
	 */
	public $instruction;

	/**
	 * Images
	 *
	 * @var array
	 */
	public $images;

	/**
	 * Task
	 *
	 * @var string
	 */
	public $task;

	/**
	 * Constructor
	 *
	 * @param string $user_prompt user prompt.
	 * @param array  $args args.
	 */
	public function __construct( $user_prompt, $args ) {
		$this->user_prompt = $user_prompt;

		$parsed_args = wp_parse_args(
			$args,
			array(
				'instruction' => '',
				'images'      => array(),
				'task'        => 'generate',
			)
		);

		$this->instruction = $parsed_args['instruction'];
		$this->task        = $parsed_args['task'];
		$this->images      = $parsed_args['images'];
	}
}
