<?php
/**
 * Upgrade scenario, stage 2: runs with the NEW plugin version (the built zip, extracted over the old folder).
 *
 * Checks that nothing was lost or changed by the upgrade, then switches the source in the
 * "dashboard" and checks that prices are recalculated correctly.
 * Mode (/one-mode/mode.txt): upgrade | upgrade-manual. Writes /one-mode/result.log.
 *
 * PHP 7.4 compatible.
 */

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

$one_mode = is_readable( '/one-mode/mode.txt' ) ? trim( file_get_contents( '/one-mode/mode.txt' ) ) : 'upgrade';
$manual   = ( 'upgrade-manual' === $one_mode );
// A real page load: the new code boots through plugins_loaded and the upgrade routine runs by itself.
require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require __DIR__ . '/one-navasan-mock.php';

ob_start();
$GLOBALS['one_pass'] = 0;
$GLOBALS['one_fail'] = 0;
function one_t( $name, $cond, $detail = '' ) {
	if ( $cond ) {
		$GLOBALS['one_pass']++;
		echo "PASS  $name\n";
	} else {
		$GLOBALS['one_fail']++;
		echo "FAIL  $name  $detail\n";
	}
}
function one_finish() {
	echo 'RESULT: ' . $GLOBALS['one_pass'] . ' pass, ' . $GLOBALS['one_fail'] . " fail\n";
	file_put_contents( '/one-mode/result.log', ob_get_contents() );
	exit( $GLOBALS['one_fail'] ? 1 : 0 );
}
function one_near( $a, $b, $eps = 0.01 ) {
	return abs( (float) $a - (float) $b ) <= $eps;
}
/** The snapshot went through JSON (18.0 became 18), so values are compared by their JSON form. */
function one_same( $a, $b ) {
	return wp_json_encode( $a ) === wp_json_encode( $b );
}

echo "== UPGRADE STAGE 2 ($one_mode): new version over the old one ==\n";
$snap = json_decode( (string) file_get_contents( '/one-mode/stage1.json' ), true );
one_t( 'snapshot of the previous version is readable', is_array( $snap ) && $snap['mode'] === $one_mode );
$plugin = 'navasan-api-wordpress-plugin/navasan-api-wordpress-plugin.php';

// ---- Version and folder ----------------------------------------------------------------
one_t( 'new version is 1.1.0', '1.1.0' === ONE_NAVASAN_VERSION, ONE_NAVASAN_VERSION );
one_t( 'still the same folder, file and text domain', is_plugin_active( $plugin ) && 'navasan-api-wordpress-plugin' === ONE_NAVASAN_DOMAIN );
one_t( 'new provider layer is loaded', class_exists( 'One_Navasan_Provider_Priceto' ) && class_exists( 'One_Navasan_Provider_Navasan' ) && class_exists( 'One_Navasan_Upgrade' ) );
one_t( 'the zip carries no test or dev files', ! is_dir( WP_PLUGIN_DIR . '/navasan-api-wordpress-plugin/tests' ) && ! file_exists( WP_PLUGIN_DIR . '/navasan-api-wordpress-plugin/composer.json' ) );
one_t( 'upgrade routine ran on load', '1.1.0' === get_option( One_Navasan_Upgrade::VERSION_OPTION ) );

// ---- Settings ----------------------------------------------------------------------------
$old = $snap['settings'];
$now = get_option( One_Navasan_Settings::OPTION );
one_t( 'rate_mode converted to the equivalent source', $now['rate_source'] === ( $manual ? 'manual' : 'navasan' ), (string) $now['rate_source'] );
one_t( 'upgrade does not add a backup source', 'none' === $now['backup_source'] );
one_t( 'rate_mode key is gone', ! array_key_exists( 'rate_mode', $now ) );
$same = true;
$diff = array();
foreach ( $old as $key => $value ) {
	if ( 'rate_mode' === $key ) {
		continue;
	}
	if ( ! array_key_exists( $key, $now ) || ! one_same( $now[ $key ], $value ) ) {
		$same   = false;
		$diff[] = $key;
	}
}
one_t( 'every other stored setting is exactly as before', $same, implode( ',', $diff ) );
one_t( 'settings read through the API match too', one_near( One_Navasan_Settings::get( 'adjust_value' ), 1.5 ) && 'percent' === One_Navasan_Settings::get( 'adjust_mode' ) && 45 === (int) One_Navasan_Settings::get( 'interval' ) && 1000 === (int) One_Navasan_Settings::get( 'round_step' ) );
one_t( 'new settings have their defaults', 'gold-18k' === One_Navasan_Settings::get( 'base_symbol_priceto' ) && 1 === (int) One_Navasan_Settings::get( 'priceto_fetch_change' ) && '18ayar' === One_Navasan_Settings::get( 'base_symbol' ) );

