<?php
/**
 * LAYOUT: one full-width clickable artwork plus a close button. No live text
 * at all — every word is baked into the image, so nothing here is translatable
 * or themeable.
 *
 * Self-contained: markup and CSS in this one file, every class built from
 * $prefix so a sibling plugin can copy it as-is.
 *
 * @var array  $notice     Promo entry (banner_src, url, close_color).
 * @var array  $query_args Dismiss-link query args.
 * @var string $prefix     Identity slug from config.php.
 */

defined( 'ABSPATH' ) || exit;

$wrapper_class    = $prefix . '-notice-wrapper';
$image_wrap_class = $prefix . '-image-notice-wrapper';
$banner_class     = $prefix . '-image-banner';
$btn_image_class  = $prefix . '-btn-image';
$close_class      = $prefix . '-content-notice-close';
$close_icon_class = $prefix . '-content-notice-close-icon';
$scope            = '.' . $wrapper_class . '.' . $image_wrap_class;
?>
<style type="text/css">
	<?php echo esc_html( $scope ); ?> {
		padding: 0 !important;
		position: relative;
		box-sizing: border-box;
		overflow: hidden;
		border-radius: 0px;
		border: none !important;
	}
	<?php echo esc_html( $scope ); ?> .<?php echo esc_html( $banner_class ); ?> {
		position: relative;
		line-height: 0;
	}
	<?php echo esc_html( $scope ); ?> .<?php echo esc_html( $btn_image_class ); ?> {
		display: block;
	}
	<?php echo esc_html( $scope ); ?> .<?php echo esc_html( $btn_image_class ); ?> img {
		display: block;
		width: 100%;
		height: auto;
		border-radius: 0;
	}
	<?php echo esc_html( $scope ); ?> .<?php echo esc_html( $close_class ); ?> {
		top: 4px;
		right: 4px;
		position: absolute;
		z-index: 999;
		text-decoration: none;
	}
	<?php echo esc_html( $scope ); ?> .<?php echo esc_html( $close_icon_class ); ?> {
		font-size: 14px;
	}
	@media screen and (max-width: 650px) {
		.<?php echo esc_html( $image_wrap_class ); ?> {
			display: none;
		}
	}
</style>
<div class="<?php echo esc_attr( $wrapper_class . ' ' . $image_wrap_class . ' notice' ); ?>">
	<div class="<?php echo esc_attr( $banner_class ); ?>">
		<a class="<?php echo esc_attr( $close_class ); ?>" href="
		<?php
		echo esc_url(
			add_query_arg(
				$query_args
			)
		);
		?>
		"><span class="<?php echo esc_attr( $close_icon_class ); ?> dashicons dashicons-dismiss" style="color: <?php echo esc_attr( $notice['close_color'] ); ?>;"> </span></a>
		<a class="<?php echo esc_attr( $btn_image_class ); ?>" target="_blank" href="<?php echo esc_url( $notice['url'] ); ?>">
			<img loading="lazy" src="<?php echo esc_url( $notice['banner_src'] ); ?>" alt="Discount Banner"/>
		</a>
	</div>
</div>
