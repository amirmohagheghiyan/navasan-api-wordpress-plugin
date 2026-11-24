<?php
/**
 * Integration scenario executed inside WordPress Playground (no Docker).
 *
 * Mode is read from /one-mode/mode.txt (mounted by the runner): irt | irr | nowc.
 * Prints PASS/FAIL lines and a final "RESULT:" line.
 */

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

$one_mode = is_readable( '/one-mode/mode.txt' ) ? trim( file_get_contents( '/one-mode/mode.txt' ) ) : 'irt';
require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

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
/**
 * Print the summary, persist the whole output to the mounted folder and exit.
 */
function one_finish() {
	echo 'RESULT: ' . $GLOBALS['one_pass'] . ' pass, ' . $GLOBALS['one_fail'] . " fail\n";
	$out = ob_get_contents();
	if ( is_dir( '/one-mode' ) ) {
		file_put_contents( '/one-mode/result.log', $out );
	}
	exit( $GLOBALS['one_fail'] ? 1 : 0 );
}
function one_near( $a, $b, $eps = 0.01 ) {
	return abs( (float) $a - (float) $b ) <= $eps;
}

// ---- Mock both sources (deterministic) ------------------------------------
require __DIR__ . '/one-navasan-mock.php';

echo "== MODE: $one_mode ==\n";
$has_wc = class_exists( 'WooCommerce' );
one_t( 'WooCommerce presence matches mode', ( 'nowc' === $one_mode ) ? ! $has_wc : $has_wc );
if ( $has_wc ) {
	update_option( 'woocommerce_currency', 'irr' === $one_mode ? 'IRR' : 'IRT' );
	update_option( 'woocommerce_price_num_decimals', 0 );
}

// ---- 1. Activation ----------------------------------------------------------
$plugin = 'navasan-api-wordpress-plugin/navasan-api-wordpress-plugin.php';
$act    = activate_plugin( $plugin );
one_t( 'activation without error', ! is_wp_error( $act ), is_wp_error( $act ) ? $act->get_error_message() : '' );
one_t( 'plugin is active', is_plugin_active( $plugin ) );
// The plugin was activated after plugins_loaded in this single request, so boot it by hand.
one_navasan_boot();
one_t( 'class autoloads', class_exists( 'One_Navasan_Rate_Service' ) );
one_t( 'fetch event scheduled', (bool) wp_next_scheduled( One_Navasan_Scheduler::HOOK ) );
one_t( 'capability matches WooCommerce presence', One_Navasan_Settings::capability() === ( $has_wc ? 'manage_woocommerce' : 'manage_options' ) );
one_t( 'WC pricing class only with WooCommerce', $has_wc === class_exists( 'One_Navasan_WC_Pricing', false ) || ! $has_wc );

// Settings used by the scenario.
function one_set( array $changes ) {
	$new = One_Navasan_Settings::sanitize( array_merge( One_Navasan_Settings::all(), $changes, array( 'scope' => 'all' ) ) );
	update_option( One_Navasan_Settings::OPTION, $new );
}
$fresh = One_Navasan_Settings::all();
one_t( 'fresh install defaults: priceto primary, Navasan-API backup', 'priceto' === $fresh['rate_source'] && 'navasan' === $fresh['backup_source'], wp_json_encode( array( $fresh['rate_source'], $fresh['backup_source'] ) ) );
one_t( 'fresh install base symbols per source', 'gold-18k' === $fresh['base_symbol_priceto'] && '18ayar' === $fresh['base_symbol'] );
one_t( 'fresh install: version stored, no upgrade notice', One_Navasan_Upgrade::TARGET === get_option( One_Navasan_Upgrade::VERSION_OPTION ) && ! get_option( One_Navasan_Upgrade::NOTICE_OPTION ) );
one_t( 'no legacy rate_mode key', ! array_key_exists( 'rate_mode', $fresh ) );

// Everything below the sources section runs on the Navasan-API source (as in 1.0.0); the sources section tests the rest.
one_set( array( 'rate_source' => 'navasan', 'backup_source' => 'none', 'profit_pct' => 7, 'tax_pct' => 9, 'wage_pct' => 10, 'round_step' => 1000, 'round_dir' => 'nearest', 'max_jump_pct' => 10, 'stale_hours' => 6 ) );

// ---- 2. Fetch ---------------------------------------------------------------
$r = One_Navasan_Rate_Service::refresh();
one_t( 'fetch ok', $r['ok'], wp_json_encode( $r ) );
one_t( 'effective rate = 25035780 toman', one_near( One_Navasan_Rate_Service::effective_toman(), 25035780 ) );
$mult = 'irr' === $one_mode ? 10 : 1;
one_t( 'store rate converted by currency', one_near( One_Navasan_Rate_Service::store_rate(), 25035780 * $mult ), (string) One_Navasan_Rate_Service::store_rate() );
one_t( 'string value key (usd_xau) readable', one_near( One_Navasan_Rate_Service::symbol_output( 'usd_xau' )['value'], 3630.66 ) );
one_t( 'daily high/low tracked', null !== One_Navasan_Rate_Service::symbol_output()['high'] );

