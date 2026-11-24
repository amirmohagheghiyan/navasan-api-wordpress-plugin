<?php
/**
 * Rate state, guards and refresh pipeline.
 *
 * @package navasan-api-wordpress-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Owns the stored rate data and decides what the "current rate" is.
 */
class One_Navasan_Rate_Service {

	const STATE   = 'one_navasan_state';
	const PENDING = 'one_navasan_pending';
	const DAILY   = 'one_navasan_daily';
	const ERROR   = 'one_navasan_last_error';

	/**
	 * Stored state {symbols, fetched_at, source, base_symbol}.
	 *
	 * @return array
	 */
	public static function state() {
		$state = get_option( self::STATE, array() );
		return is_array( $state ) ? $state : array();
	}

	/**
	 * Raw base-symbol entry from the source.
	 *
	 * @param string|null $symbol Symbol (default base).
	 * @return array|null
	 */
	public static function raw_entry( $symbol = null ) {
		$symbol = null === $symbol ? (string) One_Navasan_Settings::get( 'base_symbol' ) : $symbol;
		$state  = self::state();
		return isset( $state['symbols'][ $symbol ] ) ? $state['symbols'][ $symbol ] : null;
	}

	/**
	 * Timestamp of the data itself (base symbol).
	 *
	 * @return int|null
	 */
	public static function data_ts() {
		$e = self::raw_entry();
		return $e && ! empty( $e['date'] ) ? (int) $e['date'] : null;
	}

	/**
	 * Effective rate of the base symbol in Toman (manual or adjusted automatic).
	 *
	 * @return float|null
	 */
	public static function effective_toman() {
		$s = One_Navasan_Settings::all();
		if ( 'manual' === $s['rate_mode'] ) {
			$manual = One_Navasan_Number_Parser::parse_positive( $s['manual_rate'] );
			$toman  = null === $manual ? null : One_Navasan_Currency::to_toman( $manual, One_Navasan_Settings::multiplier() );
		} else {
			$e     = self::raw_entry();
			$toman = ( $e && null !== $e['value'] && $e['value'] > 0 ) ? One_Navasan_Rate_Adjuster::apply( $e['value'], $s['adjust_mode'], $s['adjust_value'] ) : null;
		}
		if ( null === $toman ) {
			return null;
		}
		/**
		 * Filters the final base rate in Toman.
		 *
		 * @param float $toman Rate in Toman.
		 * @param array $s     Plugin settings.
		 */
		$toman = apply_filters( 'one_navasan_final_rate', $toman, $s );
		return ( is_numeric( $toman ) && $toman > 0 ) ? (float) $toman : null;
	}

	/**
	 * Base rate in the store currency unit (used for pricing).
	 *
	 * @return float|null
	 */
	public static function store_rate() {
		$toman = self::effective_toman();
		return null === $toman ? null : One_Navasan_Currency::from_toman( $toman, One_Navasan_Settings::multiplier() );
	}

	/**
	 * Output for a symbol (shortcode / REST / JS).
	 *
	 * @param string|null $symbol Symbol (default base).
	 * @return array|null Values in Toman.
	 */
	public static function symbol_output( $symbol = null ) {
		$s       = One_Navasan_Settings::all();
		$base    = (string) $s['base_symbol'];
		$symbol  = ( null === $symbol || '' === $symbol ) ? $base : $symbol;
		$is_base = ( $symbol === $base );
		$entry   = self::raw_entry( $symbol );
		$manual  = ( $is_base && 'manual' === $s['rate_mode'] );
		if ( ! $entry && ! $manual ) {
			return null;
		}

		$day   = One_Navasan_Daily_Range::day_key( time() );
		$range = One_Navasan_Daily_Range::get( get_option( self::DAILY, array() ), $day, $symbol );
		$adj   = function ( $v ) use ( $is_base, $s ) {
			return ( $is_base && null !== $v ) ? One_Navasan_Rate_Adjuster::apply( $v, $s['adjust_mode'], $s['adjust_value'] ) : $v;
		};

		if ( $is_base ) {
			$value = self::effective_toman();
			if ( null === $value ) {
				return null;
			}
		} else {
			$value = $entry['value'];
		}
		$state = self::state();
		return array(
			'symbol'     => $symbol,
			'value'      => $value,
			'change_val' => $manual ? 0 : ( $entry ? $entry['change_val'] : null ),
			'change_pct' => $manual ? 0 : ( $entry ? $entry['change_pct'] : null ),
			'high'       => $manual ? $value : ( $range ? $adj( $range['max'] ) : $value ),
			'low'        => $manual ? $value : ( $range ? $adj( $range['min'] ) : $value ),
			'updated'    => $manual ? ( (int) $s['manual_ts'] ?: null ) : ( $entry && $entry['date'] ? (int) $entry['date'] : ( isset( $state['fetched_at'] ) ? (int) $state['fetched_at'] : null ) ),
			'fetched_at' => isset( $state['fetched_at'] ) ? (int) $state['fetched_at'] : null,
			'mode'       => $manual ? 'manual' : 'auto',
		);
	}