// ---- Rate state, log, range ------------------------------------------------------------------
$st = get_option( 'one_navasan_state' );
one_t( 'stored rate data kept (symbols, time, path, base symbol)', one_same( $st['symbols'], $snap['state']['symbols'] ) && $st['fetched_at'] === $snap['state']['fetched_at'] && $st['source'] === $snap['state']['source'] && $st['base_symbol'] === $snap['state']['base_symbol'] );
one_t( 'stored rate is marked as coming from Navasan-API', 'navasan' === $st['provider'] && false === $st['via_backup'] );
one_t( 'daily high/low kept', one_same( get_option( 'one_navasan_daily' ), $snap['daily'] ) );
$log     = get_option( 'one_navasan_log' );
$log_old = $snap['log'];
one_t( 'log kept, upgrade line appended', one_same( array_slice( $log, 0, count( $log_old ) ), $log_old ) && count( $log ) === count( $log_old ) + 1 && false !== strpos( end( $log )['message'], '1.1.0' ) );
one_t( 'effective and store rate unchanged', one_near( One_Navasan_Rate_Service::effective_toman(), $snap['effective'], 0.001 ) && one_near( One_Navasan_Rate_Service::store_rate(), $snap['store_rate'], 0.001 ) );

// ---- Pricing state and prices ------------------------------------------------------------------
$price = function ( $id ) {
	return (float) get_post_meta( $id, '_regular_price', true );
};
$prices_same = true;
foreach ( $snap['ids'] as $key => $id ) {
	$prices_same = $prices_same && get_post_meta( $id, '_regular_price', true ) === $snap['prices'][ $key ]['regular'] && get_post_meta( $id, '_price', true ) === $snap['prices'][ $key ]['price'];
}
one_t( 'no product price was touched by the upgrade', $prices_same );
one_t( 'preview-seen and prices-enabled state kept', get_option( One_Navasan_WC_Pricing::ENABLED ) == $snap['enabled'] && get_option( One_Navasan_WC_Pricing::PREVIEW_DONE ) == $snap['preview_done'] );
one_t( 'applied pricing hash kept and still current', get_option( One_Navasan_WC_Pricing::APPLIED_HASH ) === $snap['applied_hash'] && One_Navasan_WC_Pricing::pricing_hash() === $snap['pricing_hash'] );
One_Navasan_WC_Pricing::maybe_start_recalculation();
one_t( 'upgrade starts no recalculation job', ! get_option( One_Navasan_WC_Pricing::JOB ) );
one_t( 'last pricing report kept', one_same( get_option( One_Navasan_WC_Pricing::REPORT ), $snap['report'] ) );
one_t( 'fetch schedule still exists', (bool) wp_next_scheduled( One_Navasan_Scheduler::HOOK ) === $snap['schedule_next'] );

// ---- What visitors see ---------------------------------------------------------------------------
foreach ( array( 'default', 'high', 'low', 'pct', 'sekkeh', '18ayar' ) as $k ) {
	$shortcodes = array(
		'default' => '[one_gold_price]',
		'high'    => '[one_gold_price field="high" digits="en"]',
		'low'     => '[one_gold_price field="low" digits="en"]',
		'pct'     => '[one_gold_price field="change_pct" digits="en"]',
		'sekkeh'  => '[one_gold_price symbol="sekkeh" digits="en" label="no"]',
		'18ayar'  => '[one_gold_price symbol="18ayar" unit="rial"]',
	);
	one_t( "shortcode output unchanged: $k", do_shortcode( $shortcodes[ $k ] ) === $snap['shortcodes'][ $k ], do_shortcode( $shortcodes[ $k ] ) . ' vs ' . $snap['shortcodes'][ $k ] );
}
$rest = rest_do_request( new WP_REST_Request( 'GET', '/one-navasan/v1/rate' ) )->get_data();
$rest_same = true;
foreach ( array( 'symbol', 'unit', 'mode', 'value', 'change_val', 'change_pct', 'high', 'low', 'updated', 'fetched_at' ) as $field ) {
	$rest_same = $rest_same && array_key_exists( $field, $rest ) && one_same( $rest[ $field ], $snap['rest'][ $field ] );
}
one_t( 'REST fields unchanged', $rest_same );
one_t( 'REST only gains the source field', array_keys( $rest ) === array_merge( array_slice( array_keys( $snap['rest'] ), 0, 3 ), array( 'source' ), array_slice( array_keys( $snap['rest'] ), 3 ) ) && ( $manual ? 'manual' : 'navasan' ) === $rest['source'], wp_json_encode( array_keys( $rest ) ) );
one_t( 'the light script data attributes are unchanged', 1 === (int) One_Navasan_Settings::get( 'frontend_script' ) );

