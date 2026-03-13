<?php // phpcs:ignore

namespace OPTN\Frontend\Blocks;

use OPTN\Includes\Abstracts\BaseBlock;
use OPTN\Includes\Utils\BlockUtils;

/**
	Coupon Block
 */
class CouponBlock extends BaseBlock {

	/**
	 * Get block name
	 *
	 * @return string
	 */
	protected function get_block_name() {
		return 'coupon';
	}

	/**
	 * V2
	 *
	 * @return string
	 */
	protected function v2() {
		$attr   = $this->data->attributes;
		$preset = $attr->preset;

		BlockUtils::add_google_font( $attr->msgTypo->fontFamily, array( $attr->msgTypo->fontWeight ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		BlockUtils::add_google_font( $attr->codeTypo->fontFamily, array( $attr->codeTypo->fontWeight ) );  // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		$html = '<button ' . BlockUtils::build_html_attrs( $this->get_common_html_attr_array() ) . ' >';

		// Message.
		if ( '1' === $preset ) {
			$html .= '<div class="optn-block-coupon-msg">';
			$html .= esc_html( $attr->msg ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$html .= '</div>';
		}

		// Code.
		$html .= '<div class="optn-block-coupon-code">';
		$html .= esc_html( $attr->code ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$html .= '</div>';

		// Icon.
		if ( '2' === $preset ) {
			$html .= '<div class="optn-block-coupon-icon">';
			$html .= BlockUtils::get_icon_html( $attr->icon );
			$html .= '</div>';
		}

		// Message.
		if ( '3' === $preset ) {
			$html .= '<div class="optn-block-coupon-msg">';
			$html .= esc_html( $attr->msg ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$html .= '</div>';
		}

		$html .= '</button>';

		return $html;
	}

	/**
	 * V1
	 *
	 * @return string
	 */
	protected function v1() {
		$attr = $this->data->attributes;

		BlockUtils::add_google_font( $attr->msgTypo->fontFamily, array( $attr->msgTypo->fontWeight ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		BlockUtils::add_google_font( $attr->codeTypo->fontFamily, array( $attr->codeTypo->fontWeight ) );  // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		$html = '<button ' . BlockUtils::build_html_attrs( $this->get_common_html_attr_array() ) . ' >';

		// Message.
		$html .= '<div class="optn-block-coupon-msg">';
		$html .= esc_html( $attr->msg ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$html .= '</div>';

		// Code.
		$html .= '<div class="optn-block-coupon-code">';
		$html .= esc_html( $attr->code ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$html .= '</div>';

		$html .= '</button>';

		return $html;
	}
}
