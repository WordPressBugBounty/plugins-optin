<?php // phpcs:ignore

namespace OPTN\Includes\Dto;

/**
 * Carries either a successful option result or an error through shared services.
 *
 * Consumers inspect `data` for the returned value and `error` when the operation
 * could not complete, allowing callers to pass both outcomes through one result object.
 */
class Option {
	/**
	 * Result
	 *
	 * @var mixed|null
	 */
	public $data;

	/**
	 * Error
	 *
	 * @var mixed|null
	 */
	public $error;
}
