<?php
use PHPUnit\Framework\TestCase;

class One_Navasan_Source_Selector_Test extends TestCase {

	public function test_plan_primary_then_backup() {
		$this->assertSame( array( 'priceto', 'navasan' ), One_Navasan_Source_Selector::plan( 'priceto', 'navasan' ) );
		$this->assertSame( array( 'navasan', 'priceto' ), One_Navasan_Source_Selector::plan( 'navasan', 'priceto' ) );
	}

	public function test_plan_without_backup_or_with_same_backup() {
		$this->assertSame( array( 'priceto' ), One_Navasan_Source_Selector::plan( 'priceto', 'none' ) );
		$this->assertSame( array( 'priceto' ), One_Navasan_Source_Selector::plan( 'priceto', 'priceto' ) );
		$this->assertSame( array( 'navasan' ), One_Navasan_Source_Selector::plan( 'navasan', 'garbage' ) );
	}

	public function test_manual_has_no_plan() {
		$this->assertSame( array(), One_Navasan_Source_Selector::plan( 'manual', 'navasan' ) );
		$this->assertSame( array(), One_Navasan_Source_Selector::plan( 'unknown', 'none' ) );
	}

	public function test_backup_is_used_only_when_newer_than_stored_rate() {
		$stored = 1000000;
		$this->assertTrue( One_Navasan_Source_Selector::backup_is_newer( $stored + 1, $stored ) );
		$this->assertTrue( One_Navasan_Source_Selector::backup_is_newer( $stored + 7200, $stored ) );
		// Navasan-API hours behind: an older (or equal) rate must not replace a fresher one.
		$this->assertFalse( One_Navasan_Source_Selector::backup_is_newer( $stored - 3 * 3600, $stored ) );
		$this->assertFalse( One_Navasan_Source_Selector::backup_is_newer( $stored, $stored ) );
	}

	public function test_backup_with_no_time_is_never_trusted_over_a_stored_rate() {
		$this->assertFalse( One_Navasan_Source_Selector::backup_is_newer( null, 1000000 ) );
	}

	public function test_backup_is_accepted_when_nothing_is_stored() {
		$this->assertTrue( One_Navasan_Source_Selector::backup_is_newer( 5, null ) );
		$this->assertTrue( One_Navasan_Source_Selector::backup_is_newer( null, null ) );
	}

	public function test_source_change_detection() {
		$this->assertTrue( One_Navasan_Source_Selector::is_source_change( 'navasan', 'priceto' ) );
		$this->assertTrue( One_Navasan_Source_Selector::is_source_change( 'priceto', 'navasan' ) );
		$this->assertFalse( One_Navasan_Source_Selector::is_source_change( 'priceto', 'priceto' ) );
		$this->assertFalse( One_Navasan_Source_Selector::is_source_change( null, 'priceto' ) );
		$this->assertFalse( One_Navasan_Source_Selector::is_source_change( '', 'priceto' ) );
	}

	/**
	 * Switching source can create a jump (the two sources differ by a few percent, or by a lot):
	 * the same jump guard holds it and the reason says why.
	 */
	public function test_source_switch_jump_is_held_with_source_reason() {
		$old = 24080000.0; // Navasan-API, hours behind.
		$new = 25670800.0; // priceto.day, current (+6.6%).
		$jump = One_Navasan_Guard::check_jump( $new, $old, 5 );
		$this->assertFalse( $jump['ok'] );
		$changed = One_Navasan_Source_Selector::is_source_change( 'navasan', 'priceto' );
		$this->assertSame( 'source_change', One_Navasan_Source_Selector::hold_reason( $changed ) );
	}

	public function test_same_source_jump_has_jump_reason_and_small_switch_passes() {
		$this->assertSame( 'jump', One_Navasan_Source_Selector::hold_reason( false ) );
		$this->assertTrue( One_Navasan_Guard::check_jump( 25670800, 25500000, 10 )['ok'] );
		// A unit mix-up (Rial taken as Toman) is held in both directions whichever source caused it.
		$this->assertFalse( One_Navasan_Guard::check_jump( 256708000, 25670800, 10 )['ok'] );
		$this->assertFalse( One_Navasan_Guard::check_jump( 2567080, 25670800, 10 )['ok'] );
	}
}
