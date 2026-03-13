<?php // phpcs:ignore

namespace OPTN\Frontend\Blocks;

use OPTN\Includes\Abstracts\BaseBlock;
use OPTN\Includes\Utils\BlockUtils;

/**
	HTML Block
 */
class HtmlBlock extends BaseBlock {

	/**
	 * Get block name
	 *
	 * @return string
	 */
	protected function get_block_name() {
		return 'html';
	}

	/**
	 * V1
	 *
	 * @return string
	 */
	protected function v1() {
		$attr = $this->data->attributes;

		$html  = '<div ' . BlockUtils::build_html_attrs( $this->get_common_html_attr_array() ) . '>';
		$html .= '<div class="optn-block-html-content">';
		$html .= $attr->html;
		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}
}
