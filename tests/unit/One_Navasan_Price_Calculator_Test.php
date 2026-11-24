<?php
use PHPUnit\Framework\TestCase;

class One_Navasan_Price_Calculator_Test extends TestCase {

	public function test_hand_calculated_example_18_karat() {
		// Base 25,000,000 per gram, 2 g, wage 10%, profit 7%, tax 9%.
		// gold=50,000,000 wage=5,000,000 profit=3,850,000 tax=796,500 total=59,646,500.
		$r = One_Navasan_Price_Calculator::calculate(
			array(
				'base_rate'  => 25000000,
				'base_karat' => 18,
				'karat'      => 18,
				'weight'     => 2,
				'wage_value' => 10,
				'profit_pct' => 7,
				'tax_pct'    => 9,
			)
		);
		$this->assertTrue( $r['ok'] );
		$this->assertEqualsWithDelta( 50000000, $r['gold'], 0.001 );
		$this->assertEqualsWithDelta( 5000000, $r['wage'], 0.001 );
		$this->assertEqualsWithDelta( 3850000, $r['profit'], 0.001 );
		$this->assertEqualsWithDelta( 796500, $r['tax'], 0.001 );
		$this->assertEqualsWithDelta( 59646500, $r['total'], 0.001 );
	}

	public function test_karat_conversion_and_extra_and_fixed_wage() {
		// 21k rate = 24,000,000 * 21/18 = 28,000,000. 1.5 g => gold 42,000,000.
		// fixed wage 1,000,000; profit 10% of 43,000,000 = 4,300,000;
		// tax 5% of (1,000,000 + 4,300,000) = 265,000; extra 2,000,000 => 49,565,000.
		$r = One_Navasan_Price_Calculator::calculate(
			array(
				'base_rate'  => 24000000,
				'base_karat' => 18,
				'karat'      => 21,
				'weight'     => 1.5,
				'wage_mode'  => 'fixed',
				'wage_value' => 1000000,
				'profit_pct' => 10,
				'tax_pct'    => 5,
				'extra'      => 2000000,
			)
		);
		$this->assertTrue( $r['ok'] );
		$this->assertEqualsWithDelta( 28000000, $r['karat_rate'], 0.001 );
		$this->assertEqualsWithDelta( 49565000, $r['total'], 0.001 );
	}

	public function test_rounding_applies_to_final_price_only() {
		$in = array(
			'base_rate'  => 25035780,
			'weight'     => 1.22,
			'wage_value' => 6,
			'profit_pct' => 7,
			'tax_pct'    => 9,
		);
		$raw  = One_Navasan_Price_Calculator::calculate( $in );
		$down = One_Navasan_Price_Calculator::calculate( $in + array( 'round_step' => 10000, 'round_dir' => 'down' ) );
		$this->assertEqualsWithDelta( $raw['gold'], $down['gold'], 0.0001 );
		$this->assertEqualsWithDelta( $raw['wage'], $down['wage'], 0.0001 );
		$this->assertEqualsWithDelta( floor( $raw['raw'] / 10000 ) * 10000, $down['total'], 0.001 );
	}

	public function test_invalid_inputs_fail_with_reason() {
		$base = array( 'base_rate' => 1000, 'weight' => 1 );
		$this->assertSame( 'bad_weight', One_Navasan_Price_Calculator::calculate( array( 'base_rate' => 1000, 'weight' => 0 ) )['reason'] );
		$this->assertSame( 'bad_weight', One_Navasan_Price_Calculator::calculate( array( 'base_rate' => 1000, 'weight' => null ) )['reason'] );
		$this->assertSame( 'bad_rate', One_Navasan_Price_Calculator::calculate( array( 'base_rate' => 0, 'weight' => 1 ) )['reason'] );
		$this->assertSame( 'bad_karat', One_Navasan_Price_Calculator::calculate( $base + array( 'karat' => 0 ) )['reason'] );
		$this->assertSame( 'bad_wage', One_Navasan_Price_Calculator::calculate( $base + array( 'wage_value' => -1 ) )['reason'] );
		$this->assertFalse( One_Navasan_Price_Calculator::calculate( $base + array( 'profit_pct' => 'abc' ) )['ok'] );
	}
}
