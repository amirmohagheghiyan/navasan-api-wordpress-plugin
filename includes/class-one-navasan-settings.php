<?php
/**
 * Settings storage and sanitization.
 *
 * @package navasan-api-wordpress-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin settings (single option array).
 */
class One_Navasan_Settings {

	const OPTION      = 'one_navasan_settings';
	const DEFAULT_URL = 'https://raw.githubusercontent.com/HosseinOdd/Navasan-API/main/data/gold.json';
	const KEY_MASK    = '••••••••';

	/**
	 * Default values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'source_url'      => self::DEFAULT_URL,
			'api_key'         => '',
			'key_method'      => 'bearer', // bearer|url.
			'base_symbol'     => '18ayar',
			'base_karat'      => 18,
			'interval'        => 30,
			'rate_mode'       => 'auto', // auto|manual.
			'manual_rate'     => '',
			'manual_ts'       => 0,
			'adjust_mode'     => 'none', // none|percent|fixed.
			'adjust_value'    => 0,
			'max_jump_pct'    => 10,
			'stale_hours'     => 6,
			'currency_unit'   => 'auto', // auto|toman|rial.
			'round_step'      => 0,
			'round_dir'       => 'nearest',
			'profit_pct'      => 0,
			'tax_pct'         => 9,
			'wage_pct'        => 0,
			'weight_source'   => 'wc', // wc|attribute.
			'weight_attr'     => '',
			'weight_unit'     => 'g', // g|kg.
			'wage_source'     => 'default', // default|attribute|product.
			'wage_attr'       => '',
			'karat_source'    => 'default', // default|attribute.
			'karat_attr'      => '',
			'default_karat'   => 18,
			'scope'           => 'all', // all|categories|flagged.
			'scope_cats'      => array(),
			'frontend_script' => 1,
		);
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Single setting.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Whether WooCommerce is active.
	 *
	 * @return bool
	 */
	public static function wc_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Capability needed to manage the plugin.
	 *
	 * @return string
	 */
	public static function capability() {
		return self::wc_active() ? 'manage_woocommerce' : 'manage_options';
	}

	/**
	 * Store currency code (IRT when WooCommerce is absent).
	 *
	 * @return string
	 */
	public static function store_currency() {
		return function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'IRT';
	}

	/**
	 * Multiplier Toman -> store unit.
	 *
	 * @return float
	 */
	public static function multiplier() {
		return One_Navasan_Currency::multiplier( self::get( 'currency_unit' ), self::store_currency() );
	}

	/**
	 * Sanitize callback for register_setting().
	 *
	 * @param mixed $input Submitted values.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$old = self::all();
		$out = $old;
		if ( ! is_array( $input ) ) {
			return $out;
		}
		$has = function ( $key ) use ( $input ) {
			return array_key_exists( $key, $input );
		};
		$num = function ( $key, $min, $max ) use ( $input, $has, &$out ) {
			if ( $has( $key ) ) {
				$n = One_Navasan_Number_Parser::parse( is_scalar( $input[ $key ] ) ? (string) $input[ $key ] : '' );
				if ( null !== $n ) {
					$out[ $key ] = max( $min, min( $max, $n ) );
				}
			}
		};
		$choice = function ( $key, array $allowed ) use ( $input, $has, &$out ) {
			if ( $has( $key ) && is_scalar( $input[ $key ] ) && in_array( (string) $input[ $key ], $allowed, true ) ) {
				$out[ $key ] = (string) $input[ $key ];
			}
		};
		$text = function ( $key ) use ( $input, $has, &$out ) {
			if ( $has( $key ) && is_scalar( $input[ $key ] ) ) {
				$out[ $key ] = sanitize_text_field( wp_unslash( (string) $input[ $key ] ) );
			}
		};

		// Source URL: protect the {key} placeholder from esc_url_raw().
		if ( $has( 'source_url' ) && is_scalar( $input['source_url'] ) ) {
			$url = trim( wp_unslash( (string) $input['source_url'] ) );
			$url = str_replace( '{key}', 'ONEKEYPLACEHOLDER', $url );
			$url = esc_url_raw( $url, array( 'http', 'https' ) );
			$url = str_replace( 'ONEKEYPLACEHOLDER', '{key}', $url );
			$out['source_url'] = '' !== $url ? $url : self::DEFAULT_URL;
		}

		// API key: blank or masked value keeps the stored key.
		if ( ! empty( $input['api_key_clear'] ) ) {
			$out['api_key'] = '';
		} elseif ( $has( 'api_key' ) && is_scalar( $input['api_key'] ) ) {
			$key = trim( wp_unslash( (string) $input['api_key'] ) );
			if ( '' !== $key && self::KEY_MASK !== $key ) {
				$out['api_key'] = sanitize_text_field( $key );
			}
		}
		$choice( 'key_method', array( 'bearer', 'url' ) );

		$text( 'base_symbol' );
		if ( '' === $out['base_symbol'] ) {
			$out['base_symbol'] = '18ayar';
		}
		$num( 'base_karat', 1, 24 );
		$num( 'interval', 5, 1440 );
		$out['interval'] = (int) $out['interval'];

		$choice( 'rate_mode', array( 'auto', 'manual' ) );
		if ( $has( 'manual_rate' ) ) {
			$manual = One_Navasan_Number_Parser::parse_positive( is_scalar( $input['manual_rate'] ) ? (string) $input['manual_rate'] : '' );
			$out['manual_rate'] = null === $manual ? '' : $manual;
			if ( (string) $out['manual_rate'] !== (string) $old['manual_rate'] ) {
				$out['manual_ts'] = time();
			}
		}
		$choice( 'adjust_mode', array( 'none', 'percent', 'fixed' ) );
		$num( 'adjust_value', -1000000000, 1000000000 );

		$num( 'max_jump_pct', 0, 1000 );
		$num( 'stale_hours', 0, 8760 );
		$choice( 'currency_unit', array( 'auto', 'toman', 'rial' ) );
		$num( 'round_step', 0, 1000000000 );
		$choice( 'round_dir', array( 'nearest', 'up', 'down' ) );

		$num( 'profit_pct', 0, 1000 );
		$num( 'tax_pct', 0, 100 );
		$num( 'wage_pct', 0, 1000 );
		$choice( 'weight_source', array( 'wc', 'attribute' ) );
		$text( 'weight_attr' );
		$choice( 'weight_unit', array( 'g', 'kg' ) );
		$choice( 'wage_source', array( 'default', 'attribute', 'product' ) );
		$text( 'wage_attr' );
		$choice( 'karat_source', array( 'default', 'attribute' ) );
		$text( 'karat_attr' );
		$num( 'default_karat', 1, 24 );
		$choice( 'scope', array( 'all', 'categories', 'flagged' ) );
		if ( $has( 'scope' ) ) {
			$cats = ( isset( $input['scope_cats'] ) && is_array( $input['scope_cats'] ) ) ? array_values( array_filter( array_map( 'absint', $input['scope_cats'] ) ) ) : array();
			$out['scope_cats'] = $cats;
		}
		if ( $has( 'frontend_script' ) ) {
			$out['frontend_script'] = empty( $input['frontend_script'] ) ? 0 : 1;
		}

		return $out;
	}
}
