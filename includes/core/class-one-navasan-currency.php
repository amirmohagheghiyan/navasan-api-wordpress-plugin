<?php
/**
 * Currency unit conversion (WordPress independent). The only place where
 * Toman <-> store currency conversion happens.
 *
 * @package navasan-api-wordpress-plugin
 */

if ( ! class_exists( 'One_Navasan_Currency' ) ) {

	/**
	 * Converts source Toman amounts to the store currency unit and back.
	 */
	class One_Navasan_Currency {

		/**
		 * Multiplier from Toman to the store unit.
		 *
		 * @param string $setting       auto|toman|rial.
		 * @param string $currency_code Store (WooCommerce) currency code.
		 * @return float
		 */
		public static function multiplier( $setting, $currency_code = 'IRT' ) {
			if ( 'toman' === $setting ) {
				return 1.0;
			}
			if ( 'rial' === $setting ) {
				return 10.0;
			}
			switch ( strtoupper( (string) $currency_code ) ) {
				case 'IRR':
					return 10.0;
				case 'IRHR': // Thousand rial.
					return 0.01;
				case 'IRHT': // Thousand toman.
					return 0.001;
				case 'IRT':
				default:
					return 1.0;
			}
		}

		/**
		 * Toman -> store unit.
		 *
		 * @param float $toman      Amount in Toman.
		 * @param float $multiplier Multiplier from multiplier().
		 * @return float
		 */
		public static function from_toman( $toman, $multiplier ) {
			return round( (float) $toman * (float) $multiplier, 6 );
		}

		/**
		 * Store unit -> Toman.
		 *
		 * @param float $amount     Amount in store unit.
		 * @param float $multiplier Multiplier from multiplier().
		 * @return float
		 */
		public static function to_toman( $amount, $multiplier ) {
			$multiplier = (float) $multiplier;
			return $multiplier > 0 ? round( (float) $amount / $multiplier, 6 ) : 0.0;
		}
	}
}
