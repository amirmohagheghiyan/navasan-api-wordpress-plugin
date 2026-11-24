<?php
/**
 * Parsing and validation of priceto.day responses (WordPress independent).
 *
 * priceto.day quotes in Rial; everything leaving this class is in Toman.
 *
 * @package navasan-api-wordpress-plugin
 */

if ( ! class_exists( 'One_Navasan_Priceto_Parser' ) ) {

	/**
	 * Converts /v1/latest/irr/{symbol} and /v1/change/irr/{symbol} bodies to the internal entry shape:
	 * {value, high, low, change_val, change_pct, date} with money in Toman, date as unix time.
	 */
	class One_Navasan_Priceto_Parser {

		/**
		 * Rial per Toman.
		 */
		const RIAL_PER_TOMAN = 10;

		/**
		 * Parse a "latest" body.
		 *
		 * @param mixed $body Raw JSON body.
		 * @return array {ok:bool, entry:array|null, error:string}
		 */
		public static function parse_latest( $body ) {
			$json = self::decode( $body );
			if ( null === $json ) {
				return self::fail( 'invalid_json' );
			}
			if ( ! isset( $json['success'] ) || true !== $json['success'] ) {
				return self::fail( 'api_error' );
			}
			$price = One_Navasan_Source_Validator::to_number( isset( $json['price'] ) ? $json['price'] : null );
			if ( null === $price ) {
				return self::fail( 'missing_value' );
			}
			if ( $price <= 0 ) {
				return self::fail( 'non_positive_value' );
			}
			$date = self::parse_time( isset( $json['time'] ) ? $json['time'] : null );
			if ( null === $date ) {
				return self::fail( 'invalid_time' );
			}

			$high = self::positive_toman( isset( $json['high'] ) ? $json['high'] : null );
			$low  = self::positive_toman( isset( $json['low'] ) ? $json['low'] : null );
			if ( null !== $high && null !== $low && $low > $high ) {
				// Inconsistent range: ignore both and let the plugin track the range itself.
				$high = null;
				$low  = null;
			}

			return array(
				'ok'    => true,
				'entry' => array(
					'value'      => self::to_toman( $price ),
					'high'       => $high,
					'low'        => $low,
					'change_val' => null,
					'change_pct' => null,
					'date'       => $date,
				),
				'error' => '',
			);
		}

		/**
		 * Parse a "change" body and return the 1-day change in Toman and percent.
		 *
		 * @param mixed $body Raw JSON body.
		 * @return array {ok:bool, change_val:float|null, change_pct:float|null, error:string}
		 */
		public static function parse_change( $body ) {
			$json = self::decode( $body );
			if ( null === $json || empty( $json['success'] ) || ! isset( $json['changes'] ) || ! is_array( $json['changes'] ) ) {
				return array( 'ok' => false, 'change_val' => null, 'change_pct' => null, 'error' => 'invalid_change' );
			}
			foreach ( $json['changes'] as $row ) {
				if ( is_array( $row ) && isset( $row['period'] ) && '1d' === $row['period'] ) {
					$val = One_Navasan_Source_Validator::to_number( isset( $row['change'] ) ? $row['change'] : null );
					$pct = One_Navasan_Source_Validator::to_number( isset( $row['changePercent'] ) ? $row['changePercent'] : null );
					if ( null === $val || null === $pct ) {
						break;
					}
					return array( 'ok' => true, 'change_val' => self::to_toman( $val ), 'change_pct' => round( $pct, 4 ), 'error' => '' );
				}
			}
			return array( 'ok' => false, 'change_val' => null, 'change_pct' => null, 'error' => 'no_1d_change' );
		}

		/**
		 * Parse a "symbols" body.
		 *
		 * @param mixed $body Raw JSON body.
		 * @return string[] Symbols (empty on any problem).
		 */
		public static function parse_symbols( $body ) {
			$json = self::decode( $body );
			if ( null === $json || empty( $json['success'] ) || ! isset( $json['symbols'] ) || ! is_array( $json['symbols'] ) ) {
				return array();
			}
			$out = array();
			foreach ( $json['symbols'] as $symbol ) {
				if ( is_string( $symbol ) && 1 === preg_match( '/^[a-z0-9][a-z0-9-]{0,40}$/', $symbol ) && 'irr' !== $symbol ) {
					$out[] = $symbol;
				}
			}
			return $out;
		}

		/**
		 * Rial -> Toman.
		 *
		 * @param float $rial Amount in Rial.
		 * @return float
		 */
		public static function to_toman( $rial ) {
			return round( (float) $rial / self::RIAL_PER_TOMAN, 6 );
		}

		/**
		 * ISO 8601 time to unix time.
		 *
		 * @param mixed $time Value.
		 * @return int|null
		 */
		public static function parse_time( $time ) {
			if ( ! is_string( $time ) || '' === trim( $time ) ) {
				return null;
			}
			$ts = strtotime( $time );
			return ( false !== $ts && $ts > 0 ) ? (int) $ts : null;
		}

		/**
		 * Optional positive Rial value to Toman, or null.
		 *
		 * @param mixed $value Value.
		 * @return float|null
		 */
		private static function positive_toman( $value ) {
			$n = One_Navasan_Source_Validator::to_number( $value );
			return ( null !== $n && $n > 0 ) ? self::to_toman( $n ) : null;
		}

		/**
		 * Decode a JSON object (a one-element list is tolerated).
		 *
		 * @param mixed $body Raw body.
		 * @return array|null
		 */
		private static function decode( $body ) {
			if ( ! is_string( $body ) || '' === trim( $body ) ) {
				return null;
			}
			$json = json_decode( $body, true );
			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $json ) ) {
				return null;
			}
			if ( isset( $json[0] ) && is_array( $json[0] ) && ! isset( $json['success'] ) ) {
				$json = $json[0];
			}
			return $json;
		}

		/**
		 * Failure result.
		 *
		 * @param string $error Error code.
		 * @return array
		 */
		private static function fail( $error ) {
			return array( 'ok' => false, 'entry' => null, 'error' => $error );
		}
	}
}
