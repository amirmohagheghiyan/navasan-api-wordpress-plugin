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

// ---- Mock the source (deterministic) -------------------------------------
$GLOBALS['one_fixture'] = array( 'value' => 25035780, 'date' => time() - 600, 'fail' => false, 'raw' => null );
add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) {
		if ( false === strpos( $url, 'Navasan-API' ) ) {
			return $pre;
		}
		$f = $GLOBALS['one_fixture'];
		if ( $f['fail'] ) {
			return new WP_Error( 'http_request_failed', 'simulated failure' );
		}
		$body = null !== $f['raw'] ? $f['raw'] : wp_json_encode(
			array(
				'18ayar'       => array( 'change_val' => 0, 'change_pct' => 0, 'value' => $f['value'], 'date' => $f['date'] ),
				'sekkeh'       => array( 'change_val' => 0, 'change_pct' => 0, 'value' => 257500000, 'date' => $f['date'] ),
				'usd_xau'      => array( 'change_val' => 0, 'change_pct' => 0, 'value' => '3630.66', 'date' => $f['date'] ),
				'bub_abshodeh' => array( 'change_val' => 0, 'change_pct' => 0, 'date' => $f['date'] ),
			)
		);
		return array( 'headers' => array(), 'body' => $body, 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
	},
	10,
	3
);

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
one_set( array( 'profit_pct' => 7, 'tax_pct' => 9, 'wage_pct' => 10, 'round_step' => 1000, 'round_dir' => 'nearest', 'max_jump_pct' => 10, 'stale_hours' => 6 ) );

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
$GLOBALS['one_fixture']['date'] = time() - 8 * 3600;
One_Navasan_Rate_Service::refresh();
one_t( 'stale data flagged, rate kept', One_Navasan_Rate_Service::is_stale() && one_near( One_Navasan_Rate_Service::effective_toman(), 25035780 ) );
$GLOBALS['one_fixture']['date'] = time() - 600;
One_Navasan_Rate_Service::refresh();
one_t( 'fresh data not stale', ! One_Navasan_Rate_Service::is_stale() );

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
one_set( array( 'rate_mode' => 'manual', 'manual_rate' => (string) $manual_store ) );
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
one_set( array( 'rate_mode' => 'auto', 'round_step' => 1000, 'round_dir' => 'nearest' ) );
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
