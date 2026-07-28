<?php
/**
 * LAYOUT: icon, heading + subheading, CTA button. No artwork, so every pixel
 * is markup and follows $brand_color.
 *
 * Self-contained: markup and CSS in this one file, every class built from
 * $prefix so a sibling plugin can copy it as-is.
 *
 * The logo vs discount-SVG flavors are the same template — only `icon` and
 * `is_discount_logo` differ.
 *
 * @var array  $notice      Promo entry (border_color, icon, is_discount_logo,
 *                          content_heading, content_subheading, discount_content,
 *                          button_text, url, background_color).
 * @var array  $query_args  Dismiss-link query args.
 * @var string $prefix      Identity slug from config.php.
 * @var string $brand_color Accent color from config.php.
 */

defined( 'ABSPATH' ) || exit;

$border_color = $notice['border_color'];
$url          = $notice['url'];

// Faint wash of the brand color behind the notice. Derived, not a literal, so
// it follows config.php instead of staying green in a copied plugin.
$tint = sscanf( ltrim( $brand_color, '#' ), '%2x%2x%2x' );
$tint = $tint ? sprintf( 'rgba(%d, %d, %d, 0.04)', $tint[0], $tint[1], $tint[2] ) : 'transparent';

$style_id            = $prefix . '-notice-css';
$wrapper_class       = $prefix . '-content-notice-wrapper';
$icon_class          = $prefix . '-content-notice-icon';
$discount_icon_class = $prefix . '-content-notice-discout-icon';
$content_wrap_class  = $prefix . '-notice-content-wrapper';
$buttons_class       = $prefix . '-content-notice-buttons';
$btn_class           = $prefix . '-content-notice-btn';
$discount_btn_class  = $prefix . '-content-discount_btn';
$close_class         = $prefix . '-content-notice-close';
$close_icon_class    = $prefix . '-content-notice-close-icon';
?>
<style id="<?php echo esc_attr( $style_id ); ?>" type="text/css">
	.<?php echo esc_html( $wrapper_class ); ?> {
		border: 1px solid #c3c4c7;
		border-left: 3px solid <?php echo esc_attr( $brand_color ); ?>;
		margin: 15px 0 !important;
		display: flex;
		align-items: center;
		background: <?php echo esc_attr( $tint ); ?>;
		width: 100%;
		padding: 10px 0;
		position: relative;
		box-sizing: border-box;
	}

	.<?php echo esc_html( $wrapper_class ); ?>.notice {
		margin: 10px 0;
		width: calc(100% - 20px);
	}

	.wrap .<?php echo esc_html( $wrapper_class ); ?>.notice {
		width: 100%;
	}

	.<?php echo esc_html( $icon_class ); ?> {
		margin-left: 15px;
	}

	.<?php echo esc_html( $discount_icon_class ); ?> {
		margin-left: 10px;
	}

	.<?php echo esc_html( $icon_class ); ?> img {
		max-width: 42px;
		height: 70px;
	}

	.<?php echo esc_html( $discount_icon_class ); ?> img {
		height: 70px;
		width: 70px;
	}

	.<?php echo esc_html( $content_wrap_class ); ?> {
		display: flex;
		flex-direction: column;
		gap: 8px;
		font-size: 14px;
		line-height: 20px;
		margin-left: 15px;
	}

	.<?php echo esc_html( $buttons_class ); ?> {
		display: flex;
		align-items: center;
		gap: 15px;
	}

	.<?php echo esc_html( $btn_class ); ?> {
		font-weight: 600;
		text-transform: uppercase !important;
		padding: 2px 10px !important;
		background-color: <?php echo esc_attr( $brand_color ); ?>;
		border: none !important;
	}

	.<?php echo esc_html( $discount_btn_class ); ?> {
		background-color: #ffffff;
		text-decoration: none;
		border: 1px solid <?php echo esc_attr( $brand_color ); ?>;
		padding: 5px 10px;
		border-radius: 5px;
		font-weight: 500;
		text-transform: uppercase;
		color: <?php echo esc_attr( $brand_color ); ?> !important;
	}

	.<?php echo esc_html( $close_class ); ?> {
		position: absolute;
		right: 2px;
		top: 5px;
		text-decoration: none;
		color: #b6b6b6;
		font-family: dashicons;
		font-size: 16px;
		line-height: 20px;
	}

	.<?php echo esc_html( $close_icon_class ); ?> {
		font-size: 14px;
	}
</style>
<div class="<?php echo esc_attr( $wrapper_class ); ?> notice"
	style="border-left: 3px solid <?php echo esc_attr( $border_color ); ?>;"
>
	<?php
	if ( ! empty( $notice['is_discount_logo'] ) ) {
		?>
			<div class="<?php echo esc_attr( $discount_icon_class ); ?>"> <img src="<?php echo esc_url( $notice['icon'] ); ?>"/>  </div>
		<?php
	} else {
		?>
			<div class="<?php echo esc_attr( $icon_class ); ?>"> <img src="<?php echo esc_url( $notice['icon'] ); ?>"/>  </div>
		<?php
	}
	?>

	<div class="<?php echo esc_attr( $content_wrap_class ); ?>">
		<div class="">
			<?php if ( ! empty( $notice['content_heading'] ) ) : ?>
				<strong><?php echo esc_html( $notice['content_heading'] ); ?> </strong>
			<?php endif; ?>
	<?php
	printf(
		wp_kses_post( $notice['content_subheading'] ),
		'<strong>' . esc_html( $notice['discount_content'] ) . '</strong>'
	);
	?>
		</div>
		<div class="<?php echo esc_attr( $buttons_class ); ?>">
		<?php if ( isset( $notice['is_discount_logo'] ) && $notice['is_discount_logo'] ) : ?>
				<a class="<?php echo esc_attr( $discount_btn_class ); ?>" href="<?php echo esc_url( $url ); ?>" target="_blank">
					<?php echo esc_html( $notice['button_text'] ); ?>
				</a>
			<?php else : ?>
				<a class="<?php echo esc_attr( $btn_class ); ?> button button-primary" href="<?php echo esc_url( $url ); ?>" target="_blank" style="background-color: <?php echo esc_attr( ! empty( $notice['background_color'] ) ? $notice['background_color'] : $brand_color ); ?>;">
				<?php echo esc_html( $notice['button_text'] ); ?>

				</a>
			<?php endif; ?>
		</div>
	</div>
	<a href=
		<?php
		echo esc_url(
			add_query_arg(
				$query_args
			)
		);
		?>
	class="<?php echo esc_attr( $close_class ); ?>"><span class="<?php echo esc_attr( $close_icon_class ); ?> dashicons dashicons-dismiss"> </span></a>
</div>
