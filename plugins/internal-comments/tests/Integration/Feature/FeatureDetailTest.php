<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration\Feature;

use DeepWebSolutions\InternalComments\Feature\FeatureDetail;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class FeatureDetailTest extends TestCase {
	protected const array KEYS = array( DWS_IC_OPTION_GREETING, DWS_IC_OPTION_GREETING_ORDERS );

	protected array $stored = array();

	protected int $customer = 0;

	protected array $orders = array();

	#[\Override]
	public static function setUpBeforeClass(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
	}

	#[\Override]
	protected function setUp(): void {
		foreach ( self::KEYS as $key ) {
			$this->stored[ $key ] = get_option( $key );
		}

		$customer = wp_insert_user(
			array(
				'user_login' => 'dws_ic_customer_' . \wp_generate_password( 8, false ),
				'user_pass'  => wp_generate_password(),
				'role'       => 'customer',
			)
		);
		self::assertIsInt( $customer );
		$this->customer = $customer;
		wp_set_current_user( $customer );
	}

	#[After]
	protected function clean_up(): void {
		foreach ( $this->stored as $key => $value ) {
			false === $value ? delete_option( $key ) : update_option( $key, $value );
		}

		foreach ( $this->orders as $order ) {
			$order->delete( true );
		}

		wp_set_current_user( 0 );
		wp_delete_user( $this->customer );
	}

	public function test_the_greeting_prints_on_the_account_dashboard(): void {
		$detail = new FeatureDetail();
		$detail->register_hooks();

		self::assertSame( 10, has_action( 'woocommerce_account_dashboard', array( $detail, 'print_greeting' ) ) );
		self::assertSame( 10, has_action( 'wp_enqueue_scripts', array( $detail, 'enqueue_style' ) ) );
		remove_action( 'woocommerce_account_dashboard', array( $detail, 'print_greeting' ) );
		remove_action( 'wp_enqueue_scripts', array( $detail, 'enqueue_style' ) );
	}

	public function test_the_greeting_style_loads_on_the_account_dashboard_only(): void {
		( new FeatureDetail() )->enqueue_style();
		self::assertFalse( wp_style_is( 'internal-comments-greeting' ) );

		add_filter( 'woocommerce_is_account_page', '__return_true' );
		try {
			( new FeatureDetail() )->enqueue_style();
		} finally {
			remove_filter( 'woocommerce_is_account_page', '__return_true' );
		}

		self::assertTrue( wp_style_is( 'internal-comments-greeting' ) );
		self::assertSame( plugins_url( 'assets/build/greeting.css', DWS_IC_FILE ), wp_styles()->registered['internal-comments-greeting']->src );
		wp_dequeue_style( 'internal-comments-greeting' );
		wp_deregister_style( 'internal-comments-greeting' );
	}

	public function test_a_customer_whose_orders_reach_the_set_number_is_greeted_as_text(): void {
		update_option( DWS_IC_OPTION_GREETING, 'Hi & <b>welcome</b>' );
		update_option( DWS_IC_OPTION_GREETING_ORDERS, '2' );
		$this->place_orders( 2 );

		$notice = \Dom\HTMLDocument::createFromString( '<!DOCTYPE html><html><body>' . $this->print() . '</body></html>', \LIBXML_NOERROR )->querySelector( '[role="status"]' );

		self::assertNotNull( $notice );
		self::assertSame( 'greeting', $notice->getAttribute( 'data-internal-comments' ) );
		self::assertSame( 'Hi & <b>welcome</b>', \trim( (string) $notice->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
	}

	public function test_a_customer_below_the_set_number_is_not_greeted(): void {
		update_option( DWS_IC_OPTION_GREETING_ORDERS, '2' );
		$this->place_orders( 1 );

		self::assertSame( '', $this->print() );
	}

	public function test_an_unsaved_greeting_prints_its_translated_default(): void {
		delete_option( DWS_IC_OPTION_GREETING );
		delete_option( DWS_IC_OPTION_GREETING_ORDERS );
		$this->place_orders( 1 );

		self::assertStringContainsString( esc_html( dws_ic_get_option_text( DWS_IC_OPTION_GREETING ) ), $this->print() );
	}

	public function test_an_empty_greeting_prints_its_translated_default(): void {
		update_option( DWS_IC_OPTION_GREETING, '' );
		$this->place_orders( 1 );

		self::assertStringContainsString( esc_html( dws_ic_get_option_text( DWS_IC_OPTION_GREETING ) ), $this->print() );
	}

	protected function place_orders( int $count ): void {
		for ( $placed = 0; $placed < $count; $placed++ ) {
			$this->orders[] = wc_create_order( array( 'customer_id' => $this->customer ) );
		}
	}

	protected function print(): string {
		\ob_start();
		( new FeatureDetail() )->print_greeting();

		return (string) \ob_get_clean();
	}
}
