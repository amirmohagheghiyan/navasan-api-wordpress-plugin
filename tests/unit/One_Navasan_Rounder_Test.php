<?php
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class One_Navasan_Rounder_Test extends TestCase {

	public static function one_navasan_cases() {
		return array(
			'none keeps value'        => array( 1234567.6, 0, 'nearest', 1234567.6 ),
			'nearest 1000 down-ish'   => array( 1234400, 1000, 'nearest', 1234000 ),
			'nearest 1000 half up'    => array( 1234500, 1000, 'nearest', 1235000 ),
			'up 1000'                 => array( 1234001, 1000, 'up', 1235000 ),
			'down 1000'               => array( 1234999, 1000, 'down', 1234000 ),
			'up exact stays'          => array( 1235000, 1000, 'up', 1235000 ),
			'down exact stays'        => array( 1235000, 1000, 'down', 1235000 ),
			'step 50 nearest'         => array( 1274, 50, 'nearest', 1250 ),
			'step 50 up'              => array( 1251, 50, 'up', 1300 ),
			'step 100 down'           => array( 1299, 100, 'down', 1200 ),
			'step 500 nearest'        => array( 1760, 500, 'nearest', 2000 ),
			'step 5000 up'            => array( 12001, 5000, 'up', 15000 ),
			'step 10000 down'         => array( 303436481.6, 10000, 'down', 303430000 ),
			'custom step 250 nearest' => array( 1130, 250, 'nearest', 1250 ),
			'float noise up'          => array( 3000.0000000001, 1000, 'up', 3000 ),
			'float noise down'        => array( 2999.9999999999, 1000, 'down', 3000 ),
		);
	}

	#[DataProvider( 'one_navasan_cases' )]
	public function test_round( $value, $step, $dir, $expected ) {
		$this->assertEqualsWithDelta( $expected, One_Navasan_Rounder::round( $value, $step, $dir ), 0.0001 );
	}
}
