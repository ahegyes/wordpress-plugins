<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class OptionsTest extends TestCase {
	protected const array RESTORED = array( DWS_LOWC_OPTION_MAX_DEPTH, DWS_LOWC_OPTION_AUTOCOMPLETE, DWS_LOWC_OPTION_DELETE_DATA );

	protected array $stored = array();

	public static function stored_depths(): array {
		return array(
			'two'      => array( '2', 2 ),
			'zero'     => array( '0', 1 ),
			'negative' => array( '-3', 1 ),
			'word'     => array( 'deep', 1 ),
		);
	}

	public static function submitted_depths(): array {
		return array(
			'one'      => array( '1', '1' ),
			'padded'   => array( ' 3 ', '3' ),
			'zero'     => array( '0', null ),
			'negative' => array( '-3', null ),
			'decimal'  => array( '2.5', null ),
			'word'     => array( 'three', null ),
			'empty'    => array( '', null ),
			'array'    => array( array( '3' ), null ),
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
		foreach ( $this->stored as $key => $value ) {
			false === $value ? delete_option( $key ) : update_option( $key, $value );
		}
	}

	public function test_the_settings_keep_the_keys_v1_stored_them_under(): void {
		self::assertSame( 'dws-lowc_general_max-depth', DWS_LOWC_OPTION_MAX_DEPTH );
		self::assertSame( 'dws-lowc_general_autocomplete-descendants', DWS_LOWC_OPTION_AUTOCOMPLETE );
		self::assertSame( 'dws-lowc_plugin_remove-data-uninstall', DWS_LOWC_OPTION_DELETE_DATA );
	}

	public function test_a_missing_depth_reads_as_v1s_default(): void {
		foreach ( self::RESTORED as $key ) {
			delete_option( $key );
		}

		self::assertSame( 1, dws_lowc_get_max_depth() );
	}

	#[DataProvider( 'stored_depths' )]
	public function test_the_maximum_depth_reads_at_least_one( string $stored, int $expected ): void {
		update_option( DWS_LOWC_OPTION_MAX_DEPTH, $stored );

		self::assertSame( $expected, dws_lowc_get_max_depth() );
	}

	#[DataProvider( 'submitted_depths' )]
	public function test_a_submitted_depth_validates_as_a_whole_number_of_at_least_one( mixed $submitted, ?string $expected ): void {
		$validated = dws_lowc_validate_max_depth( $submitted );

		\is_null( $expected ) ? self::assertInstanceOf( \WP_Error::class, $validated ) : self::assertSame( $expected, $validated );
	}
}
