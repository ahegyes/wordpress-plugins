<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Integration;

use DeepWebSolutions\LockedPaymentMethods\Tests\Support\RecordsDeprecationsTrait;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\RestoresOptionsTrait;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\RunsRequestsTrait;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class SafeguardsTest extends TestCase {
	use RecordsDeprecationsTrait;
	use RestoresOptionsTrait;
	use RunsRequestsTrait;

	protected const array OPTIONS = array( DWS_LPMWC_OPTION_LOCKED_METHODS, self::V1_KEY );

	protected const string FILTER = 'dws_lpmwc/payment_method/should_lock';

	protected const string V1_KEY = 'dws-wc-lpm_general_locked-payment-methods';

	public static function relaxations(): array {
		return array(
			'none'         => array( array(), false ),
			'null'         => array( array( 'relaxation' => null ), false ),
			'zero'         => array( array( 'relaxation' => 0 ), false ),
			'empty-string' => array( array( 'relaxation' => '' ), false ),
			'false'        => array( array( 'relaxation' => false ), true ),
		);
	}

	public static function gateway_values(): array {
		return array(
			'null'   => array( 'null' ),
			'object' => array( 'object' ),
		);
	}

	public static function early_relaxations(): array {
		return array(
			'null'         => array( null ),
			'zero'         => array( 0 ),
			'empty-string' => array( '' ),
		);
	}

	public static function order_keys(): array {
		return array(
			'missing' => array( null ),
			'wrong'   => array( 'wc_order_wrong' ),
		);
	}

	public static function floors(): array {
		return array(
			'at-the-floors'     => array( null, '[1,true]' ),
			'below-php'         => array( 'Requires PHP', '[0,false]' ),
			'below-woocommerce' => array( 'WC requires at least', '[0,false]' ),
		);
	}

	#[Before]
	protected function lock_cod(): void {
		update_option( DWS_LPMWC_OPTION_LOCKED_METHODS, array( 'cod' ) );
	}

	#[After]
	protected function reset_the_request(): void {
		remove_filter( 'woocommerce_is_checkout', '__return_true' );
		unset( $GLOBALS['wp']->query_vars['order-pay'], $_GET['key'] );
		set_query_var( 'order-pay', '' );
		wp_set_current_user( 0 );
	}

	public function test_the_lock_removes_the_methods_the_plugins_option_lists(): void {
		$available = self::available();

		self::assertArrayNotHasKey( 'cod', $available );
		self::assertArrayHasKey( 'bacs', $available );
	}

	#[DataProvider( 'relaxations' )]
	public function test_only_an_explicit_false_from_should_lock_unlocks_a_locked_method( array $spec, bool $relaxed ): void {
		$relaxation = static fn (): mixed => $spec['relaxation'];
		if ( \array_key_exists( 'relaxation', $spec ) ) {
			add_filter( self::FILTER, $relaxation );
		}

		try {
			self::assertSame( $relaxed, \array_key_exists( 'cod', self::available() ) );
		} finally {
			remove_filter( self::FILTER, $relaxation );
		}
	}

	#[DataProvider( 'early_relaxations' )]
	public function test_a_callback_before_the_strategies_unlocks_nothing_without_an_explicit_false( mixed $relaxation ): void {
		$early = static fn (): mixed => $relaxation;
		add_filter( self::FILTER, $early, 5 );

		try {
			self::assertArrayNotHasKey( 'cod', self::available() );
		} finally {
			remove_filter( self::FILTER, $early, 5 );
		}
	}

	#[DataProvider( 'gateway_values' )]
	public function test_the_lock_judges_a_gateways_value_that_is_not_a_list_as_woocommerce_casts_it( string $kind ): void {
		$replace = static fn ( array $gateways ): ?object => 'object' === $kind ? (object) $gateways : null;
		add_filter( 'woocommerce_available_payment_gateways', $replace );

		try {
			$offered = (array) self::available();
			self::assertArrayNotHasKey( 'cod', $offered );
			self::assertSame( 'object' === $kind, \array_key_exists( 'bacs', $offered ) );
		} finally {
			remove_filter( 'woocommerce_available_payment_gateways', $replace );
		}
	}

	public function test_an_explicit_false_does_not_unlock_before_the_plugin_booted(): void {
		global $wp_actions;
		self::assertGreaterThan( 0, did_action( 'dws_lpmwc/boot/succeeded' ) );
		$fired = $wp_actions['dws_lpmwc/boot/succeeded'];
		unset( $wp_actions['dws_lpmwc/boot/succeeded'] );
		add_filter( self::FILTER, '__return_false' );

		try {
			self::assertArrayNotHasKey( 'cod', self::available() );
		} finally {
			remove_filter( self::FILTER, '__return_false' );
			$wp_actions['dws_lpmwc/boot/succeeded'] = $fired;
		}
	}

	public function test_the_lock_reads_v1s_list_while_the_plugin_has_stored_none(): void {
		delete_option( DWS_LPMWC_OPTION_LOCKED_METHODS );
		update_option( self::V1_KEY, array( 'bacs' ) );

		$available = self::available();

		self::assertArrayNotHasKey( 'bacs', $available );
		self::assertArrayHasKey( 'cod', $available );
	}

	public function test_v1s_list_stops_counting_once_the_plugin_stores_its_own_even_an_empty_one(): void {
		update_option( DWS_LPMWC_OPTION_LOCKED_METHODS, array() );
		update_option( self::V1_KEY, array( 'bacs' ) );

		$available = self::available();

		self::assertArrayHasKey( 'bacs', $available );
		self::assertArrayHasKey( 'cod', $available );
	}

	public function test_only_the_strings_of_a_stored_list_are_locked(): void {
		update_option( DWS_LPMWC_OPTION_LOCKED_METHODS, array( 'cod', 7, array( 'bacs' ) ) );
		$available = self::available();
		self::assertArrayNotHasKey( 'cod', $available );
		self::assertArrayHasKey( 'bacs', $available );

		update_option( DWS_LPMWC_OPTION_LOCKED_METHODS, 'bacs' );
		$available = self::available();
		self::assertArrayHasKey( 'bacs', $available );
		self::assertArrayHasKey( 'cod', $available );
	}

	public function test_the_locked_ids_filter_locks_more_methods_but_cannot_unlock_the_stored_ones(): void {
		$replace = static fn (): array => array( 'bacs' );
		add_filter( 'dws_lpmwc/payment_method/locked_ids', $replace );

		try {
			$available = self::available();
		} finally {
			remove_filter( 'dws_lpmwc/payment_method/locked_ids', $replace );
		}

		self::assertArrayNotHasKey( 'cod', $available );
		self::assertArrayNotHasKey( 'bacs', $available );
	}

	public function test_the_lock_check_counts_the_methods_the_locked_ids_filter_adds(): void {
		$add = static fn ( array $ids ): array => array( ...$ids, 'bacs' );
		add_filter( 'dws_lpmwc/payment_method/locked_ids', $add );

		try {
			self::assertTrue( dws_lpmwc_is_payment_method_locked( 'bacs', 0 ) );
		} finally {
			remove_filter( 'dws_lpmwc/payment_method/locked_ids', $add );
		}

		self::assertFalse( dws_lpmwc_is_payment_method_locked( 'bacs', 0 ) );
	}

	public function test_the_locked_ids_filter_follows_v1s_value_filter_and_v1s_list_filter_follows_it_at_checkout(): void {
		$seen     = array();
		$gateways = WC()->payment_gateways()->payment_gateways();
		$hooks    = array(
			'locked_payment_methods_for_woocommerce/general_settings/get_validated_option_value' => 'first',
			'dws_lpmwc/payment_method/locked_ids' => 'second',
			'locked_payment_methods_for_woocommerce/lock_manager/locked_payment_methods' => 'third',
		);
		$adders   = array();
		foreach ( $hooks as $hook => $id ) {
			$adders[ $hook ] = static function ( array $ids, mixed $context = null ) use ( &$seen, $hook, $id ): array {
				$seen[] = array( $hook, $ids, \is_array( $context ) ? \array_keys( $context ) : $context );
				return array( ...$ids, $id );
			};
			add_filter( $hook, $adders[ $hook ], 10, 2 );
		}

		try {
			self::available();
		} finally {
			foreach ( $adders as $hook => $adder ) {
				remove_filter( $hook, $adder );
			}
		}

		$hooks = \array_keys( $hooks );
		self::assertSame(
			array(
				array( $hooks[0], array( 'cod' ), 'locked-payment-methods' ),
				array( $hooks[1], array( 'cod', 'first' ), null ),
				array( $hooks[2], array( 'cod', 'first', 'second' ), \array_keys( $gateways ) ),
			),
			$seen
		);
		self::assertSame( array( array( $hooks[0], $hooks[1] ), array( $hooks[2], $hooks[1] ) ), $this->deprecated );
	}

	public function test_v1s_lock_filters_fire_as_deprecated_before_should_lock_under_v1s_safe_id(): void {
		update_option( DWS_LPMWC_OPTION_LOCKED_METHODS, array( "WooCommerce_Pay\x01ments" ) );
		$seen         = array();
		$v1           = 'locked_payment_methods_for_woocommerce/lock_manager/is_locked_payment_method';
		$v1_safe      = "$v1/woocommerce_payments";
		$record       = static function ( mixed ...$args ) use ( &$seen ): mixed {
			$seen[] = $args;
			return $args[0];
		};
		$returns_null = static function ( mixed $lock ) use ( &$seen ): mixed {
			$seen[] = array( $lock );
			return null;
		};
		add_filter( $v1, $record, 10, 2 );
		add_filter( $v1_safe, $returns_null );
		add_filter( self::FILTER, $record, 0, 4 );

		try {
			$available = self::available( array( "WooCommerce_Pay\x01ments" => new \stdClass() ) );
		} finally {
			remove_filter( $v1, $record );
			remove_filter( $v1_safe, $returns_null );
			remove_filter( self::FILTER, $record, 0 );
		}

		self::assertSame( array( array( true, "WooCommerce_Pay\x01ments" ), array( true ), array( true, "WooCommerce_Pay\x01ments", 0, 0 ) ), $seen );
		self::assertArrayNotHasKey( "WooCommerce_Pay\x01ments", $available );
		self::assertSame( array( array( $v1, self::FILTER ), array( $v1_safe, self::FILTER ) ), $this->deprecated );
	}

	public function test_a_v1_callback_that_returns_false_still_unlocks_once_the_plugin_booted(): void {
		$v1 = 'locked_payment_methods_for_woocommerce/lock_manager/is_locked_payment_method';
		add_filter( $v1, '__return_false' );

		try {
			self::assertArrayHasKey( 'cod', self::available() );
		} finally {
			remove_filter( $v1, '__return_false' );
		}
	}

	public function test_should_lock_judges_the_current_user_and_no_order_off_the_order_pay_page(): void {
		wp_set_current_user( 1 );

		self::assertSame( array( true, 'cod', 1, 0 ), self::should_lock_arguments() );
	}

	public function test_should_lock_judges_the_order_its_order_pay_page_pays_given_the_orders_key(): void {
		$order = wc_create_order();
		self::visit_order_pay( $order->get_id(), $order->get_order_key() );

		try {
			self::assertSame( array( true, 'cod', 0, $order->get_id() ), self::should_lock_arguments() );
		} finally {
			$order->delete( true );
		}
	}

	public function test_should_lock_judges_no_order_in_an_ajax_checkout_that_names_an_order_and_its_key(): void {
		$order = wc_create_order();
		self::visit_order_pay( $order->get_id(), $order->get_order_key() );
		add_filter( 'wp_doing_ajax', '__return_true' );

		try {
			self::assertSame( array( true, 'cod', 0, 0 ), self::should_lock_arguments() );
		} finally {
			remove_filter( 'wp_doing_ajax', '__return_true' );
			$order->delete( true );
		}
	}

	#[RunInSeparateProcess]
	public function test_should_lock_judges_no_order_in_a_rest_checkout_that_names_an_order_and_its_key(): void {
		\define( 'REST_REQUEST', true );
		$order = wc_create_order();
		self::visit_order_pay( $order->get_id(), $order->get_order_key() );

		try {
			self::assertSame( array( true, 'cod', 0, 0 ), self::should_lock_arguments() );
		} finally {
			$order->delete( true );
		}
	}

	#[DataProvider( 'order_keys' )]
	public function test_should_lock_judges_no_order_on_an_order_pay_page_without_the_orders_key( ?string $key ): void {
		$order = wc_create_order();
		self::visit_order_pay( $order->get_id(), $key );

		try {
			self::assertSame( array( true, 'cod', 0, 0 ), self::should_lock_arguments() );
		} finally {
			$order->delete( true );
		}
	}

	#[DataProvider( 'floors' )]
	public function test_the_lock_holds_and_nothing_relaxes_it_while_the_plugin_cannot_boot( ?string $header, string $expected ): void {
		$source = (string) \file_get_contents( DWS_LPMWC_FILE );
		if ( ! \is_null( $header ) ) {
			\file_put_contents( DWS_LPMWC_FILE, (string) \preg_replace( '/^(\s*\*\s*' . \preg_quote( $header, '/' ) . ':\s*).*$/m', '${1}99.0', $source ) );
		}

		try {
			$request = self::request( 'add_filter( "dws_lpmwc/payment_method/should_lock", "__return_false" ); echo wp_json_encode( array( did_action( "dws_lpmwc/boot/succeeded" ), array_key_exists( "cod", apply_filters( "woocommerce_available_payment_gateways", WC()->payment_gateways()->payment_gateways() ) ) ) );' );
		} finally {
			\file_put_contents( DWS_LPMWC_FILE, $source );
		}

		self::assertSame( 0, $request['status'], $request['output'] );
		self::assertSame( $expected, $request['last'] );
	}

	protected static function available( array $gateways = array() ): mixed {
		return apply_filters( 'woocommerce_available_payment_gateways', $gateways + WC()->payment_gateways()->payment_gateways() );
	}

	protected static function should_lock_arguments(): array {
		$seen   = array();
		$record = static function ( mixed ...$args ) use ( &$seen ): mixed {
			if ( 'cod' === $args[1] ) {
				$seen = $args;
			}

			return $args[0];
		};
		add_filter( self::FILTER, $record, 0, 4 );

		try {
			self::available();
		} finally {
			remove_filter( self::FILTER, $record, 0 );
		}

		return $seen;
	}

	protected static function visit_order_pay( int $order_id, ?string $key ): void {
		$GLOBALS['wp']->query_vars['order-pay'] = (string) $order_id;
		set_query_var( 'order-pay', (string) $order_id );
		add_filter( 'woocommerce_is_checkout', '__return_true' );
		if ( ! \is_null( $key ) ) {
			$_GET['key'] = $key;
		}
	}

	// WordPress loads the plugin while WooCommerce is inactive, so a safeguard that calls WooCommerce waits for it to load.
	public function test_the_gateway_lock_is_hooked_only_once_woocommerce_has_loaded(): void {
		$php     = 'echo wp_json_encode( array( did_action( "woocommerce_loaded" ), false !== has_filter( "woocommerce_available_payment_gateways", "dws_lpmwc_remove_locked_payment_methods" ) ) );';
		$with    = self::request( $php );
		$without = self::request( $php, '--skip-plugins=woocommerce' );

		self::assertSame( '[1,true]', $with['last'], $with['output'] );
		self::assertSame( '[0,false]', $without['last'], $without['output'] );
	}
}
