<?php
/**
 * Optional light front-end script.
 *
 * @package navasan-api-wordpress-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues the filler script that populates [data-one-gold] elements.
 */
class One_Navasan_Frontend {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Enqueue (only when enabled in settings).
	 */
	public static function enqueue() {
		if ( ! One_Navasan_Settings::get( 'frontend_script' ) ) {
			return;
		}
		wp_enqueue_script(
			'one-navasan-frontend',
			ONE_NAVASAN_URL . 'assets/js/one-navasan-frontend.js',
			array(),
			ONE_NAVASAN_VERSION,
			array( 'in_footer' => true, 'strategy' => 'defer' )
		);
		wp_add_inline_script(
			'one-navasan-frontend',
			'window.oneNavasan=' . wp_json_encode( array( 'rest' => esc_url_raw( rest_url( 'one-navasan/v1/rate' ) ) ) ) . ';',
			'before'
		);
	}
}
