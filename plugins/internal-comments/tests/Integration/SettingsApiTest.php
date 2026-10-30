<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class SettingsApiTest extends TestCase {
	protected const array ROWS = array( DWS_IC_OPTION_GENERAL, DWS_IC_OPTION_PLUGIN );

	protected array $stored = array();

	protected array $deprecated = array();

	public static function stored_settings(): array {
		return array(
			'post-types'        => array( DWS_IC_OPTION_GENERAL, DWS_IC_SETTING_POST_TYPES, array( 'post', 'page' ), 'post-types', 'general', array( 'post', 'page' ) ),
			'unknown-type'      => array( DWS_IC_OPTION_GENERAL, DWS_IC_SETTING_POST_TYPES, array( 'post', 'dws_ic_missing' ), 'post-types', 'general', array( 'post' ) ),
			'types-not-a-list'  => array( DWS_IC_OPTION_GENERAL, DWS_IC_SETTING_POST_TYPES, 'post', 'post-types', 'general', array() ),
			'private-on'        => array( DWS_IC_OPTION_GENERAL, DWS_IC_SETTING_PRIVATE_COMMENTS, 'yes', 'enable-private-comments', 'general', true ),
			'private-off'       => array( DWS_IC_OPTION_GENERAL, DWS_IC_SETTING_PRIVATE_COMMENTS, 'no', 'enable-private-comments', 'general', false ),
			'consent-on'        => array( DWS_IC_OPTION_PLUGIN, DWS_IC_SETTING_DELETE_DATA, 'yes', 'remove-data-uninstall', 'plugin', true ),
			'consent-malformed' => array( DWS_IC_OPTION_PLUGIN, DWS_IC_SETTING_DELETE_DATA, 'maybe', 'remove-data-uninstall', 'plugin', false ),
			'group-with-suffix' => array( DWS_IC_OPTION_PLUGIN, DWS_IC_SETTING_DELETE_DATA, 'yes', 'remove-data-uninstall', 'plugin-settings', true ),
			'group-in-field-id' => array( DWS_IC_OPTION_GENERAL, DWS_IC_SETTING_PRIVATE_COMMENTS, 'yes', 'general/enable-private-comments', null, true ),
		);
	}

	#[Before]
	protected function store_rows(): void {
		foreach ( self::ROWS as $row ) {
			$this->stored[ $row ] = get_option( $row );
		}
		add_filter( 'deprecated_hook_trigger_error', '__return_false' );
		add_action( 'deprecated_hook_run', array( $this, 'record_deprecated_hook' ), 10, 2 );
	}

	#[After]
	protected function restore_rows(): void {
		foreach ( $this->stored as $row => $value ) {
			false === $value ? delete_option( $row ) : update_option( $row, $value );
		}
		remove_filter( 'deprecated_hook_trigger_error', '__return_false' );
		remove_action( 'deprecated_hook_run', array( $this, 'record_deprecated_hook' ) );
	}

	public function record_deprecated_hook( string $hook, string $replacement ): void {
		$this->deprecated[] = array( $hook, $replacement );
	}

	#[DataProvider( 'stored_settings' )]
	public function test_a_setting_reads_from_its_v1_row_validated_by_its_field( string $row, string $field, mixed $stored, string $field_id, ?string $group, array|bool $expected ): void {
		update_option( $row, array( $field => $stored ) );

		self::assertSame( $stored, dws_ic_get_raw_setting( $field_id, $group ) );
		self::assertSame( $expected, dws_ic_get_validated_setting( $field_id, $group ) );
	}

	public function test_missing_settings_read_as_v1_defaults(): void {
		foreach ( self::ROWS as $row ) {
			delete_option( $row );
		}

		self::assertNull( dws_ic_get_raw_setting( 'post-types', 'general' ) );
		self::assertSame( array(), dws_ic_get_validated_setting( 'post-types', 'general' ) );
		self::assertFalse( dws_ic_get_validated_setting( 'enable-private-comments', 'general' ) );
		self::assertFalse( dws_ic_get_validated_setting( 'remove-data-uninstall', 'plugin' ) );
		self::assertSame( array(), dws_ic_get_selected_post_types() );
		self::assertFalse( dws_ic_are_enabled_private_comments() );
	}

	public function test_an_unknown_field_or_group_reads_as_null(): void {
		self::assertNull( dws_ic_get_raw_setting( 'unknown', 'general' ) );
		self::assertNull( dws_ic_get_validated_setting( 'unknown', 'general' ) );
		self::assertNull( dws_ic_get_validated_setting( 'post-types', 'unknown' ) );
		self::assertNull( dws_ic_get_validated_setting( 'post-types' ) );
	}

	// v1 read any field of a known group from its row, such as one an extension adds through the group's field filter.
	public function test_a_field_an_extension_added_to_a_group_reads_from_its_row(): void {
		update_option( DWS_IC_OPTION_GENERAL, array( 'extra' => 'kept' ) );

		self::assertSame( 'kept', dws_ic_get_raw_setting( 'extra', 'general' ) );
		self::assertSame( 'kept', dws_ic_get_validated_setting( 'extra', 'general-settings' ) );
		self::assertSame( 'kept', dws_ic_get_validated_setting( 'general/extra' ) );
	}

	// v1 read the selected post types raw, so a post type registered after plugins_loaded stays selected before it registers.
	public function test_the_selected_post_types_read_the_stored_names_unvalidated(): void {
		update_option( DWS_IC_OPTION_GENERAL, array( DWS_IC_SETTING_POST_TYPES => array( 'post', 'dws_ic_later', 7 ) ) );

		self::assertSame( array( 'post', 'dws_ic_later' ), dws_ic_get_selected_post_types() );
	}

	public function test_private_comments_read_as_their_validated_setting(): void {
		update_option( DWS_IC_OPTION_GENERAL, array( DWS_IC_SETTING_PRIVATE_COMMENTS => 'yes' ) );

		self::assertTrue( dws_ic_are_enabled_private_comments() );
	}

	public static function paths(): array {
		return array(
			'group'           => array(
				'enable-private-comments',
				'general',
				array(
					array( 'group', true, 'enable-private-comments' ),
					array( 'v2', 'group', 'enable-private-comments', 'general' ),
				),
				array( 'internal_comments/general_settings/get_validated_option_value' ),
			),
			'group-in-the-id' => array(
				'general/enable-private-comments',
				null,
				array(
					array( 'group', true, 'enable-private-comments' ),
					array( 'section', 'group', 'general/enable-private-comments' ),
					array( 'v2', 'section', 'enable-private-comments', 'general' ),
				),
				array( 'internal_comments/general_settings/get_validated_option_value', 'internal_comments/settings/get_validated_option_value' ),
			),
		);
	}

	// v1 fired its section filter only for a read through the section, which names the group in the field id.
	#[DataProvider( 'paths' )]
	public function test_a_validated_value_passes_the_v1_filters_of_its_path_then_the_v2_filter( string $field_id, ?string $group, array $expected, array $deprecated ): void {
		update_option( DWS_IC_OPTION_GENERAL, array( DWS_IC_SETTING_PRIVATE_COMMENTS => 'yes' ) );
		$seen    = array();
		$filters = array(
			'internal_comments/general_settings/get_validated_option_value' => static function ( mixed $value, string $field_id ) use ( &$seen ): mixed {
				$seen[] = array( 'group', $value, $field_id );

				return 'group';
			},
			'internal_comments/settings/get_validated_option_value'         => static function ( mixed $value, string $field_id ) use ( &$seen ): mixed {
				$seen[] = array( 'section', $value, $field_id );

				return 'section';
			},
		);
		$v2      = static function ( mixed $value, string $field_id, string $group ) use ( &$seen ): mixed {
			$seen[] = array( 'v2', $value, $field_id, $group );

			return 'v2';
		};
		foreach ( $filters as $hook => $filter ) {
			add_filter( $hook, $filter, 10, 2 );
		}
		add_filter( 'dws_ic/settings/validated_value', $v2, 10, 3 );

		try {
			$value = dws_ic_get_validated_setting( $field_id, $group );
		} finally {
			foreach ( $filters as $hook => $filter ) {
				remove_filter( $hook, $filter );
			}
			remove_filter( 'dws_ic/settings/validated_value', $v2 );
		}

		self::assertSame( 'v2', $value );
		self::assertSame( $expected, $seen );
		self::assertSame( \array_map( static fn ( string $hook ): array => array( $hook, 'dws_ic/settings/validated_value' ), $deprecated ), $this->deprecated );
	}
}
