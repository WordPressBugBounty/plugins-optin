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
 * Renders saved opt-in campaigns into their public popup and inline markup.
 *
 * Resolves campaign block data, rejects unsupported or invalid campaigns, and builds
 * the wrappers and frontend assets needed by each rendered placement.
 *
 * @package    optin
 * @subpackage optin/frontend
 */
class OptinGenerator {
	/** Close icon IDs select only these bundled files. */
	private const CLOSE_ICON_FILES = array(
		'close1' => 'close1.svg',
		'close2' => 'close2.svg',
		'close3' => 'close3.svg',
		'close4' => 'close4.svg',
		'close5' => 'close5.svg',
	);

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
	 * Validate a saved campaign before adding any frontend markup or assets.
	 *
	 * Checks that the campaign record is an object, its JSON body passes schema
	 * validation, and its required features are supported by this plugin edition.
	 *
	 * @param \stdClass $post Saved opt-in record being checked for frontend rendering.
	 * @return bool Whether the campaign can be rendered safely by this generator.
	 */
	private function can_render( $post ): bool {
		return is_object( $post ) && ! is_wp_error( \OPTN\Includes\Utils\CampaignSchema::sanitize_json( $post->data ?? '' ) ) && ! \OPTN\Includes\Utils\CampaignSupport::get_unsupported_features( $post );
	}

	/**
	 * Render one valid inline opt-in and its insertion slot.
	 *
	 * Rejects campaigns whose saved JSON is invalid or whose required features are
	 * unsupported in this edition, then renders sanitized campaign markup and enqueues
	 * the fonts needed by that campaign.
	 *
	 * @param \stdClass $post Saved opt-in record with its ID, data, and update timestamp.
	 * @param int       $index Position used to distinguish this inline opt-in on the page.
	 * @return string Sanitized inline markup, or an empty string when it cannot be rendered.
	 */
	public function render_inline_optin( $post, $index ) {
		if ( ! $this->can_render( $post ) ) {
			return ''; }
		$html  = '<div class="optn-inline-conversion" data-optn-post-index="' . esc_attr( $index ) . '" data-optn-post-id="' . esc_attr( $post->id ) . '"  data-optn-updated-at="' . esc_attr( $post->updated_at ) . '">';
		$html .= $this->get_optin_html( $post );
		$html .= '</div>';

		$html .= '<div class="optn-inline-slot optn-conv-wrapper optn-inline-slot-' . esc_attr( $post->id ) . ' optn-inline-slot-index-' . esc_attr( $index ) . '"></div>';

		$this->enqueue_fonts();

		return \OPTN\Includes\Utils\Sanitizer::sanitize( $html );
	}

