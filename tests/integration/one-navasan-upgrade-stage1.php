<?php
/**
 * Upgrade scenario, stage 1: runs with the PREVIOUS plugin version (v1.0.0) installed.
 *
 * Configures the plugin like the live site (settings, a rate, products priced for real) and
 * writes a snapshot to /one-mode/stage1.json for stage 2.
 * Mode (/one-mode/mode.txt): upgrade | upgrade-manual.
 *
 * PHP 7.4 compatible.
 */

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

$one_mode = is_readable( '/one-mode/mode.txt' ) ? trim( file_get_contents( '/one-mode/mode.txt' ) ) : 'upgrade';
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
	echo 'STAGE1 RESULT: ' . $GLOBALS['one_pass'] . ' pass, ' . $GLOBALS['one_fail'] . " fail\n";
	file_put_contents( '/one-mode/stage1.log', ob_get_contents() );
	exit( $GLOBALS['one_fail'] ? 1 : 0 );
}
function one_near( $a, $b, $eps = 0.01 ) {
	return abs( (float) $a - (float) $b ) <= $eps;
}

echo "== UPGRADE STAGE 1 ($one_mode): previous version ==\n";
one_t( 'WooCommerce is active', class_exists( 'WooCommerce' ) );
update_option( 'woocommerce_currency', 'IRT' );
update_option( 'woocommerce_price_num_decimals', 0 );

$plugin = 'navasan-api-wordpress-plugin/navasan-api-wordpress-plugin.php';
$act    = activate_plugin( $plugin );
one_t( 'previous version activates', ! is_wp_error( $act ), is_wp_error( $act ) ? $act->get_error_message() : '' );
one_navasan_boot();
one_t( 'previous version is 1.0.0', '1.0.0' === ONE_NAVASAN_VERSION, ONE_NAVASAN_VERSION );
one_t( 'previous version has no provider layer', ! class_exists( 'One_Navasan_Provider_Priceto', false ) && ! class_exists( 'One_Navasan_Upgrade', false ) );

$manual = ( 'upgrade-manual' === $one_mode );
$new    = One_Navasan_Settings::sanitize(
	array_merge(
		One_Navasan_Settings::all(),
		array(
			'scope'        => 'all',
			'rate_mode'    => $manual ? 'manual' : 'auto',
			'manual_rate'  => '30000000',
			'adjust_mode'  => 'percent',
			'adjust_value' => 1.5,
			'profit_pct'   => 7,
			'tax_pct'      => 9,
			'wage_pct'     => 10,
			'round_step'   => 1000,
			'round_dir'    => 'nearest',
			'max_jump_pct' => 10,
			'stale_hours'  => 6,
			'interval'     => 45,
			'frontend_script' => 1,
		)
	)
);
update_option( One_Navasan_Settings::OPTION, $new );
$stored = get_option( One_Navasan_Settings::OPTION );
one_t( 'previous layout: rate_mode stored, no rate_source', isset( $stored['rate_mode'] ) && ! isset( $stored['rate_source'] ) );

$r = One_Navasan_Rate_Service::refresh();
one_t( 'previous version fetched a rate', $r['ok'], wp_json_encode( $r ) );

// ---- Products priced for real ------------------------------------------------------
function one_simple( $name, $old_price = '303436481.6' ) {
	$p = new WC_Product_Simple();
	$p->set_name( $name );
	$p->set_regular_price( $old_price );
	$p->set_status( 'publish' );
	return $p;
}
$pa = one_simple( 'A wc-weight' );
$pa->set_weight( '1.22' );
$pa->save();
$pc = one_simple( 'C product-fields' );
$pc->update_meta_data( 'one_navasan_weight', '۳٫۵' );
$pc->update_meta_data( 'one_navasan_wage', '500000' );
$pc->update_meta_data( 'one_navasan_wage_mode', 'fixed' );
$pc->update_meta_data( 'one_navasan_extra', '1,000,000' );
$pc->update_meta_data( 'one_navasan_karat', '21' );
$pc->update_meta_data( 'one_navasan_profit', '5' );
$pc->save();
$pd = one_simple( 'D no-weight' );
$pd->save();
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
$ids = array( 'A' => $pa->get_id(), 'C' => $pc->get_id(), 'D' => $pd->get_id(), 'small' => $vars['small'], 'large' => $vars['large'], 'G' => $pg->get_id() );