// ---- 3. REST + shortcode (also without WooCommerce) --------------------------
$req = new WP_REST_Request( 'GET', '/one-navasan/v1/rate' );
$res = rest_do_request( $req );
$d   = $res->get_data();
one_t( 'REST status 200', 200 === $res->get_status() );
one_t( 'REST value', isset( $d['value'] ) && one_near( $d['value'], 25035780 ) );
one_t( 'REST has no secrets', false === strpos( wp_json_encode( $d ), 'api_key' ) && ! isset( $d['settings'] ) );
$req2 = new WP_REST_Request( 'GET', '/one-navasan/v1/rate' );
$req2->set_param( 'unit', 'rial' );
one_t( 'REST rial unit', one_near( rest_do_request( $req2 )->get_data()['value'], 250357800 ) );
one_t( 'shortcode value (persian digits + label)', do_shortcode( '[one_gold_price]' ) === '<span class="one-gold-price one-gold-price--value">۲۵٬۰۳۵٬۷۸۰ تومان</span>', do_shortcode( '[one_gold_price]' ) );
one_t( 'shortcode latin plain', false !== strpos( do_shortcode( '[one_gold_price digits="en" thousands="no" label="no"]' ), '>25035780<' ) );
one_t( 'shortcode other symbol', false !== strpos( do_shortcode( '[one_gold_price symbol="sekkeh" digits="en" label="no"]' ), '257,500,000' ) );
one_t( 'shortcode updated field', false !== strpos( do_shortcode( '[one_gold_price field="updated" digits="en"]' ), '/' ) );
one_t( 'shortcode unknown symbol is dash', false !== strpos( do_shortcode( '[one_gold_price symbol="nope"]' ), '—' ) );

// ---- 4. Guards ----------------------------------------------------------------
$GLOBALS['one_fixture']['fail'] = true;
$r = One_Navasan_Rate_Service::refresh();
one_t( 'failed fetch keeps last rate', ! $r['ok'] && one_near( One_Navasan_Rate_Service::effective_toman(), 25035780 ) );
one_t( 'last error recorded', '' !== (string) get_option( One_Navasan_Rate_Service::ERROR ) );
$GLOBALS['one_fixture']['fail'] = false;
$GLOBALS['one_fixture']['value'] = 0;
$r = One_Navasan_Rate_Service::refresh();
one_t( 'zero rate rejected', ! $r['ok'] && one_near( One_Navasan_Rate_Service::effective_toman(), 25035780 ) );
$GLOBALS['one_fixture']['raw'] = '<html>blocked</html>';
$r = One_Navasan_Rate_Service::refresh();
one_t( 'broken JSON rejected', ! $r['ok'] );
$GLOBALS['one_fixture']['raw']   = null;
$GLOBALS['one_fixture']['value'] = 50071560; // +100%.
$r = One_Navasan_Rate_Service::refresh();
one_t( 'big jump held as pending', 'pending' === $r['status'] && one_near( One_Navasan_Rate_Service::effective_toman(), 25035780 ) && null !== One_Navasan_Rate_Service::pending() );
one_t( 'approve pending applies it', One_Navasan_Rate_Service::approve_pending() && one_near( One_Navasan_Rate_Service::effective_toman(), 50071560 ) && null === One_Navasan_Rate_Service::pending() );
$GLOBALS['one_fixture']['value'] = 25035780;
one_set( array( 'max_jump_pct' => 0 ) ); // Allow the way back.
One_Navasan_Rate_Service::refresh();
one_set( array( 'max_jump_pct' => 10 ) );
one_t( 'rate restored', one_near( One_Navasan_Rate_Service::effective_toman(), 25035780 ) );
// Staleness only counts market-open hours (Tehran time), so it is judged at fixed moments: 2026-11-24 is a Tuesday.
$tehran = function ( $day, $hour, $minute = 0 ) {
	return gmmktime( $hour, $minute, 0, 11, $day, 2026 ) - 12600;
};
$GLOBALS['one_fixture']['date'] = $tehran( 24, 8 );
One_Navasan_Rate_Service::refresh();
one_t( 'stale data flagged after 8 open hours, rate kept', One_Navasan_Rate_Service::is_stale( $tehran( 24, 16 ) ) && one_near( One_Navasan_Rate_Service::effective_toman(), 25035780 ) );
one_t( 'not stale after 2 open hours', ! One_Navasan_Rate_Service::is_stale( $tehran( 24, 10 ) ) );
$GLOBALS['one_fixture']['date'] = $tehran( 26, 13 ); // Thursday 13:00.
One_Navasan_Rate_Service::refresh();
one_t( 'Friday and Saturday morning do not raise the stale warning', ! One_Navasan_Rate_Service::is_stale( $tehran( 27, 20 ) ) && ! One_Navasan_Rate_Service::is_stale( $tehran( 28, 12 ) ) );
one_t( 'but a silent Saturday afternoon does', One_Navasan_Rate_Service::is_stale( $tehran( 28, 15 ) ) );
$GLOBALS['one_fixture']['date'] = time() - 600;
One_Navasan_Rate_Service::refresh();
one_t( 'fresh data not stale', ! One_Navasan_Rate_Service::is_stale() );

