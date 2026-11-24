<?php
/**
 * Validates public form submissions against saved campaign configuration.
 *
 * @package Optin
 */

namespace OPTN\Includes\Utils;

use OPTN\Includes\Db;
use OPTN\Includes\Settings;
use OPTN\Includes\Integrations\Base\IntegrationFactory;
defined( 'ABSPATH' ) || exit;

/**
 * Resolves public form submissions against the campaign's saved form definition.
 * The visitor can submit declared field values only; campaign state, form token,
 * account credentials, destination settings, field mappings, and delivery targets
 * are reloaded and validated on the server before a delivery payload is returned.
 */
class FormSubmission {
	/**
	 * Creates a namespaced REST error for a rejected form submission.
	 *
	 * @param string $code    Submission error suffix.
	 * @param string $message Human-readable rejection reason.
	 * @param int    $status  HTTP status code stored with the error.
	 * @return \WP_Error Submission error with an `optn_` code prefix.
	 */
	private static function error( $code, $message, $status = 400 ) {
		return new \WP_Error( 'optn_' . $code, $message, array( 'status' => $status ) ); }
	/**
	 * Hashes the campaign fields that must remain unchanged during a form token's life.
	 * A changed body, update timestamp, or enabled state produces a different revision.
	 *
	 * @param \stdClass $campaign Database campaign record containing saved form data and state.
	 * @return string SHA-256 revision for the campaign state.
	 */
	public static function revision( $campaign ): string {
		return hash( 'sha256', $campaign->data . '|' . $campaign->updated_at . '|' . $campaign->enabled ); }
	/**
	 * Creates a seven-day HMAC token bound to one campaign, form, and revision.
	 * The expiry is included in the signature so the token cannot be extended by editing it.
	 *
	 * @param int    $campaign_id Campaign ID that owns the form.
	 * @param string $form_id     Saved form block client ID.
	 * @param string $revision    Current campaign revision from revision().
	 * @return string Expiry and signed token separated by a period.
	 */
	public static function create_token( int $campaign_id, string $form_id, string $revision ): string {
		$expiry  = time() + 7 * DAY_IN_SECONDS;
		$binding = $campaign_id . '|' . $form_id . '|' . $revision . '|' . $expiry;
		return $expiry . '.' . hash_hmac( 'sha256', $binding, wp_salt( 'nonce' ) );
	}
	/**
	 * Searches nested block data for the saved form with the requested client ID.
	 * The recursive walk also checks inner block collections; malformed or unrelated
	 * values are skipped and a missing form returns null.
	 *
	 * @param array|\stdClass $value Block, block list, or nested block container to search.
	 * @param string          $id    Form block client ID to find.
	 * @return object|null Matching form block, or null when it is absent.
	 */
	private static function find_form( $value, $id ) {
		if ( is_object( $value ) && ( $value->name ?? '' ) === 'form' && ( $value->clientId ?? '' ) === $id ) {
			return $value; }
		if ( is_array( $value ) || is_object( $value ) ) {
			foreach ( $value as $item ) {
				$found = self::find_form( $item, $id );
				if ( $found ) {
					return $found; }
			}
		}
		return null;
	}
	/**
	 * Validates a visitor submission and builds trusted integration delivery data.
	 * It enforces request and field size limits, verifies campaign and signed form
	 * revision, accepts only saved form fields and destinations, checks integration
	 * accounts and mappings, then applies a per-campaign/client rate limit. Rejections
	 * return a WP_Error; accepted requests return external destinations plus a local
	 * background payload containing fixed and custom fields.
	 *
	 * @param \WP_REST_Request $request Public REST request containing campaignId, formId, token, and fields.
	 * @return array|\WP_Error Validated destination/background payload, or a submission error.
	 */
	public static function resolve( \WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = $request->get_params(); }
		if ( strlen( (string) $request->get_body() ) > 32768 || strlen( wp_json_encode( $payload ) ) > 32768 ) {
			return self::error( 'submission_size', 'Submission too large', 413 ); }
		if ( array_diff( array_keys( $payload ), array( 'campaignId', 'formId', 'token', 'fields' ) ) || ( ! is_string( $payload['campaignId'] ?? null ) && ! is_int( $payload['campaignId'] ?? null ) ) || ! ctype_digit( (string) $payload['campaignId'] ) || (int) $payload['campaignId'] < 1 || empty( $payload['formId'] ) || ! is_string( $payload['formId'] ) || ! is_string( $payload['token'] ?? null ) || ! is_array( $payload['fields'] ?? null ) ) {
			return self::error( 'submission_payload', 'Expected campaignId, formId, token and declared fields' ); }
		if ( count( $payload['fields'] ) > 100 ) {
			return self::error( 'submission_size', 'Too many fields', 413 ); }
		$campaign = Db::get_instance()->get_conv_by_id( (int) $payload['campaignId'] );
		if ( ! $campaign || ! $campaign->enabled || ! empty( $campaign->deleted_at ) ) {
			return self::error( 'submission_campaign', 'Campaign unavailable' ); }
		if ( class_exists( CampaignSupport::class ) && CampaignSupport::get_unsupported_features( $campaign ) ) {
			return self::error( 'submission_campaign', 'Campaign unsupported' ); }
		$token = explode( '.', $payload['token'] );
		if ( count( $token ) !== 2 || ! ctype_digit( $token[0] ) || (int) $token[0] < time() || (int) $token[0] > time() + 7 * DAY_IN_SECONDS ) {
			return self::error( 'submission_token', 'Form token expired or invalid', 403 ); }
		$binding = $campaign->id . '|' . $payload['formId'] . '|' . self::revision( $campaign ) . '|' . $token[0];
		if ( ! hash_equals( hash_hmac( 'sha256', $binding, wp_salt( 'nonce' ) ), $token[1] ) ) {
			return self::error( 'submission_token', 'Form has changed; reload the page', 403 ); }
		$form = self::find_form( json_decode( $campaign->data ), $payload['formId'] );
		if ( ! $form ) {
			return self::error( 'submission_form', 'Form unavailable' ); }
		if ( ! is_object( $form->attributes->fields ?? null ) && ! is_array( $form->attributes->fields ?? null ) ) {
			return self::error( 'submission_field', 'Saved form fields unavailable' ); }
		$declared = array();
		$fixed    = array();
		$custom   = array();
		foreach ( (array) ( $form->attributes->fields ?? array() ) as $key => $field ) {
			if ( ! is_object( $field ) ) {
				return self::error( 'submission_field', 'Invalid saved form field' ); }
			$id = (string) ( $field->id ?? $key );
			if ( ( $field->type ?? '' ) === 'spacer' ) {
				continue; }
			$value = $payload['fields'][ $id ] ?? '';
			if ( ! is_scalar( $value ) && ! is_array( $value ) ) {
				return self::error( 'submission_field', 'Invalid field value' ); }
			$checkbox = in_array( $field->type ?? '', array( 'checkbox', 'consent' ), true );
			if ( ! $checkbox && is_array( $value ) ) {
				return self::error( 'submission_field', 'Invalid field value' ); }
			if ( strlen( wp_json_encode( $value ) ) > 2050 ) {
				return self::error( 'submission_size', 'Field too long', 413 ); }
			if ( ! empty( $field->required ) && ( $value === '' || $value === false || $value === array() || ( $checkbox && empty( $field->options ?? $field->choices ?? null ) && ! in_array( $value, array( true, 1, '1', 'on' ), true ) ) ) ) {
				return self::error( 'submission_required', 'Required field or consent missing' ); }
			if ( $checkbox ) {
				$options = $field->options ?? $field->choices ?? null;
				if ( $options ) {
					$choices = array_map(
						function ( $choice ) {
							return is_object( $choice ) ? (string) ( $choice->value ?? '' ) : (string) $choice;
						},
						(array) $options
					);
					foreach ( (array) $value as $choice ) {
						if ( ! is_scalar( $choice ) || ! in_array( (string) $choice, $choices, true ) ) {
							return self::error( 'submission_choice', 'Undeclared checkbox choice' ); }
					}
				} elseif ( ! in_array( $value, array( '', false, true, 'on', '1', '0', 0, 1 ), true ) ) {
					return self::error( 'submission_choice', 'Invalid checkbox value' ); }
			}
			if ( ( $field->inputType ?? '' ) === 'email' || ( $field->fixedName ?? '' ) === 'email' ) {
				if ( $value !== '' && ( strlen( (string) $value ) > 254 || ! is_email( $value ) ) ) {
					return self::error( 'submission_email', 'Invalid email address' ); }
			}
			if ( ! $checkbox && in_array( $field->inputType ?? '', array( 'number', 'tel', 'url' ), true ) && $value !== '' ) {
				if ( $field->inputType === 'number' && ! is_numeric( $value ) ) {
					return self::error( 'submission_field', 'Invalid number' ); }
				if ( $field->inputType === 'url' && ! wp_http_validate_url( $value ) ) {
					return self::error( 'submission_field', 'Invalid URL' ); }
			}
			$declared[ $id ] = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : sanitize_text_field( $value );
			if ( ! empty( $field->fixedName ) ) {
				$fixed[ $field->fixedName ] = $declared[ $id ];
			} else {
				$custom[ $field->name ?? $id ] = $declared[ $id ]; }
		}
		if ( array_diff( array_keys( $payload['fields'] ), array_keys( $declared ) ) ) {
			return self::error( 'submission_field', 'Undeclared field' ); }
		$configurations = (array) ( $form->attributes->integrations ?? array() );
		if ( count( $configurations ) > 20 ) {
			return self::error( 'submission_size', 'Too many destinations', 413 ); }
		$destinations = array();
		foreach ( $configurations as $id => $config ) {
			$type     = $config->intType ?? '';
			$provider = is_string( $type ) ? IntegrationFactory::get_provider( $type ) : null;
			if ( ! is_object( $config ) || ! $provider || 'mail' !== $provider['category'] || ! empty( $config->disabled ) ) {
				return self::error( 'submission_integration', 'Integration unavailable' ); }
			if ( ! $provider['account'] ) {
				if ( ! filter_var( Settings::get_settings( 'int_enable_' . $type ), FILTER_VALIDATE_BOOLEAN ) ) {
					return self::error( 'submission_integration', 'Integration disabled' ); }
			} else {
				if ( ! ctype_digit( (string) $id ) || (int) $id < 1 ) {
					return self::error( 'submission_account', 'Invalid account' ); }
				$account = Db::get_instance()->get_integration( (int) $id, false, 'all' );
				if ( ! $account || ( $account['type'] ?? $account['integration'] ?? '' ) !== $type ) {
					return self::error( 'submission_account', 'Account unavailable or mismatched' ); }
				$validation = IntegrationFactory::validate_account( $type, $account['data'] ?? null );
				if ( is_wp_error( $validation ) ) {
					return $validation; }
			}
			if ( ! is_object( $config ) || ! is_object( $config->mapping ?? null ) && ! is_array( $config->mapping ?? null ) ) {
				return self::error( 'submission_mapping', 'Saved mapping unavailable' ); }
			foreach ( array( 'list', 'tag' ) as $setting ) {
				if ( ! isset( $config->$setting ) ) {
					continue; }
				$values = is_array( $config->$setting ) ? $config->$setting : array( $config->$setting );
				if ( count( $values ) > 100 ) {
					return self::error( 'submission_destination', 'Too many saved destination IDs' ); }
				foreach ( $values as $value ) {
					if ( ! is_scalar( $value ) || ! preg_match( '/^[a-zA-Z0-9_.:@ -]{1,100}$/D', (string) $value ) ) {
						return self::error( 'submission_destination', 'Invalid saved destination ID' ); }
				}
			}
			$mapped = array();
			foreach ( (array) ( $config->mapping ?? array() ) as $field_id => $target ) {
				// Saved mappings can outlive deleted optional fields; visitor extras were already rejected.
				if ( ! array_key_exists( $field_id, $declared ) ) {
					continue; }
				if ( ! is_string( $target ) || ! preg_match( $provider['mapping_pattern'], $target ) ) {
					return self::error( 'submission_mapping', 'Invalid saved mapping' ); }
				$mapped[ $target ] = $declared[ $field_id ];
			}
			$data = array(
				'id'          => $id,
				'conv_id'     => (int) $campaign->id,
				'integration' => $type,
				'fields'      => $mapped,
			);
			foreach ( array( 'link', 'reqType', 'list', 'tag', 'doubleOpt', 'updateExisting' ) as $key ) {
				if ( isset( $config->$key ) ) {
					$data[ $key ] = $config->$key; }
			}
			$data = IntegrationFactory::prepare_destination( $type, $config, $data );
			if ( is_wp_error( $data ) ) {
				return $data; }
			$destinations[] = $data;
		}
		$address = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( ! filter_var( $address, FILTER_VALIDATE_IP ) ) {
			return self::error( 'submission_client', 'Invalid client address' ); }
		$key   = 'optn_form_rate_' . hash_hmac( 'sha256', $campaign->id . '|' . $address, wp_salt( 'auth' ) );
		$count = (int) get_transient( $key );
		if ( $count >= 20 ) {
			return self::error( 'submission_rate', 'Too many submissions; try again later', 429 ); }
		set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
		return array(
			'destinations' => $destinations,
			'background'   => array(
				'conv_id'     => (int) $campaign->id,
				'integration' => 'bg',
				'fields'      => array(
					'fixed'  => $fixed,
					'custom' => $custom,
				),
			),
		);
	}
}