// ---- One-time notice -----------------------------------------------------------------------------
wp_set_current_user( 1 );
one_t( 'upgrade notice is queued', (bool) get_option( One_Navasan_Upgrade::NOTICE_OPTION ) );
ob_start();
One_Navasan_Upgrade::show_notice();
$notice = ob_get_clean();
one_t( 'upgrade notice names the new source and the settings page', false !== strpos( $notice, 'priceto.day' ) && false !== strpos( $notice, 'one-navasan' ), $notice );
ob_start();
One_Navasan_Upgrade::show_notice();
$again = ob_get_clean();
one_t( 'upgrade notice is shown only once', '' === trim( $again ) && ! get_option( One_Navasan_Upgrade::NOTICE_OPTION ) );
$before = get_option( One_Navasan_Settings::OPTION );
One_Navasan_Upgrade::maybe_run();
one_t( 'upgrade is idempotent', one_same( get_option( One_Navasan_Settings::OPTION ), $before ) && ! get_option( One_Navasan_Upgrade::NOTICE_OPTION ) );

// ---- Settings page after the upgrade ----------------------------------------------------------------
ob_start();
One_Navasan_Admin::render();
$html = ob_get_clean();
$pos  = strpos( $html, 'id="one-f-rate_source"' );
$sel  = substr( $html, $pos, (int) strpos( $html, '</select>', $pos ) - $pos );
one_t( 'dashboard shows the converted source selected', 1 === preg_match( '/value="' . ( $manual ? 'manual' : 'navasan' ) . '"[^>]*selected/', $sel ), $sel );
// Saving the page form again keeps everything.
$posted = One_Navasan_Settings::sanitize( One_Navasan_Settings::all() );
one_t( 'saving the form after the upgrade keeps source and settings', $posted['rate_source'] === ( $manual ? 'manual' : 'navasan' ) && $posted['profit_pct'] == $old['profit_pct'] && $posted['round_step'] == $old['round_step'] );

if ( $manual ) {
	$GLOBALS['one_requests'] = array();
	$r = One_Navasan_Rate_Service::refresh();
	one_t( 'manual site stays manual: no fetching after the upgrade', 'manual' === $r['status'] && 0 === count( $GLOBALS['one_requests'] ) );
}

// ---- Changing the source in the dashboard recalculates prices correctly -----------------------------------
function one_set( array $changes ) {
	update_option( One_Navasan_Settings::OPTION, One_Navasan_Settings::sanitize( array_merge( One_Navasan_Settings::all(), $changes, array( 'scope' => 'all' ) ) ) );
}
$exp = function ( $rate, $bk, $k, $w, $wage_pct, $profit, $tax, $extra, $step, $fixed_wage = null ) {
	$gold = $w * $rate * $k / $bk;
	$wage = null === $fixed_wage ? $gold * $wage_pct / 100 : $fixed_wage;
	$p    = ( $gold + $wage ) * $profit / 100;
	$t    = ( $wage + $p ) * $tax / 100;
	return $step ? round( ( $gold + $wage + $p + $t + $extra ) / $step ) * $step : round( $gold + $wage + $p + $t + $extra );
};
$run_job = function () {
	$guard = 0;
	while ( get_option( One_Navasan_WC_Pricing::JOB ) && $guard++ < 30 ) {
		One_Navasan_WC_Pricing::background_step();
	}
	return ! get_option( One_Navasan_WC_Pricing::JOB );
};
$ids = $snap['ids'];