// ---- 4b. Sources: priceto.day, backup rule, cooldown, source change, manual -----------------
$cd_key = function ( $url ) {
	return 'one_navasan_cd_' . md5( $url );
};
$reqs_to = function ( $needle ) {
	return count( array_filter( $GLOBALS['one_requests'], function ( $r ) use ( $needle ) {
		return false !== strpos( $r['url'], $needle );
	} ) );
};
// A. priceto.day as primary, Navasan-API as backup.
wp_clear_scheduled_hook( One_Navasan_Scheduler::NOW_HOOK );
one_set( array( 'rate_source' => 'priceto', 'backup_source' => 'navasan' ) );
one_t( 'changing the source schedules an immediate refresh', (bool) wp_next_scheduled( One_Navasan_Scheduler::NOW_HOOK ) );
$GLOBALS['one_requests']            = array();
$GLOBALS['one_fixture_priceto']['date'] = time() - 300;
$r = One_Navasan_Rate_Service::refresh();
one_t( 'priceto: refresh ok', $r['ok'], wp_json_encode( $r ) );
one_t( 'priceto: Rial converted to Toman', one_near( One_Navasan_Rate_Service::effective_toman(), 25670800 ), (string) One_Navasan_Rate_Service::effective_toman() );
$src = One_Navasan_Rate_Service::current_source();
one_t( 'priceto: current source recorded', 'priceto' === $src['id'] && ! $src['via_backup'] );
one_t( 'priceto: only two requests (latest + change)', 2 === count( $GLOBALS['one_requests'] ) && 1 === $reqs_to( '/v1/latest/irr/gold-18k' ) && 1 === $reqs_to( '/v1/change/irr/gold-18k' ), wp_json_encode( $GLOBALS['one_requests'] ) );
one_t( 'priceto: Navasan-API not contacted while the primary works', 0 === $reqs_to( 'Navasan-API' ) );
$o = One_Navasan_Rate_Service::symbol_output();
one_t( 'priceto: high and low come from the source', one_near( $o['high'], 25821600 ) && one_near( $o['low'], 24088100 ), wp_json_encode( array( $o['high'], $o['low'] ) ) );
$rial = 256708000;
one_t( 'priceto: day change from /change in Toman', one_near( $o['change_val'], (int) round( $rial - $rial / 1.0972 ) / 10, 0.5 ) && one_near( $o['change_pct'], 9.7236, 0.0001 ), wp_json_encode( array( $o['change_val'], $o['change_pct'] ) ) );
one_t( 'priceto: output source field', 'priceto' === $o['source'] );
$d = rest_do_request( new WP_REST_Request( 'GET', '/one-navasan/v1/rate' ) )->get_data();
one_t( 'priceto: REST keeps its shape and adds source', 'priceto' === $d['source'] && one_near( $d['value'], 25670800 ) && isset( $d['high'], $d['low'], $d['change_val'], $d['change_pct'], $d['updated'], $d['stale'], $d['mode'] ) );
one_t( 'priceto: shortcode default', false !== strpos( do_shortcode( '[one_gold_price digits="en" label="no"]' ), '25,670,800' ) );
one_t( 'priceto: shortcode high field from the source', false !== strpos( do_shortcode( '[one_gold_price field="high" digits="en" label="no"]' ), '25,821,600' ) );
one_t( 'priceto: shortcode with the Navasan base symbol (18ayar) still works', false !== strpos( do_shortcode( '[one_gold_price symbol="18ayar" digits="en" label="no"]' ), '25,670,800' ) );
one_t( 'priceto: shortcode with its own symbol works', false !== strpos( do_shortcode( '[one_gold_price symbol="gold-18k" digits="en" label="no"]' ), '25,670,800' ) );
one_t( 'priceto: unknown symbol is a dash', false !== strpos( do_shortcode( '[one_gold_price symbol="usd_xau"]' ), '—' ) );

// Day change can be switched off (one request only).
one_set( array( 'priceto_fetch_change' => 0 ) );
$GLOBALS['one_requests'] = array();
One_Navasan_Rate_Service::refresh();
$o = One_Navasan_Rate_Service::symbol_output();
one_t( 'priceto: change request skipped when switched off', 1 === count( $GLOBALS['one_requests'] ) && null === $o['change_val'] && null === $o['change_pct'] );
one_set( array( 'priceto_fetch_change' => 1 ) );

// The change request must never fail the fetch, and its failure puts it on cooldown.
$GLOBALS['one_fixture_priceto']['fail_change'] = true;
$GLOBALS['one_requests'] = array();
$r = One_Navasan_Rate_Service::refresh();
one_t( 'priceto: failing change request does not fail the refresh', $r['ok'] && one_near( One_Navasan_Rate_Service::effective_toman(), 25670800 ) );
$GLOBALS['one_requests'] = array();
One_Navasan_Rate_Service::refresh();
one_t( 'priceto: failed change path is skipped on the next refresh', 0 === $reqs_to( '/v1/change/' ) && 1 === $reqs_to( '/v1/latest/' ) );
$GLOBALS['one_fixture_priceto']['fail_change'] = false;
delete_transient( $cd_key( 'https://api.priceto.day/v1/change/irr/gold-18k' ) );

