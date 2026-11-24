<?php
/**
 * HTTP access to the providers.
 *
 * @package navasan-api-wordpress-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Requests, per-path cooldown and provider fetching. The only hosts contacted are those of the
 * two providers: api.priceto.day, and for Navasan-API the configured source URL (default
 * raw.githubusercontent.com), api.github.com and cdn.jsdelivr.net.
 */
class One_Navasan_Fetcher {

	/**
	 * Per-request timeout in seconds.
	 */
	const TIMEOUT = 6;

	/**
	 * A path that failed with a network error or a 5xx is skipped for this long.
	 */
	const COOLDOWN = 1800;

	/**
	 * Provider instance by id.
	 *
	 * @param string $id priceto|navasan.
	 * @return One_Navasan_Provider|null
	 */
	public static function provider( $id ) {
		if ( 'priceto' === $id ) {
			return new One_Navasan_Provider_Priceto();
		}
		if ( 'navasan' === $id ) {
			return new One_Navasan_Provider_Navasan();
		}
		return null;
	}

	/**
	 * User agent with plugin name and version.
	 *
	 * @return string
	 */
	public static function user_agent() {
		return 'One-Navasan-WordPress/' . ONE_NAVASAN_VERSION . ' (+https://github.com/amirmohagheghiyan/navasan-api-wordpress-plugin)';
	}

	/**
	 * Transient key of an endpoint's cooldown.
	 *
	 * @param array $endpoint Endpoint.
	 * @return string
	 */
	private static function cooldown_key( array $endpoint ) {
		return 'one_navasan_cd_' . md5( $endpoint['url'] );
	}

	/**
	 * Is the endpoint cooling down after a recent failure?
	 *
	 * @param array $endpoint Endpoint.
	 * @return bool
	 */
	public static function is_cooling_down( array $endpoint ) {
		return false !== get_transient( self::cooldown_key( $endpoint ) );
	}

	/**
	 * Perform one request (updates the endpoint's cooldown).
	 *
	 * @param array $endpoint Endpoint definition (url, headers).
	 * @return array {ok, http, ms, error, body}
	 */
	public static function request( array $endpoint ) {
		$start    = microtime( true );
		$response = wp_remote_get(
			$endpoint['url'],
			array(
				'timeout'             => self::TIMEOUT,
				'redirection'         => 3,
				'user-agent'          => self::user_agent(),
				'headers'             => isset( $endpoint['headers'] ) ? $endpoint['headers'] : array(),
				'limit_response_size' => 2 * MB_IN_BYTES,
			)
		);
		$ms  = (int) round( ( microtime( true ) - $start ) * 1000 );
		$key = self::cooldown_key( $endpoint );
		if ( is_wp_error( $response ) ) {
			set_transient( $key, 1, self::COOLDOWN );
			return array( 'ok' => false, 'http' => 0, 'ms' => $ms, 'error' => One_Navasan_Logger::redact( $response->get_error_message() ), 'body' => '' );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			if ( $code >= 500 || 429 === $code ) {
				set_transient( $key, 1, self::COOLDOWN );
			}
			return array( 'ok' => false, 'http' => $code, 'ms' => $ms, 'error' => 'HTTP ' . $code, 'body' => '' );
		}
		delete_transient( $key );
		return array( 'ok' => true, 'http' => $code, 'ms' => $ms, 'error' => '', 'body' => (string) wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Fetch + validate one endpoint.
	 *
	 * @param One_Navasan_Provider $provider Provider.
	 * @param array                $endpoint Endpoint.
	 * @param array                $settings Settings.
	 * @return array {ok, http, ms, error, symbols, entry}
	 */
	public static function fetch_endpoint( One_Navasan_Provider $provider, array $endpoint, array $settings ) {
		$r            = self::request( $endpoint );
		$r['symbols'] = array();
		$r['entry']   = null;
		if ( ! $r['ok'] ) {
			return $r;
		}
		$parsed = $provider->parse( $endpoint, $r['body'], $settings );
		unset( $r['body'] );
		if ( ! $parsed['ok'] ) {
			$r['ok']    = false;
			$r['error'] = $parsed['error'];
			return $r;
		}
		$r['symbols'] = $parsed['symbols'];
		$r['entry']   = $parsed['symbols'][ $provider->base_symbol( $settings ) ];
		return $r;
	}

	/**
	 * Fetch from the first working endpoint of a provider.
	 *
	 * Endpoints in cooldown are skipped, unless every endpoint is in cooldown (then all are tried).
	 *
	 * @param One_Navasan_Provider $provider  Provider.
	 * @param int|null             $stored_ts Data time of the stored rate (rejects older CDN copies).
	 * @return array {ok, symbols, endpoint, error, attempts[]}
	 */
	public static function fetch_provider( One_Navasan_Provider $provider, $stored_ts = null ) {
		$settings  = One_Navasan_Settings::all();
		$endpoints = $provider->endpoints( $settings );
		$ready     = array_values( array_filter( $endpoints, function ( $e ) {
			return ! self::is_cooling_down( $e );
		} ) );
		if ( ! $ready ) {
			$ready = $endpoints;
		}
		$attempts = array();
		$last     = '';
		foreach ( $endpoints as $endpoint ) {
			if ( ! in_array( $endpoint, $ready, true ) ) {
				$attempts[] = array( 'id' => $endpoint['id'], 'label' => $endpoint['label'], 'ok' => false, 'http' => 0, 'error' => 'cooldown' );
				continue;
			}
			$r = self::fetch_endpoint( $provider, $endpoint, $settings );
			if ( $r['ok'] && ! empty( $endpoint['cdn'] ) && null !== $stored_ts && null !== $r['entry']['date'] && $r['entry']['date'] < (int) $stored_ts ) {
				$r['ok']    = false;
				$r['error'] = 'older_than_stored';
			}
			$attempts[] = array( 'id' => $endpoint['id'], 'label' => $endpoint['label'], 'ok' => $r['ok'], 'http' => $r['http'], 'error' => $r['error'] );
			if ( $r['ok'] ) {
				return array(
					'ok'       => true,
					'symbols'  => $provider->supplement( $r['symbols'], $settings ),
					'endpoint' => $endpoint['id'],
					'error'    => '',
					'attempts' => $attempts,
				);
			}
			$last = $provider->id() . '/' . $endpoint['id'] . ': ' . $r['error'];
		}
		return array( 'ok' => false, 'symbols' => array(), 'endpoint' => '', 'error' => $last, 'attempts' => $attempts );
	}

	/**
	 * Test every endpoint of both providers separately (for the settings page). Ignores cooldowns.
	 *
	 * @return array[]
	 */
	public static function test_all() {
		$settings = One_Navasan_Settings::all();
		$rows     = array();
		foreach ( array( 'priceto', 'navasan' ) as $id ) {
			$provider = self::provider( $id );
			foreach ( $provider->endpoints( $settings ) as $endpoint ) {
				$r      = self::fetch_endpoint( $provider, $endpoint, $settings );
				$rows[] = array(
					'provider' => $provider->label(),
					'label'    => $endpoint['label'],
					'url'      => One_Navasan_Logger::redact( self::display_url( $endpoint['url'] ) ),
					'ok'       => $r['ok'],
					'http'     => $r['http'],
					'ms'       => $r['ms'],
					'error'    => $r['error'],
					'value'    => $r['ok'] ? $r['entry']['value'] : null,
					'date'     => $r['ok'] ? $r['entry']['date'] : null,
				);
			}
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
