<?php
/**
 * Uninstall: remove options, post meta and schedules. Product prices are NOT reverted.
 *
 * @package navasan-api-wordpress-plugin
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Schedules.
wp_clear_scheduled_hook( 'one_navasan_fetch_event' );
wp_clear_scheduled_hook( 'one_navasan_job_event' );
if ( function_exists( 'as_unschedule_all_actions' ) && class_exists( 'ActionScheduler' ) && did_action( 'init' ) ) {
	as_unschedule_all_actions( 'one_navasan_fetch_event' );
	as_unschedule_all_actions( 'one_navasan_job_event' );
}

// Options and transients (all use the one_navasan_ prefix).
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'one\\_navasan\\_%' OR option_name LIKE '\\_transient\\_one\\_navasan\\_%' OR option_name LIKE '\\_transient\\_timeout\\_one\\_navasan\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// Product meta.
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'one\\_navasan\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

if ( is_multisite() ) {
	delete_site_option( 'one_navasan_settings' );
}
