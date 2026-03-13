<?php // phpcs:ignore

/**
 * Video class
 *
 * @package opitn/inludes/uitls
 */

namespace OPTN\Includes\Utils;

/**
 * Video
 */
class FormHelper {
	/**
	 * Get checkbox html
	 *
	 * @param object $field field data.
	 * @return string
	 */
	private static function get_text_html( $field ) {

		ob_start();
		?>
		<div class="optn-block-form-input" id="<?php echo 'optn-field-' . esc_attr( $field->id ); ?>">

		<label class="optn-block-form-input-label" for="<?php echo esc_attr( $field->name ); ?>">
			<?php echo esc_html( $field->label ); ?>
		</label>

		<div class="optn-block-form-input-wrapper">
			<input 
				class="optn-block-form-input-input"
				name="<?php echo esc_attr( $field->name ); ?>"
				placeholder="<?php echo esc_attr( $field->placeholder ); ?>"
				type="<?php echo esc_attr( $field->inputType ); // phpcs:ignore ?>"
				<?php echo $field->required ? 'required' : ''; ?>
			/>

			<?php if ( ! empty( $field->message ) ) : ?>

				<div class="optn-block-form-input-msg">
					<?php echo esc_html( $field->message ); ?>
				</div>

			<?php endif; ?>
		</div>

		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Get checkbox html
	 *
	 * @param object $field field data.
	 * @return string
	 */
	private static function get_checkbox_html( $field ) {

		ob_start();
		?>
		<div 
			class="optn-block-form-input optn-block-form-input-checkbox" 
			id="<?php echo 'optn-field-' . esc_attr( $field->id ); ?>"
		>

		<div class="optn-block-form-input-checkbox">
			<input 
				name="<?php echo esc_attr( $field->name ); ?>"
				type="<?php echo esc_attr( $field->inputType ); // phpcs:ignore ?>"
				<?php echo $field->required ? 'required' : ''; ?>
			/>

			<label for="<?php echo esc_attr( $field->name ); ?>">
				<?php echo wp_kses_post( $field->label ); ?>
			</label>
		</div>

		<?php if ( ! empty( $field->message ) ) : ?>
			<div class="optn-block-form-input-msg">
				<?php echo esc_html( $field->message ); ?>
			</div>
		<?php endif; ?>

		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Extract the Vimeo video ID from a URL.
	 *
	 * @param object $attr attributes.
	 * @return string
	 */
	public static function get_input_html_v2( &$attr ) {

		$fields = $attr->fields;

		$html = '';

		foreach ( $fields as $f_id => $field ) {

			if ( 'spacer' === $field->type ) {
				$html .= self::get_spacer_html( $field );
				continue;
			}

			$html .= '<div class="optn-block-form-field-wrapper" data-optn-form-field-id="' . esc_attr( $f_id ) . '">';

			switch ( $field->type ) {
				case 'consent':
				case 'checkbox':
					BlockUtils::add_google_font( $field->typo->fontFamily, array( $field->typo->fontWeight ) ); // phpcs:ignore
					$html .= self::get_checkbox_html( $field );
					break;
				default:
					$html .= self::get_text_html( $field );
					break;
			}

			$html .= '</div>';
		}

		// Form Button.

		$html       .= '<div class="optn-block-form-field-wrapper" data-optn-form-field-id="button">';
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
		$html       .= '</div>';

				return $html;
	}

	/**
	 * Get spacer html
	 *
	 * @return string
	 */
	private static function get_spacer_html( &$field ) {
		return '<div class="optn-form-spacer" data-optn-form-field-id="' . esc_attr( $field->id ) . '"></div>';
	}

	/**
	 * Extract the Vimeo video ID from a URL.
	 *
	 * @param object $fields attributes.
	 * @return string
	 */
	public static function get_input_html( &$fields ) {
		$html = '';

		foreach ( $fields as $field ) {
			switch ( $field->type ) {
				case 'consent':
				case 'checkbox':
					$html .= self::get_checkbox_html( $field );
					break;
				default:
					$html .= self::get_text_html( $field );
					break;
			}
		}

		return $html;
	}
}
