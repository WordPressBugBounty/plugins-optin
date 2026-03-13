<?php // phpcs:ignore

namespace OPTN\Frontend\Blocks;

use OPTN\Includes\Abstracts\BaseBlock;
use OPTN\Includes\Utils\BlockUtils;

/**
	Social Block
 */
class SocialBlock extends BaseBlock {

	/**
	 * Get block name
	 *
	 * @return string
	 */
	protected function get_block_name() {
		return 'social';
	}

	/**
	 * V1
	 *
	 * @return string
	 */
	protected function v1() {
		$attr = $this->data->attributes;

		BlockUtils::add_google_font( $attr->typo->fontFamily, array( $attr->typo->fontWeight ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		$html = '<div ' . BlockUtils::build_html_attrs( $this->get_common_html_attr_array() ) . ' >';

		$html .= '<div class="optn-block-social-content" >';

		foreach ( $attr->socials as $social ) {
			$html .= $this->get_social_item(
				$social->type,
				$attr->labelEnabled && $social->labelEnabled ? // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$social->label : null,
				'follow' === $attr->type ? $social->url : $this->generate_share_link( $social->type, $social->url ),
				$social->type
			);
		}

		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Social media links with icon
	 *
	 * @param string $icon icon.
	 * @param string $label label.
	 * @param string $url url.
	 * @param string $type type.
	 * @return string
	 */
	private function get_social_item( $icon, $label, $url, $type ) {
		$html = '<a data-optn-social-platform="' . esc_attr( $type ) . '" class="optn-block-social-content-item" href="' . esc_url( $url ) . '" >';

		$html .= file_get_contents( OPTN_DIR . '/assets/images/social-icons/' . $icon . '.svg' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( ! empty( $label ) ) {
			$html .= '<span class="optn-block-social-content-item-label">';
			$html .= esc_html( $label );
			$html .= '</span>';
		}

		$html .= '</a>';

		return $html;
	}

	/**
	 * Generate share link
	 *
	 * @param string $platform social media platform name.
	 * @param string $url url.
	 * @return string
	 */
	private function generate_share_link( $platform, $url ) {
		$encoded_url = rawurlencode( sanitize_url( $url ) );

		switch ( $platform ) {
			case 'facebook':
				return "https://www.facebook.com/sharer/sharer.php?u=$encoded_url";
			case 'instagram':
				return "https://www.instagram.com/?url=$encoded_url";
			case 'linkedin':
				return "https://www.linkedin.com/shareArticle?mini=true&url=$encoded_url";
			case 'discord':
				return "https://discord.com/channels/@me?url=$encoded_url";
			case 'x':
				return "https://twitter.com/intent/tweet?url=$encoded_url";
			case 'whatsapp':
				return "https://api.whatsapp.com/send?text=$encoded_url";
			case 'telegram':
				return "https://t.me/share/url?url=$encoded_url";
			case 'bluesky':
				return "https://bsky.app/intent/compose?text=$encoded_url";
			case 'threads':
				return "https://www.threads.net/share?url=$encoded_url";
			case 'youtube':
				return "https://www.youtube.com/share?url=$encoded_url";
			case 'tiktok':
				return "https://www.tiktok.com/share?url=$encoded_url";
			case 'twitch':
				return "https://www.twitch.tv/share?url=$encoded_url";
			case 'reddit':
				return "https://www.reddit.com/submit?url=$encoded_url";
			case 'tumblr':
				return "https://www.tumblr.com/widgets/share/tool?canonicalUrl=$encoded_url";
			case 'snapchat':
				return "https://www.snapchat.com/scan?attachmentUrl=$encoded_url";
			default:
				return '';
		}
	}
}
