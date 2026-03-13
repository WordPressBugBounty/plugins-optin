<?php // phpcs:ignore

namespace OPTN\Includes\Abstracts;

use OPTN\Includes\Utils\BlockUtils;
use OPTN\Includes\Utils\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract Base Block Class
 */
abstract class BaseBlock implements Block {
	/**
	 * Block data
	 *
	 * @var object
	 */
	protected $data;

	/**
	 * Block data
	 *
	 * @var object
	 */
	protected $post_data;

	/**
	 * Block constructor.
	 *
	 * @param object      $data block data.
	 * @param object|null $post_data post data.
	 */
	public function __construct( $data, $post_data = null ) {
		$this->data      = $data;
		$this->post_data = $post_data;
	}

	/**
	 * Generate block html
	 *
	 * @return string
	 */
	public function generate() {
		$version = $this->get_version();

		$func = 'v' . $version;

		if ( method_exists( $this, $func ) ) {
			return $this->$func();
		}

		Utils::log_error( "No block version found for {$this->get_block_name()} Version: {$version}" );

		return '';
	}

	/**
	 * Default block html
	 *
	 * @return string
	 */
	abstract protected function v1();

	/**
	 * Get block version
	 *
	 * @return string|null
	 */
	protected function get_version() {
		return isset( $this->data->attributes->version ) ? $this->data->attributes->version : '1';
	}

	/**
	 * Get block name
	 *
	 * @return string
	 */
	abstract protected function get_block_name();

	/**
	 * Block html attrs
	 *
	 * @return array
	 */
	protected function get_common_html_attr_array() {

		// Custom classes.
		$cls = '';
		if ( isset( $this->data->attributes->customClasses ) && is_string( $this->data->attributes->customClasses ) ) {
			$cls = explode( ',', $this->data->attributes->customClasses );
			$cls = array_map( 'sanitize_html_class', $cls );
			$cls = implode( ' ', $cls );
		}

		$data = array(
			'class'                   => array(
				'optn-block',
				'optn-block-' . $this->get_block_name(),
				'optn-' . esc_attr( $this->data->clientId ),
				$cls,
				BlockUtils::get_resp_hide_classes( $this->data->attributes ),
			),
			'id'                      => esc_attr( $this->data->clientId ),
			'data-optn-block-version' => esc_attr( $this->get_version() ),
		);

		if ( ! empty( $this->data->attributes->fixed ) ) {
			$data['class'][] = 'optn-block-fixed';
		}

		if ( 'columns' !== $this->get_block_name() ) {
			$pos                   = array(
				'top'       => $this->data->attributes->top,
				'left'      => $this->data->attributes->left,
				'transform' => $this->data->attributes->transform,
			);
			$data['data-optn-pos'] = htmlspecialchars( wp_json_encode( $pos ) );
		}

		return $data;
	}
}
