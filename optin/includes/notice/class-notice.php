<?php //phpcs:ignore
namespace OPTN\Includes\Notice;

use OPTN\Includes\Utils\DurbinClient;
use OPTN\Includes\Xpo;

defined( 'ABSPATH' ) || exit;
/**
 * Promo system facade. Data lives in promos/, one file per surface; all
 * surfaces share config() and the promo_is_live() date gate.
 *
 *   promos/notice.php       -> rendered here on admin_notices, via templates/<type>.php
 *   promos/hellobar.php     -> get_hellobar_config(), localized for React
 *   promos/plugin-meta.php  -> get_active_promo(), called by the plugin's
 *                              plugin_action_links / plugin_row_meta handler
 *
 * One thing here is NOT promo-driven and just lives in this file:
 *   render_durbin_consent_box()      -> the data-collection consent box
 */
class Notice {


	/**
	 * Per-plugin identity. See config.php.
	 *
	 * @var array
	 */
	private $config = array();

	/**
	 * The complete set of promo surfaces: every place in wp-admin a promo can
	 * appear. Deliberately a closed, hand-written list, unlike templates/ which
	 * is open-ended by design — see get_promos() and template_for().
	 *
	 * @var array<string, string> surface slug => file under promos/.
	 */
	private const SURFACES = array(
		'notice'      => 'notice.php',
		'hellobar'    => 'hellobar.php',
		'plugin-meta' => 'plugin-meta.php',
	);


	/**
	 * Notice Constructor
	 */
	public function __construct() {
		$this->config = self::config();

		add_action( 'admin_notices', array( $this, 'admin_notices_callback' ) );
		add_action( 'admin_init', array( $this, 'set_dismiss_notice_callback' ) );

		// REST API routes.
		add_action( 'rest_api_init', array( $this, 'register_rest_route' ) );

		add_filter( 'xpo_active_notice_lists', array( $this, 'handle_xpo_active_notice_lists' ), 99, 1 );
	}

	/**
	 * Per-plugin identity, shared by every promo surface.
	 *
	 * Always read config.php through here: it RETURNS an array, so a caller
	 * using require_once would get `true` on the second call.
	 *
	 * @return array
	 */
	public static function config() {
		static $config = null;

		if ( null === $config ) {
			$config = require __DIR__ . '/config.php';
		}

		return $config;
	}

