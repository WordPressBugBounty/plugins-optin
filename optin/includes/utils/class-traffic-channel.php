<?php

namespace OPTN\Includes\Utils;

class TrafficChannel {

	private static $organic_sources = array(
		'www.google'             => array( 'q=' ),
		'daum.net/'              => array( 'q=' ),
		'eniro.se/'              => array( 'search_word=', 'hitta:' ),
		'naver.com/'             => array( 'query=' ),
		'yahoo.com/'             => array( 'p=' ),
		'msn.com/'               => array( 'q=' ),
		'bing.com/'              => array( 'q=' ),
		'aol.com/'               => array( 'query=', 'encquery=' ),
		'lycos.com/'             => array( 'query=' ),
		'ask.com/'               => array( 'q=' ),
		'altavista.com/'         => array( 'q=' ),
		'search.netscape.com/'   => array( 'query=' ),
		'cnn.com/SEARCH/'        => array( 'query=' ),
		'about.com/'             => array( 'terms=' ),
		'mamma.com/'             => array( 'query=' ),
		'alltheweb.com/'         => array( 'q=' ),
		'voila.fr/'              => array( 'rdata=' ),
		'search.virgilio.it/'    => array( 'qs=' ),
		'baidu.com/'             => array( 'wd=' ),
		'alice.com/'             => array( 'qs=' ),
		'yandex.com/'            => array( 'text=' ),
		'najdi.org.mk/'          => array( 'q=' ),
		'aol.com/'               => array( 'q=' ),
		'mamma.com/'             => array( 'query=' ),
		'seznam.cz/'             => array( 'q=' ),
		'search.com/'            => array( 'q=' ),
		'wp.pl/'                 => array( 'szukai=' ),
		'online.onetcenter.org/' => array( 'qt=' ),
		'szukacz.pl/'            => array( 'q=' ),
		'yam.com/'               => array( 'k=' ),
		'pchome.com/'            => array( 'q=' ),
		'kvasir.no/'             => array( 'q=' ),
		'sesam.no/'              => array( 'q=' ),
		'ozu.es/'                => array( 'q=' ),
		'terra.com/'             => array( 'query=' ),
		'mynet.com/'             => array( 'q=' ),
		'ekolay.net/'            => array( 'q=' ),
		'rambler.ru/'            => array( 'words=' ),
	);

	private static $social_media_domains = array(
		'facebook.com',
		'twitter.com',
		'instagram.com',
		'linkedin.com',
		'tiktok.com',
		'pinterest.com',
		'snapchat.com',
		'reddit.com',
		'youtube.com',
	);

	private static function is_direct_traffic() {
		if ( ! isset( $_SERVER['HTTP_REFERER'] ) || empty( $_SERVER['HTTP_REFERER'] ) ) {
			return true;
		}
		return false;
	}

	private static function is_social_media_traffic() {
		if ( empty( $_SERVER['HTTP_REFERER'] ) ) {
			return false;
		}

		$referrer = sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) );

		foreach ( self::$social_media_domains as $domain ) {
			if ( strpos( $referrer, $domain ) !== false ) {
				return true;
			}
		}
			return false;
	}

	private static function is_paid_search_traffic() {
		if ( isset( $_GET['utm_medium'] ) && 'cpc' === strtolower( sanitize_text_field( wp_unslash( $_GET['utm_medium'] ) )  ) ) { // phpcs:ignore
			return true;
		}

		if ( isset( $_GET['gclid'] ) ) { // phpcs:ignore
			return true;
		}

		if ( isset( $_GET['msclkid'] ) ) { // phpcs:ignore
			return true;
		}

		return false;
	}


	/**
	 * Check if source is organic
	 *
	 * @return boolean
	 */
	private static function is_organic_search_traffic() {
		if ( empty( $_SERVER['HTTP_REFERER'] ) ) {
			return false;
		}

		foreach ( self::$organic_sources as $searchEngine => $queries ) {
			if ( strpos( sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ), $searchEngine ) !== false ) { // phpcs:ignore
				foreach ( $queries as $query ) {
					if ( strpos( sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ), $query ) !== false ) { // phpcs:ignore
						return true;
					}
				}
			}
		}

		return false;
	}

	public static function get_traffic_channels() {
		$channels = array();

		if ( self::is_direct_traffic() ) {
			$channels[] = 'direct';
		}

		if ( self::is_social_media_traffic() ) {
			$channels[] = 'social';
		}

		if ( self::is_paid_search_traffic() ) {
			$channels[] = 'paid';
		}

		if ( self::is_organic_search_traffic() ) {
			$channels[] = 'org';
		}

		return $channels;
	}
}
