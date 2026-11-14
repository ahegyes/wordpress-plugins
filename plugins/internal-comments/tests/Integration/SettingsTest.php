<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use DeepWebSolutions\InternalComments\Plugin;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\PluginIdentity;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Settings\AdminPage\OptionsPage;
use DeepWebSolutions\InternalComments\Settings;
use DeepWebSolutions\InternalComments\Tests\Support\RecordsDeprecationsTrait;
use DeepWebSolutions\InternalComments\Tests\Support\RestoresOptionsTrait;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( Settings::class )]
final class SettingsTest extends TestCase {
	use RecordsDeprecationsTrait;
	use RestoresOptionsTrait;

	protected const array OPTIONS = array( DWS_IC_OPTION_POST_TYPES, DWS_IC_OPTION_PRIVATE_COMMENTS, DWS_IC_OPTION_DELETE_DATA, 'dws_ic_general_extra', 'dws_ic_plugin_extra' );

	protected static PluginIdentity $identity;

	protected static OptionsPage $page;

	#[\Override]
	public static function setUpBeforeClass(): void {
		require_once ABSPATH . 'wp-admin/includes/template.php';
		self::$identity = new PluginIdentity( DWS_IC_FILE, Plugin::SLUG, Plugin::PREFIX );
		self::$page     = new OptionsPage( self::$identity, Settings::PAGE, new Settings( self::$identity ) );
	}

	public static function setting_groups(): array {
		return array(
			'general' => array(
				'general',
				array(
					'post-types'              => DWS_IC_OPTION_POST_TYPES,
					'enable-private-comments' => DWS_IC_OPTION_PRIVATE_COMMENTS,
				),
			),
			'plugin'  => array( 'plugin', array( 'remove-data-uninstall' => DWS_IC_OPTION_DELETE_DATA ) ),
		);
	}

	#[Before]
	protected function forget_the_settings_errors(): void {
		$GLOBALS['wp_settings_errors'] = array();
	}

	#[After]
	protected function forget_the_tab(): void {
		unset( $_GET['tab'] );
	}

	public function test_the_page_keeps_v1s_slug_parent_capability_and_tabs(): void {
		$page = new Settings( self::$identity )->get_options_page( Settings::PAGE );

		self::assertSame( 'dws-internal-comments', Settings::PAGE );
		self::assertSame( 'options-general.php', $page['parent'] );
		self::assertSame( DWS_IC_CAP_MANAGE_OPTIONS, $page['capability'] );
		self::assertSame( 'Internal Comments', $page['menu_title'] );
		self::assertSame( array( 'general', 'plugin' ), \array_column( $page['tabs'], 'slug' ) );
	}

	public function test_the_general_tab_offers_every_post_type_with_an_admin_screen_and_the_private_comments_switch(): void {
		delete_option( DWS_IC_OPTION_POST_TYPES );
		delete_option( DWS_IC_OPTION_PRIVATE_COMMENTS );

		$form   = $this->render( 'general' );
		$values = \array_map( static fn ( \Dom\Element $input ): string => $input->getAttribute( 'value' ), \iterator_to_array( $form->querySelectorAll( 'input[type="checkbox"][name="dws_ic_post_types[]"]' ) ) );

		self::assertEqualsCanonicalizing( \array_values( get_post_types( array( 'show_ui' => true ) ) ), $values );
		self::assertNull( $form->querySelector( 'input[name="dws_ic_post_types[]"][checked]' ) );
		self::assertNotNull( $form->querySelector( 'input[type="checkbox"][name="dws_ic_private_comments_enabled"]:not([checked])' ) );
		self::assertNull( $form->querySelector( 'input[name="dws_ic_delete_data"]' ) );
	}

	public function test_the_plugin_tab_holds_the_uninstall_consent(): void {
		delete_option( DWS_IC_OPTION_DELETE_DATA );

		$form = $this->render( 'plugin' );

		self::assertNotNull( $form->querySelector( 'input[type="checkbox"][name="dws_ic_delete_data"]:not([checked])' ) );
		self::assertNull( $form->querySelector( 'input[name="dws_ic_post_types[]"]' ) );
	}

	public function test_the_post_types_and_switches_store_each_in_a_row_of_its_own_as_v1_stored_them(): void {
		$this->save(
			self::$page,
			'general',
			array(
				DWS_IC_OPTION_POST_TYPES       => array( 'post', 'page' ),
				DWS_IC_OPTION_PRIVATE_COMMENTS => 'yes',
			)
		);
		$this->save( self::$page, 'plugin', array( DWS_IC_OPTION_DELETE_DATA => 'yes' ) );

		self::assertSame( array( 'post', 'page' ), get_option( DWS_IC_OPTION_POST_TYPES ) );
		self::assertSame( 'yes', get_option( DWS_IC_OPTION_PRIVATE_COMMENTS ) );
		self::assertSame( 'yes', get_option( DWS_IC_OPTION_DELETE_DATA ) );
		self::assertNotNull( $this->render( 'general' )->querySelector( 'input[name="dws_ic_post_types[]"][value="page"][checked]' ) );
	}

