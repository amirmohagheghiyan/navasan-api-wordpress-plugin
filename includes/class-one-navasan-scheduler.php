<?php
/**
 * Scheduling (Action Scheduler when present, otherwise WP-Cron).
 *
 * @package navasan-api-wordpress-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Recurring fetch.
 */
class One_Navasan_Scheduler {

	const HOOK     = 'one_navasan_fetch_event';
	const SCHEDULE = 'one_navasan_interval';
	const SIG      = 'one_navasan_schedule_sig';
	const GROUP    = 'one-navasan';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_schedule' ) );
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_action( 'init', array( __CLASS__, 'ensure' ), 30 );
		add_action( 'update_option_' . One_Navasan_Settings::OPTION, array( __CLASS__, 'on_settings_updated' ), 10, 2 );
	}

	/**
	 * Interval in seconds.
	 *
	 * @return int
	 */
	public static function interval_seconds() {
		return max( 5, (int) One_Navasan_Settings::get( 'interval' ) ) * MINUTE_IN_SECONDS;
	}

	/**
	 * Register the custom WP-Cron interval.
	 *
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public static function add_schedule( $schedules ) {
		$schedules[ self::SCHEDULE ] = array(
			'interval' => self::interval_seconds(),
			'display'  => __( 'فاصلهٔ به‌روزرسانی نرخ نوسان', ONE_NAVASAN_DOMAIN ),
		);
		return $schedules;
	}

	/**
	 * Whether Action Scheduler is usable.
	 *
	 * @return bool
	 */
	public static function uses_action_scheduler() {
		return function_exists( 'as_schedule_recurring_action' ) && function_exists( 'as_has_scheduled_action' ) && class_exists( 'ActionScheduler' ) && did_action( 'init' );
	}

	/**
	 * Signature of what is scheduled.
	 *
	 * @return string
	 */
	private static function signature() {
		return md5( self::interval_seconds() . '|' . ( self::uses_action_scheduler() ? 'as' : 'cron' ) );
	}

	/**
	 * Make sure the event exists with the right interval.
	 */
	public static function ensure() {
		$scheduled = self::uses_action_scheduler() ? as_has_scheduled_action( self::HOOK ) : (bool) wp_next_scheduled( self::HOOK );
		if ( ! $scheduled || get_option( self::SIG ) !== self::signature() ) {
			self::schedule();
		}
	}

	/**
	 * (Re)schedule.
	 */
	public static function schedule() {
		self::unschedule();
		if ( self::uses_action_scheduler() ) {
			as_schedule_recurring_action( time() + 60, self::interval_seconds(), self::HOOK, array(), self::GROUP );
		} else {
			wp_schedule_event( time() + 60, self::SCHEDULE, self::HOOK );
		}
		update_option( self::SIG, self::signature(), false );
	}

	/**
	 * Remove all schedules.
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
		if ( function_exists( 'as_unschedule_all_actions' ) && class_exists( 'ActionScheduler' ) && did_action( 'init' ) ) {
			as_unschedule_all_actions( self::HOOK );
		}
	}

	/**
	 * When settings change: reschedule if needed and react to pricing-relevant changes.
	 *
	 * @param mixed $old Old settings.
	 * @param mixed $new New settings.
	 */
	public static function on_settings_updated( $old, $new ) {
		self::schedule();
		if ( class_exists( 'One_Navasan_WC_Pricing' ) && class_exists( 'WooCommerce' ) ) {
			One_Navasan_WC_Pricing::maybe_start_recalculation();
		}
	}

	/**
	 * Cron/Action Scheduler callback.
	 */
	public static function run() {
		if ( 'manual' === One_Navasan_Settings::get( 'rate_mode' ) ) {
			return;
		}
		One_Navasan_Rate_Service::refresh();
	}

	/**
	 * Timestamp of the next run or null.
	 *
	 * @return int|null
	 */
	public static function next_run() {
		if ( self::uses_action_scheduler() && function_exists( 'as_next_scheduled_action' ) ) {
			$next = as_next_scheduled_action( self::HOOK );
			if ( is_int( $next ) ) {
				return $next;
			}
		}
		$ts = wp_next_scheduled( self::HOOK );
		return $ts ? (int) $ts : null;
	}
}
