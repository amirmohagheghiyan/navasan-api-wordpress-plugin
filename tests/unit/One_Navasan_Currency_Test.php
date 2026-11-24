<?php
use PHPUnit\Framework\TestCase;

class One_Navasan_Currency_Test extends TestCase {

	public function test_auto_detects_from_code() {
		$this->assertSame( 1.0, One_Navasan_Currency::multiplier( 'auto', 'IRT' ) );
		$this->assertSame( 10.0, One_Navasan_Currency::multiplier( 'auto', 'IRR' ) );
		$this->assertSame( 10.0, One_Navasan_Currency::multiplier( 'auto', 'irr' ) );
		$this->assertSame( 1.0, One_Navasan_Currency::multiplier( 'auto', 'USD' ) );
		$this->assertEqualsWithDelta( 0.001, One_Navasan_Currency::multiplier( 'auto', 'IRHT' ), 1e-9 );
	}

	public function test_manual_override_ignores_code() {
		$this->assertSame( 1.0, One_Navasan_Currency::multiplier( 'toman', 'IRR' ) );
		$this->assertSame( 10.0, One_Navasan_Currency::multiplier( 'rial', 'IRT' ) );
	}

	public function test_conversion_round_trip() {
		$m = One_Navasan_Currency::multiplier( 'auto', 'IRR' );
		$this->assertSame( 250357800.0, One_Navasan_Currency::from_toman( 25035780, $m ) );
		$this->assertSame( 25035780.0, One_Navasan_Currency::to_toman( 250357800, $m ) );
		$this->assertSame( 0.0, One_Navasan_Currency::to_toman( 5, 0 ) );
	}
}