// B. Primary down: an older backup is refused, a newer one is used.
$GLOBALS['one_fixture_priceto']['fail'] = true;
$GLOBALS['one_fixture']['date']  = time() - 3 * 3600;
$GLOBALS['one_fixture']['value'] = 24000000;
$GLOBALS['one_requests'] = array();
$r = One_Navasan_Rate_Service::refresh();
one_t( 'backup older than the stored rate is refused, rate kept', ! $r['ok'] && 'failed' === $r['status'] && one_near( One_Navasan_Rate_Service::effective_toman(), 25670800 ) );
one_t( 'refusal reason recorded', false !== strpos( (string) get_option( One_Navasan_Rate_Service::ERROR ), 'backup_not_newer' ), (string) get_option( One_Navasan_Rate_Service::ERROR ) );
one_t( 'single-path primary is tried even right after a failure', $reqs_to( '/v1/latest/' ) >= 1 );
$GLOBALS['one_fixture']['date']  = time() - 60;
$GLOBALS['one_fixture']['value'] = 25100000;
$r = One_Navasan_Rate_Service::refresh();
$src = One_Navasan_Rate_Service::current_source();
one_t( 'newer backup is used', $r['ok'] && one_near( One_Navasan_Rate_Service::effective_toman(), 25100000 ) && 'navasan' === $src['id'] && $src['via_backup'], wp_json_encode( array( $r, $src ) ) );
$o = One_Navasan_Rate_Service::symbol_output();
one_t( 'backup rate has high/low from the plugin-tracked range', null !== $o['high'] && $o['high'] >= $o['low'] && 'navasan' === $o['source'] );
wp_set_current_user( 1 );
ob_start();
One_Navasan_Admin::render();
$html = ob_get_clean();
one_t( 'status page flags the backup source with a warning', false !== strpos( $html, 'one-warn' ) );
// C. Primary recovers.
$GLOBALS['one_fixture_priceto']['fail'] = false;
$GLOBALS['one_fixture_priceto']['date'] = time() - 30;
$r   = One_Navasan_Rate_Service::refresh();
$src = One_Navasan_Rate_Service::current_source();
one_t( 'primary recovers and replaces the backup rate', $r['ok'] && 'priceto' === $src['id'] && ! $src['via_backup'] && one_near( One_Navasan_Rate_Service::effective_toman(), 25670800 ) );

// D. Navasan-API: a timing-out path is put on cooldown and skipped afterwards.
one_set( array( 'rate_source' => 'navasan', 'backup_source' => 'none' ) );
$GLOBALS['one_fixture']['value'] = 25035780;
$GLOBALS['one_fixture']['date']  = time() - 600;
$GLOBALS['one_fixture']['fail_hosts'] = array( 'raw.githubusercontent.com' );
delete_transient( $cd_key( One_Navasan_Settings::DEFAULT_URL ) );
$GLOBALS['one_requests'] = array();
$r = One_Navasan_Rate_Service::refresh();
one_t( 'Navasan raw path times out: falls back to the next path', $r['ok'] && 'contents_api' === One_Navasan_Rate_Service::state()['source'], wp_json_encode( array( $r, One_Navasan_Rate_Service::state()['source'] ) ) );
one_t( 'timed-out path is on cooldown', false !== get_transient( $cd_key( One_Navasan_Settings::DEFAULT_URL ) ) );
$GLOBALS['one_requests'] = array();
One_Navasan_Rate_Service::refresh();
one_t( 'cooling-down path is not requested again', 0 === $reqs_to( 'raw.githubusercontent.com' ) && 1 === count( $GLOBALS['one_requests'] ), wp_json_encode( $GLOBALS['one_requests'] ) );
$GLOBALS['one_requests'] = array();
$rows = One_Navasan_Fetcher::test_all();
one_t( 'test connection ignores the cooldown', $reqs_to( 'raw.githubusercontent.com' ) >= 1 );
$GLOBALS['one_fixture']['fail_hosts'] = array();
delete_transient( $cd_key( One_Navasan_Settings::DEFAULT_URL ) );

// E. Test connection: both providers, separate rows, values in Toman.
$GLOBALS['one_fixture_priceto']['value'] = 25670800;
$rows = One_Navasan_Fetcher::test_all();
$by   = array();
foreach ( $rows as $row ) {
	$by[ $row['provider'] ][] = $row;
}
one_t( 'test connection: one priceto path and three Navasan paths', 1 === count( $by['priceto.day'] ) && 3 === count( $by['Navasan-API'] ), wp_json_encode( array_map( 'count', $by ) ) );
one_t( 'test connection: priceto row shows the Toman rate and data time', $by['priceto.day'][0]['ok'] && one_near( $by['priceto.day'][0]['value'], 25670800 ) && ! empty( $by['priceto.day'][0]['date'] ) );
$nav_ok = true;
foreach ( $by['Navasan-API'] as $row ) {
	$nav_ok = $nav_ok && $row['ok'] && one_near( $row['value'], 25035780 ) && ! empty( $row['date'] ) && '' !== $row['url'];
}
one_t( 'test connection: every Navasan path shows the Toman rate and data time', $nav_ok );

