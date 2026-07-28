<?php
/**
 * LAYOUT: art on the left and right flanks, live text + countdown centered,
 * over a background image. The only design with a timer.
 *
 * Self-contained: markup, CSS and JS in this one file, every class and storage
 * key built from $prefix so a sibling plugin can copy it as-is.
 *
 * @var array  $notice      Promo entry (brand_color, bg_image, left_image,
 *                          right_image, text, countdown_color, countdown_duration, url).
 * @var array  $query_args  Dismiss-link query args.
 * @var string $prefix      Identity slug from config.php.
 * @var string $brand_color Accent color from config.php.
 */

defined( 'ABSPATH' ) || exit;

$wrapper_class   = $prefix . '-notice-wrapper';
$banner_class    = $prefix . '-banner-notice';
$link_class      = $prefix . '-banner-link';
$content_class   = $prefix . '-banner-content';
$side_img_class  = $prefix . '-banner-side-image';
$main_class      = $prefix . '-banner-main';
$main_text_class = $prefix . '-banner-main-text';
$countdown_class = $prefix . '-notice-countdown';
$close_class     = $prefix . '-banner-notice-close';
$scope           = '.' . $wrapper_class . '.' . $banner_class;
?>
<style type="text/css">
	<?php echo esc_html( $scope ); ?> {
		height: auto !important;
		padding: 0 !important;
		position: relative;
		box-sizing: border-box;
		background-repeat: no-repeat;
		background-size: cover;
		background-position: center;
	}
	<?php echo esc_html( $scope ); ?> .<?php echo esc_html( $link_class ); ?> {
		width: 100%;
		text-decoration: none;
		display: block;
	}
	<?php echo esc_html( $scope ); ?> .<?php echo esc_html( $content_class ); ?> {
		display: flex;
		justify-content: space-between;
		align-items: center;
		max-width: 700px;
		margin: 0 auto;
		padding: 10px 16px;
		gap: 16px;
	}
	<?php echo esc_html( $scope ); ?> .<?php echo esc_html( $side_img_class ); ?> {
		display: block;
		max-width: 100%;
		height: auto;
		max-height: 48px;
	}
	<?php echo esc_html( $scope ); ?> .<?php echo esc_html( $main_class ); ?> {
		display: flex;
		flex-direction: column;
		gap: 4px;
		align-items: center;
		justify-content: center;
		font-weight: 700;
		font-size: 18px;
		color: <?php echo esc_attr( $brand_color ); ?>;
		line-height: 1.2;
		text-align: center;
	}

	@media screen and (max-width: 1100px) {
		<?php echo esc_html( $scope ); ?> .<?php echo esc_html( $content_class ); ?> .<?php echo esc_html( $main_text_class ); ?> {
			display: none;
		}
	}

	@media screen and (max-width: 490px) {
		<?php echo esc_html( $scope ); ?> {
			display: none;
		}
	}

	@media screen and (max-width: 782px) {
		<?php echo esc_html( $scope ); ?> .<?php echo esc_html( $content_class ); ?> {
			justify-content: center;
			padding: 12px 32px 12px 12px;
		}
		<?php echo esc_html( $scope ); ?> .<?php echo esc_html( $main_class ); ?> {
			font-size: 22px;
			line-height: 28px;
		}
	}
	@media screen and (max-width: 480px) {
		<?php echo esc_html( $scope ); ?> .<?php echo esc_html( $content_class ); ?> {
			padding: 10px 32px 10px 10px;
		}
		<?php echo esc_html( $scope ); ?> .<?php echo esc_html( $main_class ); ?> {
			font-size: 18px;
			line-height: 24px;
		}
	}
</style>
<div
	class="<?php echo esc_attr( $wrapper_class . ' ' . $banner_class . ' notice' ); ?>"
	style="
		border-left: 3px solid <?php echo esc_attr( $notice['brand_color'] ); ?>;
		background-image: url('<?php echo esc_attr( $notice['bg_image'] ); ?>');
