<?php
/**
 * Elementor Pro Dynamic Tags (optional).
 *
 * @package navasan-api-wordpress-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers dynamic tags only when Elementor is active; silent otherwise.
 */
class One_Navasan_Elementor {

	/**
	 * Register hooks.
	 */
	public static function init() {
		// Elementor 3.5+ hook; the old hook is a harmless fallback.
		add_action( 'elementor/dynamic_tags/register', array( __CLASS__, 'register' ) );
		add_action( 'elementor/dynamic_tags/register_tags', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register tags.
	 *
	 * @param object $manager Dynamic tags manager.
	 */
	public static function register( $manager ) {
		if ( ! class_exists( '\Elementor\Core\DynamicTags\Tag' ) || ! is_object( $manager ) || ! method_exists( $manager, 'register' ) ) {
			return;
		}
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		require_once ONE_NAVASAN_DIR . 'includes/class-one-navasan-elementor-tags.php';
		$manager->register( new One_Navasan_Elementor_Text_Tag() );
		$manager->register( new One_Navasan_Elementor_Number_Tag() );
	}
}
