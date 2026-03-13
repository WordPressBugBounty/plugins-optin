<?php // phpcs:ignore

namespace OPTN\Includes\Integrations\Base;

use OPTN\Includes\Db;
use OPTN\Includes\Dto\ModelInput;

defined( 'ABSPATH' ) || exit;

/**
 * Base AI Integration class
 */
abstract class BaseAiIntegration implements AiIntegration {

	/**
	 * Db instance
	 *
	 * @var Db $db Db variable
	 */
	protected $db;

	/**
	 * Account id
	 *
	 * @var int $account_id account id.
	 */
	protected $account_id;

	/**
	 * Settings for current account
	 *
	 * @var array $settings
	 */
	protected $settings;

	/**
	 * System prompt
	 *
	 * @var string
	 */
	protected $system_prompt = 'You are a smart text assistant built into WowOptin, a no-code popup builder plugin for WordPress that helps users create high-converting opt-ins using features like exit-intent, audience targeting, and real-time analytics. Your job is to assist users in generating and restyling marketing copy for their popups, banners, and opt-in forms.

        You can:

        Generate text for popups, banners, CTA buttons, headlines, and form descriptions.

        Rewrite existing text in different tones (professional, casual, friendly, urgent, playful, etc.).

        Adjust formatting for readability (shorten/expand, summerize, rephrase, rewrite etc.).

        Tailor messaging to specific goals (grow email list, reduce cart abandonment, announce sales, promote lead magnets, etc.).

        Optimize text for conversions by using persuasive language, clear CTAs, and emotional triggers.

        Important rules you must follow:

        Your outputs should be relevant to popups, landing overlays, sticky bars, or inline opt-ins created inside WowOptin. You are not a general-purpose assistant — you are specialized in marketing copy for popups that convert.

        Avoid technical jargon or generic filler text unless explicitly requested. 
        
        Do not use any bullet points or numbered points. When the user asks for suggestions, only give them one, not multiple.
        
        Do not use filler text like "Sure, here you go" or "Thank you". Do not even provide any advices to user. You must output only the text that the user needs to copy and paste.
        
        Do not wrap your output text in quotation marks. 

        Your output must be raw text that the user can use directly (No HTML or Markdown, but emojis and special symbols are allowed).
        ';

	/**
	 * Instruction map
	 *
	 * @var array
	 */
	protected $instructions = array(
		'pro'       => 'Use formal and professional tone',
		'friendly'  => 'Use friendly and conversational tone',
		'urgent'    => 'Make it feel more persuasive and emotionally compelling and to create a strong sense of urgency and FOMO (Fear of Missing Out)',
		'casual'    => 'Use casual and playful tone',
		'reassure'  => 'Use reassuring and trustworthy tone',
		'inspire'   => 'Use motivational and inspiring tone',
		'shorten'   => 'Shorten the text to make it more concise',
		'expand'    => 'Expand to provide more detail and context',
		'summarize' => 'Summarize in one powerful sentence',
		'rephrase'  => 'Use different words but with the same meaning',
		'grammar'   => 'Proofread and correct any grammar, spelling, or punctuation mistakes',
	);

	/**
	 * Processes user prompt with instructions
	 *
	 * @param ModelInput $input model input.
	 * @return string|null
	 */
	protected function get_processed_user_prompt( ModelInput $input ) {
		if ( 'rewrite' === $input->task ) {
			$instruction = isset( $this->instructions[ $input->instruction ] ) ? $this->instructions[ $input->instruction ] : $input->instruction;

			return 'Rewrite the original text by following these instructions: ' .
					$instruction .
					'. Do not add quotation marks around the output. Original Text: ' .
					$input->user_prompt;
		}

		if ( ! isset( $this->instructions[ $input->instruction ] ) ) {
			return $input->user_prompt;
		}

		return 'User query to generate text: ' . $input->user_prompt . '\n You must follow this instruction while generating the text: ' . $this->instructions[ $input->instruction ];
	}

	/**
	 * Removes quotes from text if present.
	 *
	 * @param string $text text.
	 * @return string
	 */
	protected function maybe_remove_quotes( $text ) {
		if ( '"' === $text[0] && '"' === $text[ strlen( $text ) - 1 ] ) {
			return substr( $text, 1, -1 );
		}

		return $text;
	}

	/**
	 * Constructor
	 *
	 * @param int $account_id account id.
	 */
	public function __construct( $account_id ) {
		$this->db         = Db::get_instance();
		$this->account_id = $account_id;
		$res              = $this->db->get_integration( $this->account_id, false, 'ai' );
		$this->settings   = $res['data'];
	}

	/**
	 * Generate text.
	 *
	 * @param ModelInput $input model input.
	 * @return string|WP_Error
	 */
	abstract public function generate_text( ModelInput $input );

	/**
	 * Get models available for this provider.
	 *
	 * @return array
	 */
	abstract public static function get_models();
}