// Preview, then apply (the live site did exactly this).
$cursor = 0; $rows = 0;
do {
	$c = One_Navasan_WC_Pricing::process_chunk( 'preview', $cursor, $rows );
	$cursor = $c['cursor'];
	$rows  += $c['rows_count'];
} while ( ! $c['done'] );
update_option( One_Navasan_WC_Pricing::PREVIEW_DONE, time(), false );
update_option( One_Navasan_WC_Pricing::ENABLED, 1, false );
$cursor = 0;
$counts = array( 'products' => 0, 'items' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => array(), 'examples' => array() );
do {
	$c      = One_Navasan_WC_Pricing::process_chunk( 'apply', $cursor );
	$cursor = $c['cursor'];
	$counts = One_Navasan_WC_Pricing::merge_counts( $counts, $c['counts'] );
} while ( ! $c['done'] );
One_Navasan_WC_Pricing::finish( $counts );

$price = function ( $id ) {
	return (float) get_post_meta( $id, '_regular_price', true );
};
one_t( 'prices applied for real by the previous version', $price( $ids['A'] ) > 0 && ! one_near( $price( $ids['A'] ), 303436481.6 ) && one_near( $price( $ids['D'] ), 303436481.6 ) );

// ---- Snapshot -----------------------------------------------------------------------
$prices = array();
foreach ( $ids as $key => $id ) {
	$prices[ $key ] = array(
		'regular' => get_post_meta( $id, '_regular_price', true ),
		'price'   => get_post_meta( $id, '_price', true ),
	);
}
$rest = rest_do_request( new WP_REST_Request( 'GET', '/one-navasan/v1/rate' ) )->get_data();
$snap = array(
	'mode'          => $one_mode,
	'settings'      => get_option( One_Navasan_Settings::OPTION ),
	'state'         => get_option( 'one_navasan_state' ),
	'daily'         => get_option( 'one_navasan_daily' ),
	'log'           => get_option( 'one_navasan_log' ),
	'enabled'       => get_option( One_Navasan_WC_Pricing::ENABLED ),
	'preview_done'  => get_option( One_Navasan_WC_Pricing::PREVIEW_DONE ),
	'applied_hash'  => get_option( One_Navasan_WC_Pricing::APPLIED_HASH ),
	'pricing_hash'  => One_Navasan_WC_Pricing::pricing_hash(),
	'report'        => get_option( One_Navasan_WC_Pricing::REPORT ),
	'effective'     => One_Navasan_Rate_Service::effective_toman(),
	'store_rate'    => One_Navasan_Rate_Service::store_rate(),
	'ids'           => $ids,
	'prices'        => $prices,
	'rest'          => $rest,
	'shortcodes'    => array(
		'default'  => do_shortcode( '[one_gold_price]' ),
		'high'     => do_shortcode( '[one_gold_price field="high" digits="en"]' ),
		'low'      => do_shortcode( '[one_gold_price field="low" digits="en"]' ),
		'pct'      => do_shortcode( '[one_gold_price field="change_pct" digits="en"]' ),
		'sekkeh'   => do_shortcode( '[one_gold_price symbol="sekkeh" digits="en" label="no"]' ),
		'18ayar'   => do_shortcode( '[one_gold_price symbol="18ayar" unit="rial"]' ),
	),
	'schedule_next' => (bool) wp_next_scheduled( One_Navasan_Scheduler::HOOK ),
);
one_t( 'snapshot has the pricing state', ! empty( $snap['applied_hash'] ) && 1 === (int) $snap['enabled'] && ! empty( $snap['preview_done'] ) );
one_t( 'previous version prices products from the stored rate', one_near( $snap['effective'], $manual ? 30000000 : 25035780 * 1.015, 0.5 ), (string) $snap['effective'] );
file_put_contents( '/one-mode/stage1.json', wp_json_encode( $snap ) );
echo "snapshot written\n";
one_finish();
