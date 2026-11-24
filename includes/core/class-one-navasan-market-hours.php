<?php
/**
 * Iran market opening hours (WordPress independent).
 *
 * Used so the "stale data" warning does not fire while the market is closed
 * (nights, Friday and Thursday afternoon) and the sources legitimately do not change.
 * Iran has no daylight saving time since 2022, so a fixed UTC+03:30 offset is used.
 *
 * @package navasan-api-wordpress-plugin
 */

if ( ! class_exists( 'One_Navasan_Market_Hours' ) ) {

	/**
	 * Open windows per weekday (0 = Sunday ... 6 = Saturday), minutes from local midnight.
	 */
	class One_Navasan_Market_Hours {

		/**
		 * Tehran offset from UTC in seconds.
		 */
		const OFFSET = 12600;

		/**
		 * Open windows: weekday => [start_minute, end_minute].
		 *
		 * @return array
		 */
		public static function windows() {
			$full = array( 8 * 60, 22 * 60 );
			return array(
				6 => $full,                 // Saturday.
				0 => $full,                 // Sunday.
				1 => $full,                 // Monday.
				2 => $full,                 // Tuesday.
				3 => $full,                 // Wednesday.
				4 => array( 8 * 60, 14 * 60 ), // Thursday: closes in the afternoon.
				// Friday (5): closed.
			);
		}

		/**
		 * Is the market open at a moment?
		 *
		 * @param int $timestamp Unix time.
		 * @return bool
		 */
		public static function is_open( $timestamp ) {
			$local   = (int) $timestamp + self::OFFSET;
			$weekday = (int) gmdate( 'w', $local );
			$windows = self::windows();
			if ( ! isset( $windows[ $weekday ] ) ) {
				return false;
			}
			$minute = ( (int) gmdate( 'G', $local ) * 60 ) + (int) gmdate( 'i', $local );
			return $minute >= $windows[ $weekday ][0] && $minute < $windows[ $weekday ][1];
		}

		/**
		 * Seconds of open market inside [from, to].
		 *
		 * @param int $from Unix time.
		 * @param int $to   Unix time.
		 * @return int
		 */
		public static function open_seconds( $from, $to ) {
			$from = (int) $from;
			$to   = (int) $to;
			if ( $to <= $from ) {
				return 0;
			}
			$windows = self::windows();
			// Local midnight of the first day (as a UTC-based timestamp of that local midnight).
			$day_start = (int) floor( ( $from + self::OFFSET ) / 86400 ) * 86400 - self::OFFSET;
			$total     = 0;
			$guard     = 0;
			while ( $day_start < $to && $guard < 400 ) {
				$weekday = (int) gmdate( 'w', $day_start + self::OFFSET + 3600 );
				if ( isset( $windows[ $weekday ] ) ) {
					$start = $day_start + $windows[ $weekday ][0] * 60;
					$end   = $day_start + $windows[ $weekday ][1] * 60;
					$lo    = max( $start, $from );
					$hi    = min( $end, $to );
					if ( $hi > $lo ) {
						$total += $hi - $lo;
					}
				}
				$day_start += 86400;
				++$guard;
			}
			return $total;
		}

		/**
		 * Is the data stale, counting only open-market time?
		 *
		 * @param int|null $data_ts Timestamp of the data itself.
		 * @param int      $now     Current Unix time.
		 * @param float    $hours   Allowed age in open-market hours (0 disables the check).
		 * @return bool
		 */
		public static function is_stale( $data_ts, $now, $hours ) {
			if ( null === $data_ts || (float) $hours <= 0 ) {
				return false;
			}
			return self::open_seconds( (int) $data_ts, (int) $now ) > ( (float) $hours * 3600 );
		}
	}
}
