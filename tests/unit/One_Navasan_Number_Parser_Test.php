<?php
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class One_Navasan_Number_Parser_Test extends TestCase {

	public static function one_navasan_cases() {
		return array(
			'weight with unit'      => array( '1.22 گرم', 1.22 ),
			'percent'               => array( '6%', 6.0 ),
			'decimal percent'       => array( '10.5%', 10.5 ),
			'leading dot'           => array( '.5', 0.5 ),
			'comma decimal'         => array( '8,69', 8.69 ),
			'persian digits'        => array( '۱٫۲۲ گرم', 1.22 ),
			'persian percent'       => array( '۶٪', 6.0 ),
			'persian comma decimal' => array( '۸،۶۹', 8.69 ),
			'arabic indic digits'   => array( '٣٫٥', 3.5 ),
			'thousands commas'      => array( '1,234,567', 1234567.0 ),
			'single thousands'      => array( '1,234', 1234.0 ),
			'both separators'       => array( '1,234.56', 1234.56 ),
			'european style'        => array( '1.234,56', 1234.56 ),
			'dotted thousands'      => array( '1.234.567', 1234567.0 ),
			'plain int'             => array( '25', 25.0 ),
			'surrounding text'      => array( 'وزن: 2.5 گرم', 2.5 ),
			'trailing dot'          => array( '5.', 5.0 ),
			'real number input'     => array( 3, 3.0 ),
			'real float input'      => array( 1.22, 1.22 ),
		);
	}

	#[DataProvider( 'one_navasan_cases' )]
	public function test_parses( $input, $expected ) {
		$this->assertEqualsWithDelta( $expected, One_Navasan_Number_Parser::parse( $input ), 0.0000001 );
	}

	public static function one_navasan_null_cases() {
		return array(
			'empty'       => array( '' ),
			'spaces'      => array( '   ' ),
			'words only'  => array( 'نامشخص' ),
			'null'        => array( null ),
			'array'       => array( array( 1 ) ),
			'bool'        => array( true ),
			'bad grouping' => array( '1.2.3' ),
			'only dot'    => array( '.' ),
		);
	}

	#[DataProvider( 'one_navasan_null_cases' )]
	public function test_unparseable_returns_null_not_zero( $input ) {
		$this->assertNull( One_Navasan_Number_Parser::parse( $input ) );
	}

	public function test_zero_is_zero_but_not_positive() {
		$this->assertSame( 0.0, One_Navasan_Number_Parser::parse( '0' ) );
		$this->assertNull( One_Navasan_Number_Parser::parse_positive( '0' ) );
		$this->assertNull( One_Navasan_Number_Parser::parse_positive( '-3' ) );
		$this->assertSame( 3.0, One_Navasan_Number_Parser::parse_positive( '3' ) );
	}

	public function test_negative() {
		$this->assertSame( -2.5, One_Navasan_Number_Parser::parse( '-2.5' ) );
	}
}
