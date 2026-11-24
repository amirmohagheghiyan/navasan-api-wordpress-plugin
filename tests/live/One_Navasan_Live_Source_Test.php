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
		$headers = function_exists( 'http_get_last_response_headers' ) ? http_get_last_response_headers() : ( isset( ${'http_response_header'} ) ? ${'http_response_header'} : null );
		if ( false === $body || empty( $headers[0] ) || false === strpos( $headers[0], '200' ) ) {
			return null;
		}
		return $body;
	}
}