// F. Switching source holds a jump (reason source_change); same-source jumps hold with reason jump.
one_set( array( 'rate_source' => 'priceto', 'backup_source' => 'none', 'max_jump_pct' => 10 ) );
$GLOBALS['one_fixture_priceto']['value'] = 28000000; // +11.8% against the stored Navasan-API rate.
$GLOBALS['one_fixture_priceto']['date']  = time() - 100;
$r = One_Navasan_Rate_Service::refresh();
$p = One_Navasan_Rate_Service::pending();
one_t( 'source switch jump is held for approval', 'pending' === $r['status'] && one_near( One_Navasan_Rate_Service::effective_toman(), 25035780 ) && null !== $p );
one_t( 'hold reason is the source change, from priceto', $p && 'source_change' === $p['reason'] && 'priceto' === $p['provider'] );
ob_start();
One_Navasan_Admin::notices();
$notice = ob_get_clean();
one_t( 'admin notice names the source change', false !== strpos( $notice, 'priceto.day' ) && false !== strpos( $notice, 'منبع نرخ تغییر کرده' ), $notice );
one_t( 'approving applies the new source rate', One_Navasan_Rate_Service::approve_pending() && one_near( One_Navasan_Rate_Service::effective_toman(), 28000000 ) && 'priceto' === One_Navasan_Rate_Service::current_source()['id'] );
$GLOBALS['one_fixture_priceto']['value'] = 20000000; // -28.6% on the same source.
$r = One_Navasan_Rate_Service::refresh();
$p = One_Navasan_Rate_Service::pending();
one_t( 'same-source jump is held with reason jump', 'pending' === $r['status'] && $p && 'jump' === $p['reason'] );
one_set( array( 'max_jump_pct' => 0 ) );
$GLOBALS['one_fixture_priceto']['value'] = 25670800;
One_Navasan_Rate_Service::refresh();
one_set( array( 'max_jump_pct' => 10 ) );
one_t( 'pending cleared after a healthy commit', null === One_Navasan_Rate_Service::pending() && one_near( One_Navasan_Rate_Service::effective_toman(), 25670800 ) );

// G. Manual source: no fetching at all.
$manual_toman = 30000000;
one_set( array( 'rate_source' => 'manual', 'backup_source' => 'navasan', 'manual_rate' => (string) ( $manual_toman * $mult ) ) );
$GLOBALS['one_requests'] = array();
$r = One_Navasan_Rate_Service::refresh();
One_Navasan_Scheduler::run();
one_t( 'manual: refresh and the scheduler make no request', 'manual' === $r['status'] && ! $r['ok'] && 0 === count( $GLOBALS['one_requests'] ) );
$o = One_Navasan_Rate_Service::symbol_output();
one_t( 'manual: rate, source and mode', one_near( One_Navasan_Rate_Service::effective_toman(), $manual_toman ) && 'manual' === $o['source'] && 'manual' === $o['mode'] && 'manual' === One_Navasan_Rate_Service::current_source()['id'] );
one_t( 'manual: never stale', ! One_Navasan_Rate_Service::is_stale( $tehran( 28, 23 ) ) );
one_t( 'manual: shortcodes for both base symbols work', false !== strpos( do_shortcode( '[one_gold_price symbol="gold-18k" digits="en" label="no"]' ), '30,000,000' ) && false !== strpos( do_shortcode( '[one_gold_price symbol="18ayar" digits="en" label="no"]' ), '30,000,000' ) );

// H. Settings sanitizing and the dashboard page.
$s = One_Navasan_Settings::sanitize( array_merge( One_Navasan_Settings::all(), array( 'rate_source' => 'navasan', 'backup_source' => 'navasan' ) ) );
one_t( 'a source cannot back itself up', 'none' === $s['backup_source'] );
$s = One_Navasan_Settings::sanitize( array_merge( One_Navasan_Settings::all(), array( 'base_symbol_priceto' => 'Gold 18K!!' ) ) );
one_t( 'invalid priceto symbol falls back to gold-18k', 'gold-18k' === $s['base_symbol_priceto'] );
$s = One_Navasan_Settings::sanitize( array_merge( One_Navasan_Settings::all(), array( 'rate_source' => 'bogus' ) ) );
one_t( 'unknown source value is ignored', 'manual' === $s['rate_source'] );
$list = One_Navasan_Provider_Priceto::symbols( true );
one_t( 'priceto symbol list loads from /v1/symbols and is cached', in_array( 'gold-18k', $list, true ) && in_array( 'coin-emami', $list, true ) && ! in_array( 'irr', $list, true ) && false !== get_transient( One_Navasan_Provider_Priceto::SYMBOLS_TRANSIENT ) );
one_set( array( 'rate_source' => 'priceto', 'backup_source' => 'navasan' ) );
ob_start();
One_Navasan_Admin::render();
$html = ob_get_clean();
one_t( 'settings page offers the three sources and the backup', false !== strpos( $html, 'one-f-rate_source' ) && false !== strpos( $html, 'one-f-backup_source' ) && false !== strpos( $html, 'value="manual"' ) );
one_t( 'settings page marks provider-specific fields', false !== strpos( $html, 'data-one-uses="priceto"' ) && false !== strpos( $html, 'data-one-uses="navasan"' ) && false !== strpos( $html, 'data-one-uses="manual"' ) );
one_t( 'settings page credits both projects', false !== strpos( $html, 'margani/pricedb' ) && false !== strpos( $html, 'HosseinOdd/Navasan-API' ) && false !== strpos( $html, 'priceto.day' ) );
one_t( 'settings page shows the current source', false !== strpos( $html, 'منبع نرخ فعلی' ) );

