<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration;

use DeepWebSolutions\LinkedOrders\Settings;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class SettingsTest extends TestCase {
	protected const string SECTION = 'dws-linked-orders';

	protected const array KEYS = array( DWS_LOWC_OPTION_MAX_DEPTH, DWS_LOWC_OPTION_AUTOCOMPLETE, DWS_LOWC_OPTION_DELETE_DATA );

	protected static \WC_Settings_Page $advanced;

	protected array $stored = array();

	protected array $deprecated = array();

	#[\Override]
	public static function setUpBeforeClass(): void {
		require_once WC_ABSPATH . 'includes/admin/class-wc-admin-settings.php';
		// WooCommerce builds its settings pages once per request, so the class shares one build of the Advanced tab.
		foreach ( \WC_Admin_Settings::get_settings_pages() as $page ) {
			if ( 'advanced' === $page->get_id() ) {
				self::$advanced = $page;
			}
		}
	}

	public static function invalid_depths(): array {
		return array(
			'zero'     => array( '0' ),
			'negative' => array( '-2' ),
			'decimal'  => array( '2.5' ),
			'word'     => array( 'three' ),
			'empty'    => array( '' ),
		);
	}

	public static function groups(): array {
		return array(
			'general' => array( 'general', array( 'max-depth', 'autocomplete-descendants' ) ),
			'plugin'  => array( 'plugin', array( 'remove-data-uninstall' ) ),
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
		( new \ReflectionProperty( \WC_Admin_Settings::class, 'errors' ) )->setValue( null, array() );
		remove_filter( 'deprecated_hook_trigger_error', '__return_false' );
		remove_action( 'deprecated_hook_run', array( $this, 'record_deprecated_hook' ) );
	}

	public function record_deprecated_hook( string $hook, string $replacement ): void {
		$this->deprecated[] = array( $hook, $replacement );
	}

	public function test_the_section_sits_on_the_advanced_tab_under_its_v1_id(): void {
		self::assertSame( 'Linked Orders', self::$advanced->get_sections()[ self::SECTION ] ?? null );
	}

	public function test_the_section_shows_v1s_two_groups_with_their_defaults(): void {
		foreach ( self::KEYS as $key ) {
			delete_option( $key );
		}

		$form = $this->render();

		self::assertSame( array( 'General Settings', 'Plugin Settings' ), \array_map( static fn ( \Dom\Element $heading ): string => \trim( $heading->textContent ), \iterator_to_array( $form->querySelectorAll( 'h2' ) ) ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertSame( '1', $form->getElementById( DWS_LOWC_OPTION_MAX_DEPTH )?->getAttribute( 'value' ) );
		self::assertSame( '1', $form->getElementById( DWS_LOWC_OPTION_MAX_DEPTH )?->getAttribute( 'min' ) );
		self::assertSame( '99', $form->getElementById( DWS_LOWC_OPTION_MAX_DEPTH )?->getAttribute( 'max' ) );
		self::assertSame( 'checkbox', $form->getElementById( DWS_LOWC_OPTION_AUTOCOMPLETE )?->getAttribute( 'type' ) );
		self::assertFalse( $form->getElementById( DWS_LOWC_OPTION_AUTOCOMPLETE )?->hasAttribute( 'checked' ) );
		self::assertSame( 'checkbox', $form->getElementById( DWS_LOWC_OPTION_DELETE_DATA )?->getAttribute( 'type' ) );
		self::assertFalse( $form->getElementById( DWS_LOWC_OPTION_DELETE_DATA )?->hasAttribute( 'checked' ) );
	}

	public function test_the_checkboxes_store_yes_and_no_as_v1s_selects_did(): void {
		$this->save(
			array(
				DWS_LOWC_OPTION_AUTOCOMPLETE => '1',
				DWS_LOWC_OPTION_DELETE_DATA  => '1',
			)
		);

		self::assertSame( 'yes', get_option( DWS_LOWC_OPTION_AUTOCOMPLETE ) );
		self::assertSame( 'yes', get_option( DWS_LOWC_OPTION_DELETE_DATA ) );
		self::assertTrue( $this->render()->getElementById( DWS_LOWC_OPTION_DELETE_DATA )?->hasAttribute( 'checked' ) );

		$this->save( array() );

		self::assertSame( 'no', get_option( DWS_LOWC_OPTION_AUTOCOMPLETE ) );
		self::assertSame( 'no', get_option( DWS_LOWC_OPTION_DELETE_DATA ) );
	}

	#[DataProvider( 'invalid_depths' )]
	public function test_a_depth_that_is_not_a_whole_number_of_at_least_one_keeps_the_stored_depth( string $posted ): void {
		update_option( DWS_LOWC_OPTION_MAX_DEPTH, '4' );

		$this->save( array( DWS_LOWC_OPTION_MAX_DEPTH => $posted ) );

		self::assertSame( '4', get_option( DWS_LOWC_OPTION_MAX_DEPTH ) );
		self::assertSame( array( 'Maximum linked orders depth was not saved, so it keeps its previous value. Enter a whole number of 1 or more, such as 2.' ), ( new \ReflectionProperty( \WC_Admin_Settings::class, 'errors' ) )->getValue() );
	}

	public function test_a_whole_depth_of_at_least_one_is_stored(): void {
		$this->save( array( DWS_LOWC_OPTION_MAX_DEPTH => ' 3 ' ) );

		self::assertSame( '3', get_option( DWS_LOWC_OPTION_MAX_DEPTH ) );
		self::assertSame( 3, dws_lowc_get_max_depth() );
	}

	#[DataProvider( 'groups' )]
	public function test_a_groups_fields_pass_its_v1_filter_first_then_its_v2_filter( string $group, array $v1_ids ): void {
		$seen     = array();
		$v1_hook  = "linked_orders_for_woocommerce/{$group}_settings/get_group_fields";
		$v2_hook  = "dws_lowc/settings/{$group}_fields";
		$v1_extra = static function ( array $fields ) use ( &$seen ): array {
			$seen[] = array( 'v1', \array_keys( $fields ) );

			return $fields + array(
				'extra' => array(
					'type'  => 'text',
					'title' => 'Extra',
				),
			);
		};
		$v2_spy   = static function ( array $fields ) use ( &$seen ): array {
			$seen[] = array( 'v2', \array_column( $fields, 'id' ) );

			return $fields;
		};
		add_filter( $v1_hook, $v1_extra );
		add_filter( $v2_hook, $v2_spy );

		try {
			$fields = ( new Settings() )->get_section_fields( 'advanced', self::SECTION );
		} finally {
			remove_filter( $v1_hook, $v1_extra );
			remove_filter( $v2_hook, $v2_spy );
		}

		$prefix = "dws-lowc_{$group}_";
		self::assertSame( array( 'v1', $v1_ids ), $seen[0] );
		self::assertSame( array( 'v2', \array_map( static fn ( string $id ): string => $prefix . $id, \array_merge( $v1_ids, array( 'extra' ) ) ) ), $seen[1] );
		self::assertContains( $prefix . 'extra', \array_column( $fields, 'id' ) );
		self::assertSame( array( array( $v1_hook, $v2_hook ) ), $this->deprecated );
	}

	public function test_the_plugin_row_links_to_the_section_and_to_the_support_forum(): void {
		$basename = plugin_basename( DWS_LOWC_FILE );

		$links = apply_filters( "plugin_action_links_{$basename}", array( 'deactivate' => '<a href="#">Deactivate</a>' ), $basename, array(), 'all' );
		$meta  = apply_filters( 'plugin_row_meta', array(), $basename, array(), 'all' );

		self::assertStringContainsString( \esc_url( admin_url( 'admin.php?page=wc-settings&tab=advanced&section=' . self::SECTION ) ), (string) \reset( $links ) );
		self::assertStringContainsString( 'https://wordpress.org/support/plugin/linked-orders-for-woocommerce/', \implode( '', $meta ) );
		self::assertSame( array(), apply_filters( 'plugin_row_meta', array(), 'another-plugin/another-plugin.php', array(), 'all' ) );
	}

	protected function render(): \Dom\HTMLDocument {
		\ob_start();
		\WC_Admin_Settings::output_fields( self::$advanced->get_settings_for_section( self::SECTION ) );

		return \Dom\HTMLDocument::createFromString( '<!DOCTYPE html><html><body>' . \ob_get_clean() . '</body></html>', \LIBXML_NOERROR );
	}

	// WooCommerce's form always posts its save button, so a save that changes nothing still runs.
	protected function save( array $posted ): void {
		\WC_Admin_Settings::save_fields( self::$advanced->get_settings_for_section( self::SECTION ), $posted + array( 'save' => 'Save changes' ) );
		wp_cache_flush();
	}
}
