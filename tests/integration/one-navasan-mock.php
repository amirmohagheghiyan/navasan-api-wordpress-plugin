<?php
/**
 * HTTP mock for both providers, shared by the integration scenarios.
 *
 * Fixtures (global arrays) control what the "servers" answer:
 *   $GLOBALS['one_fixture']        Navasan-API:  value (Toman), date, fail, fail_hosts[], raw.
 *   $GLOBALS['one_fixture_priceto'] priceto.day: value (Toman), high, low, date, fail, fail_change, raw, change_pct.
 * Every request is recorded in $GLOBALS['one_requests'] (url, ua, timeout).
 *
 * PHP 7.4 compatible.
 */

$GLOBALS['one_fixture']         = array( 'value' => 25035780, 'date' => time() - 600, 'fail' => false, 'fail_hosts' => array(), 'raw' => null );
$GLOBALS['one_fixture_priceto'] = array( 'value' => 25670800, 'high' => 25821600, 'low' => 24088100, 'date' => time() - 300, 'fail' => false, 'fail_change' => false, 'raw' => null, 'change_pct' => 9.7236 );
$GLOBALS['one_requests']        = array();
$GLOBALS['one_requests_all']    = array();

function one_mock_response( $body, $code = 200 ) {
	return array( 'headers' => array(), 'body' => $body, 'response' => array( 'code' => $code, 'message' => 'x' ), 'cookies' => array(), 'filename' => null );
}

add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) {
		$logged                         = array(
			'url'     => $url,
			'ua'      => isset( $args['user-agent'] ) ? $args['user-agent'] : '',
			'timeout' => isset( $args['timeout'] ) ? $args['timeout'] : null,
		);
		$GLOBALS['one_requests'][]      = $logged; // Reset by the scenarios.
		$GLOBALS['one_requests_all'][]  = $logged; // Never reset.

		// ---- priceto.day (values in Rial) ----
		if ( false !== strpos( $url, 'api.priceto.day' ) ) {
			$f = $GLOBALS['one_fixture_priceto'];
			if ( $f['fail'] ) {
				return new WP_Error( 'http_request_failed', 'simulated priceto failure' );
			}
			if ( false !== strpos( $url, '/v1/symbols' ) ) {
				return one_mock_response( '{"success":true,"symbols":["irr","usd","gold-18k","gold-24k","coin-emami"],"indices":[]}' );
			}
			if ( false !== strpos( $url, '/v1/change/' ) ) {
				if ( $f['fail_change'] ) {
					return new WP_Error( 'http_request_failed', 'simulated change failure' );
				}
				$rial = (int) round( $f['value'] * 10 );
				return one_mock_response( wp_json_encode( array(
					'success' => true,
					'price'   => $rial,
					'time'    => gmdate( 'Y-m-d\TH:i:s.000\Z', $f['date'] ),
					'changes' => array(
						array( 'period' => '1d', 'price' => (int) round( $rial / 1.0972 ), 'time' => gmdate( 'Y-m-d\TH:i:s.000\Z', $f['date'] - 86400 ), 'change' => (int) round( $rial - $rial / 1.0972 ), 'changePercent' => $f['change_pct'] ),
						array( 'period' => '7d', 'price' => 1, 'time' => null, 'change' => 1, 'changePercent' => 1 ),
					),
				) ) );
			}
			if ( false !== strpos( $url, '/v1/latest/irr/' ) ) {
				if ( null !== $f['raw'] ) {
					return one_mock_response( $f['raw'] );
				}
				return one_mock_response( wp_json_encode( array(
					'success' => true,
					'price'   => (int) round( $f['value'] * 10 ),
					'high'    => (int) round( $f['high'] * 10 ),
					'low'     => (int) round( $f['low'] * 10 ),
					'time'    => gmdate( 'Y-m-d\TH:i:s.000\Z', $f['date'] ),
				) ) );
			}
			return one_mock_response( '{"success":false}', 404 );
		}

		// ---- Navasan-API (raw.githubusercontent.com, api.github.com, cdn.jsdelivr.net; values in Toman) ----
		if ( false !== strpos( $url, 'Navasan-API' ) ) {
			$f = $GLOBALS['one_fixture'];
			if ( $f['fail'] ) {
				return new WP_Error( 'http_request_failed', 'simulated failure' );
			}
			$host = wp_parse_url( $url, PHP_URL_HOST );
			if ( in_array( $host, $f['fail_hosts'], true ) ) {
				return new WP_Error( 'http_request_failed', 'simulated timeout on ' . $host );
			}
			$body = null !== $f['raw'] ? $f['raw'] : wp_json_encode(
				array(
					'18ayar'       => array( 'change_val' => 120000, 'change_pct' => 0.5, 'value' => $f['value'], 'date' => $f['date'] ),
					'sekkeh'       => array( 'change_val' => 0, 'change_pct' => 0, 'value' => 257500000, 'date' => $f['date'] ),
					'usd_xau'      => array( 'change_val' => 0, 'change_pct' => 0, 'value' => '3630.66', 'date' => $f['date'] ),
					'bub_abshodeh' => array( 'change_val' => 0, 'change_pct' => 0, 'date' => $f['date'] ),
				)
			);
			return one_mock_response( $body );
		}
		return $pre;
	},
	10,
	3
);
