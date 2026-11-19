<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Integration;

use DeepWebSolutions\LockedPaymentMethods\Options;
use DeepWebSolutions\LockedPaymentMethods\Settings;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\RecordsDeprecationsTrait;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\RestoresOptionsTrait;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\RunsRequestsTrait;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies\GroupsStrategy;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( Settings::class )]
final class SettingsTest extends TestCase {
	use RecordsDeprecationsTrait;
	use RestoresOptionsTrait;
	use RunsRequestsTrait;

	protected const string SECTION = 'dws-locked-payment-methods';

	protected const array CHECKBOXES = array( Options::UNLOCK_VIA_USER_ROLES, Options::UNLOCK_VIA_USER_PROFILES, Options::UNLOCK_VIA_ORDERS, Options::DELETE_DATA );

	protected const array OPTIONS = array( Options::LOCKED_METHODS, Options::FULL_ACCESS_ROLES, Options::UNLOCK_VIA_GROUPS, Options::UNLOCK_VIA_WC_MEMBERSHIPS, ...self::CHECKBOXES );

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

	public static function setting_groups(): array {
		return array(
			'general'      => array( 'general', array( 'locked-payment-methods', 'unlock-via-user-role', 'full-access-user-roles', 'unlock-via-user-meta', 'unlock-via-order-meta' ) ),
			'integrations' => array( 'integrations', GroupsStrategy::is_available() ? array( 'unlock-via-groups' ) : array() ),
			'plugin'       => array( 'plugin', array( 'remove-data-uninstall' ) ),
		);
	}

	#[After]
	protected function forget_the_rejections(): void {
		new \ReflectionProperty( \WC_Admin_Settings::class, 'errors' )->setValue( null, array() );
	}

	public function test_the_section_sits_on_the_advanced_tab_under_its_v1_id(): void {
		self::assertSame( 'Locked Payment Methods', self::$advanced->get_sections()[ self::SECTION ] ?? null );
	}

	// WooCommerce stores each field's default whenever its own version changes, so a default other than the seeded one would win or lose by which write runs first; an integration's field lists only while its plugin is active.
	public function test_each_field_defaults_to_the_value_the_migrations_seed(): void {
		$fields = \array_filter( self::$advanced->get_settings_for_section( self::SECTION ), static fn ( array $field ): bool => \array_key_exists( $field['id'] ?? '', Options::DEFAULTS ) );

		self::assertSame( array(), \array_diff( \array_keys( Options::DEFAULTS ), \array_column( $fields, 'id' ), array( Options::UNLOCK_VIA_GROUPS, Options::UNLOCK_VIA_WC_MEMBERSHIPS ) ) );
		foreach ( $fields as $field ) {
			self::assertSame( Options::DEFAULTS[ $field['id'] ], $field['default'] ?? null, "The field {$field['id']} defaults to another value than Options::DEFAULTS holds." );
		}
	}

