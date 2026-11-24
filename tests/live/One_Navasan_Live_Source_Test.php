<?php
use PHPUnit\Framework\TestCase;

/**
 * Reads the real source. Skipped (not failed) when there is no network.
 */
class One_Navasan_Live_Source_Test extends TestCase {

	public function test_real_gold_json_has_usable_18ayar() {
		$url  = 'https://raw.githubusercontent.com/HosseinOdd/Navasan-API/main/data/gold.json';
		$body = $this->one_navasan_get( $url );
		if ( null === $body ) {
			$this->markTestSkipped( 'Network or GitHub is not reachable from this machine.' );
		}
		$parsed = One_Navasan_Source_Validator::parse_json( $body );
		$this->assertTrue( $parsed['ok'], 'JSON should parse: ' . $parsed['error'] );
		$entry = One_Navasan_Source_Validator::get_entry( $parsed['data'], '18ayar' );
		$this->assertTrue( $entry['ok'], '18ayar should be usable: ' . $entry['error'] );
		$this->assertGreaterThan( 1000000, $entry['entry']['value'], 'Gram of 18k gold should be millions of Toman.' );
		$this->assertGreaterThan( 1600000000, $entry['entry']['date'] );
	}

	public function test_real_priceto_latest_is_usable_and_in_toman() {
		$body = $this->one_navasan_get( 'https://api.priceto.day/v1/latest/irr/gold-18k' );
		if ( null === $body ) {
			$this->markTestSkipped( 'Network or api.priceto.day is not reachable from this machine.' );
		}
		$r = One_Navasan_Priceto_Parser::parse_latest( $body );
		$this->assertTrue( $r['ok'], 'priceto.day latest should parse: ' . $r['error'] );
		$this->assertGreaterThan( 1000000, $r['entry']['value'], 'Gram of 18k gold should be millions of Toman (not Rial).' );
		$this->assertLessThan( 1000000000, $r['entry']['value'] );
		$this->assertGreaterThan( 1600000000, $r['entry']['date'] );
		if ( null !== $r['entry']['high'] && null !== $r['entry']['low'] ) {
			$this->assertGreaterThanOrEqual( $r['entry']['low'], $r['entry']['high'] );
		}
	}

	public function test_real_priceto_change_and_symbols_parse() {
		$change = $this->one_navasan_get( 'https://api.priceto.day/v1/change/irr/gold-18k' );
		$list   = $this->one_navasan_get( 'https://api.priceto.day/v1/symbols' );
		if ( null === $change || null === $list ) {
			$this->markTestSkipped( 'Network or api.priceto.day is not reachable from this machine.' );
		}
		$this->assertTrue( One_Navasan_Priceto_Parser::parse_change( $change )['ok'] );
		$this->assertContains( 'gold-18k', One_Navasan_Priceto_Parser::parse_symbols( $list ) );
	}

	private function one_navasan_get( $url ) {
		$ctx  = stream_context_create(
			array(
				'http' => array(
					'timeout'       => 15,
					'ignore_errors' => true,
					'header'        => "User-Agent: one-navasan-test\r\n",
				),
			)
		);
		$body = @file_get_contents( $url, false, $ctx );
		// PHP 8.5 deprecates the magic $http_response_header variable.
		if ( function_exists( 'http_get_last_response_headers' ) ) {
			$headers = http_get_last_response_headers();
		} else {
			$defined = get_defined_vars();
			$headers = isset( $defined['http_response_header'] ) ? $defined['http_response_header'] : null;
		}
		if ( false === $body || empty( $headers[0] ) || false === strpos( $headers[0], '200' ) ) {
			return null;
		}
		return $body;
	}
}
