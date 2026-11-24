<?php
/**
 * Plugin bootstrap.
 *
 * @package navasan-api-wordpress-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires every component. WooCommerce parts load only when WooCommerce is active.
 */
class One_Navasan_Plugin {

	/**
	 * Singleton.
	 *
	 * @var One_Navasan_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return One_Navasan_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize components.
	 */
	public function init() {
		One_Navasan_Upgrade::init();
		One_Navasan_Scheduler::init();
		One_Navasan_Shortcode::init();
		One_Navasan_Rest::init();
		One_Navasan_Frontend::init();
		One_Navasan_Elementor::init();

		if ( is_admin() ) {
			One_Navasan_Admin::init();
		}

		if ( class_exists( 'WooCommerce' ) ) {
			One_Navasan_WC_Pricing::init();
			One_Navasan_WC_Product_Fields::init();
		}
	}
}
