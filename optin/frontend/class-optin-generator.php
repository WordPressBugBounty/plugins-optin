<?php // phpcs:ignore

namespace OPTN\Frontend;

use OPTN\Includes\Settings;
use OPTN\Includes\Utils\Utils;

/**
 * Renders conversion
 *
 * @link       https://wpxpo.com
 * @since      1.0.0
 *
 * @package    optin
 * @subpackage optin/frontend
 */

/**
 * Renders Conversion
 *
 * @package    optin
 * @subpackage optin/frontend
 */
class OptinGenerator {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $plugin_name The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $version The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param string $plugin_name The name of the plugin.
	 * @param string $version The version of this plugin.
	 *
	 * @since    1.0.0
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Renders inline optin
	 *
	 * @param object $post optin post.
	 * @param int    $index index.
	 * @return string
	 */
	public function render_inline_optin( $post, $index ) {
		$html  = '<div class="optn-inline-conversion" data-optn-post-index="' . esc_attr( $index ) . '" data-optn-post-id="' . esc_attr( $post->id ) . '"  data-optn-updated-at="' . esc_attr( $post->updated_at ) . '">';
		$html .= $this->get_optin_html( $post );
		$html .= '</div>';

		$html .= '<div class="optn-inline-slot optn-conv-wrapper optn-inline-slot-' . esc_attr( $post->id ) . ' optn-inline-slot-index-' . esc_attr( $index ) . '"></div>';

		$this->enqueue_fonts();

		return $html;
	}

	/**
	 * Renders general optins
	 *
	 * @param array $posts optin posts.
	 * @return void
	 */
	public function render_optins( $posts ) {

		$html = '<div class="optn-conversion-contents" aria-hidden="true">';

		foreach ( $posts as $post ) {
			$html .= '<div class="optn-conversion-item" data-optn-post-id="' . esc_attr( $post->id ) . '" data-optn-optn-type="' . esc_attr( $post->type ) . '" data-optn-updated-at="' . esc_attr( $post->updated_at ) . '">';
			$html .= $this->get_optin_html( $post );
			$html .= '</div>';
		}

		$html .= '</div>';

		add_action(
			'optn_html',
			function ( $html_str ) use ( $html ) {
				$html_str .= $html;
				return $html_str;
			}
		);

		$this->enqueue_fonts();
	}

