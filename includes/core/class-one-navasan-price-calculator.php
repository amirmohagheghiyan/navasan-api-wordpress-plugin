<?php
/**
 * Gold price formula (WordPress independent).
 *
 * @package navasan-api-wordpress-plugin
 */

if ( ! class_exists( 'One_Navasan_Price_Calculator' ) ) {

	/**
	 * Computes a product price and its breakdown.
	 *
	 * Formula:
	 *   karat_rate = base_rate * (karat / base_karat)
	 *   gold       = weight_g * karat_rate
	 *   wage       = gold * wage%   (or a fixed amount)
	 *   profit     = (gold + wage) * profit%
	 *   tax        = (wage + profit) * tax%
	 *   total      = round(gold + wage + profit + tax + extra)
	 */
	class One_Navasan_Price_Calculator {

		/**
		 * Calculate.
		 *
		 * @param array $in Inputs: base_rate, base_karat, karat, weight, wage_mode (percent|fixed),
		 *                  wage_value, profit_pct, tax_pct, extra, round_step, round_dir.
		 * @return array {ok:true,...breakdown} or {ok:false,reason:string}.
		 */
		public static function calculate( array $in ) {
			$in = array_merge(
				array(
					'base_rate'  => 0,
					'base_karat' => 18,
					'karat'      => 18,
					'weight'     => 0,
					'wage_mode'  => 'percent',
					'wage_value' => 0,
					'profit_pct' => 0,
					'tax_pct'    => 0,
					'extra'      => 0,
					'round_step' => 0,
					'round_dir'  => 'nearest',
				),
				$in
			);

			if ( ! is_numeric( $in['base_rate'] ) || (float) $in['base_rate'] <= 0 ) {
				return self::fail( 'bad_rate' );
			}
			if ( ! is_numeric( $in['base_karat'] ) || (float) $in['base_karat'] <= 0 ) {
				return self::fail( 'bad_base_karat' );
			}
			if ( ! is_numeric( $in['karat'] ) || (float) $in['karat'] <= 0 ) {
				return self::fail( 'bad_karat' );
			}
			if ( ! is_numeric( $in['weight'] ) || (float) $in['weight'] <= 0 ) {
				return self::fail( 'bad_weight' );
			}
			foreach ( array( 'wage_value' => 'bad_wage', 'profit_pct' => 'bad_profit', 'tax_pct' => 'bad_tax', 'extra' => 'bad_extra' ) as $key => $reason ) {
				if ( ! is_numeric( $in[ $key ] ) || (float) $in[ $key ] < 0 ) {
					return self::fail( $reason );
				}
			}

			$karat_rate = (float) $in['base_rate'] * ( (float) $in['karat'] / (float) $in['base_karat'] );
			$gold       = (float) $in['weight'] * $karat_rate;
			$wage       = 'fixed' === $in['wage_mode'] ? (float) $in['wage_value'] : $gold * (float) $in['wage_value'] / 100;
			$profit     = ( $gold + $wage ) * (float) $in['profit_pct'] / 100;
			$tax        = ( $wage + $profit ) * (float) $in['tax_pct'] / 100;
			$extra      = (float) $in['extra'];
			$raw        = $gold + $wage + $profit + $tax + $extra;
			// Rounding applies to the final price only.
			$total = One_Navasan_Rounder::round( $raw, $in['round_step'], $in['round_dir'] );

			return array(
				'ok'         => true,
				'karat_rate' => $karat_rate,
				'gold'       => $gold,
				'wage'       => $wage,
				'profit'     => $profit,
				'tax'        => $tax,
				'extra'      => $extra,
				'raw'        => $raw,
				'total'      => $total,
			);
		}

		/**
		 * Failure result.
		 *
		 * @param string $reason Reason code.
		 * @return array
		 */
		private static function fail( $reason ) {
			return array(
				'ok'     => false,
				'reason' => $reason,
			);
		}
	}
}
