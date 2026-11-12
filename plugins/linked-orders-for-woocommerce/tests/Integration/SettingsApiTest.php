<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration;

use DeepWebSolutions\LinkedOrders\Tests\Support\RecordsDeprecationsTrait;
use DeepWebSolutions\LinkedOrders\Tests\Support\RestoresOptionsTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class SettingsApiTest extends TestCase {
	use RecordsDeprecationsTrait;
	use RestoresOptionsTrait;

	protected const array OPTIONS = array( DWS_LOWC_OPTION_MAX_DEPTH, DWS_LOWC_OPTION_AUTOCOMPLETE, DWS_LOWC_OPTION_DELETE_DATA );

	public static function stored_settings(): array {
		return array(
			'depth'             => array( DWS_LOWC_OPTION_MAX_DEPTH, '3', 'max-depth', 'general', 3 ),
			'depth-below-one'   => array( DWS_LOWC_OPTION_MAX_DEPTH, '0', 'max-depth', 'general', 1 ),
			'autocomplete-on'   => array( DWS_LOWC_OPTION_AUTOCOMPLETE, 'yes', 'autocomplete-descendants', 'general', true ),
			'autocomplete-off'  => array( DWS_LOWC_OPTION_AUTOCOMPLETE, 'no', 'autocomplete-descendants', 'general', false ),
			'consent-on'        => array( DWS_LOWC_OPTION_DELETE_DATA, 'yes', 'remove-data-uninstall', 'plugin', true ),
			'group-with-suffix' => array( DWS_LOWC_OPTION_DELETE_DATA, 'yes', 'remove-data-uninstall', 'plugin-settings', true ),
			'group-in-field-id' => array( DWS_LOWC_OPTION_MAX_DEPTH, '5', 'general/max-depth', null, 5 ),
		);
	}

	#[DataProvider( 'stored_settings' )]
	public function test_a_setting_reads_validated_by_its_v1_field_and_group( string $key, string $stored, string $field_id, ?string $group, int|bool $expected ): void {
		update_option( $key, $stored );

		self::assertSame( $stored, dws_lowc_get_raw_setting( $field_id, $group ) );
		self::assertSame( $expected, dws_lowc_get_validated_setting( $field_id, $group ) );
	}

	public function test_missing_settings_read_as_v1_defaults(): void {
		foreach ( self::OPTIONS as $key ) {
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
		self::assertNull( dws_lowc_get_validated_setting( 'max-depth' ) );
	}

	// v1 read any field of a known group from its option, such as one an extension adds through the group's field filter.
	public function test_a_field_an_extension_added_to_a_group_reads_from_its_option(): void {
		update_option( 'dws-lowc_general_extra', 'kept' );

		try {
			self::assertSame( 'kept', dws_lowc_get_raw_setting( 'extra', 'general' ) );
			self::assertSame( 'kept', dws_lowc_get_validated_setting( 'extra', 'general-settings' ) );
			self::assertSame( 'kept', dws_lowc_get_validated_setting( 'general/extra' ) );
			self::assertNull( dws_lowc_get_raw_setting( 'extra', 'unknown' ) );
		} finally {
			delete_option( 'dws-lowc_general_extra' );
		}
	}

	public function test_a_value_validates_against_its_fields_routine(): void {
		self::assertSame( 1, dws_lowc_validate_setting( '-4', 'max-depth', 'general' ) );
		self::assertSame( 7, dws_lowc_validate_setting( '7', 'general/max-depth' ) );
		self::assertTrue( dws_lowc_validate_setting( 'yes', 'autocomplete-descendants', 'general' ) );
		self::assertFalse( dws_lowc_validate_setting( 'maybe', 'remove-data-uninstall', 'plugin' ) );
	}

	public static function paths(): array {
		return array(
			'group'           => array(
				'max-depth',
				'general',
				array(
					array( 'group', 2, 'max-depth' ),
					array( 'v2', 4, 'max-depth', 'general' ),
				),
				array( 'linked_orders_for_woocommerce/general_settings/get_validated_option_value' ),
			),
			'group-in-the-id' => array(
				'general/max-depth',
				null,
				array(
					array( 'group', 2, 'max-depth' ),
					array( 'section', 4, 'general/max-depth' ),
					array( 'v2', 5, 'max-depth', 'general' ),
				),
				array( 'linked_orders_for_woocommerce/general_settings/get_validated_option_value', 'linked_orders_for_woocommerce/settings/get_validated_option_value' ),
			),
		);
	}

	// v1 fired its section filter only for a read through the section, which names the group in the field id.
	#[DataProvider( 'paths' )]
	public function test_a_validated_value_passes_the_v1_filters_of_its_path_then_the_v2_filter( string $field_id, ?string $group, array $expected, array $deprecated ): void {
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
			$value = dws_lowc_get_validated_setting( $field_id, $group );
		} finally {
			foreach ( $filters as $hook => $filter ) {
				remove_filter( $hook, $filter );
			}
			remove_filter( 'dws_lowc/settings/validated_value', $v2 );
		}

		self::assertSame( 6, $value );
		self::assertSame( $expected, $seen );
		self::assertSame( \array_map( static fn ( string $hook ): array => array( $hook, 'dws_lowc/settings/validated_value' ), $deprecated ), $this->deprecated );
	}

	// v1 read every id through the section's filter, so an extension answered for an id no group maps.
	public function test_a_section_read_of_an_id_no_group_maps_passes_the_v1_section_filter(): void {
		$seen    = array();
		$section = static function ( mixed $value, string $field_id ) use ( &$seen ): mixed {
			$seen[] = array( $value, $field_id );
			return "extension:{$field_id}";
		};
		add_filter( 'linked_orders_for_woocommerce/settings/get_validated_option_value', $section, 10, 2 );

		try {
			$values = array( dws_lowc_get_validated_setting( 'extension-field' ), dws_lowc_get_validated_setting( 'other/field' ), dws_lowc_get_validated_setting( 'field', 'other' ) );
		} finally {
			remove_filter( 'linked_orders_for_woocommerce/settings/get_validated_option_value', $section );
		}

		self::assertSame( array( 'extension:extension-field', 'extension:other/field', null ), $values );
		self::assertSame( array( array( null, 'extension-field' ), array( null, 'other/field' ) ), $seen );
		self::assertSame( \array_fill( 0, 2, array( 'linked_orders_for_woocommerce/settings/get_validated_option_value', 'dws_lowc/settings/validated_value' ) ), $this->deprecated );
	}

	// The v1 filter's deprecation names the v2 filter, so an extension that moves its callback there still answers.
	public function test_a_section_read_of_an_id_no_group_maps_passes_the_v2_filter_after_the_v1_one(): void {
		$seen = array();
		$v1   = static fn ( mixed $value, string $field_id ): string => "v1:{$field_id}";
		$v2   = static function ( mixed $value, string $field_id, string $group ) use ( &$seen ): string {
			$seen[] = array( $value, $field_id, $group );
			return "v2:{$field_id}";
		};
		add_filter( 'linked_orders_for_woocommerce/settings/get_validated_option_value', $v1, 10, 2 );
		add_filter( 'dws_lowc/settings/validated_value', $v2, 10, 3 );

		try {
			$values = array( dws_lowc_get_validated_setting( 'extension-field' ), dws_lowc_get_validated_setting( 'other/field' ), dws_lowc_get_validated_setting( 'other-settings/field' ), dws_lowc_get_validated_setting( 'field', 'other' ) );
		} finally {
			remove_filter( 'linked_orders_for_woocommerce/settings/get_validated_option_value', $v1 );
			remove_filter( 'dws_lowc/settings/validated_value', $v2 );
		}

		self::assertSame( array( 'v2:extension-field', 'v2:field', 'v2:field', null ), $values );
		self::assertSame( array( array( 'v1:extension-field', 'extension-field', '' ), array( 'v1:other/field', 'field', 'other' ), array( 'v1:other-settings/field', 'field', 'other' ) ), $seen );
	}
}
