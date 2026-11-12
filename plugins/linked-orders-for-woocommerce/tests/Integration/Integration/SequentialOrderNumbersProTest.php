<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration\Integration;

use DeepWebSolutions\LinkedOrders\Integration\SequentialOrderNumbersPro;
use DeepWebSolutions\LinkedOrders\Tests\Support\OrderStorageTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[CoversClass( SequentialOrderNumbersPro::class )]
final class SequentialOrderNumbersProTest extends TestCase {
	use OrderStorageTrait;

	public function test_the_integration_loads_only_beside_the_plugin(): void {
		self::assertFalse( SequentialOrderNumbersPro::should_load() );
	}

	// The stand-in defines a global function, which only a separate process can drop.
	#[RunInSeparateProcess]
	public function test_a_linked_child_is_numbered_before_it_is_saved(): void {
		require_once \dirname( __DIR__, 2 ) . '/Fixtures/sequential-order-numbers-pro.php';
		self::assertTrue( SequentialOrderNumbersPro::should_load() );
		new SequentialOrderNumbersPro()->register_hooks();
		wp_set_current_user( 1 );

		$id = dws_lowc_create_linked_child( $this->create_order()->get_id() );

		self::assertIsInt( $id );
		$this->orders[] = $id;
		self::assertSame( array( $id ), \array_map( static fn ( \WC_Order $order ): int => $order->get_id(), wc_seq_order_number_pro()->numbered ) );
		self::assertSame( 'SOS-' . $id, wc_get_order( $id )?->get_meta( '_order_number' ) );
	}

	// The child is numbered on the v2 action alone, so a listener of the deprecated v1 action sees it unnumbered, while a later listener of the v2 action sees the number and can replace it.
	#[RunInSeparateProcess]
	public function test_a_listener_after_priority_10_on_the_v2_action_sees_the_number_and_can_replace_it(): void {
		require_once \dirname( __DIR__, 2 ) . '/Fixtures/sequential-order-numbers-pro.php';
		new SequentialOrderNumbersPro()->register_hooks();
		wp_set_current_user( 1 );
		add_filter( 'deprecated_hook_trigger_error', '__return_false' );
		$seen = array();
		add_action(
			'linked_orders_for_woocommerce/created_linked_order',
			static function ( \WC_Order $child ) use ( &$seen ): void {
				$seen[] = array( 'v1', $child->get_meta( '_order_number' ) );
			},
			20
		);
		add_action(
			'dws_lowc/linked_order/created',
			static function ( \WC_Order $child ) use ( &$seen ): void {
				$seen[] = array( 'v2', $child->get_meta( '_order_number' ) );
				$child->update_meta_data( '_order_number', 'CUSTOM' );
			},
			20
		);

		$id = dws_lowc_create_linked_child( $this->create_order()->get_id() );

		self::assertIsInt( $id );
		$this->orders[] = $id;
		self::assertSame( array( array( 'v1', '' ), array( 'v2', 'SOS-' . $id ) ), $seen );
		self::assertSame( 'CUSTOM', wc_get_order( $id )?->get_meta( '_order_number' ) );
		self::assertCount( 1, wc_seq_order_number_pro()->numbered );
	}
}
