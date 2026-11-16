<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration;

use DeepWebSolutions\LinkedOrders\Tests\Support\OrderStorageTrait;
use DeepWebSolutions\LinkedOrders\Tests\Support\RunsRequestsTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class OrderTypesTest extends TestCase {
	use OrderStorageTrait;
	use RunsRequestsTrait;

	public function test_the_order_functions_answer_nothing_until_the_plugin_has_booted(): void {
		global $wp_actions;

		$parent = $this->create_order();
		$child  = $this->create_order();
		self::assertNotNull( dws_lowc_get_order_node( $parent->get_id() ) );
		self::assertGreaterThan( 0, did_action( 'linked_orders_for_woocommerce/boot/succeeded' ) );
		$fired = $wp_actions['linked_orders_for_woocommerce/boot/succeeded'];
		unset( $wp_actions['linked_orders_for_woocommerce/boot/succeeded'] );

		try {
			self::assertNull( dws_lowc_get_order_node( $parent->get_id() ) );
			self::assertNull( dws_lowc_is_root_order( $parent->get_id() ) );
			self::assertNull( dws_lowc_link_orders( $parent->get_id(), $child->get_id() ) );
			self::assertNull( dws_lowc_unlink_order( $child->get_id() ) );
			self::assertNull( dws_lowc_get_root_order( $parent->get_id() ) );
			self::assertNull( dws_lowc_get_orders_tree( $parent->get_id() ) );
			self::assertNull( dws_lowc_is_supported_order( $parent->get_id() ) );
			self::assertSame( array(), dws_lowc_get_valid_statuses_for_new_child() );
			self::assertNull( dws_lowc_create_linked_child( $parent->get_id() ) );
		} finally {
			$wp_actions['linked_orders_for_woocommerce/boot/succeeded'] = $fired;
		}
	}

	public function test_with_woocommerce_inactive_the_order_functions_answer_nothing(): void {
		$request = self::request( 'echo wp_json_encode( array( dws_lowc_get_order_node( 1 ), dws_lowc_is_root_order( 1 ), dws_lowc_link_orders( 1, 2 ), dws_lowc_unlink_order( 2 ), dws_lowc_get_root_order( 1 ), dws_lowc_get_orders_tree( 1 ), dws_lowc_is_supported_order( 1 ), dws_lowc_get_valid_statuses_for_new_child(), dws_lowc_create_linked_child( 1 ) ) );', '--skip-plugins=woocommerce' );

		self::assertSame( 0, $request['status'], $request['output'] );
		self::assertSame( '[null,null,null,null,null,null,null,[],null]', $request['last'], $request['output'] );
	}
}
