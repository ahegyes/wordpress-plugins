<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class SettingsApiTest extends TestCase {
	protected const array KEYS = array( DWS_LOWC_OPTION_MAX_DEPTH, DWS_LOWC_OPTION_AUTOCOMPLETE, DWS_LOWC_OPTION_DELETE_DATA );

	protected array $stored = array();

	protected array $deprecated = array();

	public static function stored_settings(): array {
		return array(
			'depth'               => array( DWS_LOWC_OPTION_MAX_DEPTH, '3', 'max-depth', 'general', 3 ),
			'depth-below-one'     => array( DWS_LOWC_OPTION_MAX_DEPTH, '0', 'max-depth', 'general', 1 ),
			'autocomplete-on'     => array( DWS_LOWC_OPTION_AUTOCOMPLETE, 'yes', 'autocomplete-descendants', 'general', true ),
			'autocomplete-off'    => array( DWS_LOWC_OPTION_AUTOCOMPLETE, 'no', 'autocomplete-descendants', 'general', false ),
			'consent-on'          => array( DWS_LOWC_OPTION_DELETE_DATA, 'yes', 'remove-data-uninstall', 'plugin', true ),
			'group-with-suffix'   => array( DWS_LOWC_OPTION_DELETE_DATA, 'yes', 'remove-data-uninstall', 'plugin-settings', true ),
			'group-from-field-id' => array( DWS_LOWC_OPTION_MAX_DEPTH, '5', 'max-depth', null, 5 ),
		);
	}

	#[Before]
	protected function store_rows(): void {
		foreach ( self::KEYS as $key ) {
			$this->stored[ $key ] = get_option( $key );
		}
		add_filter( 'deprecated_hook_trigger_error', '__return_false' );
		add_action( 'deprecated_hook_run', array( $this, 'record_deprecated_hook' ), 10, 2 );
	}

	#[After]
	protected function restore_rows(): void {
		foreach ( $this->stored as $key => $value ) {
			false === $value ? delete_option( $key ) : update_option( $key, $value );
		}
		remove_filter( 'deprecated_hook_trigger_error', '__return_false' );
		remove_action( 'deprecated_hook_run', array( $this, 'record_deprecated_hook' ) );
	}

	public function record_deprecated_hook( string $hook, string $replacement ): void {
		$this->deprecated[] = array( $hook, $replacement );
	}

	#[DataProvider( 'stored_settings' )]
	public function test_a_setting_reads_validated_by_its_v1_field_and_group( string $key, string $stored, string $field_id, ?string $group, int|bool $expected ): void {
		update_option( $key, $stored );

		self::assertSame( $stored, dws_lowc_get_raw_setting( $field_id, $group ) );
		self::assertSame( $expected, dws_lowc_get_validated_setting( $field_id, $group ) );
	}

	public function test_missing_settings_read_as_v1_defaults(): void {
		foreach ( self::KEYS as $key ) {
			delete_option( $key );
		}

		self::assertSame( 1, dws_lowc_get_validated_setting( 'max-depth', 'general' ) );
		self::assertFalse( dws_lowc_get_validated_setting( 'autocomplete-descendants', 'general' ) );
		self::assertFalse( dws_lowc_get_validated_setting( 'remove-data-uninstall', 'plugin' ) );
	}

	public function test_an_unknown_field_reads_as_null(): void {
		self::assertNull( dws_lowc_get_raw_setting( 'unknown', 'general' ) );
		self::assertNull( dws_lowc_get_validated_setting( 'unknown', 'general' ) );
		self::assertNull( dws_lowc_get_validated_setting( 'max-depth', 'plugin' ) );
	}

	public function test_a_value_validates_against_its_fields_routine(): void {
		self::assertSame( 1, dws_lowc_validate_setting( '-4', 'max-depth', 'general' ) );
		self::assertSame( 7, dws_lowc_validate_setting( '7', 'max-depth' ) );
		self::assertTrue( dws_lowc_validate_setting( 'yes', 'autocomplete-descendants', 'general' ) );
		self::assertFalse( dws_lowc_validate_setting( 'maybe', 'remove-data-uninstall', 'plugin' ) );
	}

	public function test_a_validated_value_passes_the_v1_group_filter_then_the_v1_section_filter_then_the_v2_filter(): void {
		update_option( DWS_LOWC_OPTION_MAX_DEPTH, '2' );
		$seen    = array();
		$filters = array(
			'linked_orders_for_woocommerce/general_settings/get_validated_option_value' => static function ( mixed $value, string $field_id ) use ( &$seen ): mixed {
				$seen[] = array( 'group', $value, $field_id );

				return 4;
			},
			'linked_orders_for_woocommerce/settings/get_validated_option_value'         => static function ( mixed $value, string $field_id ) use ( &$seen ): mixed {
				$seen[] = array( 'section', $value, $field_id );

				return 5;
			},
		);
		$v2      = static function ( mixed $value, string $field_id, string $group ) use ( &$seen ): mixed {
			$seen[] = array( 'v2', $value, $field_id, $group );

			return 6;
		};
		foreach ( $filters as $hook => $filter ) {
			add_filter( $hook, $filter, 10, 2 );
		}
		add_filter( 'dws_lowc/settings/validated_value', $v2, 10, 3 );

		try {
			$value = dws_lowc_get_validated_setting( 'max-depth', 'general' );
		} finally {
			foreach ( $filters as $hook => $filter ) {
				remove_filter( $hook, $filter );
			}
			remove_filter( 'dws_lowc/settings/validated_value', $v2 );
		}

		self::assertSame( 6, $value );
		self::assertSame(
			array(
				array( 'group', 2, 'max-depth' ),
				array( 'section', 4, 'max-depth' ),
				array( 'v2', 5, 'max-depth', 'general' ),
			),
			$seen
		);
		self::assertSame(
			array(
				array( 'linked_orders_for_woocommerce/general_settings/get_validated_option_value', 'dws_lowc/settings/validated_value' ),
				array( 'linked_orders_for_woocommerce/settings/get_validated_option_value', 'dws_lowc/settings/validated_value' ),
			),
			$this->deprecated
		);
	}
}
