<?php
/**
 * Tiny rolling log (last 20 lines).
 *
 * @package navasan-api-wordpress-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the most recent log lines in an option. Never receives secrets.
 */
class One_Navasan_Logger {

	const OPTION = 'one_navasan_log';
	const LIMIT  = 20;

	/**
	 * Add a line.
	 *
	 * @param string $level info|warning|error.
	 * @param string $message Message.
	 */
	public static function add( $level, $message ) {
		$log   = self::get();
		$log[] = array(
			'time'    => time(),
			'level'   => (string) $level,
			'message' => self::redact( (string) $message ),
		);
		$log = array_slice( $log, -self::LIMIT );
		update_option( self::OPTION, $log, false );
	}

	/**
	 * Get lines.
	 *
	 * @return array
	 */
	public static function get() {
		$log = get_option( self::OPTION, array() );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * Remove the API key from any text.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function redact( $text ) {
		$key = (string) One_Navasan_Settings::get( 'api_key' );
		if ( '' !== $key ) {
			$text = str_replace( array( $key, rawurlencode( $key ), urlencode( $key ) ), '***', $text );
		}
		return $text;
	}
}
