<?php // phpcs:ignore

namespace OPTN\Includes\Integrations\Implementations;

use OPTN\Includes\Dto\ModelInput;
use OPTN\Includes\Integrations\Base\BaseAiIntegration;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Chatgpt integration
 */
class Chatgpt extends BaseAiIntegration {


	/**
	 * Generate Text
	 *
	 * @see https://platform.openai.com/docs/api-reference/chat/create
	 *
	 * @param ModelInput $input model input.
	 * @return string
	 */
	public function generate_text( ModelInput $input ) {
		$endpoint = 'https://api.openai.com/v1/chat/completions';

		$prompt = $this->get_processed_user_prompt( $input );

		if ( is_null( $prompt ) ) {
			return new WP_Error( 'generate_text_error', 'Invalid prompt' );
		}

		$body = wp_json_encode(
			array(
				'model'    => $this->settings['model'],
				'messages' => array(
					array(
						'role'    => 'developer',
						'content' => $this->system_prompt,
					),
					array(
						'role'    => 'user',
						'content' => $prompt,
					),
				),
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

		if ( 200 !== $code || empty( $data['choices'][0]['message']['content'] ) ) {
			return new WP_Error( 'generate_text_error', 'Unexpected API response.', $data );
		}

		$result = trim( $data['choices'][0]['message']['content'] );

		$result = $this->maybe_remove_quotes( $result );

		return $result;
	}

	/**
	 * Get models.
	 *
	 * @see https://platform.openai.com/docs/models
	 *
	 * @return array
	 */
	public static function get_models() {

		return array(
			array(
				'value'  => 'gpt-5',
				'label'  => 'GPT-5',
				'input'  => array( 'text', 'image' ),
				'output' => array( 'text' ),
			),
			array(
				'value'  => 'gpt-5-mini',
				'label'  => 'GPT-5 mini',
				'input'  => array( 'text', 'image' ),
				'output' => array( 'text' ),
			),
			array(
				'value'  => 'gpt-5-nano',
				'label'  => 'GPT-5 nano',
				'input'  => array( 'text', 'image' ),
				'output' => array( 'text' ),
			),
			array(
				'value'  => 'gpt-4.1',
				'label'  => 'GPT-4.1',
				'input'  => array( 'text', 'image' ),
				'output' => array( 'text' ),
			),
			array(
				'value'  => 'gpt-4.1-mini',
				'label'  => 'GPT-4.1 mini',
				'input'  => array( 'text', 'image' ),
				'output' => array( 'text' ),
			),
			array(
				'value'  => 'gpt-4.1-nano',
				'label'  => 'GPT-4.1 nano',
				'input'  => array( 'text', 'image' ),
				'output' => array( 'text' ),
			),
			array(
				'value'  => 'gpt-4o',
				'label'  => 'GPT-4o',
				'input'  => array( 'text', 'image' ),
				'output' => array( 'text' ),
			),
			array(
				'value'  => 'gpt-3.5-turbo',
				'label'  => 'GPT-3.5 Turbo',
				'input'  => array( 'text' ),
				'output' => array( 'text' ),
			),
		);
	}
}