	/**
	 * Renders general optins
	 *
	 * @param array $posts optin posts.
	 * @return void
	 */
	public function render_optins( $posts ) {
		$posts = array_filter( $posts, array( $this, 'can_render' ) );
		if ( ! $posts ) {
			return; }

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
	 * @param \stdClass $post Saved opt-in database record used to render its campaign content.
	 * @return string
	 */
	private function get_optin_html( $post ) {
		if ( ! $this->can_render( $post ) ) {
			return ''; }

		$post_id              = $post->id;
		$post_data            = json_decode( \OPTN\Includes\Utils\CampaignSchema::sanitize_json( $post->data ) );
		\OPTN\Includes\Utils\TemplateMedia::hide_remote( $post_data );
		$post_data->_campaign = $post;

		foreach ( array( 'design', 'schedule', 'audience', 'goal' ) as $key ) {
			$post_data->$key = $post_data->$key ?? new \stdClass(); }
		foreach ( array( 'trigger', 'displayRules' ) as $key ) {
			$post_data->$key = $post_data->$key ?? array(); }
		$icon = $post_data->design->close->icon ?? '';
		$this->add_close_btn_content( $icon );

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

		/**
		 * Let an extension render campaign content using the shared single-step renderer.
		 *
		 * A null result declines handling. A string, including an empty string, replaces
		 * the content. Invalid results suppress output rather than render partial content.
		 *
		 * @param string|null $html        Extension-rendered campaign markup, or null.
		 * @param \stdClass   $post_data   Sanitized campaign settings.
		 * @param \stdClass   $post        Saved campaign record.
		 * @param callable    $render_step Renders one supplied step with its campaign settings.
		 */
		$html = apply_filters( 'optn_campaign_content_html', null, $post_data, $post, array( $this, 'render_step' ) );
		if ( null !== $html ) {
			return is_string( $html ) ? $html : '';
		}

		$steps = (array) ( $post_data->steps ?? array() );
		$step  = reset( $steps );
		return $step instanceof \stdClass ? $this->render_step( $step, $post_data ) : '';
	}

	/**
	 * Render one step using the shared block, attribute and escaping contracts.
	 *
	 * @param \stdClass $step      Sanitized step with an identifier and serialized block list.
	 * @param \stdClass $post_data Sanitized campaign settings used as block context.
	 * @return string Step markup, or an empty string when the step is malformed.
	 */
	public function render_step( \stdClass $step, \stdClass $post_data ): string {
		if ( ! is_scalar( $step->id ?? null ) || ! is_array( $step->blocks->value ?? null ) ) {
			return '';
		}
		$html = '<div class="optn-conversion-step" data-step-id="' . esc_attr( $step->id ) . '">';
		foreach ( $step->blocks->value as $block ) {
			$html .= $this->render_block( $block, $post_data );
		}
		return $html . '</div>';
	}

	/**
	 * Renders individual block
	 *
	 * @param \stdClass      $block Decoded block data, including its name, attributes, and inner blocks.
	 * @param \stdClass|null $post_data Saved campaign database record used as block context.
	 * @return string
	 */
	private function render_block( $block, $post_data = null ) {
		if ( ! is_array( $block ) || ! is_object( $block[1] ?? null ) || ! is_string( $block[1]->name ?? null ) ) {
			return '';
		}
		$class_name = '';

		if ( false !== strpos( $block[1]->name, '/' ) ) {
			$parent     = explode( '/', $block[1]->name )[0];
			$class_name = ucwords( $parent ) . 'Block';
		} else {
			$class_name = ucwords( $block[1]->name ) . 'Block';
		}

		$class_name = '\\OPTN\\Frontend\\Blocks\\' . $class_name;
		/** Extension renderer must expose the same constructor and generate() contract as a shared block. */
		$class_name = apply_filters( 'optn_block_renderer', $class_name, $block[1], $post_data );
		if ( ! is_string( $class_name ) || ! class_exists( $class_name ) ) {
			return '';
		}
		$renderer = new \ReflectionClass( $class_name );
		if ( ! $renderer->isInstantiable() || ! $renderer->hasMethod( 'generate' ) || ! $renderer->getMethod( 'generate' )->isPublic() ) {
			return '';
		}

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
				$inner_blocks .= $this->render_block( $i_block, $post_data );
			}
		}
		$block[1]->inner_block_html = $inner_blocks;
		return ( new $class_name( $block[1], $post_data ) )->generate();
	}

	/**
	 * Enqueue fonts
	 *
	 * @return void
	 */
	private function enqueue_fonts() {

		$font_data = array();

		if ( Settings::is_google_fonts_enabled() ) {
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
		if ( ! is_string( $filename ) || ! isset( self::CLOSE_ICON_FILES[ $filename ] ) ) {
			return;
		}
		$path = OPTN_DIR . '/assets/images/close-icons/' . self::CLOSE_ICON_FILES[ $filename ];
		if ( ! is_readable( $path ) ) {
			return;
		}
		add_action(
			'optn_data',
			function ( $data ) use ( $filename, $path ) {
				if ( ! isset( $data[ $filename ] ) ) {
					$svg = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
					if ( false !== $svg ) {
						$data[ $filename ] = $svg;
					}
				}
				return $data;
			}
		);
	}
}
