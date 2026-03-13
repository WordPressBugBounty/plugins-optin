<?php // phpcs:ignore

namespace OPTN\Frontend\Blocks;

use OPTN\Includes\Abstracts\BaseBlock;
use OPTN\Includes\Utils\BlockUtils;

/**
	Shape Block
 */
class ShapeBlock extends BaseBlock {

	/**
	 * Get block name
	 *
	 * @return string
	 */
	protected function get_block_name() {
		return 'shape';
	}

	/**
	 * V1
	 *
	 * @return string
	 */
	protected function v1() {
		return '<div ' . BlockUtils::build_html_attrs( $this->get_common_html_attr_array() ) . '></div>';
	}
}
