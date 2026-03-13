<?php // phpcs:ignore

namespace OPTN\Includes\Integrations\Implementations;

use OPTN\Includes\Dto\ModelInput;
use OPTN\Includes\Integrations\Base\BaseAiIntegration;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Gemini integration
 */
class Gemini extends BaseAiIntegration {


	/**
	 * Generate Text
	 *
	 * @see https://ai.google.dev/gemini-api/docs/text-generation
	 *
	 * @param ModelInput $input model input.
	 * @return string
	 */
	public function generate_text( ModelInput $input ) {

		$prompt = $this->get_processed_user_prompt( $input );

		if ( is_null( $prompt ) ) {
			return new WP_Error( 'generate_text_error', 'Invalid prompt' );
		}

		$endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' .
					$this->settings['model'] .
					':generateContent?key=' .
					$this->settings['apiKey'];

		$body = wp_json_encode(
			array(
				'system_instruction' => array(
					'parts' => array(
						'text' => $this->system_prompt,
					),
				),
				'contents'           => array(
					'role'  => 'user',
					'parts' => array(
						'text' => $prompt,
					),
				),
			)
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
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

		if ( 200 !== $code ||
			empty( $data['candidates'][0]['content']['parts'][0]['text'] ) ||
			isset( $data['error'] )
		) {
			return new WP_Error( 'generate_text_error', 'Unexpected API response.', $data );
		}

		$result = trim( $data['candidates'][0]['content']['parts'][0]['text'] );

		$result = $this->maybe_remove_quotes( $result );

		return $result;
	}

	/**
	 * Get models.
	 *
	 * @see https://ai.google.dev/gemini-api/docs/models
	 *
	 * @return array
	 */
	public static function get_models() {

		return array(
			array(
				'value'  => 'gemini-2.5-pro',
				'label'  => 'Gemini 2.5 Pro',
				'input'  => array( 'text', 'image', 'video', 'audio', 'pdf' ),
				'output' => array( 'text' ),
			),
			array(
				'value'  => 'gemini-2.5-flash',
				'label'  => 'Gemini 2.5 Flash',
				'input'  => array( 'text', 'image', 'video', 'audio', 'pdf' ),
				'output' => array( 'text' ),
			),
			array(
				'value'  => 'gemini-2.0-flash',
				'label'  => 'Gemini 2.0 Flash',
				'input'  => array( 'text', 'image', 'video', 'audio' ),
				'output' => array( 'text' ),
			),
			array(
				'value'  => 'gemini-2.0-flash-lite',
				'label'  => 'Gemini 2.0 Flash-Lite',
				'input'  => array( 'text', 'image', 'video', 'audio' ),
				'output' => array( 'text' ),
			),
			array(
				'value'  => 'gemini-1.5-pro',
				'label'  => 'Gemini 1.5 Pro',
				'input'  => array( 'text', 'image', 'video', 'audio' ),
				'output' => array( 'text' ),
			),
			array(
				'value'  => 'gemini-1.5-flash',
				'label'  => 'Gemini 1.5 Flash',
				'input'  => array( 'text', 'image', 'video', 'audio' ),
				'output' => array( 'text' ),
			),
		);
	}
}