	/**
	 * Is the data stale?
	 *
	 * @return bool
	 */
	public static function is_stale() {
		if ( 'manual' === One_Navasan_Settings::get( 'rate_mode' ) ) {
			return false;
		}
		return One_Navasan_Guard::is_stale( self::data_ts(), time(), (float) One_Navasan_Settings::get( 'stale_hours' ) );
	}

	/**
	 * Pending (blocked by the jump guard) rate or null.
	 *
	 * @return array|null
	 */
	public static function pending() {
		$p = get_option( self::PENDING, null );
		return ( is_array( $p ) && ! empty( $p['symbols'] ) ) ? $p : null;
	}

	/**
	 * Fetch and (if healthy) store the rate.
	 *
	 * @return array {ok:bool, status:string, message:string}
	 */
	public static function refresh() {
		$symbol = (string) One_Navasan_Settings::get( 'base_symbol' );
		$result = One_Navasan_Fetcher::fetch( $symbol, self::data_ts() );
		if ( ! $result['ok'] ) {
			update_option( self::ERROR, $result['error'] . ' @' . time(), false );
			One_Navasan_Logger::add( 'error', sprintf( 'Fetch failed, kept last healthy rate (%s).', $result['error'] ) );
			return array( 'ok' => false, 'status' => 'failed', 'message' => __( 'دریافت نرخ ناموفق بود؛ آخرین نرخ سالم حفظ شد.', ONE_NAVASAN_DOMAIN ) );
		}

		$new_entry = $result['data'][ $symbol ];
		$old_raw   = self::raw_entry();
		$old_value = $old_raw ? $old_raw['value'] : null;
		$jump      = One_Navasan_Guard::check_jump( $new_entry['value'], $old_value, (float) One_Navasan_Settings::get( 'max_jump_pct' ) );
		if ( ! $jump['ok'] ) {
			update_option(
				self::PENDING,
				array(
					'symbols'    => $result['data'],
					'fetched_at' => time(),
					'source'     => $result['source'],
					'new_value'  => $new_entry['value'],
					'old_value'  => $old_value,
					'pct'        => $jump['pct'],
				),
				false
			);
			One_Navasan_Logger::add( 'warning', sprintf( 'Rate jump %.2f%% exceeds the limit; waiting for admin approval.', $jump['pct'] ) );
			return array( 'ok' => false, 'status' => 'pending', 'message' => __( 'تغییر نرخ از حد مجاز بیشتر بود و منتظر تأیید مدیر است.', ONE_NAVASAN_DOMAIN ) );
		}

		self::commit( $result['data'], $result['source'] );
		One_Navasan_Logger::add( 'info', sprintf( 'Rate updated from %s.', $result['source'] ) );
		return array( 'ok' => true, 'status' => 'updated', 'message' => __( 'نرخ با موفقیت به‌روز شد.', ONE_NAVASAN_DOMAIN ) );
	}

	/**
	 * Approve the pending rate.
	 *
	 * @return bool
	 */
	public static function approve_pending() {
		$p = self::pending();
		if ( ! $p ) {
			return false;
		}
		self::commit( $p['symbols'], $p['source'] );
		One_Navasan_Logger::add( 'info', 'Pending rate approved by admin.' );
		return true;
	}

	/**
	 * Store a healthy dataset and fire follow-up work.
	 *
	 * @param array  $symbols Normalized symbols map.
	 * @param string $source  Source id.
	 */
	public static function commit( array $symbols, $source ) {
		$old_eff = self::effective_toman();
		update_option(
			self::STATE,
			array(
				'symbols'     => $symbols,
				'fetched_at'  => time(),
				'source'      => $source,
				'base_symbol' => (string) One_Navasan_Settings::get( 'base_symbol' ),
			),
			false
		);
		$values = array();
		foreach ( $symbols as $sym => $entry ) {
			if ( null !== $entry['value'] ) {
				$values[ $sym ] = $entry['value'];
			}
		}
		update_option(
			self::DAILY,
			One_Navasan_Daily_Range::update( get_option( self::DAILY, array() ), One_Navasan_Daily_Range::day_key( time() ), $values ),
			false
		);
		delete_option( self::PENDING );
		delete_option( self::ERROR );

		$new_eff = self::effective_toman();
		if ( null !== $new_eff && ( null === $old_eff || abs( $new_eff - $old_eff ) > 0.000001 ) ) {
			/**
			 * Fires after the effective base rate changed.
			 *
			 * @param float      $new_eff New rate (Toman).
			 * @param float|null $old_eff Old rate (Toman).
			 */
			do_action( 'one_navasan_rate_changed', $new_eff, $old_eff );
			if ( class_exists( 'One_Navasan_WC_Pricing' ) && class_exists( 'WooCommerce' ) ) {
				One_Navasan_WC_Pricing::maybe_start_recalculation();
			}
		}
	}
}
