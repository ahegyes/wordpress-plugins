<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Settings\AdminPage\OptionsPage;
use DeepWebSolutions\InternalComments\Settings;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class SettingsTest extends TestCase {
	protected const array ROWS = array( DWS_IC_OPTION_GENERAL, DWS_IC_OPTION_PLUGIN );

	protected static OptionsPage $page;

	protected array $stored = array();

	protected array $deprecated = array();

	#[\Override]
	public static function setUpBeforeClass(): void {
		require_once ABSPATH . 'wp-admin/includes/template.php';
		self::$page = new OptionsPage( Settings::PAGE, new Settings() );
		self::$page->register_settings();
	}

	#[\Override]
	public static function tearDownAfterClass(): void {
		foreach ( self::ROWS as $row ) {
			remove_filter( "pre_update_option_$row", array( self::$page, 'sanitize_submission' ) );
			remove_filter( "option_page_capability_$row", array( self::$page, 'get_capability' ) );
			unregister_setting( $row, $row );
		}
	}

	public static function setting_groups(): array {
		return array(
			'general' => array( 'general', array( 'post-types', 'enable-private-comments' ) ),
			'plugin'  => array( 'plugin', array( 'remove-data-uninstall' ) ),
		);
	}

	#[Before]
	protected function store_rows(): void {
		foreach ( self::ROWS as $row ) {
			$this->stored[ $row ] = get_option( $row );
		}
		$GLOBALS['wp_settings_errors'] = array();
		add_filter( 'deprecated_hook_trigger_error', '__return_false' );
		add_action( 'deprecated_hook_run', array( $this, 'record_deprecated_hook' ), 10, 2 );
	}

	#[After]
	protected function restore_rows(): void {
		foreach ( $this->stored as $row => $value ) {
			false === $value ? delete_option( $row ) : update_option( $row, $value );
		}
		unset( $_GET['tab'] );
		remove_filter( 'deprecated_hook_trigger_error', '__return_false' );
		remove_action( 'deprecated_hook_run', array( $this, 'record_deprecated_hook' ) );
	}

	public function record_deprecated_hook( string $hook, string $replacement ): void {
		$this->deprecated[] = array( $hook, $replacement );
	}

	public function test_the_page_keeps_v1s_slug_parent_capability_and_tabs(): void {
		$page = ( new Settings() )->get_options_page( Settings::PAGE );

		self::assertSame( 'dws-internal-comments', Settings::PAGE );
		self::assertSame( 'options-general.php', $page['parent'] );
		self::assertSame( DWS_IC_CAP_MANAGE_OPTIONS, $page['capability'] );
		self::assertSame( 'Internal Comments', $page['menu_title'] );
		self::assertSame( array( 'general', 'plugin' ), \array_column( $page['tabs'], 'slug' ) );
		self::assertSame( array( DWS_IC_OPTION_GENERAL, DWS_IC_OPTION_PLUGIN ), \array_column( $page['tabs'], 'option' ) );
	}

	public function test_the_general_tab_offers_every_post_type_with_an_admin_screen_and_the_private_comments_switch(): void {
		delete_option( DWS_IC_OPTION_GENERAL );

		$form   = $this->render( 'general' );
		$values = \array_map( static fn ( \Dom\Element $input ): string => $input->getAttribute( 'value' ), \iterator_to_array( $form->querySelectorAll( 'input[type="checkbox"][name="dws-internal-comments_general[post-types][]"]' ) ) );

		self::assertEqualsCanonicalizing( \array_values( get_post_types( array( 'show_ui' => true ) ) ), $values );
		self::assertNull( $form->querySelector( 'input[name="dws-internal-comments_general[post-types][]"][checked]' ) );
		self::assertNotNull( $form->querySelector( 'input[type="checkbox"][name="dws-internal-comments_general[enable-private-comments]"]:not([checked])' ) );
		self::assertNull( $form->querySelector( 'input[name="dws-internal-comments_plugin[remove-data-uninstall]"]' ) );
	}

	public function test_the_plugin_tab_holds_the_uninstall_consent(): void {
		delete_option( DWS_IC_OPTION_PLUGIN );

		$form = $this->render( 'plugin' );

		self::assertNotNull( $form->querySelector( 'input[type="checkbox"][name="dws-internal-comments_plugin[remove-data-uninstall]"]:not([checked])' ) );
		self::assertNull( $form->querySelector( 'input[name="dws-internal-comments_general[post-types][]"]' ) );
	}

	public function test_the_post_types_and_switches_store_as_v1_stored_them(): void {
		$this->save(
			DWS_IC_OPTION_GENERAL,
			array(
				DWS_IC_SETTING_POST_TYPES       => array( 'post', 'page' ),
				DWS_IC_SETTING_PRIVATE_COMMENTS => 'yes',
			)
		);
		$this->save( DWS_IC_OPTION_PLUGIN, array( DWS_IC_SETTING_DELETE_DATA => 'yes' ) );

		self::assertSame(
			array(
				DWS_IC_SETTING_POST_TYPES       => array( 'post', 'page' ),
				DWS_IC_SETTING_PRIVATE_COMMENTS => 'yes',
			),
			get_option( DWS_IC_OPTION_GENERAL )
		);
		self::assertSame( array( DWS_IC_SETTING_DELETE_DATA => 'yes' ), get_option( DWS_IC_OPTION_PLUGIN ) );
		self::assertNotNull( $this->render( 'general' )->querySelector( 'input[name="dws-internal-comments_general[post-types][]"][value="page"][checked]' ) );
	}

	// v1's form posted nothing for an empty selection, so the selection it had stayed stored.
	public function test_clearing_every_post_type_stores_an_empty_selection(): void {
		update_option( DWS_IC_OPTION_GENERAL, array( DWS_IC_SETTING_POST_TYPES => array( 'post' ) ) );

		$this->save(
			DWS_IC_OPTION_GENERAL,
			array(
				DWS_IC_SETTING_POST_TYPES       => '',
				DWS_IC_SETTING_PRIVATE_COMMENTS => 'no',
			)
		);

		self::assertSame( array(), get_option( DWS_IC_OPTION_GENERAL )[ DWS_IC_SETTING_POST_TYPES ] ?? null );
	}

	public function test_a_post_type_without_an_admin_screen_is_refused(): void {
		update_option( DWS_IC_OPTION_GENERAL, array( DWS_IC_SETTING_POST_TYPES => array( 'post' ) ) );

		$this->save( DWS_IC_OPTION_GENERAL, array( DWS_IC_SETTING_POST_TYPES => array( 'post', 'nav_menu_item' ) ) );

		self::assertSame( array( 'post' ), get_option( DWS_IC_OPTION_GENERAL )[ DWS_IC_SETTING_POST_TYPES ] ?? null );
		self::assertCount( 1, get_settings_errors( DWS_IC_OPTION_GENERAL ) );
	}

	#[DataProvider( 'setting_groups' )]
	public function test_a_groups_fields_pass_its_v1_filter_first_then_its_v2_filter( string $group, array $v1_ids ): void {
		$seen     = array();
		$v1_hook  = "internal_comments/{$group}_settings/get_group_fields";
		$v2_hook  = "dws_ic/settings/{$group}_fields";
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
			$tabs = ( new Settings() )->get_options_page( Settings::PAGE )['tabs'];
		} finally {
			remove_filter( $v1_hook, $v1_extra );
			remove_filter( $v2_hook, $v2_spy );
		}

		$tab = \array_find( $tabs, static fn ( array $tab ): bool => $group === $tab['slug'] );
		self::assertSame( array( array( 'v1', $v1_ids ), array( 'v2', \array_merge( $v1_ids, array( 'extra' ) ) ) ), $seen );
		self::assertSame( \array_merge( $v1_ids, array( 'extra' ) ), \array_column( $tab['fields'] ?? array(), 'id' ) );
		self::assertSame( array( array( $v1_hook, $v2_hook ) ), $this->deprecated );
	}

	public function test_the_plugin_row_links_to_the_page_and_to_the_support_forum(): void {
		$basename = plugin_basename( DWS_IC_FILE );

		$links = apply_filters( "plugin_action_links_{$basename}", array( 'deactivate' => '<a href="#">Deactivate</a>' ), $basename, array(), 'all' );
		$meta  = apply_filters( 'plugin_row_meta', array(), $basename, array(), 'all' );

		self::assertStringContainsString( \esc_url( admin_url( 'options-general.php?page=dws-internal-comments' ) ), (string) \reset( $links ) );
		self::assertStringContainsString( 'https://wordpress.org/support/plugin/internal-comments/', \implode( '', $meta ) );
		self::assertSame( array(), apply_filters( 'plugin_row_meta', array(), 'another-plugin/another-plugin.php', array(), 'all' ) );
	}

	protected function render( string $tab ): \Dom\HTMLDocument {
		global $wp_settings_fields;
		$wp_settings_fields = array();
		$_GET['tab']        = $tab;
		\ob_start();
		self::$page->render_page();

		return \Dom\HTMLDocument::createFromString( '<!DOCTYPE html><html><body>' . \ob_get_clean() . '</body></html>', \LIBXML_NOERROR );
	}

	// options.php saves a submission through update_option(), whose filter the page hooks for its own option page only.
	protected function save( string $row, array $posted ): void {
		$GLOBALS['pagenow']      = 'options.php';
		$_REQUEST['option_page'] = $row;

		try {
			update_option( $row, $posted );
		} finally {
			unset( $GLOBALS['pagenow'], $_REQUEST['option_page'] );
		}
	}
}
