<?php
use PHPUnit\Framework\TestCase;

class One_Navasan_Source_Validator_Test extends TestCase {

	private function one_navasan_sample() {
		return '{"18ayar":{"change_val":0,"change_pct":0,"value":25035780,"date":1795498220},'
			. '"usd_xau":{"change_val":0,"change_pct":0,"value":"3630.66","date":1795498220},'
			. '"bub_abshodeh":{"change_val":0,"change_pct":0,"date":1795498220},'
			. '"zero":{"value":0,"date":1795498220},'
			. '"neg":{"value":-5,"date":1795498220},'
			. '"junk":{"value":"abc","date":1795498220},'
			. '"notarray":5}';
	}

	public function test_parses_real_shape() {
		$r = One_Navasan_Source_Validator::parse_json( $this->one_navasan_sample() );
		$this->assertTrue( $r['ok'] );
		$this->assertSame( 25035780.0, $r['data']['18ayar']['value'] );
		$this->assertSame( 1795498220, $r['data']['18ayar']['date'] );
		$this->assertArrayNotHasKey( 'notarray', $r['data'] );
	}

	public function test_string_value_is_accepted_as_number() {
		$r = One_Navasan_Source_Validator::parse_json( $this->one_navasan_sample() );
		$e = One_Navasan_Source_Validator::get_entry( $r['data'], 'usd_xau' );
		$this->assertTrue( $e['ok'] );
		$this->assertSame( 3630.66, $e['entry']['value'] );
	}

	public function test_key_without_value_is_rejected() {
		$r = One_Navasan_Source_Validator::parse_json( $this->one_navasan_sample() );
		$e = One_Navasan_Source_Validator::get_entry( $r['data'], 'bub_abshodeh' );
		$this->assertFalse( $e['ok'] );
		$this->assertSame( 'missing_value', $e['error'] );
	}

	public function test_zero_negative_and_non_numeric_rates_are_rejected() {
		$r = One_Navasan_Source_Validator::parse_json( $this->one_navasan_sample() );
		$this->assertSame( 'non_positive_value', One_Navasan_Source_Validator::get_entry( $r['data'], 'zero' )['error'] );
		$this->assertSame( 'non_positive_value', One_Navasan_Source_Validator::get_entry( $r['data'], 'neg' )['error'] );
		$this->assertSame( 'missing_value', One_Navasan_Source_Validator::get_entry( $r['data'], 'junk' )['error'] );
		$this->assertSame( 'missing_symbol', One_Navasan_Source_Validator::get_entry( $r['data'], 'nope' )['error'] );
	}

	public function test_broken_json_and_empty() {
		$this->assertSame( 'invalid_json', One_Navasan_Source_Validator::parse_json( '{"a":' )['error'] );
		$this->assertSame( 'invalid_json', One_Navasan_Source_Validator::parse_json( '<html>blocked</html>' )['error'] );
		$this->assertSame( 'empty_body', One_Navasan_Source_Validator::parse_json( '' )['error'] );
		$this->assertSame( 'no_entries', One_Navasan_Source_Validator::parse_json( '[]' )['error'] );
		$this->assertSame( 'invalid_json', One_Navasan_Source_Validator::parse_json( '"text"' )['error'] );
	}

	public function test_bad_date_becomes_null() {
		$r = One_Navasan_Source_Validator::parse_json( '{"18ayar":{"value":10,"date":"x"}}' );
		$this->assertNull( $r['data']['18ayar']['date'] );
	}
}
