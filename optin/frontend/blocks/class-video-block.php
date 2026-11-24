<?php // phpcs:ignore

namespace OPTN\Frontend\Blocks;

use OPTN\Includes\Abstracts\BaseBlock;
use OPTN\Includes\Utils\BlockUtils;
use OPTN\Includes\Utils\VideoHelper;

/**
	Video Block
 */
class VideoBlock extends BaseBlock {

	/**
	 * Get block name
	 *
	 * @return string
	 */
	protected function get_block_name() {
		return 'video';
	}

	/**
	 * V1
	 *
	 * @return string
	 */
	protected function v1() {
		$attr  = $this->data->attributes;
		$html  = '<div ' . BlockUtils::build_html_attrs( $this->get_common_html_attr_array() ) . '>';
		$html .= VideoHelper::get_video_html( $attr );
		$html .= '</div>';
		return $html;
	}
}
