<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration;

use DeepWebSolutions\LinkedOrders\Linking;
use DeepWebSolutions\LinkedOrders\Options;
use DeepWebSolutions\LinkedOrders\Tests\Support\RecordsDeprecationsTrait;
use DeepWebSolutions\LinkedOrders\Tests\Support\RestoresOptionsTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class OptionsTest extends TestCase {
	use RecordsDeprecationsTrait;
	use RestoresOptionsTrait;

	protected const array OPTIONS = array( Options::MAX_DEPTH, Options::AUTOCOMPLETE, Options::DELETE_DATA );

	public static function stored_depths(): array {
		return array(
			'two'      => array( '2', 2 ),
			'zero'     => array( '0', 1 ),
			'negative' => array( '-3', 1 ),
			'word'     => array( 'deep', 1 ),
		);
	}

	public function test_the_settings_keep_the_keys_v1_stored_them_under(): void {
		self::assertSame( 'dws-lowc_general_max-depth', Options::MAX_DEPTH );
		self::assertSame( 'dws-lowc_general_autocomplete-descendants', Options::AUTOCOMPLETE );
		self::assertSame( 'dws-lowc_plugin_remove-data-uninstall', Options::DELETE_DATA );
	}

	public function test_a_missing_depth_reads_as_v1s_default(): void {
		foreach ( self::OPTIONS as $key ) {
			delete_option( $key );
		}

		self::assertSame( 1, Linking::get_max_depth() );
	}

	// v1 compared the filtered value as it came, so a filter could stop new children with 0.
	public function test_v1s_filter_on_the_validated_depth_is_the_last_word(): void {
		$hook = 'linked_orders_for_woocommerce/general_settings/get_validated_option_value';
		$zero = static fn ( mixed $value, string $field_id ): mixed => 'max-depth' === $field_id ? 0 : $value;
		add_filter( $hook, $zero, 10, 2 );

		try {
			self::assertSame( 0, Linking::get_max_depth() );
		} finally {
			remove_filter( $hook, $zero );
		}

		self::assertSame( array( array( $hook, 'linked_orders_for_woocommerce/node/can_create_child' ) ), $this->deprecated );
	}

	public function test_v1s_filter_turns_autocompletion_on_and_off(): void {
		$hook   = 'linked_orders_for_woocommerce/general_settings/get_validated_option_value';
		$invert = static fn ( mixed $value, string $field_id ): mixed => 'autocomplete-descendants' === $field_id ? ! $value : $value;
		update_option( Options::AUTOCOMPLETE, 'no' );
		add_filter( $hook, $invert, 10, 2 );

		try {
			self::assertTrue( Options::get_bool( Options::AUTOCOMPLETE ) );
			update_option( Options::AUTOCOMPLETE, 'yes' );
			self::assertFalse( Options::get_bool( Options::AUTOCOMPLETE ) );
		} finally {
			remove_filter( $hook, $invert );
		}

		self::assertSame( \array_fill( 0, 2, array( $hook, 'option_' . Options::AUTOCOMPLETE ) ), $this->deprecated );
	}

	#[DataProvider( 'stored_depths' )]
	public function test_the_maximum_depth_reads_at_least_one( string $stored, int $expected ): void {
		update_option( Options::MAX_DEPTH, $stored );

		self::assertSame( $expected, Linking::get_max_depth() );
		self::assertSame( $expected, Options::get_int( Options::MAX_DEPTH ) );
		self::assertSame( array(), $this->deprecated );
	}
}
