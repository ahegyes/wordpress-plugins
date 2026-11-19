<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Integration\UnlockStrategies;

use DeepWebSolutions\LockedPaymentMethods\Migrations;
use DeepWebSolutions\LockedPaymentMethods\Options;
use DeepWebSolutions\LockedPaymentMethods\Plugin;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\PluginIdentity;
use DeepWebSolutions\LockedPaymentMethods\Scoped\Psr\Log\NullLogger as PsrNullLogger;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\InsertsUsersTrait;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\IsolatesStrategiesTrait;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\OrderStorageTrait;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\RecordsGrantUpdatesTrait;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\RestoresOptionsTrait;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies\OrderStrategy;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( OrderStrategy::class )]
final class OrderStrategyTest extends TestCase {
	use InsertsUsersTrait;
	use IsolatesStrategiesTrait;
	use OrderStorageTrait;
	use RecordsGrantUpdatesTrait;
	use RestoresOptionsTrait;

	protected const string FORM = 'dws_lpmwc_order';

	protected const array OPTIONS = array( Options::LOCKED_METHODS, Options::UNLOCK_VIA_ORDERS );

	protected static OrderStrategy $strategy;

	protected static int $admin;

	// Orders are off by default, so the booted plugin runs no order strategy; the class hooks one of its own, as the plugin does with the switch on.
	#[\Override]
	public static function setUpBeforeClass(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		require_once ABSPATH . 'wp-admin/includes/template.php'; // WordPress and WooCommerce load their meta box and screen APIs only in admin requests.
		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		require_once WC_ABSPATH . 'includes/admin/wc-admin-functions.php';
		$identity       = new PluginIdentity( DWS_LPMWC_FILE, Plugin::SLUG, Plugin::PREFIX );
		self::$strategy = new OrderStrategy( $identity, new AdminNotices( $identity, new PsrNullLogger() ) );
		self::$strategy->register_hooks();
		self::$admin = self::insert_user(
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
	protected function lock_the_methods(): void {
		update_option( Options::LOCKED_METHODS, array( 'cod', 'bacs' ) );
	}

	#[After]
	protected function clean_up(): void {
		unset( $_POST[ self::FORM ], $_POST[ self::FORM . '_nonce' ] );
		$GLOBALS['wp_meta_boxes'] = array();
		wp_set_current_user( 0 );
	}

	public function test_the_strategy_loads_only_while_its_switch_is_on_as_v1s_did(): void {
		delete_option( Options::UNLOCK_VIA_ORDERS );
		self::assertFalse( OrderStrategy::should_load() );

		update_option( Options::UNLOCK_VIA_ORDERS, 'yes' );
		self::assertTrue( OrderStrategy::should_load() );
	}

	#[DataProvider( 'storages' )]
	public function test_an_order_unlocks_only_the_methods_its_list_holds_when_it_is_the_order_being_paid( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$order = $this->create_order();
		$order->update_meta_data( '_dws_lpmwc_unlocked_payment_methods', array( 'cod' ) );
		$order->save();

		self::assertSame( array( 'cod' ), self::$strategy->get_granted_ids( $order->get_id() ) );
		self::assertFalse( self::should_lock( 'cod', 0, $order->get_id() ) );
		self::assertTrue( self::should_lock( 'bacs', 0, $order->get_id() ) );
		self::assertTrue( self::should_lock( 'cod', 0 ) );
	}

	// The posts editor passes its post to the box and the order-tables editor its order, so the row passes what its storage's editor does.
	#[DataProvider( 'storages' )]
	public function test_the_box_offers_the_locked_methods_and_the_orders_other_grants_and_marks_each_one( bool $hpos, bool $sync ): void {
		global $wp_meta_boxes;

		$this->use_storage( $hpos, $sync );
		$order = $this->create_order();
		$order->update_meta_data( '_dws_lpmwc_unlocked_payment_methods', array( 'cod', 'cheque' ) );
		$order->save();
		wp_set_current_user( self::$admin );
		$screen = wc_get_page_screen_id( 'shop-order' ); // WooCommerce names the order screen after the menu the current user sees.
		$edited = $hpos ? $order : get_post( $order->get_id() );

		wp_set_current_user( 0 );
		do_action( 'add_meta_boxes', $screen, $edited );
		self::assertNull( $wp_meta_boxes[ $screen ]['side']['default'][ self::FORM ] ?? null );

		wp_set_current_user( self::$admin );
		do_action( 'add_meta_boxes', $screen, $edited );
		$box = $wp_meta_boxes[ $screen ]['side']['default'][ self::FORM ] ?? null;
		self::assertIsArray( $box );
		\ob_start();
		\call_user_func( $box['callback'], $edited, $box );
		$form = self::parse( (string) \ob_get_clean() );

		$choices = array();
		foreach ( $form->querySelectorAll( 'input[type="checkbox"]' ) as $input ) {
			$choices[ (string) $input->getAttribute( 'value' ) ] = $input->hasAttribute( 'checked' ) && ! $input->hasAttribute( 'disabled' );
		}
		self::assertSame(
			array(
				'cod'    => true,
				'bacs'   => false,
				'cheque' => true,
			),
			$choices
		);
		self::assertStringContainsString( 'Check payments (not locked)', (string) $form->body?->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertSame( 1, wp_verify_nonce( (string) $form->querySelector( 'input[name="' . self::FORM . '_nonce"]' )?->getAttribute( 'value' ), self::FORM . '_nonce_' . $order->get_id() ) );
	}

	#[DataProvider( 'storages' )]
	public function test_the_box_of_a_paid_order_is_disabled_so_a_save_leaves_its_grants( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$order = $this->create_order( array( 'status' => 'processing' ) );
		$order->update_meta_data( '_dws_lpmwc_unlocked_payment_methods', array( 'cod' ) );
		$order->save();
		wp_set_current_user( self::$admin );

		self::assertSame( array( 'disabled' => 'disabled' ), self::$strategy->get_object_form( self::FORM, $order->get_id() )['fields'][0]['custom_attributes'] ?? null );

		self::save( $order->get_id(), array() );
		self::assertSame( array( 'cod' ), self::$strategy->get_granted_ids( $order->get_id() ) );
	}

	#[DataProvider( 'storages' )]
	public function test_saving_the_box_stores_the_checked_methods_on_the_order( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$order = $this->create_order();
		wp_set_current_user( self::$admin );

		self::save( $order->get_id(), array( 'bacs' ) );

		self::assertSame( array( 'bacs' ), self::$strategy->get_granted_ids( $order->get_id() ) );
		self::assertSame( array( array( 'order', $order->get_id(), array( 'bacs' ), array() ) ), $this->updates );
	}

	#[DataProvider( 'storages' )]
	public function test_the_strategy_grants_and_revokes_an_unpaid_orders_methods_and_announces_each_change( bool $hpos, bool $sync ): void {
		$this->use_storage( $hpos, $sync );
		$order = $this->create_order()->get_id();

		self::assertInstanceOf( \DWS_LPMWC_Grant_Strategy::class, self::$strategy );
		self::assertTrue( self::$strategy->grant( $order, 'cod', 'bacs' ) );
		self::assertTrue( self::$strategy->grant( $order, 'bacs' ) );
		self::assertTrue( self::$strategy->revoke( $order, 'cod', 'cheque' ) );

		self::assertSame( array( 'bacs' ), self::$strategy->get_granted_ids( $order ) );
		self::assertSame( array( 'bacs' ), wc_get_order( $order )?->get_meta( '_dws_lpmwc_unlocked_payment_methods' ) );
		self::assertSame(
			array(
				array( 'order', $order, array( 'cod', 'bacs' ), array() ),
				array( 'order', $order, array( 'bacs' ), array( 'cod', 'bacs' ) ),
			),
			$this->updates
		);
	}

	public function test_the_strategy_refuses_grants_for_a_paid_or_missing_order(): void {
		$order = $this->create_order( array( 'status' => 'processing' ) );
		$order->update_meta_data( '_dws_lpmwc_unlocked_payment_methods', array( 'cod' ) );
		$order->save();

		$granted = self::$strategy->grant( $order->get_id(), 'bacs' );
		$revoked = self::$strategy->revoke( $order->get_id(), 'cod' );
		$missing = self::$strategy->grant( $order->get_id() + 1000, 'cod' );

		self::assertInstanceOf( \WP_Error::class, $granted );
		self::assertSame( 'dws_lpmwc_order_paid', $granted->get_error_code() );
		self::assertInstanceOf( \WP_Error::class, $revoked );
		self::assertSame( 'dws_lpmwc_order_paid', $revoked->get_error_code() );
		self::assertInstanceOf( \WP_Error::class, $missing );
		self::assertSame( 'dws_lpmwc_subject_not_found', $missing->get_error_code() );
		self::assertSame( array( 'cod' ), self::$strategy->get_granted_ids( $order->get_id() ) );
		self::assertSame( array(), $this->updates );
	}

	public function test_a_grant_whose_write_fails_returns_an_error_and_announces_nothing(): void {
		$this->use_storage( false, false );
		$order  = $this->create_order()->get_id();
		$refuse = static fn ( mixed $check, int $object_id, string $key ): mixed => '_dws_lpmwc_unlocked_payment_methods' === $key ? false : $check;
		add_filter( 'add_post_metadata', $refuse, 10, 3 );

		try {
			$granted = self::$strategy->grant( $order, 'cod' );
		} finally {
			remove_filter( 'add_post_metadata', $refuse );
		}

		self::assertInstanceOf( \WP_Error::class, $granted );
		self::assertSame( 'dws_lpmwc_grants_not_stored', $granted->get_error_code() );
		self::assertSame( array(), $this->updates );
	}

	public function test_on_its_order_pay_page_an_order_unlocks_its_methods_given_its_key(): void {
		$order = $this->create_order();
		$order->update_meta_data( '_dws_lpmwc_unlocked_payment_methods', array( 'cod' ) );
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
		$merged->update_meta_data( '_dws_lpmwc_unlocked_payment_methods', array( 'cheque' ) );
		$merged->save();

		Migrations::get_steps()[3]();
		Migrations::get_steps()[3]();

		self::assertSame( array( 'cod' ), self::$strategy->get_granted_ids( $moved->get_id() ) );
		self::assertSame( array( 'cheque', 'cod' ), self::$strategy->get_granted_ids( $merged->get_id() ) );
		foreach ( array( $moved, $merged ) as $order ) {
			$keys = \array_map( static fn ( \WC_Meta_Data $meta ): string => (string) $meta->get_data()['key'], wc_get_order( $order->get_id() )->get_meta_data() );
			self::assertSame( array(), \array_values( \array_filter( $keys, static fn ( string $key ): bool => \str_starts_with( $key, '_dws_wc_lpm_' ) ) ) );
		}
	}

	public function test_the_grants_step_throws_when_it_cannot_tell_whether_an_order_table_exists(): void {
		global $wpdb;

		$fail       = static fn ( string $query ): string => \str_starts_with( \ltrim( $query ), 'SHOW TABLES' ) ? "SELECT * FROM {$wpdb->prefix}dws_lpmwc_missing" : $query;
		$suppressed = $wpdb->suppress_errors();
		add_filter( 'query', $fail );
		try {
			$this->expectExceptionObject( new \RuntimeException( 'The v1 order grants could not be read.' ) );
			Migrations::get_steps()[3]();
		} finally {
			remove_filter( 'query', $fail );
			$wpdb->suppress_errors( $suppressed );
		}
	}

	#[DataProvider( 'storages' )]
	public function test_a_grants_step_whose_write_fails_keeps_v1s_grants_for_its_retry( bool $hpos, bool $sync ): void {
		global $wpdb;

		$this->use_storage( $hpos, $sync );
		$order = $this->create_order();
		$order->update_meta_data( '_dws_wc_lpm_grant_access_cod', 'yes' );
		$order->save();
		$refuse     = static fn ( string $query ): string => \str_contains( $query, "'_dws_lpmwc_unlocked_payment_methods'" ) && \preg_match( '/^\s*(INSERT|UPDATE)/i', $query ) ? "SELECT * FROM {$wpdb->prefix}dws_lpmwc_missing" : $query;
		$suppressed = $wpdb->suppress_errors();
		add_filter( 'query', $refuse );

		$failed = false;
		try {
			Migrations::get_steps()[3]();
		} catch ( \RuntimeException ) {
			$failed = true;
		} finally {
			remove_filter( 'query', $refuse );
			$wpdb->suppress_errors( $suppressed );
		}

		self::assertTrue( $failed );
		self::assertSame( 'yes', wc_get_order( $order->get_id() )->get_meta( '_dws_wc_lpm_grant_access_cod' ) );
		Migrations::get_steps()[3]();
		self::assertSame( array( 'cod' ), self::$strategy->get_granted_ids( $order->get_id() ) );
	}


	protected static function save( int $order_id, array|string $methods ): void {
		$_POST[ self::FORM ]            = array( 'dws_lpmwc_unlocked_payment_methods' => $methods );
		$_POST[ self::FORM . '_nonce' ] = wp_create_nonce( self::FORM . "_nonce_$order_id" );
		do_action( 'woocommerce_process_shop_order_meta', $order_id, wc_get_order( $order_id ) );
	}
}