	/**
	 * A developer typo: throw in dev so it's caught, ignore in production so a
	 * shipped mistake can't white-screen every admin page.
	 *
	 * @param string $message What was wrong.
	 * @return void
	 */
	private static function fail_in_dev( $message ) {
		if ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || defined( 'XPO_DEV_MODE' ) ) {
			$config = self::config();
			throw new \RuntimeException( $config['brand_name'] . ' promos: ' . $message );
		}
	}

	/**
	 * Load one surface's promo list from promos/.
	 *
	 * The filename comes from the fixed SURFACES map, not from $surface: the
	 * surface set is closed, unlike templates/. See "Adding a fourth surface"
	 * in README.md.
	 *
	 * The locals below are inherited by the included file — that is how promos/
	 * files reach $prefix, $asset_url, etc. without requiring config.php.
	 *
	 * @param string $surface One of the SURFACES keys.
	 * @return array
	 */
	public static function get_promos( $surface = 'notice' ) {
		$config      = self::config();
		$prefix      = $config['prefix'];
		$asset_url   = $config['asset_url'];
		$brand_name  = $config['brand_name'];
		$brand_color = $config['brand_color'];

		if ( ! isset( self::SURFACES[ $surface ] ) ) {
			self::fail_in_dev( sprintf( 'unknown surface "%s" (known: %s)', $surface, implode( ', ', array_keys( self::SURFACES ) ) ) );
			return array();
		}

		// A known surface with no file is legitimate, not a typo: a plugin
		// without a hello bar just deletes promos/hellobar.php.
		$file = __DIR__ . '/promos/' . self::SURFACES[ $surface ];
		if ( ! is_readable( $file ) ) {
			return array();
		}

		// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- filename comes from SURFACES.
		$promos = include $file;

		return is_array( $promos ) ? $promos : array();
	}

	/**
	 * First live promo on a surface, for the surfaces that show only one thing.
	 * The notice surface shows one per type, so it loops in render_notices().
	 *
	 * @param string $surface Surface slug.
	 * @return array|null Promo entry, or null when nothing is live.
	 */
	public static function get_active_promo( $surface ) {
		foreach ( self::get_promos( $surface ) as $promo ) {
			if ( self::promo_is_live( $promo ) ) {
				return $promo;
			}
		}

		return null;
	}

	/**
	 * Resolve a promo's `type` to templates/<type>.php. No map to maintain:
	 * adding a design means dropping in a template and naming the type after it.
	 *
	 * @param string $type Promo type, from a promos/ entry (never user input).
	 * @return string Absolute template path, or '' if the type is unknown.
	 */
	private function template_for( $type ) {
		if ( is_string( $type ) && preg_match( '/^[a-z0-9_-]+$/', $type ) ) {
			$template = __DIR__ . '/templates/' . $type . '.php';
			if ( is_readable( $template ) ) {
				return $template;
			}
		}

		self::fail_in_dev( sprintf( 'no template for type "%s" (expected templates/%s.php)', $type, $type ) );
		return '';
	}

	/**
	 * The one date gate, shared by all three surfaces: dismiss-in-flight
	 * ($_GET), date window, visibility, dismissed transient.
	 *
	 * XPO_DEV_MODE forces every start date to 2026-01-01, so upcoming promos
	 * are visible without editing promos/.
	 *
	 * @param array $notice Promo entry.
	 * @return bool
	 */
	private static function promo_is_live( $notice ) {
		$config = self::config();

		if ( empty( $notice['key'] ) ) {
			return false;
		}
		$notice_key  = $notice['key'];
		$dismiss_arg = 'disable_' . $config['prefix'] . '_notice';

		if ( isset( $_GET[ $dismiss_arg ] ) && $notice_key === sanitize_text_field( wp_unslash( $_GET[ $dismiss_arg ] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}

		$current_time = gmdate( 'U' );
		$start = defined( 'XPO_DEV_MODE' ) ? '2026-01-01 00:00 Asia/Dhaka' : $notice['start'];
		$notice_start = gmdate( 'U', strtotime( $start ) );
		$notice_end   = gmdate( 'U', strtotime( $notice['end'] ) );

		if ( $current_time < $notice_start || $current_time > $notice_end || empty( $notice['visibility'] ) ) {
			return false;
		}

		if ( 'off' === Xpo::get_transient_without_cache( $config['prefix'] . '_get_pro_notice_' . $notice_key ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Does this plugin have at least one promo eligible to show right now?
	 *
	 * @return bool
	 */
	private function has_active_promo() {
		foreach ( self::get_promos( 'notice' ) as $notice ) {
			$type = isset( $notice['type'] ) ? $notice['type'] : '';
			if ( $this->template_for( $type ) && self::promo_is_live( $notice ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Handle Plugin Notice for all plugins
	 *
	 * @param array $active_lists Lists of all active plugin notice.
	 * @return array
	 */
	public function handle_xpo_active_notice_lists( $active_lists ) {

		if ( $this->has_active_promo() ) {
			$active_lists[ $this->config['priority_key'] ] = $this->config['priority'];
		}

		return $active_lists;
	}

	/**
	 * Handle Plugin Notice for all plugins
	 *
	 * @return bool
	 */
	public function is_available_for_notice() {
		$active_notices = apply_filters( 'xpo_active_notice_lists', array() );

		if ( empty( $active_notices ) ) {
			return true;
		}

		asort( $active_notices );

		return array_key_first( $active_notices ) === $this->config['priority_key'];
	}


	/**
	 * Registers REST API endpoints.
	 *
	 * @return void
	 */
	public function register_rest_route() {
		$routes = array(
			// Hello Bar.
			array(
				'endpoint'            => 'hello_bar',
				'methods'             => 'POST',
				'callback'            => array( $this, 'hello_bar_callback' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			),
		);

		// Namespace tracks config.php's prefix, so this file carries no plugin
		// literal. The React side must use the same string — see README.
		// example api: optn/v1/hello_bar
		$rest_namespace = $this->config['prefix'] . '/v1';

		foreach ( $routes as $route ) {
			register_rest_route(
				$rest_namespace,
				$route['endpoint'],
				array(
					array(
						'methods'             => $route['methods'],
						'callback'            => $route['callback'],
						'permission_callback' => $route['permission_callback'],
					),
				)
			);
		}
	}

	/**
	 * The live hello-bar promo, ready to render. The plugin's admin-menu class
	 * localizes this into its JS object under `helloBar`. PHP resolves which
	 * promo is live and builds its URL, so the React bundle holds no promo list
	 * or date logic.
	 *
	 * @return array|null Null when no promo is live (React renders nothing).
	 */
	public static function get_hellobar_config() {
		$promo = null;

		foreach ( self::get_promos( 'hellobar' ) as $candidate ) {
			// Hello-bar dismissal writes 'hide' under the promo key itself, not
			// the notices' transient. Don't unify: it would un-dismiss the bar
			// for everyone who already closed it.
			if ( 'hide' === Xpo::get_transient_without_cache( $candidate['key'] ) ) {
				continue;
			}

			if ( self::promo_is_live( $candidate ) ) {
				$promo = $candidate;
				break;
			}
		}

		if ( ! $promo ) {
			return null;
		}

		$config = self::config();

		return array(
			'id'                => $promo['key'],
			'brandColor'        => $config['brand_color'],
			'text'              => isset( $promo['text'] ) ? $promo['text'] : '',
			'highlight'         => isset( $promo['highlight'] ) ? $promo['highlight'] : '',
			'url'               => isset( $promo['url'] ) ? $promo['url'] : '',
			'countdownDuration' => isset( $promo['countdown_duration'] ) ? (int) $promo['countdown_duration'] : 0,
		);
	}

	/**
	 * Handles Hello Bar dismissal action via REST API .
	 *
	 * @param \WP_REST_Request $request REST request object .
	 * @return \WP_REST_Response
	 */
	public function hello_bar_callback( \WP_REST_Request $request ) {
		$request_params = $request->get_params();
		$type           = isset( $request_params['type'] ) ? $request_params['type'] : '';
		$id             = isset( $request_params['id'] ) ? $request_params['id'] : '';

		if ( 'hello_bar' === $type && ! empty( $id ) ) {
			// we are setting the transient for 15 days to hide the hello bar.
			Xpo::set_transient_without_cache( $id, 'hide', 15 * DAY_IN_SECONDS );
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Hello Bar Action performed', 'optin' ),
			),
			200
		);
	}

	/**
	 * Set Notice Dismiss Callback
	 *
	 * @return void
	 */
	public function set_dismiss_notice_callback() {
		$prefix = $this->config['prefix'];

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['wpnonce'] ?? '' ) ), $prefix . '-nonce' ) ) {
			return;
		}

		$durbin_key = sanitize_text_field( wp_unslash( $_GET[ $prefix . '_durbin_key' ] ?? '' ) );

		// Durbin notice dismiss.
		if ( ! empty( $durbin_key ) ) {
			Xpo::set_transient_without_cache( $prefix . '_durbin_notice_' . $durbin_key, 'off' );

			if ( 'get' === sanitize_text_field( wp_unslash( $_GET[ $prefix . '_get_durbin' ] ?? '' ) ) ) {
				DurbinClient::send( DurbinClient::ACTIVATE_ACTION );
			}
		}

		$notice_key = sanitize_text_field( wp_unslash( $_GET[ 'disable_' . $prefix . '_notice' ] ?? '' ) );
		if ( ! empty( $notice_key ) ) {
			$interval = (int) sanitize_text_field( wp_unslash( $_GET[ $prefix . '_interval' ] ?? '' ) );
			if ( ! empty( $interval ) ) {
				Xpo::set_transient_without_cache( $prefix . '_get_pro_notice_' . $notice_key, 'off', $interval );
			} else {
				Xpo::set_transient_without_cache( $prefix . '_get_pro_notice_' . $notice_key, 'off' );
			}
		}
	}

	/**
	 * Admin Notices Callback
	 *
	 * @return void
	 */
	public function admin_notices_callback() {
		if ( $this->is_available_for_notice() ) {
			$this->render_notices( self::get_promos( 'notice' ) );
		}
		$this->render_durbin_consent_box();
	}

	/**
	 * Render at most one live promo per type (first match wins), each through
	 * its own template. XPO_DEV_MODE drops the one-per-type limit so every
	 * design can be checked at once.
	 *
	 * @param array $promos List of promo entries.
	 * @return void
	 */
	private function render_notices( $promos ) {
		$shown = array();
		$enforce_one_per_type = ! defined( 'XPO_DEV_MODE' );
		$prefix      = $this->config['prefix'];
		$brand_color = $this->config['brand_color'];

		foreach ( $promos as $notice ) {
			$type = isset( $notice['type'] ) ? $notice['type'] : '';

			if ( $enforce_one_per_type && isset( $shown[ $type ] ) ) {
				continue;
			}

			$template = $this->template_for( $type );
			if ( ! $template || ! self::promo_is_live( $notice ) ) {
				continue;
			}

			$query_args = array(
				'disable_' . $prefix . '_notice' => $notice['key'],
				'wpnonce'                        => wp_create_nonce( $prefix . '-nonce' ),
			);
			if ( ! empty( $notice['repeat_interval'] ) ) {
				$query_args[ $prefix . '_interval' ] = $notice['repeat_interval'];
			}

			// $notice, $query_args, $prefix and $brand_color are in scope for the template.
			include $template;

			$shown[ $type ] = true;
		}
	}

	/**
	 * The Durbin Html
	 *
	 * @return void
	 */
	public function render_durbin_consent_box() {
		$prefix       = $this->config['prefix'];
		$durbin_key   = $prefix . '_durbin_dc1';
		$consent_box  = $prefix . '-consent-box';
		$consent_cont = $prefix . '-consent-content';
		$text_first   = $prefix . '-consent-text-first';
		$text_last    = $prefix . '-consent-text-last';
		$accept_btn   = $prefix . '-consent-accept';
		$close_link   = $prefix . '-notice-close';
		$close_icon   = $prefix . '-notice-close-icon';
		$text_group   = $prefix . '-consent-text';
		$wrapper      = $prefix . '-notice-wrapper';
		$brand_name   = $this->config['brand_name'];

		if (
			isset( $_GET[ $prefix . '_durbin_key' ] ) || // phpcs:ignore
			'off' === Xpo::get_transient_without_cache( $prefix . '_durbin_notice_' . $durbin_key )
		) {
			return;
		}

		$db_nonce = wp_create_nonce( $prefix . '-nonce' );

		?>
		<style>
				.<?php echo esc_attr( $consent_box ); ?> {
					width: 656px;
					padding: 16px;
					border: 1px solid #070707;
					border-left-width: 4px;
					border-radius: 4px;
					background-color: #fff;
					position: relative;
					width: 100%;
					box-sizing: border-box;
				}
				.<?php echo esc_attr( $consent_cont ); ?> {
					display: flex;
					justify-content: flex-start;
					align-items: flex-end;
					gap: 26px;
				}

				.<?php echo esc_attr( $text_first ); ?> {
					font-size: 14px;
					font-weight: 600;
					color: #070707;
				}
				.<?php echo esc_attr( $text_last ); ?> {
					margin: 4px 0 0;
					font-size: 14px;
					color: #070707;
				}

				.<?php echo esc_attr( $accept_btn ); ?> {
					background-color: #070707;
					color: #fff;
					border: none;
					padding: 6px 10px;
					border-radius: 4px;
					cursor: pointer;
					font-size: 12px;
					font-weight: 600;
					text-decoration: none;
				}
				.<?php echo esc_attr( $accept_btn ); ?>:hover {
					background-color:rgb(38, 38, 38);
					color: #fff;
				}
			</style>
			<div class="<?php echo esc_attr( $consent_box . ' ' . $wrapper ); ?> notice data_collection_notice">
			<div class="<?php echo esc_attr( $consent_cont ); ?>">
			<div class="<?php echo esc_attr( $text_group ); ?>">
			<div class="<?php echo esc_attr( $text_first ); ?>">
			<?php
				/* translators: %s: plugin brand name, from config.php. */
				printf( esc_html__( 'Want to help make %s even more awesome?', 'optin' ), esc_html( $brand_name ) );
			?>
			</div>
			<div class="<?php echo esc_attr( $text_last ); ?>">
					<?php esc_html_e( 'Allow us to collect diagnostic data and usage information. see ', 'optin' ); ?>
			<a href="https://www.wpxpo.com/data-collection-policy/" target="_blank" ><?php esc_html_e( 'what we collect.', 'optin' ); ?></a>
			</div>
			</div>
			<a
					class="<?php echo esc_attr( $accept_btn ); ?>"
					href=
					<?php
									echo esc_url(
										add_query_arg(
											array(
												$prefix . '_durbin_key' => $durbin_key,
												$prefix . '_get_durbin' => 'get',
												'wpnonce' => $db_nonce,
											)
										)
									);
					?>
									class="<?php echo esc_attr( $close_link ); ?>"
			><?php esc_html_e( 'Accept & Close', 'optin' ); ?></a>
			</div>
			<a href=
				<?php
							echo esc_url(
								add_query_arg(
									array(
										$prefix . '_durbin_key' => $durbin_key,
										'wpnonce'                => $db_nonce,
									)
								)
							);
				?>
				class="<?php echo esc_attr( $close_link ); ?>"
				style="
					position: absolute;
					right: 2px;
					top: 5px;
					text-decoration: unset;
					color: #b6b6b6;
					font-family: dashicons;
					font-size: 16px;
					font-style: normal;
					font-weight: 400;
					line-height: 20px;
				"
			>
				<span
				style="font-size: 14px;"
				class="<?php echo esc_attr( $close_icon ); ?> dashicons dashicons-dismiss"> </span></a>
			</div>
		<?php
	}
}
