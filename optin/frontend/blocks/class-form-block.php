<?php // phpcs:ignore

namespace OPTN\Frontend\Blocks;

use OPTN\Includes\Abstracts\BaseBlock;
use OPTN\Includes\Utils\BlockUtils;
use OPTN\Includes\Utils\FormHelper;

/**
 * Renders a campaign form block with its saved fields and signed submission context.
 *
 * Builds the form controls through FormHelper and adds a campaign-bound token so the
 * public submission endpoint can validate the form against the current saved revision.
 */
class FormBlock extends BaseBlock {
	/**
	 * Add signed campaign and form identifiers to the rendered form attributes.
	 *
	 * When the block belongs to a saved campaign, this method includes its campaign ID,
	 * form client ID, and a token bound to the current saved campaign revision.
	 *
	 * @return array<string, string|int> HTML attributes shared by the form block.
	 */
	protected function get_common_html_attr_array() {
		$attributes = parent::get_common_html_attr_array();
		if ( isset( $this->post_data->_campaign ) ) {
			$campaign                            = $this->post_data->_campaign;
			$attributes['data-optn-campaign-id'] = $campaign->id;
			$attributes['data-optn-form-id']     = $this->data->clientId;
			$attributes['data-optn-form-token']  = \OPTN\Includes\Utils\FormSubmission::create_token( (int) $campaign->id, $this->data->clientId, \OPTN\Includes\Utils\FormSubmission::revision( $campaign ) );
		}
		return $attributes;
	}

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

		$html = '<form ' . BlockUtils::build_html_attrs( $this->get_common_html_attr_array() ) . '>';

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

		$html = '<form ' . BlockUtils::build_html_attrs( $this->get_common_html_attr_array() ) . '>';

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
		$html       .= esc_html( $attr->btnText );  // phpcs:ignore
		$html       .= '</div>';
		$html       .= '</button>';

		$html .= '</div>';
		$html .= '</form>';

		return $html;
	}
}
