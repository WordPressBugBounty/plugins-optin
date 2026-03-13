<?php // phpcs:ignore

namespace OPTN\Frontend\Blocks;

use OPTN\Includes\Abstracts\BaseBlock;
use OPTN\Includes\Utils\BlockUtils;

/**
	Button Block
 */
class ButtonBlock extends BaseBlock {

	/**
	 * Get block name
	 *
	 * @return string
	 */
	protected function get_block_name() {
		return 'button';
	}

	/**
	 * V1
	 *
	 * @return string
	 */
	protected function v1() {
		$attr = $this->data->attributes;

		BlockUtils::add_google_font(
			$attr->typo->fontFamily,
			array(
				$attr->typo->fontWeight,
				$attr->typo->boldFontWeight,
			)
		);

		$icon_html = ! empty( $attr->icon ) ? BlockUtils::get_icon_html( $attr->icon ) : '';

		$html = '<div ' . BlockUtils::build_html_attrs( $this->get_common_html_attr_array() ) . ' >';

		if ( 'link' === $attr->action ) {
			$html .= BlockUtils::get_link_opening_tag_html( $attr->link, 'class="optn-block-button-btn"' );
		} elseif ( 'call' === $attr->action ) {
			$html .= '<a  href="tel:' . esc_attr( $attr->phone ) . '" class="optn-block-button-btn" >';
		} else {
			$html .= '<div role="button" tabindex="-1" class="optn-block-button-btn" >';
		}

		if ( 'left' === $attr->iconPos ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$html .= $icon_html;
		}

		$html .= '<div class="optn-block-button-btn-text">' . $attr->text . '</div>';

		if ( 'right' === $attr->iconPos ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$html .= $icon_html;
		}

		$html .= in_array( $attr->action, array( 'link', 'call' ), true ) ? '</a>' : '</div>';
		$html .= '</div>';

		return $html;
	}
}
