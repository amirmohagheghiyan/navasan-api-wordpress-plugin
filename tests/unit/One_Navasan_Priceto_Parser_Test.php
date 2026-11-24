<?php
use PHPUnit\Framework\TestCase;

class One_Navasan_Priceto_Parser_Test extends TestCase {

	/** Real response of https://api.priceto.day/v1/latest/irr/gold-18k (2026-11-24). */
	const REAL = '{ "success": true, "price": 256708000, "high": 258216000, "low": 240881000, "time": "2026-11-24T10:59:53.000Z" }';

	public function test_real_sample_is_converted_from_rial_to_toman() {
		$r = One_Navasan_Priceto_Parser::parse_latest( self::REAL );
		$this->assertTrue( $r['ok'] );
		$this->assertEqualsWithDelta( 25670800, $r['entry']['value'], 0.001 );
		$this->assertEqualsWithDelta( 25821600, $r['entry']['high'], 0.001 );
		$this->assertEqualsWithDelta( 24088100, $r['entry']['low'], 0.001 );
		$this->assertSame( strtotime( '2026-11-24T10:59:53Z' ), $r['entry']['date'] );
		$this->assertNull( $r['entry']['change_val'] );
		$this->assertNull( $r['entry']['change_pct'] );
	}

	public function test_success_false_is_rejected() {
		$r = One_Navasan_Priceto_Parser::parse_latest( '{"success":false,"error":"x"}' );
		$this->assertFalse( $r['ok'] );
		$this->assertSame( 'api_error', $r['error'] );
	}

	public function test_broken_or_empty_json_is_rejected() {
		foreach ( array( '', '   ', '{not json', 'null', '"text"', '[]' ) as $body ) {
			$r = One_Navasan_Priceto_Parser::parse_latest( $body );
			$this->assertFalse( $r['ok'], 'Body should be rejected: ' . $body );
		}
		$this->assertSame( 'invalid_json', One_Navasan_Priceto_Parser::parse_latest( '{not json' )['error'] );
	}

	public function test_zero_negative_or_missing_price_is_rejected() {
		$this->assertSame( 'non_positive_value', One_Navasan_Priceto_Parser::parse_latest( '{"success":true,"price":0,"time":"2026-11-24T10:59:53.000Z"}' )['error'] );
		$this->assertSame( 'non_positive_value', One_Navasan_Priceto_Parser::parse_latest( '{"success":true,"price":-5,"time":"2026-11-24T10:59:53.000Z"}' )['error'] );
		$this->assertSame( 'missing_value', One_Navasan_Priceto_Parser::parse_latest( '{"success":true,"time":"2026-11-24T10:59:53.000Z"}' )['error'] );
		$this->assertSame( 'missing_value', One_Navasan_Priceto_Parser::parse_latest( '{"success":true,"price":"abc","time":"2026-11-24T10:59:53.000Z"}' )['error'] );
	}

	public function test_missing_high_and_low_still_parses() {
		$r = One_Navasan_Priceto_Parser::parse_latest( '{"success":true,"price":256708000,"time":"2026-11-24T10:59:53.000Z"}' );
		$this->assertTrue( $r['ok'] );
		$this->assertNull( $r['entry']['high'] );
		$this->assertNull( $r['entry']['low'] );
	}

	public function test_zero_or_inconsistent_range_is_dropped() {
		$r = One_Navasan_Priceto_Parser::parse_latest( '{"success":true,"price":256708000,"high":0,"low":240881000,"time":"2026-11-24T10:59:53.000Z"}' );
		$this->assertNull( $r['entry']['high'] );
		$this->assertEqualsWithDelta( 24088100, $r['entry']['low'], 0.001 );

		// Low above high: both ignored.
		$r = One_Navasan_Priceto_Parser::parse_latest( '{"success":true,"price":256708000,"high":240881000,"low":258216000,"time":"2026-11-24T10:59:53.000Z"}' );
		$this->assertNull( $r['entry']['high'] );
		$this->assertNull( $r['entry']['low'] );
	}

	public function test_missing_or_bad_time_is_rejected() {
		$this->assertSame( 'invalid_time', One_Navasan_Priceto_Parser::parse_latest( '{"success":true,"price":256708000}' )['error'] );
		$this->assertSame( 'invalid_time', One_Navasan_Priceto_Parser::parse_latest( '{"success":true,"price":256708000,"time":"yesterday-ish"}' )['error'] );
		$this->assertSame( 'invalid_time', One_Navasan_Priceto_Parser::parse_latest( '{"success":true,"price":256708000,"time":12345}' )['error'] );
	}

	public function test_one_element_list_form_is_tolerated() {
		$r = One_Navasan_Priceto_Parser::parse_latest( '[' . self::REAL . ']' );
		$this->assertTrue( $r['ok'] );
		$this->assertEqualsWithDelta( 25670800, $r['entry']['value'], 0.001 );
	}

	public function test_change_uses_the_1d_row_in_toman() {
		$body = '{"success":true,"price":257394000,"time":"2026-11-24T11:29:54.000Z","changes":['
			. '{"period":"1d","price":234584000,"time":"2026-11-22T16:29:56.000Z","change":22810000,"changePercent":9.723595812161102},'
			. '{"period":"7d","price":245674000,"time":"2026-11-16T16:29:53.000Z","change":11720000,"changePercent":4.77}]}';
		$c = One_Navasan_Priceto_Parser::parse_change( $body );
		$this->assertTrue( $c['ok'] );
		$this->assertEqualsWithDelta( 2281000, $c['change_val'], 0.001 );
		$this->assertEqualsWithDelta( 9.7236, $c['change_pct'], 0.0001 );
	}

	public function test_change_with_null_fields_or_without_1d_is_not_used() {
		$nulls = '{"success":true,"changes":[{"period":"1d","price":null,"time":null,"change":null,"changePercent":null}]}';
		$this->assertFalse( One_Navasan_Priceto_Parser::parse_change( $nulls )['ok'] );
		$no1d = '{"success":true,"changes":[{"period":"7d","change":1,"changePercent":1}]}';
		$this->assertFalse( One_Navasan_Priceto_Parser::parse_change( $no1d )['ok'] );
		$this->assertFalse( One_Navasan_Priceto_Parser::parse_change( '{bad' )['ok'] );
		$this->assertFalse( One_Navasan_Priceto_Parser::parse_change( '{"success":false}' )['ok'] );
	}

	public function test_symbols_list_is_filtered() {
		$body = '{"success":true,"symbols":["irr","usd","gold-18k","Bad Symbol","coin-emami",5,"../x"],"indices":[{"symbol":"tedpix"}]}';
		$this->assertSame( array( 'usd', 'gold-18k', 'coin-emami' ), One_Navasan_Priceto_Parser::parse_symbols( $body ) );
		$this->assertSame( array(), One_Navasan_Priceto_Parser::parse_symbols( '{bad' ) );
		$this->assertSame( array(), One_Navasan_Priceto_Parser::parse_symbols( '{"success":false}' ) );
	}

	public function test_rial_to_toman_helper() {
		$this->assertEqualsWithDelta( 100, One_Navasan_Priceto_Parser::to_toman( 1000 ), 0.0001 );
	}
}
