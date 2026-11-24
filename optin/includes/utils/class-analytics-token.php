<?php
/**
 * Signs narrowly scoped public analytics credentials without storing account sessions.
 *
 * @package Optin
 */

namespace OPTN\Includes\Utils;

defined( 'ABSPATH' ) || exit;

/** A credential grants access only to one interaction, campaign and event scope. */
class AnalyticsToken {
	/** Matches the existing two-day purchase attribution cookie lifetime. */
	const LIFETIME = 2 * DAY_IN_SECONDS;

	/**
	 * Issues a credential for an interaction created by this server.
	 *
	 * @param int      $id Interaction ID.
	 * @param int      $campaign_id Campaign ID.
	 * @param string   $scope Event or purchase scope.
	 * @param int|null $expires Expiry timestamp; defaults to the attribution lifetime.
	 * @return string Signed credential, never an account authentication token.
	 */
	public static function issue( int $id, int $campaign_id, string $scope = 'event', ?int $expires = null ): string {
		$expires = $expires ?? time() + self::LIFETIME;
		// Multisite blogs share salts but can contain unrelated rows with the same IDs.
		$message = implode( '|', array( 'optn-analytics-v1', get_current_blog_id(), $id, $campaign_id, $scope, $expires ) );
		return $expires . '.' . hash_hmac( 'sha256', $message, wp_salt( 'auth' ) );
	}

	/**
	 * Verifies identity, scope, expiry and signature using constant-time comparison.
	 *
	 * @param string $token Submitted credential.
	 * @param int    $id Interaction ID.
	 * @param int    $campaign_id Campaign ID.
	 * @param string $scope Required scope.
	 * @return bool Whether this exact interaction is authorized.
	 */
	public static function verify( string $token, int $id, int $campaign_id, string $scope = 'event' ): bool {
		if ( $id < 1 || $campaign_id < 1 || ! preg_match( '/^([0-9]{10})\.([a-f0-9]{64})$/D', $token, $parts ) ) {
			return false;
		}
		$expires = (int) $parts[1];
		if ( $expires <= time() || $expires > time() + self::LIFETIME ) {
			return false;
		}
		return hash_equals( self::issue( $id, $campaign_id, $scope, $expires ), $token );
	}
}
