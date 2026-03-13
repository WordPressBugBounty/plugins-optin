<?php // phpcs:ignore

namespace OPTN\Frontend\Blocks;

use OPTN\Includes\Abstracts\BaseBlock;
use OPTN\Includes\Utils\BlockUtils;
use OPTN\Includes\Utils\FormHelper;

/**
	Form Block
 */
class FormBlock extends BaseBlock {

	/**
	 * Get block name
	 *
	 * @return string
	 */
	protected function get_block_name() {
		return 'form';
	}

	/**
	 * V2
	 *
	 * @return string
	 */
	protected function v2() {
		$attr = $this->data->attributes;

		BlockUtils::add_google_font( $attr->labelTypo->fontFamily, array( $attr->labelTypo->fontWeight ) ); // phpcs:ignore
		BlockUtils::add_google_font( $attr->inputTypo->fontFamily, array( $attr->inputTypo->fontWeight ) ); // phpcs:ignore
		BlockUtils::add_google_font( $attr->msgTypo->fontFamily, array( $attr->msgTypo->fontWeight ) ); // phpcs:ignore
		BlockUtils::add_google_font( $attr->btnTypo->fontFamily, array( $attr->btnTypo->fontWeight ) ); // phpcs:ignore

		$html = '<form ' . BlockUtils::build_html_attrs( $this->get_common_html_attr_array() ) . ' ">';

		$html .= '<div class="optn-block-form-content">';

		$html .= FormHelper::get_input_html_v2( $attr );

		$html .= '</div>';
		$html .= '</form>';

		return $html;
	}

	/**
	 * V1
	 *
	 * @return string
	 */
	protected function v1() {
		$attr = $this->data->attributes;

		BlockUtils::add_google_font( $attr->labelTypo->fontFamily, array( $attr->labelTypo->fontWeight ) ); // phpcs:ignore
		BlockUtils::add_google_font( $attr->inputTypo->fontFamily, array( $attr->inputTypo->fontWeight ) ); // phpcs:ignore
		BlockUtils::add_google_font( $attr->msgTypo->fontFamily, array( $attr->msgTypo->fontWeight ) ); // phpcs:ignore
		BlockUtils::add_google_font( $attr->btnTypo->fontFamily, array( $attr->btnTypo->fontWeight ) ); // phpcs:ignore

		$html = '<form ' . BlockUtils::build_html_attrs( $this->get_common_html_attr_array() ) . ' ">';

		$html .= '<div class="optn-block-form-content">';

		$html .= FormHelper::get_input_html( $attr->fields );

		// Form Button.
		$button_data = array(
			'text'           => $attr->btnText, // phpcs:ignore
			'submittedText'  => $attr->btnSuccessText, // phpcs:ignore
			'submittingText' => $attr->btnWaitText, // phpcs:ignore
		);
		$html       .= '<button class="optn-block-form-button" type="submit" ';
		$html       .= 'data-optn="' . esc_attr( wp_json_encode( $button_data ) ) . '">';
		$html       .= '<div class="optn-block-form-button-text">';
		$html .= esc_html( $attr->btnText );  // phpcs:ignore
		$html       .= '</div>';
		$html       .= '</button>';

		$html .= '</div>';
		$html .= '</form>';

		return $html;
	}
}
