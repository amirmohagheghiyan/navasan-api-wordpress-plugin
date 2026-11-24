<?php
/**
 * Provider: priceto.day.
 *
 * @package navasan-api-wordpress-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * priceto.day (github.com/margani/pricedb), market data from tgju.org. Quotes are in Rial.
 */
class One_Navasan_Provider_Priceto implements One_Navasan_Provider {

	const API              = 'https://api.priceto.day/v1';
	const DEFAULT_SYMBOL   = 'gold-18k';
	const SYMBOLS_TRANSIENT = 'one_navasan_priceto_symbols';

	/**
	 * Symbols known from the API docs; used until the live list is loaded.
	 *
	 * @return string[]
	 */
	public static function known_symbols() {
		return array( 'gold-18k', 'gold-24k', 'gold-miskal', 'coin-emami', 'coin-baharazadi', 'coin-baharazadi-nim', 'coin-baharazadi-rob', 'coin-gerami', 'usd', 'euro', 'gbp', 'aed', 'try' );
	}

	/**
	 * Symbol list for the settings page (cached live list or the known one).
	 *
	 * @param bool $refresh Fetch from /v1/symbols now.
	 * @return string[]
	 */
	public static function symbols( $refresh = false ) {
		if ( ! $refresh ) {
			$cached = get_transient( self::SYMBOLS_TRANSIENT );
			if ( is_array( $cached ) && $cached ) {
				return $cached;
			}
			return self::known_symbols();
		}
		$r = One_Navasan_Fetcher::request(
			array(
				'id'      => 'symbols',
				'url'     => self::API . '/symbols',
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);
		if ( $r['ok'] ) {
			$list = One_Navasan_Priceto_Parser::parse_symbols( $r['body'] );
			if ( $list ) {
				set_transient( self::SYMBOLS_TRANSIENT, $list, DAY_IN_SECONDS );
				return $list;
			}
		}
		return self::symbols( false );
	}

	/**
	 * {@inheritdoc}
	 */
	public function id() {
		return 'priceto';
	}

	/**
	 * {@inheritdoc}
	 */
	public function label() {
		return __( 'priceto.day', ONE_NAVASAN_DOMAIN );
	}

	/**
	 * {@inheritdoc}
	 */
	public function base_symbol( array $settings ) {
		$symbol = isset( $settings['base_symbol_priceto'] ) ? (string) $settings['base_symbol_priceto'] : '';
		return '' !== $symbol ? $symbol : self::DEFAULT_SYMBOL;
	}

	/**
	 * {@inheritdoc}
	 */
	public function endpoints( array $settings ) {
		return array(
			array(
				'id'      => 'latest',
				'label'   => __( 'priceto.day — آخرین قیمت', ONE_NAVASAN_DOMAIN ),
				'url'     => self::API . '/latest/irr/' . rawurlencode( $this->base_symbol( $settings ) ),
				'headers' => array( 'Accept' => 'application/json' ),
				'cdn'     => false,
			),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function parse( array $endpoint, $body, array $settings ) {
		$parsed = One_Navasan_Priceto_Parser::parse_latest( $body );
		if ( ! $parsed['ok'] ) {
			return array( 'ok' => false, 'error' => $parsed['error'], 'symbols' => array() );
		}
		return array(
			'ok'      => true,
			'error'   => '',
			'symbols' => array( $this->base_symbol( $settings ) => $parsed['entry'] ),
		);
	}

	/**
	 * Day change (a second request, optional and cheap; never fails the fetch).
	 *
	 * @param array $symbols  Parsed symbols.
	 * @param array $settings Plugin settings.
	 * @return array
	 */
	public function supplement( array $symbols, array $settings ) {
		if ( empty( $settings['priceto_fetch_change'] ) ) {
			return $symbols;
		}
		$base = $this->base_symbol( $settings );
		if ( ! isset( $symbols[ $base ] ) ) {
			return $symbols;
		}
		$endpoint = array(
			'id'      => 'change',
			'url'     => self::API . '/change/irr/' . rawurlencode( $base ),
			'headers' => array( 'Accept' => 'application/json' ),
		);
		if ( One_Navasan_Fetcher::is_cooling_down( $endpoint ) ) {
			return $symbols;
		}
		$r = One_Navasan_Fetcher::request( $endpoint );
		if ( ! $r['ok'] ) {
			return $symbols;
		}
		$change = One_Navasan_Priceto_Parser::parse_change( $r['body'] );
		if ( $change['ok'] ) {
			$symbols[ $base ]['change_val'] = $change['change_val'];
			$symbols[ $base ]['change_pct'] = $change['change_pct'];
		}
		return $symbols;
	}
}