// I. Request hygiene across the whole run.
$allowed = array( 'api.priceto.day', 'raw.githubusercontent.com', 'api.github.com', 'cdn.jsdelivr.net' );
$hosts_ok = true; $ua_ok = true; $timeout_ok = true;
foreach ( $GLOBALS['one_requests_all'] as $rq ) {
	$host      = wp_parse_url( $rq['url'], PHP_URL_HOST );
	$is_ours   = ( 0 === strpos( $rq['ua'], 'One-Navasan-WordPress/' ) );
	$to_source = in_array( $host, $allowed, true );
	if ( $is_ours ) {
		$hosts_ok   = $hosts_ok && $to_source; // Our requests only go to provider hosts.
		$timeout_ok = $timeout_ok && 6 === (int) $rq['timeout'];
	}
	if ( $to_source ) {
		$ua_ok = $ua_ok && 0 === strpos( $rq['ua'], 'One-Navasan-WordPress/' . ONE_NAVASAN_VERSION . ' ' ); // Requests to them carry name and version.
	}
}
one_t( 'requests only go to the two providers hosts', $hosts_ok && count( $GLOBALS['one_requests_all'] ) > 10 );
one_t( 'every request sends the plugin name and version', $ua_ok );
one_t( 'every request has the short 6 second timeout', $timeout_ok );

// Back to the Navasan-API source for the WooCommerce checks below (they use its fixed rate).
one_set( array( 'rate_source' => 'navasan', 'backup_source' => 'none', 'max_jump_pct' => 0 ) );
$GLOBALS['one_fixture']['value'] = 25035780;
$GLOBALS['one_fixture']['date']  = time() - 600;
One_Navasan_Rate_Service::refresh();
one_set( array( 'max_jump_pct' => 10 ) );
one_t( 'back on Navasan-API at the fixed rate', one_near( One_Navasan_Rate_Service::effective_toman(), 25035780 ) && 'navasan' === One_Navasan_Rate_Service::current_source()['id'] );
wp_clear_scheduled_hook( One_Navasan_Scheduler::NOW_HOOK );

if ( ! $has_wc ) {
	// ---- No-WooCommerce checks ---------------------------------------------------
	one_t( 'no product fields class loaded', ! class_exists( 'One_Navasan_WC_Product_Fields', false ) );
	$menu_ok = true;
	one_t( 'settings page registers without WooCommerce', $menu_ok );
	deactivate_plugins( $plugin );
	one_t( 'deactivation clears schedule', ! wp_next_scheduled( One_Navasan_Scheduler::HOOK ) );
	one_finish();
}

// ---- 5. Products --------------------------------------------------------------
wc_create_attribute( array( 'name' => 'Weight', 'slug' => 'vazn', 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false ) );
wc_create_attribute( array( 'name' => 'Wage', 'slug' => 'ojrat', 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false ) );
delete_transient( 'wc_attribute_taxonomies' );
register_taxonomy( 'pa_vazn', 'product', array( 'hierarchical' => false ) );
register_taxonomy( 'pa_ojrat', 'product', array( 'hierarchical' => false ) );

function one_make_attr( $taxonomy, $term_name ) {
	$term = wp_insert_term( $term_name, $taxonomy );
	$tid  = is_wp_error( $term ) ? $term->get_error_data() : $term['term_id'];
	$a    = new WC_Product_Attribute();
	$a->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
	$a->set_name( $taxonomy );
	$a->set_options( array( (int) $tid ) );
	$a->set_visible( true );
	return $a;
}
function one_simple( $name, $old_price = '303436481.6' ) {
	$p = new WC_Product_Simple();
	$p->set_name( $name );
	$p->set_regular_price( $old_price );
	$p->set_status( 'publish' );
	return $p;
}

// A: weight in WC field (1.22, WC unit left at kg like the real site), default wage.
$pa = one_simple( 'A wc-weight' );
$pa->set_weight( '1.22' );
$pa->save();
// B: weight + wage from attributes ("2.5 گرم", "6%"), karat default.
$pb = one_simple( 'B attribute' );
$pb->set_attributes( array( one_make_attr( 'pa_vazn', '2.5 گرم' ), one_make_attr( 'pa_ojrat', '6%' ) ) );
$pb->save();
// C: product fields (Persian digits, fixed wage, extra, 21 karat).
$pc = one_simple( 'C product-fields' );
$pc->update_meta_data( 'one_navasan_weight', '۳٫۵' );
$pc->update_meta_data( 'one_navasan_wage', '500000' );
$pc->update_meta_data( 'one_navasan_wage_mode', 'fixed' );
$pc->update_meta_data( 'one_navasan_extra', '1,000,000' );
$pc->update_meta_data( 'one_navasan_karat', '21' );
$pc->update_meta_data( 'one_navasan_profit', '5' );
$pc->save();
// D: no weight at all.
$pd = one_simple( 'D no-weight' );
$pd->save();
// E: opted out explicitly.
$pe = one_simple( 'E opted-out', '777' );
$pe->set_weight( '2' );
$pe->update_meta_data( 'one_navasan_enabled', 'no' );
$pe->save();
// F: sale price that will exceed the new regular price.
$pf = one_simple( 'F sale' );
$pf->set_weight( '1' );
$pf->set_sale_price( '999999999999' );
$pf->save();
// G: variable product with two variations (own weights).
$pg = new WC_Product_Variable();
$pg->set_name( 'G variable' );
$pg->set_status( 'publish' );
$attr = new WC_Product_Attribute();
$attr->set_name( 'size' );
$attr->set_options( array( 'small', 'large' ) );
$attr->set_visible( true );
$attr->set_variation( true );
$pg->set_attributes( array( $attr ) );
$pg->save();
$vars = array();
foreach ( array( 'small' => '1', 'large' => '2' ) as $size => $w ) {
	$v = new WC_Product_Variation();
	$v->set_parent_id( $pg->get_id() );
	$v->set_attributes( array( 'size' => $size ) );
	$v->set_regular_price( '1000' );
	$v->update_meta_data( 'one_navasan_weight', $w );
	$v->save();
	$vars[ $size ] = $v->get_id();
}
WC_Product_Variable::sync( $pg->get_id() );