// The dashboard: priceto.day as the main source, Navasan-API as backup.
$GLOBALS['one_fixture_priceto']['value'] = 25670800;
$GLOBALS['one_fixture_priceto']['date']  = time() - 120;
one_set( array( 'rate_source' => 'priceto', 'backup_source' => 'navasan' ) );
one_t( 'source change schedules an immediate refresh', (bool) wp_next_scheduled( One_Navasan_Scheduler::NOW_HOOK ) );
$r = One_Navasan_Rate_Service::refresh();
one_t( 'new source delivers after the upgrade (+2.5% vs old rate: allowed)', $r['ok'] && 'priceto' === One_Navasan_Rate_Service::current_source()['id'], wp_json_encode( $r ) );
$rate = 25670800 * 1.015; // The 1.5% adjustment from the old settings still applies.
one_t( 'rate is the new source rate with the old adjustment', one_near( One_Navasan_Rate_Service::effective_toman(), $rate, 0.5 ), (string) One_Navasan_Rate_Service::effective_toman() );
$job = get_option( One_Navasan_WC_Pricing::JOB );
one_t( 'rate change queued the background recalculation', is_array( $job ) && 'running' === $job['status'] );
one_t( 'background recalculation finished', $run_job() );
one_t( 'A: recalculated with the new rate', one_near( $price( $ids['A'] ), $exp( $rate, 18, 18, 1.22, 10, 7, 9, 0, 1000 ) ), (string) $price( $ids['A'] ) . ' vs ' . $exp( $rate, 18, 18, 1.22, 10, 7, 9, 0, 1000 ) );
one_t( 'C: product fields recalculated', one_near( $price( $ids['C'] ), $exp( $rate, 18, 21, 3.5, 0, 5, 9, 1000000, 1000, 500000 ) ), (string) $price( $ids['C'] ) );
one_t( 'variations recalculated', one_near( $price( $ids['small'] ), $exp( $rate, 18, 18, 1, 10, 7, 9, 0, 1000 ) ) && one_near( $price( $ids['large'] ), $exp( $rate, 18, 18, 2, 10, 7, 9, 0, 1000 ) ) );
one_t( 'product without weight still untouched', one_near( $price( $ids['D'] ), 303436481.6 ) );
one_t( 'prices really changed against the pre-upgrade values', ! one_near( $price( $ids['A'] ), (float) $snap['prices']['A']['regular'], 1 ) );
$o = One_Navasan_Rate_Service::symbol_output();
one_t( 'priceto high/low and source now reach visitors', 'priceto' === $o['source'] && one_near( $o['high'], 25821600 * 1.015, 0.5 ) && one_near( $o['low'], 24088100 * 1.015, 0.5 ) );
one_t( 'old shortcode symbol still resolves to the base rate', do_shortcode( '[one_gold_price symbol="18ayar" digits="en" label="no"]' ) === do_shortcode( '[one_gold_price digits="en" label="no"]' ) );

// A big source difference is held, and prices do not move until it is approved.
$held_price = $price( $ids['A'] );
$GLOBALS['one_fixture_priceto']['value'] = 30000000; // +17% against the stored rate.
$GLOBALS['one_fixture_priceto']['date']  = time() - 60;
$r = One_Navasan_Rate_Service::refresh();
$p = One_Navasan_Rate_Service::pending();
one_t( 'large jump is held, reason depends on the unchanged source', 'pending' === $r['status'] && $p && 'jump' === $p['reason'] );
one_t( 'held rate starts no recalculation and moves no price', ! get_option( One_Navasan_WC_Pricing::JOB ) && one_near( $price( $ids['A'] ), $held_price ) );
one_t( 'approving applies it and recalculates', One_Navasan_Rate_Service::approve_pending() && $run_job() && one_near( $price( $ids['A'] ), $exp( 30000000 * 1.015, 18, 18, 1.22, 10, 7, 9, 0, 1000 ) ) );

// Back to a manual rate: prices follow.
one_set( array( 'rate_source' => 'manual', 'manual_rate' => '28000000' ) );
one_t( 'manual rate recalculates too', $run_job() && one_near( $price( $ids['A'] ), $exp( 28000000, 18, 18, 1.22, 10, 7, 9, 0, 1000 ) ), (string) $price( $ids['A'] ) );

one_finish();
