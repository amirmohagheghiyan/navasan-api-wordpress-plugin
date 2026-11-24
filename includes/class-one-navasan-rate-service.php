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
	 * Stored state {symbols, fetched_at, source (path id), provider, via_backup, base_symbol}.
	 *
	 * @return array
	 */
	public static function state() {
		$state = get_option( self::STATE, array() );
		return is_array( $state ) ? $state : array();
	}

	/**
	 * Base symbol of the stored data. It follows the data, not the settings, so switching the
	 * source in the dashboard never blanks the rate before the next fetch.
	 *
	 * @return string
	 */
	public static function stored_base_symbol() {
		$state = self::state();
		if ( ! empty( $state['base_symbol'] ) && isset( $state['symbols'][ $state['base_symbol'] ] ) ) {
			return (string) $state['base_symbol'];
		}
		return One_Navasan_Settings::base_symbol();
	}

	/**
	 * Provider of the stored rate (legacy state without the field is Navasan-API).
	 *
	 * @return string|null Null when nothing is stored.
	 */
	public static function stored_provider() {
		$state = self::state();
		if ( empty( $state['symbols'] ) ) {
			return null;
		}
		return ! empty( $state['provider'] ) ? (string) $state['provider'] : 'navasan';
	}

	/**
	 * Raw base-symbol entry from the source.
	 *
	 * @param string|null $symbol Symbol (default: base symbol of the stored data).
	 * @return array|null
	 */
	public static function raw_entry( $symbol = null ) {
		$state  = self::state();
		$symbol = null === $symbol ? self::stored_base_symbol() : $symbol;
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
		if ( 'manual' === $s['rate_source'] ) {
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
	 * Which source the current rate comes from.
	 *
	 * @return array {id:string (priceto|navasan|manual|none), via_backup:bool}
	 */
	public static function current_source() {
		if ( One_Navasan_Settings::is_manual() ) {
			return array( 'id' => 'manual', 'via_backup' => false );
		}
		$provider = self::stored_provider();
		$state    = self::state();
		return array(
			'id'         => null === $provider ? 'none' : $provider,
			'via_backup' => ! empty( $state['via_backup'] ),
		);
	}

	/**
	 * Output for a symbol (shortcode / REST / JS).
	 *
	 * @param string|null $symbol Symbol (default base).
	 * @return array|null Values in Toman.
	 */
	public static function symbol_output( $symbol = null ) {
		$s       = One_Navasan_Settings::all();
		$aliases = One_Navasan_Settings::base_symbol_aliases();
		$base    = self::stored_base_symbol();
		// The base symbol of either provider means "the base rate", so existing shortcodes keep working.
		$symbol  = ( null === $symbol || '' === $symbol || in_array( $symbol, $aliases, true ) ) ? $base : $symbol;
		$is_base = ( $symbol === $base );
		$entry   = self::raw_entry( $symbol );
		$manual  = ( $is_base && 'manual' === $s['rate_source'] );
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

		// High and low: the source's own range when it gives one, otherwise the range seen by this plugin today.
		if ( $manual ) {
			$high = $value;
			$low  = $value;
		} elseif ( $entry && isset( $entry['high'], $entry['low'] ) ) {
			$high = $adj( (float) $entry['high'] );
			$low  = $adj( (float) $entry['low'] );
		} else {
			$high = $range ? $adj( $range['max'] ) : $value;
			$low  = $range ? $adj( $range['min'] ) : $value;
		}

		$state  = self::state();
		$source = $manual ? 'manual' : ( ! empty( $state['provider'] ) ? (string) $state['provider'] : 'navasan' );
		return array(
			'symbol'     => $symbol,
			'value'      => $value,
			'change_val' => $manual ? 0 : ( $entry ? $entry['change_val'] : null ),
			'change_pct' => $manual ? 0 : ( $entry ? $entry['change_pct'] : null ),
			'high'       => $high,
			'low'        => $low,
			'updated'    => $manual ? ( (int) $s['manual_ts'] ?: null ) : ( $entry && $entry['date'] ? (int) $entry['date'] : ( isset( $state['fetched_at'] ) ? (int) $state['fetched_at'] : null ) ),
			'fetched_at' => isset( $state['fetched_at'] ) ? (int) $state['fetched_at'] : null,
			'mode'       => $manual ? 'manual' : 'auto',
			'source'     => $source,
		);
	}

	/**
	 * Is the data stale? Only market-open time counts, so nights, Fridays and Thursday
	 * afternoons never raise the warning by themselves.
	 *
	 * @param int|null $now Unix time to judge at (default: now; used by tests).
	 * @return bool
	 */
	public static function is_stale( $now = null ) {
		if ( One_Navasan_Settings::is_manual() ) {
			return false;
		}
		return One_Navasan_Market_Hours::is_stale( self::data_ts(), null === $now ? time() : (int) $now, (float) One_Navasan_Settings::get( 'stale_hours' ) );
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
	 * The primary source is tried first. The backup is tried only when the primary fails, and its
	 * result is used only when its data is newer than the last healthy rate.
	 *
	 * @return array {ok:bool, status:string, message:string}
	 */
	public static function refresh() {
		$s    = One_Navasan_Settings::all();
		$plan = One_Navasan_Source_Selector::plan( (string) $s['rate_source'], (string) $s['backup_source'] );
		if ( ! $plan ) {
			return array( 'ok' => false, 'status' => 'manual', 'message' => __( 'منبع نرخ روی «دستی» است؛ دریافتی انجام نشد.', ONE_NAVASAN_DOMAIN ) );
		}

		$stored_ts  = self::data_ts();
		$errors     = array();
		$result     = null;
		$used       = null;
		$via_backup = false;
		foreach ( $plan as $index => $provider_id ) {
			$provider = One_Navasan_Fetcher::provider( $provider_id );
			$try      = One_Navasan_Fetcher::fetch_provider( $provider, $stored_ts );
			if ( ! $try['ok'] ) {
				$errors[] = $try['error'];
				continue;
			}
			if ( $index > 0 ) {
				$entry = $try['symbols'][ $provider->base_symbol( $s ) ];
				if ( ! One_Navasan_Source_Selector::backup_is_newer( $entry['date'], $stored_ts ) ) {
					$errors[] = $provider_id . ': backup_not_newer';
					continue;
				}
				$via_backup = true;
			}
			$result = $try;
			$used   = $provider;
			break;
		}

		if ( null === $result ) {
			$error = implode( ' | ', $errors );
			update_option( self::ERROR, $error . ' @' . time(), false );
			One_Navasan_Logger::add( 'error', sprintf( 'Fetch failed, kept last healthy rate (%s).', $error ) );
			return array( 'ok' => false, 'status' => 'failed', 'message' => __( 'دریافت نرخ ناموفق بود؛ آخرین نرخ سالم حفظ شد.', ONE_NAVASAN_DOMAIN ) );
		}

		$base           = $used->base_symbol( $s );
		$new_entry      = $result['symbols'][ $base ];
		$old_raw        = self::raw_entry();
		$old_value      = $old_raw ? $old_raw['value'] : null;
		$source_changed = One_Navasan_Source_Selector::is_source_change( self::stored_provider(), $used->id() );
		$jump           = One_Navasan_Guard::check_jump( $new_entry['value'], $old_value, (float) $s['max_jump_pct'] );
		if ( ! $jump['ok'] ) {
			$reason = One_Navasan_Source_Selector::hold_reason( $source_changed );
			update_option(
				self::PENDING,
				array(
					'symbols'     => $result['symbols'],
					'fetched_at'  => time(),
					'source'      => $result['endpoint'],
					'provider'    => $used->id(),
					'via_backup'  => $via_backup,
					'base_symbol' => $base,
					'reason'      => $reason,
					'new_value'   => $new_entry['value'],
					'old_value'   => $old_value,
					'pct'         => $jump['pct'],
				),
				false
			);
			One_Navasan_Logger::add( 'warning', sprintf( 'Rate jump %.2f%% exceeds the limit (%s, from %s); waiting for admin approval.', $jump['pct'], $reason, $used->id() ) );
			return array( 'ok' => false, 'status' => 'pending', 'message' => __( 'تغییر نرخ از حد مجاز بیشتر بود و منتظر تأیید مدیر است.', ONE_NAVASAN_DOMAIN ) );
		}

		self::commit( $result['symbols'], $result['endpoint'], $used->id(), $via_backup, $base );
		One_Navasan_Logger::add( $via_backup ? 'warning' : 'info', sprintf( 'Rate updated from %s/%s%s.', $used->id(), $result['endpoint'], $via_backup ? ' (backup source)' : '' ) );
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
		$provider = ! empty( $p['provider'] ) ? (string) $p['provider'] : 'navasan';
		$base     = ! empty( $p['base_symbol'] ) ? (string) $p['base_symbol'] : One_Navasan_Settings::base_symbol();
		self::commit( $p['symbols'], $p['source'], $provider, ! empty( $p['via_backup'] ), $base );
		One_Navasan_Logger::add( 'info', 'Pending rate approved by admin.' );
		return true;
	}

	/**
	 * Store a healthy dataset and fire follow-up work.
	 *
	 * @param array  $symbols    Normalized symbols map.
	 * @param string $source     Path (endpoint) id.
	 * @param string $provider   Provider id.
	 * @param bool   $via_backup Whether the backup source delivered it.
	 * @param string $base       Base symbol of the data.
	 */
	public static function commit( array $symbols, $source, $provider = 'navasan', $via_backup = false, $base = '' ) {
		$old_eff = self::effective_toman();
		update_option(
			self::STATE,
			array(
				'symbols'     => $symbols,
				'fetched_at'  => time(),
				'source'      => $source,
				'provider'    => $provider,
				'via_backup'  => (bool) $via_backup,
				'base_symbol' => '' !== $base ? $base : One_Navasan_Settings::base_symbol(),
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