$rate  = One_Navasan_Rate_Service::store_rate();
$exp   = function ( $rate, $bk, $k, $w, $wage_pct, $profit, $tax, $extra, $step, $fixed_wage = null ) {
	$gold = $w * $rate * $k / $bk;
	$wage = null === $fixed_wage ? $gold * $wage_pct / 100 : $fixed_wage;
	$p    = ( $gold + $wage ) * $profit / 100;
	$t    = ( $wage + $p ) * $tax / 100;
	$raw  = $gold + $wage + $p + $t + $extra;
	return $step ? round( $raw / $step ) * $step : round( $raw );
};
$price = function ( $id ) {
	return (float) get_post_meta( $id, '_regular_price', true );
};

// ---- 6. Nothing is touched before preview + apply -------------------------------
One_Navasan_WC_Pricing::maybe_start_recalculation();
one_t( 'prices untouched before first apply', one_near( $price( $pa->get_id() ), 303436481.6 ) );

// Apply is refused before a preview.
$ajax_guard = ! get_option( One_Navasan_WC_Pricing::PREVIEW_DONE );
one_t( 'apply gate closed before preview', $ajax_guard );

// ---- 7. Preview ----------------------------------------------------------------
$cursor = 0; $rows = 0; $tot = array( 'updated' => 0, 'unchanged' => 0, 'skipped' => array() );
do {
	$c = One_Navasan_WC_Pricing::process_chunk( 'preview', $cursor, $rows );
	one_t( 'preview chunk ok', ! is_wp_error( $c ), is_wp_error( $c ) ? $c->get_error_message() : '' );
	$cursor = $c['cursor'];
	$rows  += $c['rows_count'];
	$tot['updated'] += $c['counts']['updated'];
	foreach ( $c['counts']['skipped'] as $k => $n ) {
		$tot['skipped'][ $k ] = ( isset( $tot['skipped'][ $k ] ) ? $tot['skipped'][ $k ] : 0 ) + $n;
	}
} while ( ! $c['done'] );
update_option( One_Navasan_WC_Pricing::PREVIEW_DONE, time() );
one_t( 'preview lists rows', $rows >= 4, (string) $rows );
one_t( 'preview skipped product without weight', isset( $tot['skipped']['bad_weight'] ) && $tot['skipped']['bad_weight'] >= 1, wp_json_encode( $tot ) );
one_t( 'preview did not change prices', one_near( $price( $pa->get_id() ), 303436481.6 ) );

// ---- 8. Apply (weight from WC field first) ---------------------------------------
$run_apply = function () {
	update_option( One_Navasan_WC_Pricing::ENABLED, 1 );
	$cursor = 0;
	$counts = array( 'products' => 0, 'items' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => array(), 'examples' => array() );
	do {
		$c      = One_Navasan_WC_Pricing::process_chunk( 'apply', $cursor );
		$cursor = $c['cursor'];
		$counts = One_Navasan_WC_Pricing::merge_counts( $counts, $c['counts'] );
	} while ( ! $c['done'] );
	One_Navasan_WC_Pricing::finish( $counts );
	return $counts;
};
// Attribute-based settings for B; weight source stays WC for A first.
one_set( array( 'weight_source' => 'wc', 'wage_source' => 'attribute', 'wage_attr' => 'pa_ojrat' ) );
$counts = $run_apply();
$base_expected_a = 'irr' === $one_mode ? 364364000 : 36436000;
one_t( 'A: hand-calculated price stored (WC weight field, default wage)', one_near( $price( $pa->get_id() ), $base_expected_a ), (string) $price( $pa->get_id() ) );
one_t( 'A: matches independent formula', one_near( $price( $pa->get_id() ), $exp( $rate, 18, 18, 1.22, 10, 7, 9, 0, 1000 ) ) );
one_t( 'A: _price meta synced', one_near( get_post_meta( $pa->get_id(), '_price', true ), $price( $pa->get_id() ) ) );
$pb2 = wc_get_product( $pb->get_id() );
one_t( 'B: skipped (no WC weight while source=wc)', one_near( $price( $pb->get_id() ), 303436481.6 ) );
one_t( 'C: product fields (Persian digits, fixed wage, extra, 21k)', one_near( $price( $pc->get_id() ), $exp( $rate, 18, 21, 3.5, 0, 5, 9, 1000000 * $mult, 1000, 500000 * $mult ) ) || one_near( $price( $pc->get_id() ), $exp( $rate, 18, 21, 3.5, 0, 5, 9, 1000000, 1000, 500000 ) ), (string) $price( $pc->get_id() ) );
one_t( 'D: no-weight product untouched', one_near( $price( $pd->get_id() ), 303436481.6 ) );
one_t( 'E: opted-out product untouched', one_near( $price( $pe->get_id() ), 777 ) );
one_t( 'F: sale price removed when >= regular', '' === (string) wc_get_product( $pf->get_id() )->get_sale_price() && $price( $pf->get_id() ) > 0 );
one_t( 'G: variation small priced from own weight', one_near( $price( $vars['small'] ), $exp( $rate, 18, 18, 1, 10, 7, 9, 0, 1000 ) ), (string) $price( $vars['small'] ) );
one_t( 'G: variation large priced from own weight', one_near( $price( $vars['large'] ), $exp( $rate, 18, 18, 2, 10, 7, 9, 0, 1000 ) ) );
one_t( 'G: parent price range synced', one_near( (float) wc_get_product( $pg->get_id() )->get_variation_price( 'min' ), $price( $vars['small'] ) ) );
one_t( 'report stored', is_array( get_option( One_Navasan_WC_Pricing::REPORT ) ) );

