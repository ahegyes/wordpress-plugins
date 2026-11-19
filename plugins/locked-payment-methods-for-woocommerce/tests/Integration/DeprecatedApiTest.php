<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Integration;

use DeepWebSolutions\LockedPaymentMethods\Options;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\InsertsUsersTrait;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\RecordsDeprecationsTrait;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\RestoresOptionsTrait;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\RunsRequestsTrait;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class DeprecatedApiTest extends TestCase {
	use InsertsUsersTrait;
	use RecordsDeprecationsTrait;
	use RestoresOptionsTrait;
	use RunsRequestsTrait;

	protected const array OPTIONS = array( Options::LOCKED_METHODS, Options::FULL_ACCESS_ROLES, Options::UNLOCK_VIA_USER_ROLES, Options::UNLOCK_VIA_ORDERS );

	protected static array $users = array();

	public static function hook_tags(): array {
		return array(
			'plugin'              => array( 'dws_lpmwc_get_hook_tag', array( 'check_payment_method_access_for_user' ), 'locked_payment_methods_for_woocommerce/check_payment_method_access_for_user' ),
			'plugin-unsafe'       => array( 'dws_lpmwc_get_hook_tag', array( 'Some Name', array( 'A\\B', '' ) ), 'locked_payment_methods_for_woocommerce/some_name/a_b' ),
			'plugin-non-ascii'    => array( 'dws_lpmwc_get_hook_tag', array( "Caf\u{e9}" ), 'locked_payment_methods_for_woocommerce/caf' ),
			'plugin-bytes'        => array( 'dws_lpmwc_get_hook_tag', array( 'name', array( "ex\x01tra\u{e9}" ) ), 'locked_payment_methods_for_woocommerce/name/extra' ),
			'component'           => array( 'dws_lpmwc_get_component_hook_tag', array( 'lock-manager', 'is_locked_payment_method', array( 'cod' ) ), 'locked_payment_methods_for_woocommerce/lock_manager/is_locked_payment_method/cod' ),
			'component-gateway'   => array( 'dws_lpmwc_get_component_hook_tag', array( 'lock-manager', 'is_locked_payment_method', 'My_Gateway' ), 'locked_payment_methods_for_woocommerce/lock_manager/is_locked_payment_method/my_gateway' ),
			'component-group'     => array( 'dws_lpmwc_get_component_hook_tag', array( 'general-settings', 'get_group_fields' ), 'locked_payment_methods_for_woocommerce/general_settings/get_group_fields' ),
			'component-bytes'     => array( 'dws_lpmwc_get_component_hook_tag', array( 'general-settings', "get_group\x01_fields\u{e9}" ), 'locked_payment_methods_for_woocommerce/general_settings/get_group_fields' ),
			'component-strategy'  => array( 'dws_lpmwc_get_component_hook_tag', array( 'user-meta-strategy', 'name' ), 'locked_payment_methods_for_woocommerce/user_meta_unlock_strategy/name' ),
			'component-premium'   => array( 'dws_lpmwc_get_component_hook_tag', array( 'wc-memberships-strategy', 'name' ), 'locked_payment_methods_for_woocommerce/wc_memberships_unlock_strategy/name' ),
			'component-integrate' => array( 'dws_lpmwc_get_component_hook_tag', array( 'integrations-settings', 'get_group_fields' ), 'locked_payment_methods_for_woocommerce/integrations_settings/get_group_fields' ),
			'component-unmapped'  => array( 'dws_lpmwc_get_component_hook_tag', array( 'some-component', 'name' ), 'locked_payment_methods_for_woocommerce/some_component/name' ),
		);
	}

	#[\Override]
	public static function setUpBeforeClass(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array( 'shop_manager', 'customer' ) as $role ) {
			self::$users[ $role ] = self::insert_user(
				array(
					'user_login' => "lpm-deprecated-$role",
					'user_pass'  => 'password',
					'user_email' => "lpm-deprecated-$role@example.org",
					'role'       => $role,
				)
			);
		}
	}

	#[\Override]
	public static function tearDownAfterClass(): void {
		foreach ( self::$users as $user ) {
			wp_delete_user( $user );
		}
	}

	#[Before]
	protected function store_the_settings(): void {
		update_option( Options::LOCKED_METHODS, array( 'cod' ) );
		update_option( Options::FULL_ACCESS_ROLES, array( 'shop_manager' ) );
	}

	public function test_the_v1_getters_answer_from_the_plugin_header(): void {
		$header = self::read_header();

		self::assertSame( dws_lpmwc_get_plugin_name(), dws_lpmwc_name() );
		self::assertSame( $header['Version'], dws_lpmwc_version() );
		self::assertSame( DWS_LPMWC_FILE, dws_lpmwc_path() );
		self::assertSame( $header['RequiresPHP'], dws_lpmwc_min_php() );
		self::assertSame( $header['RequiresWP'], dws_lpmwc_min_wp() );
		self::assertSame(
			array(
				array( 'dws_lpmwc_name', 'dws_lpmwc_get_plugin_name()' ),
				array( 'dws_lpmwc_version', 'dws_lpmwc_get_plugin_version()' ),
				array( 'dws_lpmwc_path', '' ),
				array( 'dws_lpmwc_min_php', '' ),
				array( 'dws_lpmwc_min_wp', '' ),
			),
			$this->deprecated
		);
	}

	public function test_the_v1_constants_hold_the_plugin_header_without_v1s_whitelabel_prefix(): void {
		$header = self::read_header();

		self::assertSame( 'Locked Payment Methods for WooCommerce', DWS_LPMWC_NAME );
		self::assertSame( $header['Version'], DWS_LPMWC_VERSION );
		self::assertSame( DWS_LPMWC_FILE, DWS_LPMWC_PATH );
		self::assertSame( $header['RequiresPHP'], DWS_LPMWC_MIN_PHP );
		self::assertSame( $header['RequiresWP'], DWS_LPMWC_MIN_WP );
	}

	#[DataProvider( 'hook_tags' )]
	public function test_a_v1_hook_tag_builds_as_v1_built_it( string $shim, array $arguments, string $expected ): void {
		self::assertSame( $expected, $shim( ...$arguments ) );
		self::assertSame( array( array( $shim, '' ) ), $this->deprecated );
	}

	// The v1 hooks fire at the earliest priority, so every v1 listener runs before a v2 listener of any later one.
	public function test_the_v1_boot_hooks_fire_at_the_earliest_priority_with_the_failure(): void {
		$fired = array();
		$spy   = static function ( mixed $error = null ) use ( &$fired ): void {
			$fired[] = array( current_action(), $error );
		};
		add_action( 'dws_lpmwc_initialized', $spy );
		add_action( 'dws_lpmwc_initialization_failure', $spy );
		add_action( 'locked_payment_methods_for_woocommerce/boot/succeeded', $spy, PHP_INT_MIN + 1 );
		add_action( 'locked_payment_methods_for_woocommerce/boot/failed', $spy, PHP_INT_MIN + 1 );
		$error = new \RuntimeException( 'Failed.' );

		try {
			do_action( 'locked_payment_methods_for_woocommerce/boot/succeeded' );
			do_action( 'locked_payment_methods_for_woocommerce/boot/failed', $error );
		} finally {
			remove_action( 'dws_lpmwc_initialized', $spy );
			remove_action( 'dws_lpmwc_initialization_failure', $spy );
			remove_action( 'locked_payment_methods_for_woocommerce/boot/succeeded', $spy, PHP_INT_MIN + 1 );
			remove_action( 'locked_payment_methods_for_woocommerce/boot/failed', $spy, PHP_INT_MIN + 1 );
		}

		self::assertSame(
			array(
				array( 'dws_lpmwc_initialized', null ),
				array( 'locked_payment_methods_for_woocommerce/boot/succeeded', '' ),
				array( 'dws_lpmwc_initialization_failure', $error ),
				array( 'locked_payment_methods_for_woocommerce/boot/failed', $error ),
			),
			$fired
		);
		self::assertSame(
			array(
				array( 'dws_lpmwc_initialized', 'locked_payment_methods_for_woocommerce/boot/succeeded' ),
				array( 'dws_lpmwc_initialization_failure', 'locked_payment_methods_for_woocommerce/boot/failed' ),
			),
			$this->deprecated
		);
		foreach ( array( 'locked_payment_methods_for_woocommerce/boot/succeeded', 'locked_payment_methods_for_woocommerce/boot/failed' ) as $hook ) {
			$relays = \array_filter( $GLOBALS['wp_filter'][ $hook ]->callbacks[ PHP_INT_MIN ] ?? array(), static fn ( array $callback ): bool => $callback['function'] instanceof \Closure && \dirname( DWS_LPMWC_FILE ) . '/functions.php' === new \ReflectionFunction( $callback['function'] )->getFileName() );
			self::assertCount( 1, $relays, $hook );
		}
	}

	public function test_v1s_access_checks_answer_true_for_locked_from_the_strategies_alone_after_v1s_filter(): void {
		$v1   = 'locked_payment_methods_for_woocommerce/check_payment_method_access_for_user';
		$seen = array();
		$spy  = static function ( mixed ...$args ) use ( &$seen ): mixed {
			$seen[] = $args;
			return $args[0];
		};
		add_filter( $v1, $spy, 10, 3 );

		try {
			self::assertTrue( dws_lpmwc_check_payment_method_access_for_user( 'bacs', self::$users['customer'] ) );
			self::assertFalse( dws_lpmwc_check_payment_method_access_for_user( 'bacs', self::$users['shop_manager'] ) );
		} finally {
			remove_filter( $v1, $spy );
		}

		self::assertSame( array( array( true, 'bacs', self::$users['customer'] ), array( true, 'bacs', self::$users['shop_manager'] ) ), $seen );
		self::assertContains( array( 'dws_lpmwc_check_payment_method_access_for_user', 'dws_lpmwc_is_payment_method_locked()' ), $this->deprecated );
		self::assertContains( array( $v1, 'locked_payment_methods_for_woocommerce/payment_method/should_lock' ), $this->deprecated );
	}

	public function test_v1s_order_access_check_judges_the_order_and_its_customer_after_v1s_customer_and_order_filters(): void {
		$order    = wc_create_order( array( 'customer_id' => self::$users['shop_manager'] ) );
		$order_id = $order->get_id();
		$v1       = 'locked_payment_methods_for_woocommerce/check_payment_method_access_for_wc_order';
		$v1_user  = 'locked_payment_methods_for_woocommerce/check_payment_method_access_for_user';
		$seen     = array();
		$spy      = static function ( mixed ...$args ) use ( &$seen ): mixed {
			$seen[] = $args;
			return $args[0];
		};
		$guest    = wc_create_order();
		$guest_id = $guest->get_id();
		add_filter( $v1, $spy, 10, 3 );
		add_filter( $v1_user, $spy, 10, 3 );

		try {
			self::assertFalse( dws_lpmwc_check_payment_method_access_for_wc_order( 'cod', $order_id ) );
			self::assertTrue( dws_lpmwc_check_payment_method_access_for_wc_order( 'cod', $guest_id ) );
		} finally {
			remove_filter( $v1_user, $spy );
			remove_filter( $v1, $spy );
			$order->delete( true );
			$guest->delete( true );
		}

		self::assertSame( array( array( true, 'cod', self::$users['shop_manager'] ), array( true, 'cod', $order_id ), array( true, 'cod', $guest_id ) ), $seen );
		self::assertContains( array( $v1, 'locked_payment_methods_for_woocommerce/payment_method/should_lock' ), $this->deprecated );
	}

	public function test_v1s_order_checks_answer_locked_until_the_plugin_has_booted(): void {
		update_option( Options::UNLOCK_VIA_ORDERS, 'yes' );
		$order = wc_create_order( array( 'customer_id' => self::$users['shop_manager'] ) );
		$order->update_meta_data( '_dws_lpmwc_unlocked_payment_methods', array( 'cod' ) );
		$order->save();
		$checks = 'array( dws_lpmwc_check_payment_method_access_via_wc_order_meta( "cod", ' . $order->get_id() . ' ), dws_lpmwc_check_payment_method_access_for_wc_order( "cod", ' . $order->get_id() . ' ) )';

		try {
			$request = self::request( 'add_filter( "deprecated_function_trigger_error", "__return_false" ); $booted = ' . $checks . '; unset( $GLOBALS["wp_actions"]["locked_payment_methods_for_woocommerce/boot/succeeded"] ); echo wp_json_encode( array( $booted, ' . $checks . ' ) );' );
		} finally {
			$order->delete( true );
		}

		self::assertSame( array( array( false, false ), array( true, true ) ), \json_decode( $request['last'], true ), $request['output'] );
	}

	public function test_with_woocommerce_inactive_v1s_order_checks_answer_locked(): void {
		$request = self::request( 'add_filter( "deprecated_function_trigger_error", "__return_false" ); add_filter( "pre_option_' . Options::UNLOCK_VIA_ORDERS . '", static fn (): string => "yes" ); echo wp_json_encode( array( dws_lpmwc_check_payment_method_access_for_wc_order( "cod", 1 ), dws_lpmwc_check_payment_method_access_via_wc_order_meta( "cod", 1 ) ) );', '--skip-plugins=woocommerce' );

		self::assertSame( 0, $request['status'], $request['output'] );
		self::assertSame( '[true,true]', $request['last'], $request['output'] );
	}

	public function test_with_woocommerce_inactive_v1s_role_check_answers_locked_for_a_full_access_user(): void {
		$request = self::request( 'add_filter( "deprecated_function_trigger_error", "__return_false" ); add_filter( "pre_option_' . Options::UNLOCK_VIA_USER_ROLES . '", static fn (): string => "yes" ); echo wp_json_encode( dws_lpmwc_check_payment_method_access_via_user_roles( "cod", ' . self::$users['shop_manager'] . ' ) );', '--skip-plugins=woocommerce' );

		self::assertSame( 0, $request['status'], $request['output'] );
		self::assertSame( 'true', $request['last'], $request['output'] );
	}

	public function test_v1s_per_strategy_checks_answer_from_their_strategy_alone_and_true_while_it_is_off(): void {
		$order = wc_create_order();
		$order->update_meta_data( '_dws_lpmwc_unlocked_payment_methods', array( 'cod' ) );
		$order->save();
		$checks = 'add_filter( "deprecated_function_trigger_error", "__return_false" ); echo wp_json_encode( array( dws_lpmwc_check_payment_method_access_via_user_roles( "cod", ' . self::$users['shop_manager'] . ' ), dws_lpmwc_check_payment_method_access_via_user_roles( "cod", ' . self::$users['customer'] . ' ), dws_lpmwc_check_payment_method_access_via_user_meta( "cod", ' . self::$users['shop_manager'] . ' ), dws_lpmwc_check_payment_method_access_via_wc_order_meta( "cod", ' . $order->get_id() . ' ) ) );';

		try {
			update_option( Options::UNLOCK_VIA_USER_ROLES, 'yes' );
			update_option( Options::UNLOCK_VIA_ORDERS, 'yes' );
			$on = self::request( $checks );

			update_option( Options::UNLOCK_VIA_USER_ROLES, 'no' );
			update_option( Options::UNLOCK_VIA_ORDERS, 'no' );
			$off = self::request( $checks );

			dws_lpmwc_check_payment_method_access_via_user_roles( 'cod', self::$users['customer'] );
		} finally {
			$order->delete( true );
		}

		self::assertSame( '[false,true,true,false]', $on['last'], $on['output'] );
		self::assertSame( '[true,true,true,true]', $off['last'], $off['output'] );
		self::assertContains( array( 'dws_lpmwc_check_payment_method_access_via_user_roles', 'dws_lpmwc_is_payment_method_locked()' ), $this->deprecated );
	}

	public function test_v1s_setting_readers_map_v1s_ids_to_the_plugins_options_through_v1s_filters(): void {
		update_option( Options::UNLOCK_VIA_USER_ROLES, 'yes' );
		$page    = 'locked_payment_methods_for_woocommerce/settings/get_validated_option_value';
		$group   = 'locked_payment_methods_for_woocommerce/general_settings/get_validated_option_value';
		$through = static fn ( mixed $value ): mixed => $value;
		add_filter( $group, $through );

		try {
			self::assertSame( array( 'cod' ), dws_lpmwc_get_validated_general_setting( 'locked-payment-methods' ) );
			self::assertTrue( dws_lpmwc_get_validated_setting( 'unlock-via-user-role', 'general' ) );
			self::assertSame( array( 'shop_manager' ), dws_lpmwc_get_validated_setting( 'general/full-access-user-roles' ) );
			self::assertSame( 'yes', dws_lpmwc_get_raw_setting( 'unlock-via-user-role', 'general' ) );
			self::assertFalse( dws_lpmwc_validate_setting( 'maybe-not', 'remove-data-uninstall', 'plugin' ) );
			self::assertSame( array( 'bacs' ), dws_lpmwc_validate_setting( array( 'bacs', 7 ), 'locked-payment-methods', 'general' ) );
			self::assertNull( dws_lpmwc_get_validated_setting( 'unknown', 'unknown' ) );
		} finally {
			remove_filter( $group, $through );
		}

		self::assertContains( array( $group, 'option_' . Options::FULL_ACCESS_ROLES ), $this->deprecated );
		self::assertContains( array( $group, 'locked_payment_methods_for_woocommerce/payment_method/locked_ids' ), $this->deprecated );
		self::assertNotContains( array( $page, 'option_' . Options::FULL_ACCESS_ROLES ), $this->deprecated ); // No callback hooks the page's filter, so it reports no deprecation.
		self::assertContains( array( 'dws_lpmwc_get_validated_general_setting', 'dws_lpmwc_get_locked_payment_method_ids(), the locked_payment_methods_for_woocommerce/payment_method/locked_ids filter or dws_lpmwc_is_payment_method_locked()' ), $this->deprecated );
		self::assertContains( array( 'dws_lpmwc_get_validated_setting', 'get_option() on ' . Options::UNLOCK_VIA_USER_ROLES ), $this->deprecated );
		self::assertContains( array( 'dws_lpmwc_get_validated_setting', 'get_option() on ' . Options::FULL_ACCESS_ROLES ), $this->deprecated );
		self::assertContains( array( 'dws_lpmwc_get_raw_setting', 'get_option() on ' . Options::UNLOCK_VIA_USER_ROLES ), $this->deprecated );
		self::assertContains( array( 'dws_lpmwc_get_validated_setting', 'get_option()' ), $this->deprecated );
	}

	public function test_v1s_setting_readers_take_a_group_with_its_settings_suffix_and_read_a_field_an_extension_added(): void {
		update_option( Options::UNLOCK_VIA_USER_ROLES, 'yes' );
		update_option( 'dws-wc-lpm_general_acme-note', 'Ask for a deposit.' );

		try {
			self::assertTrue( dws_lpmwc_get_validated_setting( 'unlock-via-user-role', 'general-settings' ) );
			self::assertSame( 'yes', dws_lpmwc_get_raw_setting( 'unlock-via-user-role', 'general-settings' ) );
			self::assertSame( 'Ask for a deposit.', dws_lpmwc_get_raw_setting( 'acme-note', 'general' ) );
			self::assertSame( 'Ask for a deposit.', dws_lpmwc_get_validated_setting( 'general/acme-note' ) );
			self::assertNull( dws_lpmwc_get_raw_setting( 'acme-missing', 'general' ) );
		} finally {
			delete_option( 'dws-wc-lpm_general_acme-note' );
		}

		self::assertContains( array( 'dws_lpmwc_get_raw_setting', 'get_option() on dws-wc-lpm_general_acme-note' ), $this->deprecated );
	}

	public function test_v1s_validator_gives_a_null_value_the_settings_default(): void {
		self::assertTrue( dws_lpmwc_validate_setting( null, 'unlock-via-user-meta', 'general' ) );
		self::assertFalse( dws_lpmwc_validate_setting( null, 'remove-data-uninstall', 'plugin' ) );
		self::assertSame( Options::DEFAULTS[ Options::FULL_ACCESS_ROLES ], dws_lpmwc_validate_setting( null, 'full-access-user-roles', 'general' ) );
	}

	// v1's groups resolved a page-level read at priority 10 inside the page's filter, so a callback before it sees null and one after it sees the group's value.
	public function test_v1s_page_level_read_resolves_its_group_at_v1s_priority_inside_v1s_filter(): void {
		update_option( Options::FULL_ACCESS_ROLES, array( 'shop_manager' ) );
		$page   = 'locked_payment_methods_for_woocommerce/settings/get_validated_option_value';
		$seen   = array();
		$before = static function ( mixed $value ) use ( &$seen ): mixed {
			$seen['before'] = $value;
			return $value;
		};
		$after  = static function ( mixed $value ) use ( &$seen ): mixed {
			$seen['after'] = $value;
			return $value;
		};
		add_filter( $page, $before, 9 );
		add_filter( $page, $after, 11 );

		try {
			self::assertSame( array( 'shop_manager' ), dws_lpmwc_get_validated_setting( 'general/full-access-user-roles' ) );
		} finally {
			remove_filter( $page, $after, 11 );
			remove_filter( $page, $before, 9 );
		}

		self::assertSame(
			array(
				'before' => null,
				'after'  => array( 'shop_manager' ),
			),
			$seen
		);
	}

	protected static function read_header(): array {
		return get_file_data(
			DWS_LPMWC_FILE,
			array(
				'Version'     => 'Version',
				'RequiresWP'  => 'Requires at least',
				'RequiresPHP' => 'Requires PHP',
			)
		);
	}
}
