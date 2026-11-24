<?php
/**
 * Robust number parsing from free text (WordPress independent).
 *
 * @package navasan-api-wordpress-plugin
 */

if ( ! class_exists( 'One_Navasan_Number_Parser' ) ) {

	/**
	 * Parses numbers such as "1.22 گرم", "6%", ".5", "8,69" or Persian digits.
	 */
	class One_Navasan_Number_Parser {

		/**
		 * Convert Persian/Arabic-Indic digits and separators to ASCII.
		 *
		 * @param string $text Input text.
		 * @return string
		 */
		public static function normalize_digits( $text ) {
			$map = array(
				'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
				'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
				'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
				'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
				'٫' => '.', // Arabic decimal separator.
				'٬' => ',', // Arabic thousands separator.
				'،' => ',', // Arabic comma.
				'−' => '-', // Unicode minus.
			);
			return strtr( (string) $text, $map );
		}

		/**
		 * Parse the first number found in the value.
		 *
		 * A lone comma followed by exactly three digits (e.g. "1,234") is read as a
		 * thousands separator; any other lone comma (e.g. "8,69") is a decimal comma.
		 *
		 * @param mixed $value Number or text.
		 * @return float|null Null when no usable number exists (never zero by default).
		 */
		public static function parse( $value ) {
			if ( is_int( $value ) || is_float( $value ) ) {
				return is_finite( (float) $value ) ? (float) $value : null;
			}
			if ( ! is_string( $value ) ) {
				return null;
			}
			$text = self::normalize_digits( trim( $value ) );
			if ( '' === $text ) {
				return null;
			}
			if ( ! preg_match( '/(-)?\s*([0-9][0-9.,]*|[.,][0-9]+)/', $text, $m ) ) {
				return null;
			}
			$negative = '-' === $m[1];
			$token    = rtrim( $m[2], '.,' );
			if ( '' === $token ) {
				return null;
			}

			$dots   = substr_count( $token, '.' );
			$commas = substr_count( $token, ',' );

			if ( $dots > 0 && $commas > 0 ) {
				// The right-most separator is the decimal one.
				$decimal_is_dot = strrpos( $token, '.' ) > strrpos( $token, ',' );
				if ( $decimal_is_dot ) {
					if ( $dots > 1 ) {
						return null;
					}
					$token = str_replace( ',', '', $token );
				} else {
					if ( $commas > 1 ) {
						return null;
					}
					$token = str_replace( '.', '', $token );
					$token = str_replace( ',', '.', $token );
				}
			} elseif ( $commas > 0 ) {
				$token = self::resolve_single_separator( $token, ',', $commas );
			} elseif ( $dots > 1 ) {
				$token = self::resolve_single_separator( $token, '.', $dots );
			}

			if ( null === $token || ! is_numeric( $token ) ) {
				return null;
			}
			$number = (float) $token;
			if ( ! is_finite( $number ) ) {
				return null;
			}
			return $negative ? -$number : $number;
		}

		/**
		 * Parse and require a strictly positive result.
		 *
		 * @param mixed $value Input.
		 * @return float|null
		 */
		public static function parse_positive( $value ) {
			$n = self::parse( $value );
			return ( null !== $n && $n > 0 ) ? $n : null;
		}

		/**
		 * Decide whether a single kind of separator is thousands or decimal.
		 *
		 * @param string $token Number token containing only $sep as separator.
		 * @param string $sep   Separator character.
		 * @param int    $count Occurrences of $sep.
		 * @return string|null Normalized token using "." as decimal or null if ambiguous.
		 */
		private static function resolve_single_separator( $token, $sep, $count ) {
			$parts = explode( $sep, $token );
			if ( $count > 1 ) {
				// Several separators must be grouping: every group after the first has 3 digits.
				$first = array_shift( $parts );
				foreach ( $parts as $group ) {
					if ( 3 !== strlen( $group ) ) {
						return null;
					}
				}
				if ( '' === $first || strlen( $first ) > 3 ) {
					return null;
				}
				return $first . implode( '', $parts );
			}
			// Exactly one separator.
			if ( ',' === $sep && 3 === strlen( $parts[1] ) && '' !== $parts[0] && strlen( $parts[0] ) <= 3 && '0' !== $parts[0] ) {
				return $parts[0] . $parts[1];
			}
			return $parts[0] . '.' . $parts[1];
		}
	}
}
