<?php // phpcs:ignore

namespace OPTN\Frontend\Blocks;

use OPTN\Includes\Abstracts\BaseBlock;
use OPTN\Includes\Utils\BlockUtils;
use OPTN\Includes\Utils\VideoHelper;

/**
	Columns Block
 */
class ColumnsBlock extends BaseBlock {

	/**
	 * Get block name
	 *
	 * @return string
	 */
	protected function get_block_name() {
		return 'columns';
	}

	/**
	 * V1
	 *
	 * @return string
	 */
	protected function v1() {
		$client_id    = $this->data->clientId; // phpcs:ignore
		$bg        = $this->post_data->design->bg;

		if ( isset( $post_data->design->customCss ) ) {
			$custom_css = $post_data->design->customCss; // phpcs:ignore
			add_filter(
				'optn_css',
				function ( $css ) use ( $custom_css, $client_id ) {
					if ( strpos( $custom_css, '{{optn}}' ) !== false ) {
						$css .= str_replace( '{{optn}}', '.optn-' . $client_id, $custom_css );
					}

					return $css;
				}
			);
		}

		$html  = '<div ' . BlockUtils::build_html_attrs( $this->get_common_html_attr_array() ) . '>';
		$html .= "<div class='optn-block-columns-content'>";

		if ( 'vid' === $bg->type ) {
			$html .= VideoHelper::get_video_html( $bg->vid, true, array( 'class' => 'optn-bg-video' ) );
		}

		$html .= $this->data->inner_block_html;

		$html .= '</div>';

		if ( defined( 'OPTN_SHOW_WATERMARK' ) && true === OPTN_SHOW_WATERMARK ) {
			$html .= BlockUtils::get_watermark_html();
		}

		$html .= '</div>';

		return $html;
	}
}
