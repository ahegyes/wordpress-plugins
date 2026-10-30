<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration\Integration;

use DeepWebSolutions\LinkedOrders\Integration\SequentialOrderNumbersPro;
use DeepWebSolutions\LinkedOrders\Tests\Integration\OrderStorage;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class SequentialOrderNumbersProTest extends TestCase {
	use OrderStorage;

	public function test_the_integration_loads_only_beside_the_plugin(): void {
		self::assertFalse( SequentialOrderNumbersPro::should_load() );
	}

	// The stand-in defines a global function, which only a separate process can drop.
	#[RunInSeparateProcess]
	public function test_a_linked_child_is_numbered_before_it_is_saved(): void {
		require_once \dirname( __DIR__, 2 ) . '/Fixtures/sequential-order-numbers-pro.php';
		self::assertTrue( SequentialOrderNumbersPro::should_load() );
		( new SequentialOrderNumbersPro() )->register_hooks();
		wp_set_current_user( 1 );

		$id = dws_lowc_create_linked_child( $this->create_order()->get_id() );

		self::assertIsInt( $id );
		$this->orders[] = $id;
		self::assertSame( array( $id ), \array_map( static fn ( \WC_Order $order ): int => $order->get_id(), wc_seq_order_number_pro()->numbered ) );
		self::assertSame( 'SOS-' . $id, wc_get_order( $id )?->get_meta( '_order_number' ) );
	}
}
