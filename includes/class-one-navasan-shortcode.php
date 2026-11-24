<?php
/**
 * [one_gold_price] shortcode.
 *
 * @package navasan-api-wordpress-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode output.
 */
class One_Navasan_Shortcode {

	const FIELDS = array( 'value', 'change_val', 'change_pct', 'high', 'low', 'updated' );

	/**
	 * Register.
	 */
	public static function init() {
		add_shortcode( 'one_gold_price', array( __CLASS__, 'render' ) );
	}

	/**
	 * Unit conversion factor Toman -> unit.
	 *
	 * @param string $unit toman|rial.
	 * @return float
	 */
	public static function unit_factor( $unit ) {
		return 'rial' === $unit ? 10.0 : 1.0;
	}

	/**
	 * Format one field of an output array.
	 *
	 * @param array  $out   symbol_output() result.
	 * @param string $field Field.
	 * @param array  $a     Options: unit, thousands, digits, decimals, label, date_format.
	 * @return string Plain text (not escaped).
	 */
	public static function format_field( array $out, $field, array $a ) {
		if ( 'updated' === $field ) {
			if ( empty( $out['updated'] ) ) {
				return '';
			}
			$text = wp_date( $a['date_format'], (int) $out['updated'] );
			return 'fa' === $a['digits'] ? One_Navasan_Number_Formatter::to_persian_digits( $text ) : $text;
		}
		$number = isset( $out[ $field ] ) ? $out[ $field ] : null;
		if ( null === $number ) {
			return '';
		}
		if ( 'change_pct' !== $field ) {
			$number = $number * self::unit_factor( $a['unit'] );
		}
		$decimals = ( '' === (string) $a['decimals'] ) ? null : (int) $a['decimals'];
		$text     = One_Navasan_Number_Formatter::format( $number, $a['thousands'], $a['digits'], $decimals );
		if ( 'yes' === $a['label'] && ! in_array( $field, array( 'change_pct' ), true ) ) {
			$text .= ' ' . ( 'rial' === $a['unit'] ? __( 'ریال', ONE_NAVASAN_DOMAIN ) : __( 'تومان', ONE_NAVASAN_DOMAIN ) );
		}
		if ( 'change_pct' === $field && 'yes' === $a['label'] ) {
			$text .= '٪';
		}
		return $text;
	}

	/**
	 * Render.
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'symbol'      => '',
				'field'       => 'value',
				'unit'        => 'toman',
				'thousands'   => 'yes',
				'digits'      => 'fa',
				'label'       => 'yes',
				'decimals'    => '',
				'date_format' => 'Y/m/d H:i',
			),
			$atts,
			'one_gold_price'
		);
		$field = in_array( $atts['field'], self::FIELDS, true ) ? $atts['field'] : 'value';
		$out   = One_Navasan_Rate_Service::symbol_output( sanitize_text_field( $atts['symbol'] ) );
		$class = 'one-gold-price one-gold-price--' . sanitize_html_class( $field );
		if ( ! $out ) {
			return '<span class="' . esc_attr( $class ) . ' one-gold-price--empty">—</span>';
		}
		$text = self::format_field(
			$out,
			$field,
			array(
				'unit'        => 'rial' === $atts['unit'] ? 'rial' : 'toman',
				'thousands'   => 'no' !== $atts['thousands'],
				'digits'      => 'en' === $atts['digits'] ? 'en' : 'fa',
				'label'       => 'no' === $atts['label'] ? 'no' : 'yes',
				'decimals'    => $atts['decimals'],
				'date_format' => sanitize_text_field( $atts['date_format'] ),
			)
		);
		return '<span class="' . esc_attr( $class ) . '">' . esc_html( $text ) . '</span>';
	}
}
