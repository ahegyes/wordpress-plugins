<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration;

use DeepWebSolutions\LinkedOrders\Plugin;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class OptionsTest extends TestCase {
	protected const string KEY = Plugin::PREFIX . '_options_test';

	protected const array RESTORED = array( DWS_LOWC_OPTION_DELETE_DATA );

	protected array $stored = array();

	public static function stored_bools(): array {
		return array(
			'yes'       => array( 'yes', true ),
			'no'        => array( 'no', false ),
			'one'       => array( '1', true ),
			'zero'      => array( '0', false ),
			'true'      => array( true, true ),
			'empty'     => array( '', false ),
			'malformed' => array( 'maybe', false ),
			'array'     => array( array( 'yes' ), false ),
		);
	}

	public static function stored_ints(): array {
		return array(
			'int'       => array( 7, 7 ),
			'numeric'   => array( '7', 7 ),
			'padded'    => array( ' 7 ', 7 ),
			'negative'  => array( '-7', -7 ),
			'float'     => array( '7.5', 0 ),
			'malformed' => array( 'seven', 0 ),
			'array'     => array( array( 7 ), 0 ),
		);
	}

	public static function stored_strings(): array {
		return array(
			'string' => array( 'kept', 'kept' ),
			'empty'  => array( '', '' ),
			'array'  => array( array( 'kept' ), '' ),
		);
	}

	public static function stored_lists(): array {
		return array(
			'list'   => array( array( 'a', 'b' ), array( 'a', 'b' ) ),
			'mixed'  => array( array( 'a', 7, 'b' ), array( 'a', 'b' ) ),
			'keyed'  => array( array( 'x' => 'a' ), array( 'a' ) ),
			'string' => array( 'a', array() ),
		);
	}

	#[Before]
	protected function store_rows(): void {
		foreach ( self::RESTORED as $key ) {
			$this->stored[ $key ] = get_option( $key );
		}
	}

	#[After]
	protected function restore_rows(): void {
		delete_option( self::KEY );
		foreach ( $this->stored as $key => $value ) {
			false === $value ? delete_option( $key ) : update_option( $key, $value );
		}
	}

	#[DataProvider( 'stored_bools' )]
	public function test_a_bool_reads_the_stored_checkbox_value_or_false( mixed $stored, bool $expected ): void {
		update_option( DWS_LOWC_OPTION_DELETE_DATA, $stored );

		self::assertSame( $expected, dws_lowc_get_bool_option( DWS_LOWC_OPTION_DELETE_DATA ) );
	}

	public function test_a_missing_option_reads_its_default(): void {
		delete_option( DWS_LOWC_OPTION_DELETE_DATA );

		self::assertSame( DWS_LOWC_OPTION_DEFAULTS[ DWS_LOWC_OPTION_DELETE_DATA ], dws_lowc_get_bool_option( DWS_LOWC_OPTION_DELETE_DATA ) );
	}

	#[DataProvider( 'stored_ints' )]
	public function test_an_int_reads_a_whole_number_or_zero( mixed $stored, int $expected ): void {
		update_option( self::KEY, $stored );

		self::assertSame( $expected, dws_lowc_get_int_option( self::KEY ) );
	}

	#[DataProvider( 'stored_strings' )]
	public function test_a_string_reads_a_stored_string_or_the_empty_string( mixed $stored, string $expected ): void {
		update_option( self::KEY, $stored );

		self::assertSame( $expected, dws_lowc_get_string_option( self::KEY ) );
	}

	#[DataProvider( 'stored_lists' )]
	public function test_a_list_reads_the_stored_strings_in_order( mixed $stored, array $expected ): void {
		update_option( self::KEY, $stored );

		self::assertSame( $expected, dws_lowc_get_list_option( self::KEY ) );
	}
}
