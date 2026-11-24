<?php
/**
 * Validation of the source JSON (WordPress independent).
 *
 * @package navasan-api-wordpress-plugin
 */

if ( ! class_exists( 'One_Navasan_Source_Validator' ) ) {

	/**
	 * Parses and validates Navasan-API style JSON.
	 */
	class One_Navasan_Source_Validator {

		/**
		 * Decode and normalize a JSON body.
		 *
		 * @param string $body Raw body.
		 * @return array {ok:bool, data:array, error:string}
		 */
		public static function parse_json( $body ) {
			if ( ! is_string( $body ) || '' === trim( $body ) ) {
				return self::result( false, array(), 'empty_body' );
			}
			$decoded = json_decode( $body, true );
			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
				return self::result( false, array(), 'invalid_json' );
			}
			$data = array();
			foreach ( $decoded as $symbol => $entry ) {
				if ( ! is_array( $entry ) || ! is_string( $symbol ) ) {
					continue;
				}
				$data[ $symbol ] = array(
					'value'      => self::to_number( isset( $entry['value'] ) ? $entry['value'] : null ),
					'change_val' => self::to_number( isset( $entry['change_val'] ) ? $entry['change_val'] : null ),
					'change_pct' => self::to_number( isset( $entry['change_pct'] ) ? $entry['change_pct'] : null ),
					'date'       => ( isset( $entry['date'] ) && is_numeric( $entry['date'] ) && (int) $entry['date'] > 0 ) ? (int) $entry['date'] : null,
				);
			}
			if ( empty( $data ) ) {
				return self::result( false, array(), 'no_entries' );
			}
			return self::result( true, $data, '' );
		}

		/**
		 * Get a validated, usable entry for a symbol.
		 *
		 * @param array  $data   Result of parse_json()['data'].
		 * @param string $symbol Symbol key.
		 * @return array {ok:bool, entry:array|null, error:string}
		 */
		public static function get_entry( array $data, $symbol ) {
			if ( ! isset( $data[ $symbol ] ) ) {
				return array( 'ok' => false, 'entry' => null, 'error' => 'missing_symbol' );
			}
			$entry = $data[ $symbol ];
			if ( null === $entry['value'] ) {
				return array( 'ok' => false, 'entry' => null, 'error' => 'missing_value' );
			}
			if ( $entry['value'] <= 0 ) {
				return array( 'ok' => false, 'entry' => null, 'error' => 'non_positive_value' );
			}
			return array( 'ok' => true, 'entry' => $entry, 'error' => '' );
		}

		/**
		 * Numeric strings and numbers become floats; everything else null.
		 *
		 * @param mixed $value Value.
		 * @return float|null
		 */
		public static function to_number( $value ) {
			if ( is_bool( $value ) || null === $value ) {
				return null;
			}
			if ( is_int( $value ) || is_float( $value ) ) {
				return is_finite( (float) $value ) ? (float) $value : null;
			}
			if ( is_string( $value ) && is_numeric( trim( $value ) ) ) {
				$n = (float) trim( $value );
				return is_finite( $n ) ? $n : null;
			}
			return null;
		}

		/**
		 * Result array.
		 *
		 * @param bool   $ok    Success.
		 * @param array  $data  Data.
		 * @param string $error Error code.
		 * @return array
		 */
		private static function result( $ok, $data, $error ) {
			return array(
				'ok'    => $ok,
				'data'  => $data,
				'error' => $error,
			);
		}
	}
}