	/**
	 * Renders inline placeholder
	 *
	 * @return string
	 */
	public function render_inline_placeholder() {
		ob_start();

		?>
			<div style="display: flex;justify-content: center;align-items: center;width: 100%;height: 250px;background-color: #f5f5f5;border: 1px solid #ccc;">
				<div style="font-size: 16px;font-weight: bold;color: #333;">
					<?php esc_html_e( 'Your Inline Optin will appear here', 'optin' ); ?>
				</div>
			</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Renders a optin
	 *
	 * @param object $post optin post.
	 * @return string
	 */
	private function get_optin_html( $post ) {
		if ( empty( $post ) ) {
			return '';
		}

		$post_id   = $post->id;
		$post_data = json_decode( $post->data );

		$steps = isset( $post_data->steps ) ? $post_data->steps : array();
		$html  = '';

		$this->add_close_btn_content( $post_data->design->close->icon );

		add_filter(
			'optn_data',
			function ( $data ) use ( $post_data, $post_id ) {

				$data[ $post_id ] = array(
					'design'       => $post_data->design,
					'schedule'     => $post_data->schedule,
					'audience'     => $post_data->audience,
					'goal'         => $post_data->goal,
					'trigger'      => $post_data->trigger,
					'displayRules' => $post_data->displayRules, // phpcs:ignore
				);

				return $data;
			},
			9999,
			1
		);

		$css = $post_data->css ?? '';
		add_filter(
			'optn_css',
			function ( $css_str ) use ( $css ) {
				$css_str .= $css;
				return $css_str;
			}
		);

		if ( count( $steps ) > 0 ) {
			foreach ( $steps as $step ) {
				$html  .= '<div class="optn-conversion-step" data-step-id="' . esc_attr( $step->id ) . '">';
				$blocks = $step->blocks->value;
				foreach ( $blocks as $block ) {
					$html .= $this->render_block( $block, $post_data );
				}
				$html .= '</div>';
			}
		}

		return $html;
	}

	/**
	 * Renders individual block
	 *
	 * @param object      $block block data.
	 * @param object|null $post_data post data.
	 * @return string
	 */
	private function render_block( $block, $post_data = null ) {
		$class_name = '';

		if ( str_contains( $block[1]->name, '/' ) ) {
			$parent     = explode( '/', $block[1]->name )[0];
			$class_name = ucwords( $parent ) . 'Block';
		} else {
			$class_name = ucwords( $block[1]->name ) . 'Block';
		}

		$class_name = '\\OPTN\\Frontend\\Blocks\\' . $class_name;

		$html = '';

		if ( class_exists( $class_name ) ) {
			add_filter(
				'optn_block_attrs',
				function ( $attrs ) use ( $block ) {
					$attrs[ $block[1]->clientId ] = $block[1]->attributes;
					return $attrs;
				}
			);

			$inner_blocks = '';
			if ( ! empty( $block[1]->innerBlocks->value ) ) {
				foreach ( $block[1]->innerBlocks->value as $i_block ) {
					$inner_blocks .= $this->render_block( $i_block );
				}
			}

			$block[1]->inner_block_html = $inner_blocks;

			$html .= ( new $class_name( $block[1], $post_data ) )->generate();
		} else {
			Utils::log_error( "Block renderer class not found: {$class_name}" );
		}

		return $html;
	}

	/**
	 * Enqueue fonts
	 *
	 * @return void
	 */
	private function enqueue_fonts() {

		$font_data = array();

		if ( Settings::get_settings( 'global_google_fonts' ) ) {
			$font_data = apply_filters( 'optn_google_fonts', array() );
		}

		add_filter(
			'optn_fonts',
			function ( $font_list ) use ( $font_data ) {

				$processed = array();

				foreach ( $font_data as $font_name => $weights ) {

					if ( ! empty( $font_name ) && ! empty( $weights ) ) {
						$weight_variations = array();

						$weights = array_unique( $weights );

						sort( $weights, SORT_NUMERIC );

						$n_weights = array();
						$i_weights = array();
						foreach ( $weights as $weight ) {
							$n_weights[] = "0,{$weight}";
							$i_weights[] = "1,{$weight}";
						}

						$weight_variations = array_merge( $n_weights, $i_weights );

						if ( ! empty( $weight_variations ) ) {
							// Replace + with space in font name for the URL.
							$font_name_url = str_replace( '+', ' ', $font_name );

							$font_url = 'family=' . rawurlencode( $font_name_url ) . ':ital,wght@' . implode( ';', $weight_variations );

							$processed[ $font_name ] = $font_url;
						}
					}
				}

				$chunks = array_chunk( $processed, 5, true );

				foreach ( $chunks as $chunk ) {

					$font_families = array();
					$font_urls     = '';

					foreach ( $chunk as $font_name => $font_url ) {
						$font_families[] = $font_name;
						$font_urls      .= $font_url . '&';
					}

					$id  = $this->plugin_name . '-font-' . implode( '-', $font_families );
					$url = 'https://fonts.googleapis.com/css2?' . $font_urls . 'display=swap';

					$font_list[ $id ] = $url;
				}

				return $font_list;
			}
		);
	}

	/**
	 * Close button html content
	 *
	 * @param string $filename close button icon filename.
	 * @return void
	 */
	private function add_close_btn_content( $filename ) {
		add_action(
			'optn_data',
			function ( $data ) use ( $filename ) {
				if ( ! isset( $data[ $filename ] ) ) {
					$data[ $filename ] = file_get_contents( OPTN_DIR . '/assets/images/close-icons/' . $filename . '.svg' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				}
				return $data;
			}
		);
	}
}
