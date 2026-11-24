<?php
/**
 * Resolves shared integration providers and creates their mail handlers.
 * Provider extensions register callbacks without adding provider-specific
 * classes to the common submission flow.
 *
 * @package Optin
 */

namespace OPTN\Includes\Integrations\Base;

use OPTN\Includes\Integrations\Implementations\Mailchimp;
use OPTN\Includes\Integrations\Implementations\Webhook;

/**
 * Maintains provider metadata and constructs supported mail integrations.
 * Built-in Mailchimp and Webhook providers share the same validation path,
 * while registered extensions supply their own metadata and callbacks.
 */
class IntegrationFactory {
	/**
	 * Registered provider definitions indexed by their integration identifier.
	 *
	 * Each definition stores its handler class, category, account requirements, and
	 * optional validation callbacks used when preparing a submission destination.
	 *
	 * @var array<string, array<string, string|bool|callable>>
	 */
	private static $providers = array();

	/**
	 * Register provider metadata without coupling the common flow to extension classes.
	 *
	 * The provider identifier selects this metadata for later validation and construction;
	 * optional settings override the default category, account behavior, or callbacks.
	 *
	 * @param string $type    Provider identifier used by saved integration settings.
	 * @param string $class   Fully qualified handler class name to instantiate.
	 * @param array  $options Optional provider metadata and validation callbacks.
	 * @return void
	 */
	public static function register_provider( $type, $class, array $options = array() ) {
		self::$providers[ $type ] = array_merge(
			array(
				'class'           => $class,
				'account'         => true,
				'category'        => 'mail',
				'mapping_pattern' => '/^[a-zA-Z0-9_.-]{1,100}$/D',
			),
			$options
		);
	}

	/**
	 * Gets provider metadata from the extension registry or built-in providers.
	 * Unknown provider names and non-string values return null.
	 *
	 * @param string $type Provider identifier to look up.
	 * @return array|null Provider metadata, or null when the provider is unknown.
	 */
	public static function get_provider( $type ) {
		if ( ! is_string( $type ) ) {
			return null; }
		if ( isset( self::$providers[ $type ] ) ) {
			return self::$providers[ $type ];
		}
		if ( 'mailchimp' === $type || 'webhook' === $type ) {
			return array(
				'class'           => 'mailchimp' === $type ? Mailchimp::class : Webhook::class,
				'account'         => 'mailchimp' === $type,
				'category'        => 'mail',
				'mapping_pattern' => '/^[a-zA-Z0-9_.-]{1,100}$/D',
			);
		}
		return null;
	}

	/**
	 * Creates a provider handler when the provider is a supported mail integration.
	 * Providers with another category or without a registered class return null.
	 *
	 * @param string   $type Provider identifier to instantiate.
	 * @param int|null $account_id Saved account identifier passed to the handler.
	 * @return object|null Integration handler, or null when no mail handler is available.
	 */
	public static function get_integration_class( $type, $account_id ) {
		$provider = self::get_provider( $type );
		if ( ! $provider || 'mail' !== $provider['category'] || empty( $provider['class'] ) ) {
			return null;
		}
		$class = $provider['class'];
		return new $class( $account_id );
	}

	/**
	 * Validate the saved account data before provider-specific checks or delivery.
	 *
	 * Requires an enabled account with a non-empty API key, applies the built-in
	 * Mailchimp data-center format check, and invokes a registered provider validator.
	 *
	 * @param string $type Integration provider identifier.
	 * @param array  $data Saved account fields loaded by the server.
	 * @return bool|\WP_Error|mixed True, a provider validator result, or a validation error.
	 */
	public static function validate_account( $type, $data ) {
		$provider = self::get_provider( $type );
		if ( ! $provider || ! is_array( $data ) || ! empty( $data['disabled'] ) ||
			! is_string( $data['apiKey'] ?? null ) || '' === trim( $data['apiKey'] ) ) {
			return new \WP_Error( 'optn_submission_account', 'Account unavailable or mismatched', array( 'status' => 400 ) );
		}
		if ( 'mailchimp' === $type && ! preg_match( '/^[a-zA-Z0-9]+-us[0-9]+$/D', $data['apiKey'] ) ) {
			return new \WP_Error( 'optn_submission_account', 'Invalid Mailchimp data center', array( 'status' => 400 ) );
		}
		return isset( $provider['validate_account'] ) ? call_user_func( $provider['validate_account'], $data ) : true;
	}

	/**
	 * Validate saved destination settings and mapped fields for a provider.
	 *
	 * Delegates to a provider-specific destination validator when registered; otherwise
	 * applies the built-in Mailchimp list/email checks or Webhook URL/method checks.
	 *
	 * @param string $type   Integration provider identifier.
	 * @param object $config Server-loaded provider configuration object; each extension defines its own fields.
	 * @param array  $data   Validated form and mapped field values.
	 * @return array<string, mixed>|\WP_Error|mixed Validated delivery data or a validation error.
	 */
	public static function prepare_destination( $type, $config, $data ) {
		$provider = self::get_provider( $type );
		if ( ! $provider ) {
			return new \WP_Error( 'optn_submission_integration', 'Integration unavailable', array( 'status' => 400 ) );
		}
		if ( isset( $provider['validate_destination'] ) ) {
			return call_user_func( $provider['validate_destination'], $config, $data );
		}
		if ( 'mailchimp' === $type && ( ! is_email( $data['fields']['email'] ?? '' ) || empty( $config->list ) ) ) {
			return new \WP_Error( 'optn_submission_mapping', 'Saved email mapping or destination list unavailable', array( 'status' => 400 ) );
		}
		if ( 'webhook' === $type && ( ! wp_http_validate_url( $data['link'] ?? '' ) || ! in_array( $data['reqType'] ?? '', array( 'GET', 'POST' ), true ) ) ) {
			return new \WP_Error( 'optn_submission_destination', 'Invalid saved webhook', array( 'status' => 400 ) );
		}
		return $data;
	}
}
