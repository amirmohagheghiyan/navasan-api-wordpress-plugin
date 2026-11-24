<?php
use PHPUnit\Framework\TestCase;

class One_Navasan_Misc_Test extends TestCase {

	public function test_adjuster() {
		$this->assertSame( 1000.0, One_Navasan_Rate_Adjuster::apply( 1000, 'none', 5 ) );
		$this->assertEqualsWithDelta( 1050, One_Navasan_Rate_Adjuster::apply( 1000, 'percent', 5 ), 0.0001 );
		$this->assertEqualsWithDelta( 950, One_Navasan_Rate_Adjuster::apply( 1000, 'percent', -5 ), 0.0001 );
		$this->assertEqualsWithDelta( 1500, One_Navasan_Rate_Adjuster::apply( 1000, 'fixed', 500 ), 0.0001 );
		$this->assertEqualsWithDelta( 700, One_Navasan_Rate_Adjuster::apply( 1000, 'fixed', -300 ), 0.0001 );
	}

	public function test_formatter() {
		$this->assertSame( '25,035,780', One_Navasan_Number_Formatter::format( 25035780 ) );
		$this->assertSame( '25035780', One_Navasan_Number_Formatter::format( 25035780, false ) );
		$this->assertSame( '۲۵٬۰۳۵٬۷۸۰', One_Navasan_Number_Formatter::format( 25035780, true, 'fa' ) );
		$this->assertSame( '3,630.66', One_Navasan_Number_Formatter::format( 3630.66 ) );
		$this->assertSame( '1.50', One_Navasan_Number_Formatter::format( 1.5, true, 'en', 2 ) );
		$this->assertSame( '', One_Navasan_Number_Formatter::format( null ) );
	}

	public function test_daily_range_tracks_min_max_and_resets() {
		$day   = '2026-11-24';
		$state = One_Navasan_Daily_Range::update( array(), $day, array( '18ayar' => 100, 'bad' => 0 ) );
		$state = One_Navasan_Daily_Range::update( $state, $day, array( '18ayar' => 90 ) );
		$state = One_Navasan_Daily_Range::update( $state, $day, array( '18ayar' => 120 ) );
		$this->assertSame( array( 'min' => 90.0, 'max' => 120.0 ), One_Navasan_Daily_Range::get( $state, $day, '18ayar' ) );
		$this->assertNull( One_Navasan_Daily_Range::get( $state, $day, 'bad' ) );
		$state = One_Navasan_Daily_Range::update( $state, '2026-11-25', array( '18ayar' => 110 ) );
		$this->assertSame( array( 'min' => 110.0, 'max' => 110.0 ), One_Navasan_Daily_Range::get( $state, '2026-11-25', '18ayar' ) );
	}

	public function test_day_key_uses_tehran_time() {
		// 2026-11-24 21:00 UTC is already 2026-11-25 00:30 in Tehran (UTC+3:30).
		$ts = gmmktime( 21, 0, 0, 11, 24, 2026 );
		$this->assertSame( '2026-11-25', One_Navasan_Daily_Range::day_key( $ts ) );
		$this->assertSame( '2026-11-24', One_Navasan_Daily_Range::day_key( $ts, 'UTC' ) );
	}
}
