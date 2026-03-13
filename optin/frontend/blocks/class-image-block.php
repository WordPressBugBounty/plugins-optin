<?php // phpcs:ignore

namespace OPTN\Frontend\Blocks;

use OPTN\Includes\Abstracts\BaseBlock;
use OPTN\Includes\Utils\BlockUtils;

/**
 * Image Block
 */
class ImageBlock extends BaseBlock {

	/**
	 * Get block name
	 *
	 * @return string
	 */
	protected function get_block_name() {
		return 'image';
	}

	/**
	 * V2
	 *
	 * @return string
	 */
	protected function v2() {
		$attr = $this->data->attributes;
		$html = '';

		$common_attrs = BlockUtils::build_html_attrs( $this->get_common_html_attr_array() );

		if ( ! empty( $attr->link->url ) ) {
			$html .= BlockUtils::get_link_opening_tag_html( $attr->link, $common_attrs . ' style="display:inline-block;" ' );
		} else {
			$html .= "<div {$common_attrs} >";
		}

		$html .= '<img src="' . esc_url( $attr->src->url ) . '" alt="' . esc_attr( $attr->alt ) . '" />'; // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage

		$html .= ! empty( $attr->link->url ) ? '</a>' : '</div>';

		return $html;
	}


	/**
	 * V1
	 *
	 * @return string
	 */
	protected function v1() {
		$attr         = $this->data->attributes;
		$common_attrs = BlockUtils::build_html_attrs( $this->get_common_html_attr_array() );
		$html         = "<div {$common_attrs} >";

		if ( ! empty( $attr->link->url ) ) {
			$html .= BlockUtils::get_link_opening_tag_html( $attr->link, 'class="optn-block-image-content" style="display:inline-block;"' );
		} else {
			$html .= '<div class="optn-block-image-content" >';
		}

		$html .= '<img src="' . esc_url( $attr->src->url ) . '" alt="' . esc_attr( $attr->alt ) . '" />'; // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage

		$html .= ! empty( $attr->link->url ) ? '</a>' : '</div>';
		$html .= '</div>';

		return $html;
	}
}
