<?php // phpcs:ignore

namespace OPTN\Frontend\Blocks;

use OPTN\Includes\Abstracts\BaseBlock;
use OPTN\Includes\Utils\BlockUtils;

/**
	Countdown Block
 */
class CountdownBlock extends BaseBlock {

	/**
	 * Get block name
	 *
	 * @return string
	 */
	protected function get_block_name() {
		return 'countdown';
	}

	/**
	 * V1
	 *
	 * @return string
	 */
	protected function v1() {
		$attr      = $this->data->attributes;
		$client_id = $this->data->clientId; // phpcs:ignore

		BlockUtils::add_google_font( $attr->digitTypo->fontFamily, array( $attr->digitTypo->fontWeight ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		BlockUtils::add_google_font( $attr->labelTypo->fontFamily, array( $attr->labelTypo->fontWeight ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		$def_value = $attr->format00 ? '00' : '0';

		$html = '<div ' . BlockUtils::build_html_attrs( $this->get_common_html_attr_array() ) . ' >';

		$html .= '<div style="display: none;" class="optn-countdown-typo optn-block-countdown-replace-text" id="optn-countdown-replace-text-' . esc_attr( $client_id ) . '"><span>' . esc_html( $attr->endMsg ) . '</span></div>'; // phpcs:ignore

		$html .= '<div class="optn-block-countdown-content">';

		if ( $attr->dayEnabled ) { // phpcs:ignore
			$html .= $this->get_countdown_time_part_html( $def_value, $attr->dayLabel, 'day', $attr->enableLabels ); // phpcs:ignore
		}

		if ( $attr->dayEnabled && $attr->enableSep ) { // phpcs:ignore
			$html .= $this->wrap_sep( $attr->sepStyle ); // phpcs:ignore
		}

		if ( $attr->hourEnabled ) { // phpcs:ignore
			$html .= $this->get_countdown_time_part_html( $def_value, $attr->hourLabel, 'hour', $attr->enableLabels ); // phpcs:ignore
		}

		if ( $attr->hourEnabled && $attr->enableSep ) { // phpcs:ignore
			$html .= $this->wrap_sep( $attr->sepStyle ); // phpcs:ignore
		}

		if ( $attr->minEnabled ) { // phpcs:ignore
			$html .= $this->get_countdown_time_part_html( $def_value, $attr->minLabel, 'min', $attr->enableLabels ); // phpcs:ignore
		}

		if ( $attr->minEnabled && $attr->enableSep ) { // phpcs:ignore
			$html .= $this->wrap_sep( $attr->sepStyle ); // phpcs:ignore
		}

		if ( $attr->secEnabled ) { // phpcs:ignore
			$html .= $this->get_countdown_time_part_html( $def_value, $attr->secLabel, 'sec', $attr->enableLabels ); // phpcs:ignore
		}

		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Time HTML
	 *
	 * @param string $def_value default digit value.
	 * @param string $label label.
	 * @param string $type type.
	 * @param bool   $label_enabled label enabled.
	 * @return string
	 */
	private function get_countdown_time_part_html( $def_value, $label, $type, $label_enabled ) {
		$html  = '';
		$html .= '<div class="optn-timepart optn-timepart-' . esc_attr( $type ) . '">';

		$html .= '<div class="optn-timepart-digit optn-countdown-typo">';

		$values = str_split( $def_value );

		foreach ( $values as $value ) {
			$html .= '<span class="optn-timepart-digit-num">' . esc_html( $value ) . '</span>';
		}

		$html .= '</div>';

		if ( $label_enabled ) { // phpcs:ignore
			$html .= '<div class="optn-timepart-label">' . esc_html( $label ) . '</div>';
		}

		$html .= '</div>';
		return $html;
	}

	/**
	 * Divider Wrapper
	 *
	 * @param string $div children html.
	 * @return string
	 */
	private function wrap_sep( $div ) {
		return '<div class="optn-timepart-div">' . esc_html( $div ) . '</div>';
	}
}
