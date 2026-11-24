<?php
use PHPUnit\Framework\TestCase;

class One_Navasan_Settings_Migrator_Test extends TestCase {

	/** A typical 1.0.0 settings array. */
	private function legacy( $mode ) {
		return array(
			'source_url'    => 'https://raw.githubusercontent.com/HosseinOdd/Navasan-API/main/data/gold.json',
			'base_symbol'   => '18ayar',
			'base_karat'    => 18,
			'interval'      => 30,
			'rate_mode'     => $mode,
			'manual_rate'   => '2500000',
			'manual_ts'     => 1795500000,
			'adjust_mode'   => 'percent',
			'adjust_value'  => 1.5,
			'max_jump_pct'  => 10,
			'stale_hours'   => 6,
			'round_step'    => 1000,
			'profit_pct'    => 7,
			'tax_pct'       => 9,
			'scope_cats'    => array( 12, 15 ),
		);
	}

	public function test_auto_becomes_navasan_without_backup() {
		$out = One_Navasan_Settings_Migrator::migrate( $this->legacy( 'auto' ) );
		$this->assertSame( 'navasan', $out['rate_source'] );
		$this->assertSame( 'none', $out['backup_source'] );
		$this->assertArrayNotHasKey( 'rate_mode', $out );
	}

	public function test_manual_becomes_manual() {
		$out = One_Navasan_Settings_Migrator::migrate( $this->legacy( 'manual' ) );
		$this->assertSame( 'manual', $out['rate_source'] );
		$this->assertSame( '2500000', $out['manual_rate'] );
		$this->assertSame( 1795500000, $out['manual_ts'] );
	}

	public function test_missing_mode_is_read_as_auto() {
		$legacy = $this->legacy( 'auto' );
		unset( $legacy['rate_mode'] );
		$this->assertSame( 'navasan', One_Navasan_Settings_Migrator::migrate( $legacy )['rate_source'] );
	}

	public function test_every_other_setting_is_kept_unchanged() {
		$legacy = $this->legacy( 'auto' );
		$out    = One_Navasan_Settings_Migrator::migrate( $legacy );
		unset( $legacy['rate_mode'] );
		foreach ( $legacy as $key => $value ) {
			$this->assertSame( $value, $out[ $key ], 'Setting kept: ' . $key );
		}
		$this->assertSame( count( $legacy ) + 2, count( $out ), 'Only rate_source and backup_source are added.' );
	}

	public function test_new_layout_is_left_alone_and_migration_is_idempotent() {
		$new = array_merge( $this->legacy( 'auto' ), array( 'rate_source' => 'priceto', 'backup_source' => 'navasan' ) );
		unset( $new['rate_mode'] );
		$this->assertFalse( One_Navasan_Settings_Migrator::needs_migration( $new ) );
		$this->assertSame( $new, One_Navasan_Settings_Migrator::migrate( $new ) );

		$once  = One_Navasan_Settings_Migrator::migrate( $this->legacy( 'auto' ) );
		$twice = One_Navasan_Settings_Migrator::migrate( $once );
		$this->assertSame( $once, $twice );
	}

	public function test_needs_migration_detection() {
		$this->assertTrue( One_Navasan_Settings_Migrator::needs_migration( $this->legacy( 'auto' ) ) );
		$this->assertFalse( One_Navasan_Settings_Migrator::needs_migration( 'not an array' ) );
		$this->assertFalse( One_Navasan_Settings_Migrator::needs_migration( null ) );
	}
}
