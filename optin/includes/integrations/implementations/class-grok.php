<?php // phpcs:ignore

namespace OPTN\Includes\Integrations\Implementations;

use OPTN\Includes\Dto\ModelInput;
use OPTN\Includes\Integrations\Base\BaseAiIntegration;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Grok integration
 */
class Grok extends BaseAiIntegration {


	/**
	 * Generate Text
	 *
	 * @see https://docs.x.ai/docs/guides/chat
	 *
	 * @param ModelInput $input model input.
	 * @return string
	 */
	public function generate_text( ModelInput $input ) {
		$endpoint = 'https://api.x.ai/v1/chat/completions';

		$prompt = $this->get_processed_user_prompt( $input );

		if ( is_null( $prompt ) ) {
			return new WP_Error( 'generate_text_error', 'Invalid prompt' );
		}

		$body = wp_json_encode(
			array(
				'model'    => $this->settings['model'],
				'messages' => array(
					array(
						'role'    => 'system',
						'content' => $this->system_prompt,
					),
					array(
						'role'    => 'user',
						'content' => $prompt,
					),
				),
				'stream'   => false,
			)
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $this->settings['apiKey'],
				),
				'body'    => $body,
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || ! isset( $data['choices'][0]['message']['content'] ) ) {
			return new WP_Error( 'generate_text_error', 'Unexpected API response.', $data );
		}

		$result = trim( $data['choices'][0]['message']['content'] );

		$result = $this->maybe_remove_quotes( $result );

		return $result;
	}

	/**
	 * Get models.
	 *
	 * @see https://docs.x.ai/docs/models
	 *
	 * @return array
	 */
	public static function get_models() {

		return array(
			array(
				'value'  => 'grok-3-latest',
				'label'  => 'Grok 3',
				'input'  => array( 'text' ),
				'output' => array( 'text' ),
			),
			array(
				'value'  => 'grok-3-mini-latest',
				'label'  => 'Grok 3 Mini',
				'input'  => array( 'text' ),
				'output' => array( 'text' ),
			),
		);
	}
}
