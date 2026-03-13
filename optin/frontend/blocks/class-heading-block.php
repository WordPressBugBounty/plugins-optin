<?php // phpcs:ignore

namespace OPTN\Frontend\Blocks;

use OPTN\Includes\Abstracts\BaseBlock;
use OPTN\Includes\Utils\BlockUtils;

/**
	Heading Block
 */
class HeadingBlock extends BaseBlock {

	/**
	 * Get block name
	 *
	 * @return string
	 */
	protected function get_block_name() {
		return 'heading';
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

		$html = '<div ' . BlockUtils::build_html_attrs( $this->get_common_html_attr_array() ) . '>';

		$html .= $this->get_content( $attr );

		$html .= '</div>';

		return $html;
	}

	/**
	 * Get content
	 *
	 * @param any $attr attributes.
	 * @return string
	 */
	private function get_content( &$attr ) {

		$has_shape = isset( $attr->shape ) && ! empty( $attr->shape );

		if ( $has_shape ) {
			$shape = $attr->shape->name;
			switch ( $shape ) {
				case 'arc':
					return $this->get_arc_shape( $attr );
				case 'angle':
					return $this->get_angle_shape( $attr );
				default:
					return '';
			}
		} else {
			return $attr->text;
		}
	}

	/**
	 * Arc Shape
	 *
	 * @param any $attr attr.
	 * @return string
	 */
	private function get_arc_shape( &$attr ) {

		$id = uniqid( 'optn' );

		$shape_data = $attr->shape->data;
		$shape_attr = $shape_data->attr;

		$gt = "translate({$shape_attr->transformX}, {$shape_attr->transformY})";

		ob_start();
		?>
			<svg
				width="<?php echo esc_attr( $shape_attr->width ); ?>"
				height="<?php echo esc_attr( $shape_attr->height ); ?>"
				class="optn-text-shape"
			>
				<g
					transform="<?php echo esc_attr( $gt ); ?>"
				>
					<defs>
						<path 
							id="<?php echo esc_attr( $id ); ?>"
							d="<?php echo esc_attr( $shape_attr->d ); ?>"
							fill="none"
						/>
					</defs>
					<text>
						<textPath
							class="optn-text-shape-content" 
							xlink:href="#<?php echo esc_attr( $id ); ?>"
							startOffset="50%"
							text-anchor="middle"
							dominant-baseline="<?php echo $shape_data->direction ? 'text-after-edge' : 'text-before-edge'; ?>"
						>
							<?php echo esc_html( $shape_attr->text ); ?>
						</textPath>
					</text>
				</g>
			</svg>
		<?php

		return ob_get_clean();
	}

	/**
	 * Angle Shape
	 *
	 * @param any $attr attr.
	 * @return string
	 */
	private function get_angle_shape( &$attr ) {
		$shape_data = $attr->shape->data;
		$shape_attr = $shape_data->attr;

		$skew = "skewY({$shape_attr->skewY}deg)";

		ob_start();
		?>
			<div class="optn-text-shape">
				<div class="optn-text-shape-content" style="transform: <?php echo esc_attr( $skew ); ?>;">
					<?php
						// Will be sanitized later.
						echo $attr->text; // phpcs:ignore 
					?>
				</div>
			</div>
		<?php

		return ob_get_clean();
	}
}
