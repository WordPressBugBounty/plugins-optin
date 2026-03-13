<?php // phpcs:ignore

namespace OPTN\Includes\Abstracts;

defined( 'ABSPATH' ) || exit;

/**
 * Block Interface
 */
interface Block {

	/**
	 * Block constructor.
	 *
	 * @param object      $data block data.
	 * @param object|null $post_data post data.
	 */
	public function __construct( $data, $post_data = null );

	/**
	 * Generate block html
	 *
	 * @return string
	 */
	public function generate();
}
