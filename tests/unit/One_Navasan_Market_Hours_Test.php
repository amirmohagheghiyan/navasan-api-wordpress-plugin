<?php
use PHPUnit\Framework\TestCase;

/**
 * Dates: 2026-11-24 is a Tuesday. Times below are Tehran local (UTC+03:30).
 */
class One_Navasan_Market_Hours_Test extends TestCase {

	/** Tehran local time to Unix time. */
	private function tehran( $day, $hour, $minute = 0 ) {
		return gmmktime( $hour, $minute, 0, 11, $day, 2026 ) - One_Navasan_Market_Hours::OFFSET;
	}

	public function test_weekday_sanity() {
		$this->assertSame( 'Tue', gmdate( 'D', $this->tehran( 24, 12 ) + One_Navasan_Market_Hours::OFFSET ) );
		$this->assertSame( 'Fri', gmdate( 'D', $this->tehran( 27, 12 ) + One_Navasan_Market_Hours::OFFSET ) );
	}

	public function test_open_during_the_working_day() {
		$this->assertTrue( One_Navasan_Market_Hours::is_open( $this->tehran( 24, 8, 0 ) ) );
		$this->assertTrue( One_Navasan_Market_Hours::is_open( $this->tehran( 24, 14, 29 ) ) );
		$this->assertTrue( One_Navasan_Market_Hours::is_open( $this->tehran( 24, 21, 59 ) ) );
	}

	public function test_closed_at_night() {
		$this->assertFalse( One_Navasan_Market_Hours::is_open( $this->tehran( 24, 22, 0 ) ) );
		$this->assertFalse( One_Navasan_Market_Hours::is_open( $this->tehran( 24, 3, 0 ) ) );
		$this->assertFalse( One_Navasan_Market_Hours::is_open( $this->tehran( 24, 7, 59 ) ) );
	}

	public function test_thursday_closes_in_the_afternoon_and_friday_is_closed() {
		$this->assertTrue( One_Navasan_Market_Hours::is_open( $this->tehran( 26, 13, 59 ) ) );
		$this->assertFalse( One_Navasan_Market_Hours::is_open( $this->tehran( 26, 14, 0 ) ) );
		$this->assertFalse( One_Navasan_Market_Hours::is_open( $this->tehran( 26, 19, 0 ) ) );
		$this->assertFalse( One_Navasan_Market_Hours::is_open( $this->tehran( 27, 11, 0 ) ) );
	}

	public function test_saturday_morning_opens() {
		$this->assertFalse( One_Navasan_Market_Hours::is_open( $this->tehran( 28, 7, 59 ) ) );
		$this->assertTrue( One_Navasan_Market_Hours::is_open( $this->tehran( 28, 8, 0 ) ) );
	}

	public function test_open_seconds_inside_one_day() {
		$this->assertSame( 3600, One_Navasan_Market_Hours::open_seconds( $this->tehran( 24, 10 ), $this->tehran( 24, 11 ) ) );
		$this->assertSame( 0, One_Navasan_Market_Hours::open_seconds( $this->tehran( 24, 23 ), $this->tehran( 25, 2 ) ) );
		$this->assertSame( 0, One_Navasan_Market_Hours::open_seconds( 100, 100 ) );
		$this->assertSame( 0, One_Navasan_Market_Hours::open_seconds( 200, 100 ) );
	}

	public function test_open_seconds_skip_the_night() {
		// Tue 21:00 -> Wed 09:00: 1h before 22:00 and 1h after 08:00.
		$this->assertSame( 2 * 3600, One_Navasan_Market_Hours::open_seconds( $this->tehran( 24, 21 ), $this->tehran( 25, 9 ) ) );
	}

	public function test_open_seconds_skip_thursday_afternoon_and_friday() {
		// Thu 13:00 -> Sat 12:00: 1h (Thu) + 4h (Sat).
		$this->assertSame( 5 * 3600, One_Navasan_Market_Hours::open_seconds( $this->tehran( 26, 13 ), $this->tehran( 28, 12 ) ) );
	}

	public function test_weekend_does_not_raise_the_stale_warning() {
		$data = $this->tehran( 26, 13 ); // Last update Thursday 13:00.
		$this->assertFalse( One_Navasan_Market_Hours::is_stale( $data, $this->tehran( 27, 20 ), 6 ), 'Friday evening' );
		$this->assertFalse( One_Navasan_Market_Hours::is_stale( $data, $this->tehran( 28, 12 ), 6 ), 'Saturday noon: 5 open hours' );
		$this->assertTrue( One_Navasan_Market_Hours::is_stale( $data, $this->tehran( 28, 14 ), 6 ), 'Saturday 14:00: 7 open hours without an update' );
	}

	public function test_overnight_does_not_raise_the_stale_warning() {
		$data = $this->tehran( 24, 21, 30 ); // Last update Tuesday 21:30.
		$this->assertFalse( One_Navasan_Market_Hours::is_stale( $data, $this->tehran( 25, 9, 30 ), 6 ) );
	}

	public function test_missing_update_during_market_hours_is_stale() {
		$data = $this->tehran( 24, 9 );
		$this->assertTrue( One_Navasan_Market_Hours::is_stale( $data, $this->tehran( 24, 16 ), 6 ) );
		$this->assertFalse( One_Navasan_Market_Hours::is_stale( $data, $this->tehran( 24, 14 ), 6 ) );
	}

	public function test_stale_check_can_be_disabled_or_has_no_data() {
		$this->assertFalse( One_Navasan_Market_Hours::is_stale( null, $this->tehran( 24, 12 ), 6 ) );
		$this->assertFalse( One_Navasan_Market_Hours::is_stale( 1, $this->tehran( 24, 12 ), 0 ) );
	}

	public function test_very_old_data_is_stale_and_cheap_to_compute() {
		$this->assertTrue( One_Navasan_Market_Hours::is_stale( 1, $this->tehran( 24, 12 ), 6 ) );
	}
}
