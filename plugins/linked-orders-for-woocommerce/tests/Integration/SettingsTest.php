<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration;

use DeepWebSolutions\LinkedOrders\Settings;
use DeepWebSolutions\LinkedOrders\Tests\Support\RecordsDeprecationsTrait;
use DeepWebSolutions\LinkedOrders\Tests\Support\RestoresOptionsTrait;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( Settings::class )]
final class SettingsTest extends TestCase {
	use RecordsDeprecationsTrait;
	use RestoresOptionsTrait;

	protected const string SECTION = 'dws-linked-orders';

	protected const array OPTIONS = array( DWS_LOWC_OPTION_MAX_DEPTH, DWS_LOWC_OPTION_AUTOCOMPLETE, DWS_LOWC_OPTION_DELETE_DATA );

	protected static \WC_Settings_Page $advanced;

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

	public static function setting_groups(): array {
		return array(
			'general' => array( 'general', array( 'max-depth', 'autocomplete-descendants' ) ),
			'plugin'  => array( 'plugin', array( 'remove-data-uninstall' ) ),
		);
	}

	#[After]
	protected function forget_the_rejections(): void {
		new \ReflectionProperty( \WC_Admin_Settings::class, 'errors' )->setValue( null, array() );
	}

	public function test_the_section_sits_on_the_advanced_tab_under_its_v1_id(): void {
		self::assertSame( 'Linked Orders', self::$advanced->get_sections()[ self::SECTION ] ?? null );
	}

	public function test_the_section_shows_v1s_two_groups_with_their_defaults(): void {
		foreach ( self::OPTIONS as $key ) {
			delete_option( $key );
		}

		$form = $this->render();

		self::assertSame( array( 'General settings', 'Plugin settings' ), \array_map( static fn ( \Dom\Element $heading ): string => \trim( $heading->textContent ), \iterator_to_array( $form->querySelectorAll( 'h2' ) ) ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertSame( '1', $form->getElementById( DWS_LOWC_OPTION_MAX_DEPTH )?->getAttribute( 'value' ) );
		self::assertSame( '1', $form->getElementById( DWS_LOWC_OPTION_MAX_DEPTH )?->getAttribute( 'min' ) );
		self::assertSame( '99', $form->getElementById( DWS_LOWC_OPTION_MAX_DEPTH )?->getAttribute( 'max' ) );
		self::assertSame( 'checkbox', $form->getElementById( DWS_LOWC_OPTION_AUTOCOMPLETE )?->getAttribute( 'type' ) );
		self::assertFalse( $form->getElementById( DWS_LOWC_OPTION_AUTOCOMPLETE )?->hasAttribute( 'checked' ) );
		self::assertSame( 'checkbox', $form->getElementById( DWS_LOWC_OPTION_DELETE_DATA )?->getAttribute( 'type' ) );
		self::assertFalse( $form->getElementById( DWS_LOWC_OPTION_DELETE_DATA )?->hasAttribute( 'checked' ) );
	}

	// The help says what each setting does where the merchant reads it, counting levels as the orders list does, and the consent names what deleting the plugin erases.
	public function test_the_maximum_depth_and_the_consent_explain_themselves_beside_their_fields(): void {
		$form = $this->render();

		self::assertSame( 'How many levels of children an order can have below it, such as 2 for children and grandchildren.', \trim( (string) $form->getElementById( DWS_LOWC_OPTION_MAX_DEPTH )?->parentElement?->querySelector( '.description' )?->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertStringContainsString( 'Delete the plugin\'s settings and the links between orders when the plugin is deleted. This cannot be undone.', (string) $form->getElementById( DWS_LOWC_OPTION_DELETE_DATA )?->parentElement?->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
	}

	// WooCommerce fires an action named after each group's title and end ids, which v1's listeners hook.
	public function test_the_groups_keep_v1s_ids_for_the_actions_woocommerce_fires_around_them(): void {
		$hooks  = array( 'woocommerce_settings_dws-lowc_general_start', 'woocommerce_settings_dws-lowc_general_end_end', 'woocommerce_settings_dws-lowc_plugin_start', 'woocommerce_settings_dws-lowc_plugin_end_end' );
		$before = \array_map( 'did_action', $hooks );

		$this->render();

		self::assertSame( array( 1, 1, 1, 1 ), \array_map( static fn ( string $hook, int $count ): int => did_action( $hook ) - $count, $hooks, $before ) );
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
		self::assertSame( array( 'Maximum linked orders depth was not saved and keeps its previous value. The other changes were saved. Enter a whole number of 1 or more, such as 2.' ), new \ReflectionProperty( \WC_Admin_Settings::class, 'errors' )->getValue() );
	}

	public function test_a_whole_depth_of_at_least_one_is_stored(): void {
		$this->save( array( DWS_LOWC_OPTION_MAX_DEPTH => ' 3 ' ) );

		self::assertSame( '3', get_option( DWS_LOWC_OPTION_MAX_DEPTH ) );
		self::assertSame( 3, dws_lowc_get_max_depth() );
	}

	#[DataProvider( 'setting_groups' )]
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
			$fields = new Settings()->get_section_fields( 'advanced', self::SECTION );
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

	// v1 keyed its row links and named them for screen readers, and other plugins find them by key.
	public function test_the_plugin_row_links_to_the_section_and_to_the_support_forum_under_v1s_keys(): void {
		$basename = plugin_basename( DWS_LOWC_FILE );

		$links = apply_filters( "plugin_action_links_{$basename}", array( 'deactivate' => '<a href="#">Deactivate</a>' ), $basename, array(), 'all' );
		$meta  = apply_filters( 'plugin_row_meta', array(), $basename, array(), 'all' );

		self::assertSame( array( 'settings', 'deactivate' ), \array_keys( $links ) );
		self::assertStringContainsString( esc_url( admin_url( 'admin.php?page=wc-settings&tab=advanced&section=' . self::SECTION ) ), $links['settings'] );
		self::assertStringContainsString( 'aria-label="View settings"', $links['settings'] );
		self::assertSame( array( 'support' ), \array_keys( $meta ) );
		self::assertStringContainsString( 'https://wordpress.org/support/plugin/linked-orders-for-woocommerce/', $meta['support'] );
		self::assertStringContainsString( 'aria-label="Visit community forums"', $meta['support'] );
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
