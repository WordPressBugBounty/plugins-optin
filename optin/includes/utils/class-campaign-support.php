<?php
/**
 * Defines Free schema requirements for campaign evaluation.
 *
 * @package Optin
 */

namespace OPTN\Includes\Utils;

defined( 'ABSPATH' ) || exit;

/** Free schema requirements, independent of license options or Pro installation. */
class CampaignSupport {
	/** Opaque campaign IDs exported by extensions, including while an extension is inactive. */
	public static function held_campaigns(): array {
		$owners = get_option( 'optn_campaign_execution_holds', array() );
		$ids    = array();
		foreach ( (array) $owners as $holds ) {
			foreach ( (array) $holds as $id ) {
				if ( is_numeric( $id ) && (int) $id > 0 ) {
					$ids[] = (int) $id;
				}
			}
		}
		/** Extensions may release only the opaque holds they own before candidate queries run. */
		$ids = apply_filters( 'optn_campaign_execution_holds', array_values( array_unique( $ids ) ), $owners );
		return is_array( $ids ) ? array_values(
			array_unique(
				array_filter(
					array_map( 'intval', $ids ),
					function ( $id ) {
						return $id > 0;
					}
				)
			)
		) : array_values( array_unique( self::raw_holds( $owners ) ) );
	}

	/**
	 * Collects positive campaign IDs stored in extension holds.
	 *
	 * @param array $owners Campaign IDs grouped by extension owner.
	 * @return array<int, int> Positive campaign IDs.
	 */
	private static function raw_holds( $owners ): array {
		$ids = array();
		foreach ( (array) $owners as $holds ) {
			foreach ( (array) $holds as $id ) {
				if ( is_numeric( $id ) && (int) $id > 0 ) {
					$ids[] = (int) $id; }
			}
		}
		return $ids;
	}
	/**
	 * Finds campaign features that Free cannot evaluate.
	 *
	 * @param object $campaign Database campaign object with serialized block data; the public contract accepts any object exposing `data`.
	 * @return array<int, string> Unsupported feature identifiers.
	 */
	public static function get_unsupported_features( object $campaign ): array {
		$data        = json_decode( $campaign->data ?? '' );
		$unsupported = array();
		if ( ! is_object( $data ) ) {
			return array( 'schema' ); }
		// Group envelopes are shared schema, independent of which extension evaluates their conditions.
		foreach ( array(
			'trigger'      => 'triggers',
			'displayRules' => 'rules',
		) as $setting => $conditions ) {
			if ( ! property_exists( $data, $setting ) ) {
				continue; }
			if ( ! is_array( $data->$setting ) ) {
				$unsupported[] = 'schema';
				continue; }
			foreach ( $data->$setting as $group ) {
				if ( ! is_object( $group ) || ! is_array( $group->$conditions ?? null ) ) {
					$unsupported[] = 'schema';
					continue; }
				foreach ( $group->$conditions as $condition ) {
					if ( ! is_object( $condition ) || ! is_string( $condition->type ?? null ) ) {
						$unsupported[] = 'schema'; }
				}
			}
		}
		if ( count( (array) ( $data->steps ?? array() ) ) > 1 ) {
			$unsupported[] = 'multiple_steps'; }
		$triggers = (array) ( $data->trigger ?? array() );
		if ( count( $triggers ) > 1 ) {
			$unsupported[] = 'trigger_groups'; }
		foreach ( $triggers as $group ) {
			foreach ( (array) ( $group->triggers ?? array() ) as $trigger ) {
				if ( ( $trigger->type ?? '' ) !== 'after_time' ) {
					$unsupported[] = 'trigger'; }
			}
		}
		if ( ! empty( $data->schedule->recurr ) ) {
			$unsupported[] = 'recurrence'; }
		if ( ! empty( $data->goal->stopOnView ) ) {
			$unsupported[] = 'view_limit'; }
		if ( ( $data->goal->showEvery ?? 'page_load' ) !== 'page_load' && ! empty( $data->goal->cooldown ) && ! in_array( $data->goal->cooldownType ?? '', array( 'secs', 'mins', 'hours' ), true ) ) {
			$unsupported[] = 'long_interval'; }
		if ( ( $data->audience->locType ?? 'all' ) !== 'all' ) {
			$unsupported[] = 'location'; }
		if ( ( $data->audience->tSrc ?? 'all' ) !== 'all' ) {
			$unsupported[] = 'traffic_source'; }
		$rules = (array) ( $data->displayRules ?? array() );
		if ( count( $rules ) > 1 ) {
			$unsupported[] = 'rule_groups'; }
		$free_rules = array( 'entire_nocond', 'front_nocond', 'page', 'parent_page', 'author', 'post_type', 'post_posts', 'post_cat', 'post_tags' );
		foreach ( $rules as $group ) {
			foreach ( (array) ( $group->rules ?? array() ) as $rule ) {
				if ( ! in_array( $rule->type ?? '', $free_rules, true ) ) {
					$unsupported[] = 'display_rule'; }
				if ( ( $rule->type ?? '' ) === 'post_type' ) {
					foreach ( (array) ( $rule->values ?? array() ) as $value ) {
						if ( ! is_string( $value ) || ! in_array( explode( '###', $value )[0], DisplayRules::DEFAULT_POST_TYPES, true ) ) {
							$unsupported[] = 'custom_post_type'; }
					}
				}
			}
		}
		$blocks = array( 'heading', 'html', 'countdown', 'image', 'coupon', 'social', 'button', 'form', 'columns', 'video', 'shortcode', 'shape', 'preset-loader', 'group' );
		$scan   = function ( $value ) use ( &$scan, &$unsupported, $blocks ) {
			if ( is_object( $value ) && isset( $value->name, $value->attributes ) ) {
				if ( ! in_array( $value->name, $blocks, true ) ) {
					$unsupported[] = 'block'; }
				$attr = $value->attributes;
				if ( $value->name === 'countdown' && ( $attr->cType ?? 'static' ) !== 'static' ) {
					$unsupported[] = 'evergreen_countdown'; }
				if ( ( $attr->action ?? '' ) === 'next_step' ) {
					$unsupported[] = 'step_navigation'; }
				if ( $value->name === 'form' ) {
					foreach ( (array) ( $attr->integrations ?? array() ) as $integration ) {
						if ( ! in_array( $integration->intType ?? '', array( 'mailchimp', 'webhook' ), true ) ) {
							$unsupported[] = 'integration'; }
					}
				}
			}
			if ( is_array( $value ) || is_object( $value ) ) {
				foreach ( $value as $item ) {
					$scan( $item ); }
			}
		};
		$scan( $data );
		if ( isset( $campaign->id ) && in_array( (int) $campaign->id, self::held_campaigns(), true ) ) {
			$unsupported[] = 'extension_hold'; }
		/** Registered handlers remove only the features they can execute. Unknown features stay inert. */
		$features = apply_filters( 'optn_campaign_unsupported_features', array_values( array_unique( $unsupported ) ), $campaign );
		return is_array( $features ) ? array_values( array_unique( $features ) ) : array_values( array_unique( $unsupported ) );
	}
}
