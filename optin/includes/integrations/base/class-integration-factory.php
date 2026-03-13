<?php // phpcs:ignore
/**
 * Mail Integration Interface
 *
 * @package optin
 * @subpackage optin/intergrtions
 */

namespace OPTN\Includes\Integrations\Base;

use OPTN\Includes\Integrations\Implementations\ActiveCampaign;
use OPTN\Includes\Integrations\Implementations\Brevo;
use OPTN\Includes\Integrations\Implementations\CampaignMonitor;
use OPTN\Includes\Integrations\Implementations\Chatgpt;
use OPTN\Includes\Integrations\Implementations\ConvertKit;
use OPTN\Includes\Integrations\Implementations\Drip;
use OPTN\Includes\Integrations\Implementations\FluentCrm;
use OPTN\Includes\Integrations\Implementations\Gemini;
use OPTN\Includes\Integrations\Implementations\GetResponse;
use OPTN\Includes\Integrations\Implementations\Grok;
use OPTN\Includes\Integrations\Implementations\HubSpot;
use OPTN\Includes\Integrations\Implementations\Mailchimp;
use OPTN\Includes\Integrations\Implementations\MailerLite;
use OPTN\Includes\Integrations\Implementations\MailPoet;
use OPTN\Includes\Integrations\Implementations\Moosend;
use OPTN\Includes\Integrations\Implementations\Omnisend;
use OPTN\Includes\Integrations\Implementations\Sendfox;
use OPTN\Includes\Integrations\Implementations\Webhook;

/**
 * Integration Factory class
 */
class IntegrationFactory {

	/**
	 * Intergration classes
	 *
	 * @var array
	 */
	private static $obj = array(
		'mailerlite'      => MailerLite::class,
		'mailchimp'       => Mailchimp::class,
		'hubspot'         => HubSpot::class,
		'fluentcrm'       => FluentCrm::class,
		'webhook'         => Webhook::class,
		'brevo'           => Brevo::class,
		'moosend'         => Moosend::class,
		'omnisend'        => Omnisend::class,
		'mailpoet'        => MailPoet::class,
		'convertkit'      => ConvertKit::class,
		'campaignmonitor' => CampaignMonitor::class,
		'getresponse'     => GetResponse::class,
		'drip'            => Drip::class,
		'sendfox'         => Sendfox::class,
		'activecampaign'  => ActiveCampaign::class,
		'chatgpt'         => Chatgpt::class,
		'gemini'          => Gemini::class,
		'grok'            => Grok::class,
	);

	/**
	 * AI Intergration classes
	 *
	 * @var array
	 */
	private static $ai_integrations = array(
		'chatgpt' => Chatgpt::class,
		'gemini'  => Gemini::class,
		'grok'    => Grok::class,
	);

	/**
	 * Creates integration factory object.
	 *
	 * @param string $type type.
	 * @param int    $account_id account id.
	 * @return MailIntegration
	 */
	public static function get_integration_class( $type, $account_id ) {
		$classes = self::$obj;
		if ( ! empty( $classes[ $type ] ) ) {
			return new $classes[ $type ]( $account_id );
		}
		return null;
	}

	/**
	 * Creates AI integration factory object.
	 *
	 * @param string $type type.
	 * @param int    $account_id account id.
	 * @return AiIntegration
	 */
	public static function get_ai_integration_class( $type, $account_id ) {
		$classes = self::$ai_integrations;
		if ( ! empty( $classes[ $type ] ) ) {
			return new $classes[ $type ]( $account_id );
		}
		return null;
	}

	/**
	 * Creates integration info.
	 *
	 * @param string $type type.
	 * @param string $integration integration.
	 * @param string $query query.
	 * @return MailIntegration
	 */
	public static function get_integration_info( $type, $integration, $query ) {
		if ( 'ai' === $type ) {
			$class = self::$ai_integrations[ $integration ] ?? null;
			if ( ! empty( $class ) ) {
				switch ( $query ) {
					case 'models':
						return $class::get_models();
				}
			}
		}

		return null;
	}
}
