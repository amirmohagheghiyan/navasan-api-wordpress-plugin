<?php
/**
 * Safety guards for incoming rates (WordPress independent).
 *
 * @package navasan-api-wordpress-plugin
 */

if ( ! class_exists( 'One_Navasan_Guard' ) ) {

	/**
	 * Jump and staleness guards.
	 */
	class One_Navasan_Guard {

		/**
		 * Check whether a new rate differs too much from the previous one.
		 *
		 * @param float      $new     New rate.
		 * @param float|null $old     Previous healthy rate.
		 * @param float      $max_pct Maximum allowed change in percent (0 = disabled).
		 * @return array {ok:bool, pct:float|null}
		 */
		public static function check_jump( $new, $old, $max_pct ) {
			if ( null === $old || (float) $old <= 0 || (float) $max_pct <= 0 ) {
				return array( 'ok' => true, 'pct' => null );
			}
			$pct = abs( ( (float) $new - (float) $old ) / (float) $old ) * 100;
			return array(
				'ok'  => $pct <= (float) $max_pct,
				'pct' => round( $pct, 4 ),
			);
		}

		/**
		 * Whether the data timestamp is older than N hours.
		 *
		 * @param int|null $data_ts Timestamp of the data itself.
		 * @param int      $now     Current timestamp.
		 * @param float    $hours   Maximum age in hours.
		 * @return bool
		 */
		public static function is_stale( $data_ts, $now, $hours ) {
			if ( null === $data_ts || (float) $hours <= 0 ) {
				return false;
			}
			return ( (int) $now - (int) $data_ts ) > ( (float) $hours * 3600 );
		}
	}
}
