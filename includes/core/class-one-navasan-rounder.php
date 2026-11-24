<?php
/**
 * Price rounding (WordPress independent).
 *
 * @package navasan-api-wordpress-plugin
 */

if ( ! class_exists( 'One_Navasan_Rounder' ) ) {

	/**
	 * Rounds a final price to a step in a chosen direction.
	 */
	class One_Navasan_Rounder {

		/**
		 * Round a value.
		 *
		 * @param float  $value     Value.
		 * @param float  $step      Step (0 or less = no rounding).
		 * @param string $direction nearest|up|down.
		 * @return float
		 */
		public static function round( $value, $step, $direction = 'nearest' ) {
			$value = (float) $value;
			$step  = (float) $step;
			if ( $step <= 0 ) {
				return $value;
			}
			// Remove float noise so 3000.0000000001 / 1000 does not ceil to 4.
			$quotient = round( $value / $step, 9 );
			if ( 'up' === $direction ) {
				$units = ceil( $quotient );
			} elseif ( 'down' === $direction ) {
				$units = floor( $quotient );
			} else {
				$units = round( $quotient, 0 );
			}
			return round( $units * $step, 6 );
		}
	}
}