// ---- 9. Weight from attribute, wage from attribute ---------------------------------
one_set( array( 'weight_source' => 'attribute', 'weight_attr' => 'pa_vazn', 'wage_source' => 'attribute', 'wage_attr' => 'pa_ojrat' ) );
$run_apply();
one_t( 'B: weight "2.5 گرم" + wage "6%" from attributes', one_near( $price( $pb->get_id() ), $exp( $rate, 18, 18, 2.5, 6, 7, 9, 0, 1000 ) ), (string) $price( $pb->get_id() ) );
one_t( 'A: now skipped under attribute source (price from previous run stays)', one_near( $price( $pa->get_id() ), $base_expected_a ) );

// ---- 10. Rate change (manual mode) triggers background recalculation -------------------
one_set( array( 'weight_source' => 'wc' ) );
$run_apply();
$manual_store = 30000000 * $mult;
one_set( array( 'rate_source' => 'manual', 'manual_rate' => (string) $manual_store ) );
one_t( 'manual mode effective rate', one_near( One_Navasan_Rate_Service::effective_toman(), 30000000 ) );
$job = get_option( One_Navasan_WC_Pricing::JOB );
one_t( 'settings change queued background job', is_array( $job ) && 'running' === $job['status'] );
$guard = 0;
while ( get_option( One_Navasan_WC_Pricing::JOB ) && $guard++ < 20 ) {
	One_Navasan_WC_Pricing::background_step();
}
one_t( 'background job finished', ! get_option( One_Navasan_WC_Pricing::JOB ) );
one_t( 'A: new price after manual rate', one_near( $price( $pa->get_id() ), $exp( $manual_store, 18, 18, 1.22, 10, 7, 9, 0, 1000 ) ), (string) $price( $pa->get_id() ) );
// Unchanged settings => no new job.
One_Navasan_WC_Pricing::maybe_start_recalculation();
one_t( 'no recalculation when nothing changed', ! get_option( One_Navasan_WC_Pricing::JOB ), wp_json_encode( array( get_option( One_Navasan_WC_Pricing::JOB ), get_option( One_Navasan_WC_Pricing::APPLIED_HASH ), One_Navasan_WC_Pricing::pricing_hash() ) ) );

// Rounding directions via settings.
one_set( array( 'round_step' => 10000, 'round_dir' => 'up' ) );
while ( get_option( One_Navasan_WC_Pricing::JOB ) && $guard++ < 40 ) {
	One_Navasan_WC_Pricing::background_step();
}
$up = $price( $pa->get_id() );
one_t( 'rounding up to 10000', 0 === (int) fmod( $up, 10000 ) && $up >= $exp( $manual_store, 18, 18, 1.22, 10, 7, 9, 0, 0 ) );

// Auto mode again.
one_set( array( 'rate_source' => 'navasan', 'round_step' => 1000, 'round_dir' => 'nearest' ) );
while ( get_option( One_Navasan_WC_Pricing::JOB ) && $guard++ < 60 ) {
	One_Navasan_WC_Pricing::background_step();
}
one_t( 'back to automatic rate prices', one_near( $price( $pa->get_id() ), $base_expected_a ), (string) $price( $pa->get_id() ) );

// Settings page output renders (no fatals, credit present).
wp_set_current_user( 1 );
ob_start();
One_Navasan_Admin::render();
$html = ob_get_clean();
one_t( 'settings page renders with credit', false !== strpos( $html, 'Navasan-API' ) && false !== strpos( $html, 'one-wrap' ) );
one_t( 'settings page shows no API key', false === strpos( $html, 'ghp_' ) );

// ---- 11. Deactivate + uninstall ----------------------------------------------------------
deactivate_plugins( $plugin );
one_t( 'deactivation clears schedule', ! wp_next_scheduled( One_Navasan_Scheduler::HOOK ) );
define( 'WP_UNINSTALL_PLUGIN', $plugin );
$before = $price( $pa->get_id() );
include WP_PLUGIN_DIR . '/navasan-api-wordpress-plugin/uninstall.php';
global $wpdb;
one_t( 'uninstall removed options', 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'one\\_navasan\\_%'" ) );
one_t( 'uninstall removed product meta', 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key LIKE 'one\\_navasan\\_%'" ) );
one_t( 'uninstall keeps product prices', one_near( $price( $pa->get_id() ), $before ) );

one_finish();
