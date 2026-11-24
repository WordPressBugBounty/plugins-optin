<?php // phpcs:ignore

namespace OPTN\Includes\Abstracts;

defined( 'ABSPATH' ) || exit;

/**
 * Block Interface
 */
interface Block {

	/**
	 * Construct a block renderer from decoded block data and optional campaign context.
	 *
	 * @param \stdClass      $data      Decoded block name, attributes, and nested block data.
	 * @param \stdClass|null $post_data Saved campaign record that owns the block, when available.
	 */
	public function __construct( $data, $post_data = null );

	/**
	 * Generate block html
	 *
	 * @return string
	 */
	public function generate();
}
