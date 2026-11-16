<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Integration;

use DeepWebSolutions\LockedPaymentMethods\Options;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\RecordsDeprecationsTrait;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\RestoresOptionsTrait;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\RunsRequestsTrait;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies\OrderStrategy;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class DeprecatedApiTest extends TestCase {
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
			self::$users[ $role ] = (int) wp_insert_user(
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
		$header = get_file_data(
			DWS_LPMWC_FILE,
			array(
				'Version'     => 'Version',
				'RequiresWP'  => 'Requires at least',
				'RequiresPHP' => 'Requires PHP',
			)
		);

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
		$header = get_file_data(
			DWS_LPMWC_FILE,
			array(
				'Version'     => 'Version',
				'RequiresWP'  => 'Requires at least',
				'RequiresPHP' => 'Requires PHP',
			)
		);

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

	public function test_a_payment_method_is_locked_for_a_user_when_it_is_listed_and_no_strategy_unlocks_it(): void {
		self::assertTrue( dws_lpmwc_is_payment_method_locked( 'cod', self::$users['customer'] ) );
		self::assertFalse( dws_lpmwc_is_payment_method_locked( 'cod', self::$users['shop_manager'] ) );
		self::assertFalse( dws_lpmwc_is_payment_method_locked( 'bacs', self::$users['customer'] ) );
		self::assertSame( array(), $this->deprecated );
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

	public function test_v1s_order_access_check_judges_the_order_and_its_customer_after_v1s_filter(): void {
		$order    = wc_create_order( array( 'customer_id' => self::$users['shop_manager'] ) );
		$order_id = $order->get_id();
		$v1       = 'locked_payment_methods_for_woocommerce/check_payment_method_access_for_wc_order';
		$seen     = array();
		$spy      = static function ( mixed ...$args ) use ( &$seen ): mixed {
			$seen[] = $args;
			return $args[0];
		};
		add_filter( $v1, $spy, 10, 3 );

		try {
			self::assertFalse( dws_lpmwc_check_payment_method_access_for_wc_order( 'cod', $order_id ) );
		} finally {
			remove_filter( $v1, $spy );
			$order->delete( true );
		}

		self::assertSame( array( array( true, 'cod', $order_id ) ), $seen );
		self::assertContains( array( $v1, 'locked_payment_methods_for_woocommerce/payment_method/should_lock' ), $this->deprecated );
	}

	public function test_v1s_order_checks_answer_locked_until_the_plugin_has_booted(): void {
		global $wp_actions;

		update_option( Options::UNLOCK_VIA_ORDERS, 'yes' );
		$order = wc_create_order( array( 'customer_id' => self::$users['shop_manager'] ) );
		$order->update_meta_data( OrderStrategy::META_KEY, array( 'cod' ) );
		$order->save();
		self::assertFalse( dws_lpmwc_check_payment_method_access_via_wc_order_meta( 'cod', $order->get_id() ) );
		self::assertGreaterThan( 0, did_action( 'locked_payment_methods_for_woocommerce/boot/succeeded' ) );
		$fired = $wp_actions['locked_payment_methods_for_woocommerce/boot/succeeded'];
		unset( $wp_actions['locked_payment_methods_for_woocommerce/boot/succeeded'] );

		try {
			self::assertTrue( dws_lpmwc_check_payment_method_access_via_wc_order_meta( 'cod', $order->get_id() ) );
			self::assertTrue( dws_lpmwc_check_payment_method_access_for_wc_order( 'cod', $order->get_id() ) );
		} finally {
			$wp_actions['locked_payment_methods_for_woocommerce/boot/succeeded'] = $fired;
			$order->delete( true );
		}
	}

	public function test_with_woocommerce_inactive_v1s_order_checks_answer_locked(): void {
		$request = self::request( 'add_filter( "deprecated_function_trigger_error", "__return_false" ); add_filter( "pre_option_' . Options::UNLOCK_VIA_ORDERS . '", static fn (): string => "yes" ); echo wp_json_encode( array( dws_lpmwc_check_payment_method_access_for_wc_order( "cod", 1 ), dws_lpmwc_check_payment_method_access_via_wc_order_meta( "cod", 1 ) ) );', '--skip-plugins=woocommerce' );

		self::assertSame( 0, $request['status'], $request['output'] );
		self::assertSame( '[true,true]', $request['last'], $request['output'] );
	}

	public function test_v1s_per_strategy_checks_answer_from_their_strategy_alone_and_true_while_it_is_off(): void {
		$order = wc_create_order();
		$order->update_meta_data( OrderStrategy::META_KEY, array( 'cod' ) );
		$order->save();

		try {
			self::assertFalse( dws_lpmwc_check_payment_method_access_via_user_roles( 'cod', self::$users['shop_manager'] ) );
			self::assertTrue( dws_lpmwc_check_payment_method_access_via_user_roles( 'cod', self::$users['customer'] ) );
			self::assertTrue( dws_lpmwc_check_payment_method_access_via_user_meta( 'cod', self::$users['shop_manager'] ) );
			self::assertTrue( dws_lpmwc_check_payment_method_access_via_wc_order_meta( 'cod', $order->get_id() ) );

			update_option( Options::UNLOCK_VIA_ORDERS, 'yes' );
			self::assertFalse( dws_lpmwc_check_payment_method_access_via_wc_order_meta( 'cod', $order->get_id() ) );

			update_option( Options::UNLOCK_VIA_USER_ROLES, 'no' );
			self::assertTrue( dws_lpmwc_check_payment_method_access_via_user_roles( 'cod', self::$users['shop_manager'] ) );
		} finally {
			$order->delete( true );
		}

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
			self::assertNull( dws_lpmwc_get_validated_setting( 'unknown', 'general' ) );
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
}
