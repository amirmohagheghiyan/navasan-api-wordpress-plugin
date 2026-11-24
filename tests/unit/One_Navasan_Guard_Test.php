<?php
use PHPUnit\Framework\TestCase;

class One_Navasan_Guard_Test extends TestCase {

	public function test_jump_within_limit_passes() {
		$r = One_Navasan_Guard::check_jump( 105, 100, 10 );
		$this->assertTrue( $r['ok'] );
		$this->assertEqualsWithDelta( 5, $r['pct'], 0.0001 );
	}

	public function test_jump_exactly_at_limit_passes() {
		$this->assertTrue( One_Navasan_Guard::check_jump( 110, 100, 10 )['ok'] );
	}

	public function test_jump_over_limit_fails_both_directions() {
		$this->assertFalse( One_Navasan_Guard::check_jump( 111, 100, 10 )['ok'] );
		$this->assertFalse( One_Navasan_Guard::check_jump( 89, 100, 10 )['ok'] );
		$this->assertFalse( One_Navasan_Guard::check_jump( 250357800, 25035780, 10 )['ok'] ); // x10 unit mistake.
	}

	public function test_no_previous_rate_or_disabled_guard_passes() {
		$this->assertTrue( One_Navasan_Guard::check_jump( 999, null, 10 )['ok'] );
		$this->assertTrue( One_Navasan_Guard::check_jump( 999, 0, 10 )['ok'] );
		$this->assertTrue( One_Navasan_Guard::check_jump( 999, 100, 0 )['ok'] );
	}

	public function test_staleness() {
		$now = 1000000;
		$this->assertFalse( One_Navasan_Guard::is_stale( $now - 3600 * 5, $now, 6 ) );
		$this->assertTrue( One_Navasan_Guard::is_stale( $now - 3600 * 7, $now, 6 ) );
		$this->assertFalse( One_Navasan_Guard::is_stale( $now - 3600 * 6, $now, 6 ) );
		$this->assertFalse( One_Navasan_Guard::is_stale( null, $now, 6 ) );
		$this->assertFalse( One_Navasan_Guard::is_stale( 1, $now, 0 ) );
	}
}
