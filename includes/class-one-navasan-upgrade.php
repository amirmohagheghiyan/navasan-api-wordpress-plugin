<?php
/**
 * Upgrade routine between plugin versions.
 *
 * @package navasan-api-wordpress-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Runs once per version bump: converts stored 1.0.0 data to the 1.1.0 layout without touching
 * product prices, the pricing state, the log or the daily range.
 */
class One_Navasan_Upgrade {

	const VERSION_OPTION = 'one_navasan_db_version';
	const NOTICE_OPTION  = 'one_navasan_upgrade_notice';
	const TARGET         = '1.1.0';

	/**
	 * Register hooks.
	 */
	public static function init() {
		self::maybe_run();
		if ( is_admin() ) {
			add_action( 'admin_notices', array( __CLASS__, 'show_notice' ) );
		}
	}

	/**
	 * Run when the stored version is older than the target.
	 */
	public static function maybe_run() {
		$current = get_option( self::VERSION_OPTION, '' );
		if ( '' !== $current && version_compare( (string) $current, self::TARGET, '>=' ) ) {
			return;
		}
		self::run();
	}

	/**
	 * The upgrade itself (idempotent).
	 */
	public static function run() {
		$saved     = get_option( One_Navasan_Settings::OPTION, null );
		// A 1.0.0 install stored settings without "rate_source"; a fresh install already has the new layout.
		$had_1_0_0 = is_array( $saved ) && $saved && One_Navasan_Settings_Migrator::needs_migration( $saved );

		if ( $had_1_0_0 ) {
			// rate_mode auto -> source Navasan-API, manual -> manual; every other setting is kept.
			update_option( One_Navasan_Settings::OPTION, One_Navasan_Settings_Migrator::migrate( $saved ), 'no' );
		}

		// The stored rate came from Navasan-API; mark it so source changes can be detected.
		$state = get_option( One_Navasan_Rate_Service::STATE, null );
		if ( is_array( $state ) && ! empty( $state['symbols'] ) && empty( $state['provider'] ) ) {
			$state['provider']   = 'navasan';
			$state['via_backup'] = false;
			update_option( One_Navasan_Rate_Service::STATE, $state, false );
		}
		$pending = get_option( One_Navasan_Rate_Service::PENDING, null );
		if ( is_array( $pending ) && ! empty( $pending['symbols'] ) && empty( $pending['provider'] ) ) {
			$pending['provider']   = 'navasan';
			$pending['via_backup'] = false;
			$pending['reason']     = 'jump';
			update_option( One_Navasan_Rate_Service::PENDING, $pending, false );
		}

		if ( $had_1_0_0 ) {
			update_option( self::NOTICE_OPTION, self::TARGET, false );
		}
		update_option( self::VERSION_OPTION, self::TARGET, false );
		One_Navasan_Logger::add( 'info', 'Upgraded to ' . self::TARGET . ( $had_1_0_0 ? ' (settings converted, source unchanged).' : ' (fresh install).' ) );
	}

	/**
	 * Print the one-time notice and forget it.
	 */
	public static function show_notice() {
		if ( ! get_option( self::NOTICE_OPTION ) || ! current_user_can( One_Navasan_Settings::capability() ) ) {
			return;
		}
		delete_option( self::NOTICE_OPTION );
		printf(
			'<div class="notice notice-info is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'نسخهٔ ۱٫۱ افزونهٔ نرخ طلا: منبع priceto.day اضافه شد (بازار tgju.org، معمولاً تازه‌تر از Navasan-API). منبع فعلی شما همان قبلی (Navasan-API) مانده است؛ برای تغییر، «منبع نرخ» و «منبع پشتیبان» را از صفحهٔ تنظیمات انتخاب کنید.', ONE_NAVASAN_DOMAIN ),
			esc_url( One_Navasan_Admin::page_url() ),
			esc_html__( 'باز کردن تنظیمات', ONE_NAVASAN_DOMAIN )
		);
	}
}
