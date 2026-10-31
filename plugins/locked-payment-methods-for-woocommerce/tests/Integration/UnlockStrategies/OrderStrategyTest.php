<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Integration\UnlockStrategies;

use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use DeepWebSolutions\LockedPaymentMethods\Scoped\Psr\Log\NullLogger as PsrNullLogger;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\OrderStorage;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\RunsRequestsTrait;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies\OrderStrategy;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class OrderStrategyTest extends TestCase {
	use OrderStorage;
	use RunsRequestsTrait;

	protected const string FORM = 'dws_lpmwc_order';

	protected const array KEYS = array( DWS_LPMWC_OPTION_LOCKED_METHODS, DWS_LPMWC_OPTION_UNLOCK_VIA_ORDERS );

	protected static OrderStrategy $strategy;

	protected static int $admin;

	protected array $stored = array();

	// Orders are off by default, so the booted plugin runs no order strategy; the class hooks one of its own, as the plugin does with the switch on.
	#[\Override]
	public static function setUpBeforeClass(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		self::$strategy = new OrderStrategy( new AdminNotices( 'dws_lpmwc', new PsrNullLogger() ) );
		self::$strategy->register_hooks();
		self::$admin = (int) wp_insert_user(
			array(
				'user_login' => 'lpm-orders-admin',
				'user_pass'  => 'password',
				'user_email' => 'lpm-orders-admin@example.org',
				'role'       => 'administrator',
			)
		);
	}

	#[\Override]
	public static function tearDownAfterClass(): void {
		wp_delete_user( self::$admin );
	}

	#[Before]
	protected function store_rows(): void {
		foreach ( self::KEYS as $key ) {
			$this->stored[ $key ] = get_option( $key );
		}
		update_option( DWS_LPMWC_OPTION_LOCKED_METHODS, array( 'cod', 'bacs' ) );
	}

	#[After]
	protected function restore_rows(): void {
		foreach ( $this->stored as $key => $value ) {
			false === $value ? delete_option( $key ) : update_option( $key, $value );
		}
		unset( $_POST[ self::FORM ], $_POST[ self::FORM . '_nonce' ] );
		wp_set_current_user( 0 );
	}

	public function test_the_strategy_loads_only_while_its_switch_is_on_as_v1s_did(): void {
		delete_option( DWS_LPMWC_OPTION_UNLOCK_VIA_ORDERS );
		self::assertFalse( OrderStrategy::should_load() );

		update_option( DWS_LPMWC_OPTION_UNLOCK_VIA_ORDERS, 'yes' );
		self::assertTrue( OrderStrategy::should_load() );
	}

	public function test_the_plugin_runs_the_strategy_once_its_switch_is_on(): void {
		update_option( DWS_LPMWC_OPTION_UNLOCK_VIA_ORDERS, 'yes' );

		$request = self::request( 'echo wp_json_encode( in_array( DeepWebSolutions\LockedPaymentMethods\UnlockStrategies\OrderStrategy::class, dws_lpmwc_plugin()->kernel->resolved, true ) );' );

		self::assertSame( 0, $request['status'], $request['output'] );
		self::assertSame( 'true', $request['last'] );
	}

	#[DataProvider( 'storages' )]
	public function test_an_order_unlocks_only_the_methods_its_list_holds_when_it_is_the_order_being_paid( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$order = $this->create_order();
		$order->update_meta_data( DWS_LPMWC_META_UNLOCKED_METHODS, array( 'cod' ) );
		$order->save();

		self::assertSame( array( 'cod' ), dws_lpmwc_get_order_unlocked_payment_method_ids( $order->get_id() ) );
		self::assertFalse( self::should_lock( 'cod', $order->get_id() ) );
		self::assertTrue( self::should_lock( 'bacs', $order->get_id() ) );
		self::assertTrue( self::should_lock( 'cod', 0 ) );
	}

	public function test_the_box_offers_the_locked_methods_and_the_orders_other_grants_and_marks_each_one(): void {
		$order = $this->create_order();
		$order->update_meta_data( DWS_LPMWC_META_UNLOCKED_METHODS, array( 'cod', 'cheque' ) );
		$order->save();

		$form = self::$strategy->get_object_form( self::FORM, $order->get_id() );

		self::assertSame( DWS_LPMWC_CAP_UNLOCK_VIA_ORDERS, $form['capability'] );
		self::assertSame( DWS_LPMWC_META_UNLOCKED_METHODS, $form['fields'][0]['id'] );
		self::assertSame(
			array(
				'cod'    => 'Cash on delivery',
				'bacs'   => 'Direct bank transfer',
				'cheque' => 'Check payments (not locked)',
			),
			$form['fields'][0]['options']
		);
		self::assertArrayNotHasKey( 'custom_attributes', $form['fields'][0] );
	}

	public function test_the_box_of_a_paid_order_is_disabled_so_a_save_leaves_its_grants(): void {
		$order = $this->create_order( array( 'status' => 'processing' ) );
		$order->update_meta_data( DWS_LPMWC_META_UNLOCKED_METHODS, array( 'cod' ) );
		$order->save();
		wp_set_current_user( self::$admin );

		self::assertSame( array( 'disabled' => 'disabled' ), self::$strategy->get_object_form( self::FORM, $order->get_id() )['fields'][0]['custom_attributes'] ?? null );

		self::save( $order->get_id(), array() );
		self::assertSame( array( 'cod' ), dws_lpmwc_get_order_unlocked_payment_method_ids( $order->get_id() ) );
	}

	#[DataProvider( 'storages' )]
	public function test_saving_the_box_stores_the_checked_methods_on_the_order( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$order = $this->create_order();
		wp_set_current_user( self::$admin );

		self::save( $order->get_id(), array( 'bacs' ) );

		self::assertSame( array( 'bacs' ), dws_lpmwc_get_order_unlocked_payment_method_ids( $order->get_id() ) );
	}

	public function test_on_its_order_pay_page_an_order_unlocks_its_methods_given_its_key(): void {
		$order = $this->create_order();
		$order->update_meta_data( DWS_LPMWC_META_UNLOCKED_METHODS, array( 'cod' ) );
		$order->save();
		$GLOBALS['wp']->query_vars['order-pay'] = (string) $order->get_id();
		add_filter( 'woocommerce_is_checkout', '__return_true' );
		$_GET['key'] = $order->get_order_key();

		try {
			$available = apply_filters( 'woocommerce_available_payment_gateways', WC()->payment_gateways()->payment_gateways() );
		} finally {
			unset( $GLOBALS['wp']->query_vars['order-pay'], $_GET['key'] );
			remove_filter( 'woocommerce_is_checkout', '__return_true' );
		}

		self::assertArrayHasKey( 'cod', $available );
		self::assertArrayNotHasKey( 'bacs', $available );
	}

	#[DataProvider( 'storages' )]
	public function test_the_grants_step_moves_v1s_order_grants_into_the_orders_list( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$moved = $this->create_order();
		$moved->update_meta_data( '_dws_wc_lpm_grant_access_cod', 'yes' );
		$moved->update_meta_data( '_dws_wc_lpm_grant_access_bacs', 'no' );
		$moved->save();
		$merged = $this->create_order();
		$merged->update_meta_data( '_dws_wc_lpm_grant_access_cod', 'yes' );
		$merged->update_meta_data( DWS_LPMWC_META_UNLOCKED_METHODS, array( 'cheque' ) );
		$merged->save();

		dws_lpmwc_move_order_grants();
		dws_lpmwc_move_order_grants();

		self::assertSame( array( 'cod' ), dws_lpmwc_get_order_unlocked_payment_method_ids( $moved->get_id() ) );
		self::assertSame( array( 'cheque', 'cod' ), dws_lpmwc_get_order_unlocked_payment_method_ids( $merged->get_id() ) );
		foreach ( array( $moved, $merged ) as $order ) {
			$keys = \array_map( static fn ( \WC_Meta_Data $meta ): string => (string) $meta->get_data()['key'], wc_get_order( $order->get_id() )->get_meta_data() );
			self::assertSame( array(), \array_values( \array_filter( $keys, static fn ( string $key ): bool => \str_starts_with( $key, '_dws_wc_lpm_' ) ) ) );
		}
	}

	protected static function should_lock( string $gateway_id, int $order_id ): mixed {
		return apply_filters( 'dws_lpmwc/payment_method/should_lock', true, $gateway_id, 0, $order_id );
	}

	protected static function save( int $order_id, array|string $methods ): void {
		$_POST[ self::FORM ]            = array( DWS_LPMWC_META_UNLOCKED_METHODS => $methods );
		$_POST[ self::FORM . '_nonce' ] = wp_create_nonce( self::FORM . "_nonce_$order_id" );
		do_action( 'woocommerce_process_shop_order_meta', $order_id, wc_get_order( $order_id ) );
	}
}
