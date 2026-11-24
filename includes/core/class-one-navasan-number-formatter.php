<?php
/**
 * Number formatting for display (WordPress independent).
 *
 * @package navasan-api-wordpress-plugin
 */

if ( ! class_exists( 'One_Navasan_Number_Formatter' ) ) {

	/**
	 * Formats numbers with thousands separators and chosen digit set.
	 */
	class One_Navasan_Number_Formatter {

		/**
		 * Format a number.
		 *
		 * @param float|int|null $number    Number.
		 * @param bool           $thousands Use thousands separator.
		 * @param string         $digits    en|fa.
		 * @param int|null       $decimals  Fixed decimals; null = integer if whole, else up to 2.
		 * @return string
		 */
		public static function format( $number, $thousands = true, $digits = 'en', $decimals = null ) {
			if ( null === $number || ! is_numeric( $number ) ) {
				return '';
			}
			$number = (float) $number;
			$auto   = ( null === $decimals );
			if ( $auto ) {
				$decimals = ( abs( $number - round( $number ) ) < 0.00001 ) ? 0 : 2;
			}
			$text = number_format( $number, (int) $decimals, '.', $thousands ? ',' : '' );
			if ( $auto && false !== strpos( $text, '.' ) ) {
				$text = rtrim( rtrim( $text, '0' ), '.' );
			}
			return 'fa' === $digits ? self::to_persian_digits( $text ) : $text;
		}

		/**
		 * Latin digits to Persian digits (also "," -> "٬" and "." -> "٫").
		 *
		 * @param string $text Text.
		 * @return string
		 */
		public static function to_persian_digits( $text ) {
			return strtr(
				(string) $text,
				array(
					'0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
					'5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
					',' => '٬', '.' => '٫',
				)
			);
		}
	}
}
