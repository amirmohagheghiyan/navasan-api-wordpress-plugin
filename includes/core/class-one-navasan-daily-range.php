<?php
/**
 * Per-day min/max tracking (WordPress independent).
 *
 * @package navasan-api-wordpress-plugin
 */

if ( ! class_exists( 'One_Navasan_Daily_Range' ) ) {

	/**
	 * Tracks the lowest and highest value of each symbol for the current day (Tehran time).
	 */
	class One_Navasan_Daily_Range {

		/**
		 * Day key (Y-m-d) in the given timezone.
		 *
		 * @param int    $timestamp Unix timestamp.
		 * @param string $timezone  Timezone name.
		 * @return string
		 */
		public static function day_key( $timestamp, $timezone = 'Asia/Tehran' ) {
			$date = new DateTime( '@' . (int) $timestamp );
			$date->setTimezone( new DateTimeZone( $timezone ) );
			return $date->format( 'Y-m-d' );
		}

		/**
		 * Update the state with new values.
		 *
		 * @param array  $state  Previous state {day:string, symbols:{sym:{min,max}}}.
		 * @param string $day    Current day key.
		 * @param array  $values Map symbol => numeric value.
		 * @return array New state.
		 */
		public static function update( $state, $day, array $values ) {
			if ( ! is_array( $state ) || ! isset( $state['day'] ) || $state['day'] !== $day || ! isset( $state['symbols'] ) ) {
				$state = array(
					'day'     => $day,
					'symbols' => array(),
				);
			}
			foreach ( $values as $symbol => $value ) {
				if ( ! is_numeric( $value ) || (float) $value <= 0 ) {
					continue;
				}
				$value = (float) $value;
				if ( ! isset( $state['symbols'][ $symbol ] ) ) {
					$state['symbols'][ $symbol ] = array(
						'min' => $value,
						'max' => $value,
					);
				} else {
					$state['symbols'][ $symbol ]['min'] = min( $state['symbols'][ $symbol ]['min'], $value );
					$state['symbols'][ $symbol ]['max'] = max( $state['symbols'][ $symbol ]['max'], $value );
				}
			}
			return $state;
		}

		/**
		 * Get min/max for a symbol on a day.
		 *
		 * @param array  $state  State.
		 * @param string $day    Day key.
		 * @param string $symbol Symbol.
		 * @return array|null {min,max} or null.
		 */
		public static function get( $state, $day, $symbol ) {
			if ( is_array( $state ) && isset( $state['day'], $state['symbols'][ $symbol ] ) && $state['day'] === $day ) {
				return $state['symbols'][ $symbol ];
			}
			return null;
		}
	}
}
