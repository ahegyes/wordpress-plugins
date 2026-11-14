<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Integration;

use DeepWebSolutions\LockedPaymentMethods\Options;
use DeepWebSolutions\LockedPaymentMethods\Plugin;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\RestoresOptionsTrait;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class OptionsTest extends TestCase {
	use RestoresOptionsTrait;

	protected const string KEY = Plugin::PREFIX . '_options_test';

	protected const array OPTIONS = array( Options::DELETE_DATA );

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

	public static function stored_lists(): array {
		return array(
			'list'   => array( array( 'a', 'b' ), array( 'a', 'b' ) ),
			'mixed'  => array( array( 'a', 7, 'b' ), array( 'a', 'b' ) ),
			'keyed'  => array( array( 'x' => 'a' ), array( 'a' ) ),
			'string' => array( 'a', array() ),
		);
	}

	#[After]
	protected function delete_the_test_option(): void {
		delete_option( self::KEY );
	}

	#[DataProvider( 'stored_bools' )]
	public function test_a_bool_reads_the_stored_checkbox_value_or_false( mixed $stored, bool $expected ): void {
		update_option( Options::DELETE_DATA, $stored );

		self::assertSame( $expected, Options::get_bool( Options::DELETE_DATA ) );
	}

	public function test_a_missing_option_reads_its_default(): void {
		delete_option( Options::DELETE_DATA );

		self::assertSame( Options::DEFAULTS[ Options::DELETE_DATA ], Options::get_bool( Options::DELETE_DATA ) );
	}

	#[DataProvider( 'stored_lists' )]
	public function test_a_list_reads_the_stored_strings_in_order( mixed $stored, array $expected ): void {
		update_option( self::KEY, $stored );

		self::assertSame( $expected, Options::get_list( self::KEY ) );
	}
}
