<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration;

use DeepWebSolutions\LinkedOrders\Options;
use DeepWebSolutions\LinkedOrders\Tests\Support\RecordsDeprecationsTrait;
use DeepWebSolutions\LinkedOrders\Tests\Support\RestoresOptionsTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class SettingsApiTest extends TestCase {
	use RecordsDeprecationsTrait;
	use RestoresOptionsTrait;

	protected const array OPTIONS = array( Options::MAX_DEPTH, Options::AUTOCOMPLETE, Options::DELETE_DATA );

	public static function stored_settings(): array {
		return array(
			'depth'             => array( Options::MAX_DEPTH, '3', 'max-depth', 'general', 3 ),
			'depth-below-one'   => array( Options::MAX_DEPTH, '0', 'max-depth', 'general', 1 ),
			'autocomplete-on'   => array( Options::AUTOCOMPLETE, 'yes', 'autocomplete-descendants', 'general', true ),
			'autocomplete-off'  => array( Options::AUTOCOMPLETE, 'no', 'autocomplete-descendants', 'general', false ),
			'consent-on'        => array( Options::DELETE_DATA, 'yes', 'remove-data-uninstall', 'plugin', true ),
			'group-with-suffix' => array( Options::DELETE_DATA, 'yes', 'remove-data-uninstall', 'plugin-settings', true ),
			'group-in-field-id' => array( Options::MAX_DEPTH, '5', 'general/max-depth', null, 5 ),
		);
	}

	public static function paths(): array {
		return array(
			'group'           => array(
				'max-depth',
				'general',
				array( array( 'group', 2, 'max-depth' ) ),
				4,
				array( 'linked_orders_for_woocommerce/general_settings/get_validated_option_value' ),
			),
			'group-in-the-id' => array(
				'general/max-depth',
				null,
				array(
					array( 'group', 2, 'max-depth' ),
					array( 'section', 4, 'general/max-depth' ),
				),
				5,
				array( 'linked_orders_for_woocommerce/general_settings/get_validated_option_value', 'linked_orders_for_woocommerce/settings/get_validated_option_value' ),
			),
		);
	}

	#[DataProvider( 'stored_settings' )]
	public function test_a_setting_reads_validated_by_its_v1_field_and_group( string $key, string $stored, string $field_id, ?string $group, int|bool $expected ): void {
		update_option( $key, $stored );

		self::assertSame( $stored, dws_lowc_get_raw_setting( $field_id, $group ) );
		self::assertSame( $expected, dws_lowc_get_validated_setting( $field_id, $group ) );
		self::assertSame( array( array( 'dws_lowc_get_raw_setting', "get_option() on {$key}" ), array( 'dws_lowc_get_validated_setting', "get_option() on {$key}" ) ), $this->deprecated );
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
		self::assertSame( array( 'dws_lowc_get_validated_setting', 'get_option()' ), \end( $this->deprecated ) );
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
		self::assertSame( \array_fill( 0, 4, array( 'dws_lowc_validate_setting', '' ) ), $this->deprecated );
	}

	// v1 fired its section filter only for a read through the section, which names the group in the field id; the depth's filters give way to the child-creation decision.
	#[DataProvider( 'paths' )]
	public function test_a_validated_value_passes_the_v1_filters_of_its_path_alone( string $field_id, ?string $group, array $expected, int $filtered, array $deprecated ): void {
		update_option( Options::MAX_DEPTH, '2' );
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
		add_filter( 'linked_orders_for_woocommerce/settings/validated_value', $v2, 10, 3 );

		try {
			$value = dws_lowc_get_validated_setting( $field_id, $group );
		} finally {
			foreach ( $filters as $hook => $filter ) {
				remove_filter( $hook, $filter );
			}
			remove_filter( 'linked_orders_for_woocommerce/settings/validated_value', $v2 );
		}

		self::assertSame( $filtered, $value );
		self::assertSame( $expected, $seen );
		self::assertSame( array( array( 'dws_lowc_get_validated_setting', 'get_option() on dws-lowc_general_max-depth' ), ...\array_map( static fn ( string $hook ): array => array( $hook, 'linked_orders_for_woocommerce/node/can_create_child' ), $deprecated ) ), $this->deprecated );
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
		$read = array( 'dws_lowc_get_validated_setting', 'get_option()' );
		self::assertSame( array( $read, array( 'linked_orders_for_woocommerce/settings/get_validated_option_value', 'get_option' ), $read, array( 'linked_orders_for_woocommerce/settings/get_validated_option_value', 'get_option' ), $read ), $this->deprecated );
	}
}