	// v1's form posted nothing for an empty selection, so the selection it had stayed stored.
	public function test_clearing_every_post_type_stores_an_empty_selection(): void {
		update_option( DWS_IC_OPTION_POST_TYPES, array( 'post' ) );

		$this->save(
			self::$page,
			'general',
			array(
				DWS_IC_OPTION_POST_TYPES       => '',
				DWS_IC_OPTION_PRIVATE_COMMENTS => null,
			)
		);

		self::assertSame( array(), get_option( DWS_IC_OPTION_POST_TYPES ) );
	}

	public function test_a_post_type_without_an_admin_screen_is_refused(): void {
		update_option( DWS_IC_OPTION_POST_TYPES, array( 'post' ) );

		$this->save( self::$page, 'general', array( DWS_IC_OPTION_POST_TYPES => array( 'post', 'nav_menu_item' ) ) );

		self::assertSame( array( 'post' ), get_option( DWS_IC_OPTION_POST_TYPES ) );
		self::assertCount( 1, get_settings_errors( DWS_IC_OPTION_POST_TYPES ) );
	}

	// v1's group filter still sees v1's field ids, and the page's own fields filter is its replacement.
	#[DataProvider( 'setting_groups' )]
	public function test_a_groups_fields_pass_its_v1_filter_keyed_by_v1s_ids_and_a_field_it_adds_takes_a_row_of_its_own( string $group, array $rows ): void {
		$seen     = array();
		$v1_hook  = "internal_comments/{$group}_settings/get_group_fields";
		$v1_extra = static function ( array $fields ) use ( &$seen ): array {
			$seen[] = \array_keys( $fields );
			return $fields + array(
				'extra' => array(
					'type'  => 'text',
					'title' => 'Extra',
				),
			);
		};
		add_filter( $v1_hook, $v1_extra );

		try {
			$fields = new Settings( self::$identity )->get_tab_fields( Settings::PAGE, $group );
		} finally {
			remove_filter( $v1_hook, $v1_extra );
		}

		self::assertSame( array( \array_keys( $rows ) ), $seen );
		self::assertSame( array( ...\array_values( $rows ), "dws_ic_{$group}_extra" ), \array_column( $fields, 'id' ) );
		self::assertSame( array( array( $v1_hook, 'internal_comments/options_page/fields' ) ), $this->deprecated );
	}

	public function test_a_field_a_v1_callback_adds_saves_in_its_own_row(): void {
		$v1_extra = static fn ( array $fields ): array => $fields + array(
			'extra' => array(
				'type'  => 'text',
				'title' => 'Extra',
			),
		);
		add_filter( 'internal_comments/general_settings/get_group_fields', $v1_extra );

		try {
			$this->save( new OptionsPage( self::$identity, Settings::PAGE, new Settings( self::$identity ) ), 'general', array( 'dws_ic_general_extra' => 'saved' ) );
		} finally {
			remove_filter( 'internal_comments/general_settings/get_group_fields', $v1_extra );
		}

		self::assertSame( 'saved', get_option( 'dws_ic_general_extra' ) );
		self::assertSame( 'saved', dws_ic_get_raw_setting( 'extra', 'general' ) );
	}

	public function test_the_plugin_row_links_to_the_page_and_to_the_support_forum(): void {
		$basename = plugin_basename( DWS_IC_FILE );

		$links = apply_filters( "plugin_action_links_{$basename}", array( 'deactivate' => '<a href="#">Deactivate</a>' ), $basename, array(), 'all' );
		$meta  = apply_filters( 'plugin_row_meta', array(), $basename, array(), 'all' );

		self::assertStringContainsString( esc_url( admin_url( 'options-general.php?page=dws-internal-comments' ) ), (string) \reset( $links ) );
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

	// options.php registers the posted tab's settings, then saves each of its fields through update_option(), with null for one the form did not post; the page's filters go afterwards, so a later row saves without them.
	protected function save( OptionsPage $page, string $tab, array $posted ): void {
		$group                   = Settings::PAGE . "_$tab";
		$GLOBALS['pagenow']      = 'options.php';
		$_REQUEST['option_page'] = $group;

		try {
			$page->register_settings();
			foreach ( $posted as $option => $value ) {
				update_option( $option, $value );
			}
		} finally {
			foreach ( \array_unique( array( ...\array_values( DWS_IC_V1_SETTINGS[ $tab ] ), ...\array_keys( $posted ) ) ) as $option ) {
				remove_filter( "pre_update_option_$option", array( $page, 'sanitize_submission' ) );
				unregister_setting( $group, $option );
			}
			remove_filter( "option_page_capability_$group", array( $page, 'get_capability' ) );
			unset( $GLOBALS['pagenow'], $_REQUEST['option_page'] );
		}
	}
}
