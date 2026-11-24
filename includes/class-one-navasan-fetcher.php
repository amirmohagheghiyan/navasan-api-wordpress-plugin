<?php
/**
 * HTTP access to the source.
 *
 * @package navasan-api-wordpress-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fetches the JSON from the configured URL and, for the default Navasan-API source only,
 * falls back to GitHub Contents API and jsDelivr (both read the same repository file).
 */
class One_Navasan_Fetcher {

	const CONTENTS_API = 'https://api.github.com/repos/HosseinOdd/Navasan-API/contents/data/gold.json';
	const JSDELIVR     = 'https://cdn.jsdelivr.net/gh/HosseinOdd/Navasan-API@main/data/gold.json';

	/**
	 * Build the ordered list of sources.
	 *
	 * @return array[] Each: id, label, url, headers, cdn.
	 */
	public static function sources() {
		$s       = One_Navasan_Settings::all();
		$key     = (string) $s['api_key'];
		$url     = (string) $s['source_url'];
		$headers = array( 'Accept' => 'application/json' );
		$is_default = ( One_Navasan_Settings::DEFAULT_URL === $url );

		$bearer = ( '' !== $key && 'bearer' === $s['key_method'] ) ? 'Bearer ' . $key : '';
		if ( '' !== $key && 'url' === $s['key_method'] ) {
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
	 * Perform one request.
	 *
	 * @param array $source Source definition.
	 * @return array {ok, http, ms, error, body}
	 */
	public static function request( array $source ) {
		$start    = microtime( true );
		$response = wp_remote_get(
			$source['url'],
			array(
				'timeout'             => 10,
				'redirection'         => 3,
				'user-agent'          => 'One-Navasan-WordPress/' . ONE_NAVASAN_VERSION,
				'headers'             => $source['headers'],
				'limit_response_size' => 2 * MB_IN_BYTES,
			)
		);
		$ms = (int) round( ( microtime( true ) - $start ) * 1000 );
		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'http' => 0, 'ms' => $ms, 'error' => One_Navasan_Logger::redact( $response->get_error_message() ), 'body' => '' );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return array( 'ok' => false, 'http' => $code, 'ms' => $ms, 'error' => 'HTTP ' . $code, 'body' => '' );
		}
		return array( 'ok' => true, 'http' => $code, 'ms' => $ms, 'error' => '', 'body' => (string) wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Fetch + validate one source for the base symbol.
	 *
	 * @param array  $source Source.
	 * @param string $symbol Base symbol.
	 * @return array {ok, http, ms, error, data, entry}
	 */
	public static function fetch_source( array $source, $symbol ) {
		$r = self::request( $source );
		$r['data']  = array();
		$r['entry'] = null;
		if ( ! $r['ok'] ) {
			return $r;
		}
		$parsed = One_Navasan_Source_Validator::parse_json( $r['body'] );
		if ( ! $parsed['ok'] ) {
			$r['ok']    = false;
			$r['error'] = $parsed['error'];
			return $r;
		}
		$entry = One_Navasan_Source_Validator::get_entry( $parsed['data'], $symbol );
		if ( ! $entry['ok'] ) {
			$r['ok']    = false;
			$r['error'] = $entry['error'] . ' (' . $symbol . ')';
			return $r;
		}
		$r['data']  = $parsed['data'];
		$r['entry'] = $entry['entry'];
		unset( $r['body'] );
		return $r;
	}

	/**
	 * Fetch from the first source that works.
	 *
	 * @param string   $symbol         Base symbol.
	 * @param int|null $stored_data_ts Data timestamp of the stored rate (to reject older CDN copies).
	 * @return array {ok, data, source, error, attempts[]}
	 */
	public static function fetch( $symbol, $stored_data_ts = null ) {
		$attempts = array();
		$last     = '';
		foreach ( self::sources() as $source ) {
			$r = self::fetch_source( $source, $symbol );
			if ( $r['ok'] && $source['cdn'] && null !== $stored_data_ts && null !== $r['entry']['date'] && $r['entry']['date'] < (int) $stored_data_ts ) {
				$r['ok']    = false;
				$r['error'] = 'older_than_stored';
			}
			$attempts[] = array( 'id' => $source['id'], 'label' => $source['label'], 'ok' => $r['ok'], 'http' => $r['http'], 'error' => $r['error'] );
			if ( $r['ok'] ) {
				return array( 'ok' => true, 'data' => $r['data'], 'source' => $source['id'], 'error' => '', 'attempts' => $attempts );
			}
			$last = $source['id'] . ': ' . $r['error'];
		}
		return array( 'ok' => false, 'data' => array(), 'source' => '', 'error' => $last, 'attempts' => $attempts );
	}

	/**
	 * Test every source separately (for the settings page).
	 *
	 * @return array[]
	 */
	public static function test_all() {
		$symbol = (string) One_Navasan_Settings::get( 'base_symbol' );
		$rows   = array();
		foreach ( self::sources() as $source ) {
			$r      = self::fetch_source( $source, $symbol );
			$rows[] = array(
				'label' => $source['label'],
				'url'   => One_Navasan_Logger::redact( self::display_url( $source['url'] ) ),
				'ok'    => $r['ok'],
				'http'  => $r['http'],
				'ms'    => $r['ms'],
				'error' => $r['error'],
				'value' => $r['ok'] ? $r['entry']['value'] : null,
				'date'  => $r['ok'] ? $r['entry']['date'] : null,
			);
		}
		return $rows;
	}

	/**
	 * URL without query string (never show query-string keys).
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private static function display_url( $url ) {
		$pos = strpos( $url, '?' );
		return false === $pos ? $url : substr( $url, 0, $pos ) . '?…';
	}
}
