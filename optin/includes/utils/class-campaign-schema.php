<?php
/**
 * Validates and sanitizes saved campaign JSON before it is persisted.
 *
 * @package Optin
 */

namespace OPTN\Includes\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Validates and sanitizes the saved campaign envelope before it is stored.
 * It preserves unknown inert blocks while enforcing structural, size, nesting,
 * URL, CSS, and Gutenberg block identity limits.
 */
class CampaignSchema {
	/**
	 * Decodes, validates, sanitizes, and re-encodes a campaign JSON document.
	 * The envelope is limited to one megabyte, 100 steps, 50,000 nested values, and
	 * 64 levels. Markup, URLs, CSS, and block identities are checked before JSON is
	 * returned; invalid size, settings, or block structures produce WP_Error values.
	 *
	 * @param string $json Encoded campaign data received from storage or an API request.
	 * @return string|\WP_Error Sanitized JSON, or an error describing invalid campaign data.
	 */
	public static function sanitize_json( $json ) {
		if ( ! is_string( $json ) || strlen( $json ) > 1048576 ) {
			return new \WP_Error( 'optn_campaign_size', 'Invalid campaign size', array( 'status' => 413 ) ); }
		$data = json_decode( $json );
		if ( ! is_object( $data ) || ! isset( $data->steps ) || ! ( is_object( $data->steps ) || is_array( $data->steps ) ) || count( (array) $data->steps ) > 100 ) {
			return new \WP_Error( 'optn_campaign_schema', 'Invalid campaign structure', array( 'status' => 400 ) ); }
		foreach ( array( 'design', 'schedule', 'audience', 'goal' ) as $key ) {
			if ( isset( $data->$key ) && ! is_object( $data->$key ) ) {
				return new \WP_Error( 'optn_campaign_schema', 'Invalid campaign settings', array( 'status' => 400 ) ); }
		}
		foreach ( array( 'trigger', 'displayRules' ) as $key ) {
			if ( isset( $data->$key ) && ! is_array( $data->$key ) && ! is_object( $data->$key ) ) {
				return new \WP_Error( 'optn_campaign_schema', 'Invalid campaign settings', array( 'status' => 400 ) ); }
		}
		if ( isset( $data->css ) && ! is_string( $data->css ) ) {
			return new \WP_Error( 'optn_campaign_schema', 'Invalid campaign CSS', array( 'status' => 400 ) ); }
		foreach ( array( 'close', 'pos' ) as $key ) {
			if ( isset( $data->design->$key ) && ! is_object( $data->design->$key ) ) {
				return new \WP_Error( 'optn_campaign_schema', 'Invalid campaign design', array( 'status' => 400 ) ); }
		}
		unset( $data->design->pos->customHookName, $data->design->pos->customHookPos );
		if ( isset( $data->design->close->icon ) && ( ! is_string( $data->design->close->icon ) || ! preg_match( '/^[a-zA-Z0-9_-]{1,100}$/D', $data->design->close->icon ) ) ) {
			return new \WP_Error( 'optn_campaign_schema', 'Invalid close icon', array( 'status' => 400 ) ); }
		if ( 'vid' === ( $data->design->bg->type ?? null ) && ! is_string( $data->design->bg->vid->url ?? null ) ) {
			return new \WP_Error( 'optn_campaign_schema', 'Invalid background video URL', array( 'status' => 400 ) );
		}
		$nodes = 0;
		$clean = function ( &$value, $key = '', $depth = 0 ) use ( &$clean, &$nodes ) {
			if ( ++$nodes > 50000 || $depth > 64 ) {
				return false; }
			if ( is_array( $value ) || is_object( $value ) ) {
				foreach ( $value as $name => &$item ) {
					if ( ! $clean( $item, (string) $name, $depth + 1 ) ) {
						return false; }
				}
				unset( $item );
			} elseif ( is_string( $value ) ) {
				if ( 'css' === $key ) {
					$value = wp_strip_all_tags( $value ); } elseif ( in_array( $key, array( 'url', 'src', 'href' ), true ) ) {
					$value = esc_url_raw( $value, array( 'http', 'https', 'mailto', 'tel' ) ); } elseif ( strpos( $value, '<' ) !== false ) {
						$value = Sanitizer::sanitize( $value ); }
			}
			return true;
		};
		if ( ! $clean( $data ) ) {
			return new \WP_Error( 'optn_campaign_schema', 'Campaign structure exceeds limits', array( 'status' => 400 ) ); }
		$valid_blocks = function ( $blocks ) use ( &$valid_blocks ) {
			if ( ! is_array( $blocks ) ) {
				return false; }
			foreach ( $blocks as $block ) {
				if ( ! is_array( $block ) || count( $block ) !== 2 || ! is_string( $block[0] ) || ! is_object( $block[1] ) || ! is_string( $block[1]->clientId ?? null ) || $block[1]->clientId !== $block[0] || ! is_string( $block[1]->name ?? null ) || ! is_object( $block[1]->attributes ?? null ) ) {
					return false; }
				if ( 'video' === $block[1]->name && ! is_string( $block[1]->attributes->url ?? null ) ) {
					return false;
				}
				if ( isset( $block[1]->innerBlocks ) && ( ! is_object( $block[1]->innerBlocks ) || ! isset( $block[1]->innerBlocks->value ) || ! $valid_blocks( $block[1]->innerBlocks->value ) ) ) {
					return false; }
			}
			return true;
		};
		foreach ( $data->steps as $step ) {
			if ( ! is_object( $step ) || ! isset( $step->blocks->value ) || ! $valid_blocks( $step->blocks->value ) ) {
				return new \WP_Error( 'optn_campaign_schema', 'Invalid campaign blocks', array( 'status' => 400 ) ); }
		}
		CampaignStyles::sanitize( $data );
		return wp_json_encode( $data );
	}
}