	public function test_the_section_shows_v1s_groups_with_their_defaults(): void {
		foreach ( self::OPTIONS as $key ) {
			delete_option( $key );
		}

		$form = $this->render();

		self::assertSame( GroupsStrategy::is_available() ? array( 'General Settings', 'Integrations Settings', 'Plugin Settings' ) : array( 'General Settings', 'Plugin Settings' ), \array_map( static fn ( \Dom\Element $heading ): string => \trim( $heading->textContent ), \iterator_to_array( $form->querySelectorAll( 'h2' ) ) ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertSame( array(), self::selected( $form, Options::LOCKED_METHODS ) );
		self::assertSame( array( 'administrator', 'shop_manager' ), self::selected( $form, Options::FULL_ACCESS_ROLES ) );
		foreach ( self::CHECKBOXES as $key ) {
			self::assertSame( 'checkbox', $form->getElementById( $key )?->getAttribute( 'type' ), $key );
		}
		self::assertTrue( $form->getElementById( Options::UNLOCK_VIA_USER_ROLES )?->hasAttribute( 'checked' ) );
		self::assertTrue( $form->getElementById( Options::UNLOCK_VIA_USER_PROFILES )?->hasAttribute( 'checked' ) );
		self::assertFalse( $form->getElementById( Options::UNLOCK_VIA_ORDERS )?->hasAttribute( 'checked' ) );
		self::assertFalse( $form->getElementById( Options::DELETE_DATA )?->hasAttribute( 'checked' ) );
	}

	public function test_the_locked_list_offers_every_registered_gateway_and_marks_the_disabled_ones(): void {
		$expected = array();
		foreach ( WC()->payment_gateways()->payment_gateways() as $id => $gateway ) {
			$expected[ $id ] = 'yes' === $gateway->enabled ? $gateway->get_title() : $gateway->get_title() . ' (disabled)';
		}

		$options = array();
		foreach ( $this->render()->querySelectorAll( '[id="' . Options::LOCKED_METHODS . '"] option' ) as $option ) {
			$options[ (string) $option->getAttribute( 'value' ) ] = \trim( $option->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		}

		self::assertNotSame( array(), $expected );
		self::assertContains( 'no', \array_column( WC()->payment_gateways()->payment_gateways(), 'enabled' ), 'The tests site has no disabled gateway to mark.' );
		self::assertSame( $expected, $options );
	}

	public function test_a_disabled_gateway_stays_locked_through_a_save(): void {
		$disabled = \array_key_first( \array_filter( WC()->payment_gateways()->payment_gateways(), static fn ( \WC_Payment_Gateway $gateway ): bool => 'yes' !== $gateway->enabled ) );
		update_option( Options::LOCKED_METHODS, array( $disabled ) );

		self::assertSame( array( $disabled ), self::selected( $this->render(), Options::LOCKED_METHODS ) );

		$this->save( array( Options::LOCKED_METHODS => array( $disabled ) ) );

		self::assertSame( array( $disabled ), get_option( Options::LOCKED_METHODS ) );
	}

	// The section builds its choices once per request, so the row renders and saves in a request of its own, as the settings screen does.
	public function test_a_locked_method_no_gateway_registers_is_offered_as_not_available_and_stays_locked_through_a_save(): void {
		update_option( Options::LOCKED_METHODS, array( 'cod', 'acme_invoice' ) );

		$request = self::request( 'require_once WC_ABSPATH . "includes/admin/class-wc-admin-settings.php"; foreach ( WC_Admin_Settings::get_settings_pages() as $page ) { if ( "advanced" === $page->get_id() ) { $fields = $page->get_settings_for_section( "' . self::SECTION . '" ); } } $locked = array_values( array_filter( $fields, static fn ( array $field ): bool => "' . Options::LOCKED_METHODS . '" === ( $field["id"] ?? null ) ) )[0]; WC_Admin_Settings::save_fields( $fields, array( "' . Options::LOCKED_METHODS . '" => array( "cod", "acme_invoice" ), "save" => "Save changes" ) ); echo wp_json_encode( $locked["options"]["acme_invoice"] ?? null );' );
		wp_cache_flush();

		self::assertSame( '"acme_invoice (not available)"', $request['last'], $request['output'] );
		self::assertSame( array( 'cod', 'acme_invoice' ), get_option( Options::LOCKED_METHODS ) );
	}

	public function test_the_roles_field_offers_every_role_and_shows_while_the_role_strategy_is_off(): void {
		update_option( Options::UNLOCK_VIA_USER_ROLES, 'no' );

		$options = array();
		foreach ( $this->render()->querySelectorAll( '[id="' . Options::FULL_ACCESS_ROLES . '"] option' ) as $option ) {
			$options[ (string) $option->getAttribute( 'value' ) ] = \trim( $option->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		}

		self::assertSame( \array_map( 'translate_user_role', \array_column( wp_roles()->roles, 'name' ) ), \array_values( $options ) );
		self::assertSame( \array_keys( wp_roles()->roles ), \array_map( 'strval', \array_keys( $options ) ) );
	}

	public function test_the_checkboxes_store_yes_and_no_as_v1s_selects_did(): void {
		$this->save( \array_fill_keys( self::CHECKBOXES, '1' ) );

		foreach ( self::CHECKBOXES as $key ) {
			self::assertSame( 'yes', get_option( $key ), $key );
		}

		$this->save( array() );

		foreach ( self::CHECKBOXES as $key ) {
			self::assertSame( 'no', get_option( $key ), $key );
		}
	}

	#[DataProvider( 'setting_groups' )]
	public function test_a_groups_fields_pass_its_v1_filter_under_v1s_ids_and_keep_v1s_keys_for_fields_it_adds( string $group, array $v1_ids ): void {
		$seen     = array();
		$v1_hook  = "locked_payment_methods_for_woocommerce/{$group}_settings/get_group_fields";
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
			$ids = \array_column( new Settings()->get_section_fields( 'advanced', self::SECTION ), 'id' );
		} finally {
			remove_filter( $v1_hook, $v1_extra );
		}

		self::assertSame( array( $v1_ids ), $seen );
		foreach ( $v1_ids as $v1_id ) {
			self::assertContains( Options::V1_SETTINGS[ $group ][ $v1_id ], $ids );
		}
		self::assertContains( "dws-wc-lpm_{$group}_extra", $ids );
		self::assertSame( array( array( $v1_hook, 'woocommerce_get_settings_advanced' ) ), $this->deprecated );
	}

	public function test_the_plugin_row_links_to_the_section_and_to_the_support_forum(): void {
		$basename = plugin_basename( DWS_LPMWC_FILE );

		$links = apply_filters( "plugin_action_links_{$basename}", array( 'deactivate' => '<a href="#">Deactivate</a>' ), $basename, array(), 'all' );
		$meta  = apply_filters( 'plugin_row_meta', array(), $basename, array(), 'all' );

		self::assertStringContainsString( esc_url( admin_url( 'admin.php?page=wc-settings&tab=advanced&section=' . self::SECTION ) ), (string) \reset( $links ) );
		self::assertStringContainsString( 'https://wordpress.org/support/plugin/locked-payment-methods-for-woocommerce/', \implode( '', $meta ) );
		self::assertSame( array(), apply_filters( 'plugin_row_meta', array(), 'another-plugin/another-plugin.php', array(), 'all' ) );
	}

	protected static function selected( \Dom\HTMLDocument $form, string $key ): array {
		$values = array();
		foreach ( $form->querySelectorAll( '[id="' . $key . '"] option[selected]' ) as $option ) {
			$values[] = (string) $option->getAttribute( 'value' );
		}

		return $values;
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