">
	<a
		class="<?php echo esc_attr( $close_class ); ?> dashicons dashicons-no-alt"
		style="
			position: absolute;
			top: 1px;
			right: 1px;
			border-radius: 50%;
			background-color: black;
			color: white;
			font-size: 14px;
			display: flex;
			align-items: center;
			justify-content: center;
		"
		aria-label="<?php esc_html_e( 'Close Banner', 'optin' ); ?>"
		href="<?php echo esc_url( add_query_arg( $query_args ) ); ?>">
	</a>

	<a class="<?php echo esc_attr( $link_class ); ?>" target="_blank" href="<?php echo esc_url( $notice['url'] ); ?>">
		<div class="<?php echo esc_attr( $content_class ); ?>">
			<img class="<?php echo esc_attr( $side_img_class ); ?>" loading="lazy" src="<?php echo esc_url( $notice['left_image'] ); ?>" />
			<div class="<?php echo esc_attr( $main_class ); ?>">
				<span>
					<?php echo esc_html( $notice['text'] ); ?>
				</span>
				<div
					class="<?php echo esc_attr( $countdown_class ); ?>"
					style="
						color: <?php echo esc_attr( $notice['countdown_color'] ); ?>;
					"
					data-notice-key="<?php echo esc_attr( $notice['key'] . '-countdown' ); ?>"
					data-duration="<?php echo esc_attr( $notice['countdown_duration'] ); ?>">
					00:00:00:00
				</div>
			</div>
			<img class="<?php echo esc_attr( $side_img_class ); ?>" loading="lazy" src="<?php echo esc_url( $notice['right_image'] ); ?>" />
		</div>
	</a>
</div>
<script type="text/javascript">
	jQuery(function($) {
		'use strict';

		const storagePrefix = '<?php echo esc_js( $prefix ); ?>_notice_countdown_';
		const countdownSelector = '.<?php echo esc_js( $countdown_class ); ?>';

		const formatCountdown = function(seconds) {
			const days = Math.floor(seconds / 86400);
			const hours = Math.floor((seconds % 86400) / 3600);
			const minutes = Math.floor((seconds % 3600) / 60);
			const secs = seconds % 60;

			return String(days).padStart(2, '0') + ':' + String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
		};

		const parseDurationToSeconds = function(duration) {
			if (typeof duration === 'number' && Number.isFinite(duration) && duration > 0) {
				return Math.floor(duration);
			}

			const durationString = String(duration || '').trim();
			if (/^\d+$/.test(durationString)) {
				return parseInt(durationString, 10);
			}

			return 0;
		};

		const nowInSeconds = function() {
			return Math.floor(Date.now() / 1000);
		};

		$(countdownSelector).each(function() {
			const countdownElement = $(this);
			const noticeKey = String(countdownElement.data('noticeKey') || '');
			const duration = parseDurationToSeconds(countdownElement.data('duration'));

			if (!noticeKey || duration <= 0) {
				return;
			}

			const storageKey = storagePrefix + noticeKey;
			let endAt = 0;

			try {
				const storedDataRaw = window.localStorage.getItem(storageKey);
				if (storedDataRaw) {
					const storedData = JSON.parse(storedDataRaw);
					if (storedData && parseInt(storedData.duration, 10) === duration) {
						endAt = parseInt(storedData.endAt, 10) || 0;
					}
				}
			} catch (error) {
				endAt = 0;
			}

			const saveTimerState = function(nextEndAt) {
				try {
					window.localStorage.setItem(
						storageKey,
						JSON.stringify({
							endAt: nextEndAt,
							duration: duration,
						})
					);
				} catch (error) {
					// No-op.
				}
			};

			const resetTimer = function(currentTime) {
				endAt = currentTime + duration;
				saveTimerState(endAt);
			};

			const tick = function() {
				const currentTime = nowInSeconds();

				if (endAt <= currentTime) {
					resetTimer(currentTime);
				}

				const remaining = Math.max(endAt - currentTime, 0);
				countdownElement.text(formatCountdown(remaining));
			};

			if (endAt <= nowInSeconds()) {
				resetTimer(nowInSeconds());
			}

			tick();
			window.setInterval(tick, 1000);
		});
	});
</script>
