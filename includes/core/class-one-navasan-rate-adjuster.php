<?php
/**
 * Optional adjustment of the automatic rate (WordPress independent).
 *
 * @package navasan-api-wordpress-plugin
 */

if ( ! class_exists( 'One_Navasan_Rate_Adjuster' ) ) {

	/**
	 * Adds/subtracts a percentage or a fixed amount to the source rate.
	 */
	class One_Navasan_Rate_Adjuster {

		/**
		 * Apply an adjustment.
		 *
		 * @param float  $rate  Source rate (Toman).
		 * @param string $mode  none|percent|fixed.
		 * @param float  $value Percent or amount (may be negative).
		 * @return float
		 */
		public static function apply( $rate, $mode, $value ) {
			$rate  = (float) $rate;
			$value = (float) $value;
			if ( 'percent' === $mode ) {
				return round( $rate * ( 1 + $value / 100 ), 6 );
			}
			if ( 'fixed' === $mode ) {
				return round( $rate + $value, 6 );
			}
			return $rate;
		}
	}
}
