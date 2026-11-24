<?php
/**
 * Provider: Navasan-API.
 *
 * @package navasan-api-wordpress-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Navasan-API (github.com/HosseinOdd/Navasan-API): one JSON file in the repository, values in Toman.
 * Fallback paths (GitHub Contents API and jsDelivr) belong to this provider only and are used
 * only with the default source URL.
 */
class One_Navasan_Provider_Navasan implements One_Navasan_Provider {

	const CONTENTS_API   = 'https://api.github.com/repos/HosseinOdd/Navasan-API/contents/data/gold.json';
	const JSDELIVR       = 'https://cdn.jsdelivr.net/gh/HosseinOdd/Navasan-API@main/data/gold.json';
	const DEFAULT_SYMBOL = '18ayar';

	/**
	 * {@inheritdoc}
	 */
	public function id() {
		return 'navasan';
	}

	/**
	 * {@inheritdoc}
	 */
	public function label() {
		return __( 'Navasan-API', ONE_NAVASAN_DOMAIN );
	}

	/**
	 * {@inheritdoc}
	 */
	public function base_symbol( array $settings ) {
		$symbol = isset( $settings['base_symbol'] ) ? (string) $settings['base_symbol'] : '';
		return '' !== $symbol ? $symbol : self::DEFAULT_SYMBOL;
	}

	/**
	 * {@inheritdoc}
	 */
	public function endpoints( array $settings ) {
		$key        = (string) $settings['api_key'];
		$url        = (string) $settings['source_url'];
		$headers    = array( 'Accept' => 'application/json' );
		$is_default = ( One_Navasan_Settings::DEFAULT_URL === $url );

		$bearer = ( '' !== $key && 'bearer' === $settings['key_method'] ) ? 'Bearer ' . $key : '';
		if ( '' !== $key && 'url' === $settings['key_method'] ) {
			$url = str_replace( '{key}', rawurlencode( $key ), $url );
		}

		$primary_headers = $headers;
		if ( $bearer ) {
			$primary_headers['Authorization'] = $bearer;
		}
		$list = array(
			array(
				'id'      => 'primary',
				'label'   => $is_default ? __( 'آدرس اصلی (raw.githubusercontent.com)', ONE_NAVASAN_DOMAIN ) : __( 'آدرس منبع تنظیم‌شده', ONE_NAVASAN_DOMAIN ),
				'url'     => $url,
				'headers' => $primary_headers,
				'cdn'     => false,
			),
		);
		if ( $is_default ) {
			$api_headers = array( 'Accept' => 'application/vnd.github.raw+json' );
			if ( $bearer ) {
				// The key is a GitHub token here; it is only ever sent to GitHub hosts.
				$api_headers['Authorization'] = $bearer;
			}
			$list[] = array(
				'id'      => 'contents_api',
				'label'   => __( 'GitHub Contents API', ONE_NAVASAN_DOMAIN ),
				'url'     => self::CONTENTS_API,
				'headers' => $api_headers,
				'cdn'     => false,
			);
			$list[] = array(
				'id'      => 'jsdelivr',
				'label'   => __( 'jsDelivr (کش چندساعته)', ONE_NAVASAN_DOMAIN ),
				'url'     => self::JSDELIVR,
				'headers' => $headers,
				'cdn'     => true,
			);
		}
		return $list;
	}

	/**
	 * {@inheritdoc}
	 */
	public function parse( array $endpoint, $body, array $settings ) {
		$parsed = One_Navasan_Source_Validator::parse_json( $body );
		if ( ! $parsed['ok'] ) {
			return array( 'ok' => false, 'error' => $parsed['error'], 'symbols' => array() );
		}
		$base  = $this->base_symbol( $settings );
		$entry = One_Navasan_Source_Validator::get_entry( $parsed['data'], $base );
		if ( ! $entry['ok'] ) {
			return array( 'ok' => false, 'error' => $entry['error'] . ' (' . $base . ')', 'symbols' => array() );
		}
		$symbols = array();
		foreach ( $parsed['data'] as $symbol => $e ) {
			$e['high']         = null;
			$e['low']          = null;
			$symbols[ $symbol ] = $e;
		}
		return array( 'ok' => true, 'error' => '', 'symbols' => $symbols );
	}

	/**
	 * {@inheritdoc}
	 */
	public function supplement( array $symbols, array $settings ) {
		return $symbols;
	}
}
