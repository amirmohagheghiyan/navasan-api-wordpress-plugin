<?php
/**
 * Plugin Name:       Navasan-API plugin for WordPress
 * Plugin URI:        https://github.com/amirmohagheghiyan/navasan-api-wordpress-plugin
 * Description:       نرخ لحظه‌ای طلا از پروژهٔ متن‌باز Navasan-API، شورت‌کد و REST برای نمایش نرخ، و قیمت‌گذاری خودکار محصولات ووکامرس بر اساس وزن و عیار.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            one28
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       navasan-api-wordpress-plugin
 * WC requires at least: 7.0
 *
 * Data source: Powered by Navasan-API (https://github.com/HosseinOdd/Navasan-API)
 * by Hossein (HosseinOdd). This plugin only reads the JSON files published in
 * that repository; no code from it is included.
 *
 * @package navasan-api-wordpress-plugin
 */

defined( 'ABSPATH' ) || exit;

define( 'ONE_NAVASAN_VERSION', '1.0.0' );
define( 'ONE_NAVASAN_FILE', __FILE__ );
define( 'ONE_NAVASAN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ONE_NAVASAN_URL', plugin_dir_url( __FILE__ ) );
define( 'ONE_NAVASAN_DOMAIN', 'navasan-api-wordpress-plugin' );

/**
 * Class autoloader: One_Navasan_Foo_Bar => includes/{,core/,woocommerce/}class-one-navasan-foo-bar.php
 *
 * @param string $class_name Class name.
 */
function one_navasan_autoload( $class_name ) {
	if ( 0 !== strpos( $class_name, 'One_Navasan_' ) ) {
		return;
	}
	$file = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';
	foreach ( array( 'includes/', 'includes/core/', 'includes/woocommerce/' ) as $dir ) {
		if ( is_readable( ONE_NAVASAN_DIR . $dir . $file ) ) {
			require_once ONE_NAVASAN_DIR . $dir . $file;
			return;
		}
	}
}
spl_autoload_register( 'one_navasan_autoload' );

// Declare HPOS compatibility.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', ONE_NAVASAN_FILE, true );
		}
	}
);

/**
 * Activation: schedule fetching.
 */
function one_navasan_activate() {
	if ( false === get_option( One_Navasan_Settings::OPTION, false ) ) {
		add_option( One_Navasan_Settings::OPTION, One_Navasan_Settings::defaults(), '', 'no' );
	}
	One_Navasan_Scheduler::schedule();
	// First fetch shortly after activation (not inside the activation request).
	wp_schedule_single_event( time() + 10, One_Navasan_Scheduler::HOOK );
}
register_activation_hook( __FILE__, 'one_navasan_activate' );

/**
 * Deactivation: remove schedules (settings and prices stay).
 */
function one_navasan_deactivate() {
	One_Navasan_Scheduler::unschedule();
	wp_clear_scheduled_hook( One_Navasan_WC_Pricing::JOB_HOOK );
}
register_deactivation_hook( __FILE__, 'one_navasan_deactivate' );

/**
 * Boot the plugin.
 */
function one_navasan_boot() {
	One_Navasan_Plugin::instance()->init();
}
add_action( 'plugins_loaded', 'one_navasan_boot' );
