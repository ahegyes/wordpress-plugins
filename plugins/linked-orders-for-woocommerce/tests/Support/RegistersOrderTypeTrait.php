<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Support;

use PHPUnit\Framework\Attributes\After;

// Another plugin's order type, which v1 1.2.0 linked once its filter named the type, and which words its own add-child phrase, as a registrant whose creator makes children of the type does.
trait RegistersOrderTypeTrait {
	protected const string ORDER_TYPE = 'dws_lowc_test_order';

	protected array $meta_caps = array();

	protected function register_order_type(): void {
		require_once \dirname( __DIR__ ) . '/Fixtures/class-dws-lowc-test-order.php';
		$this->meta_caps = $GLOBALS['post_type_meta_caps'] ?? array();
		wc_register_order_type(
			self::ORDER_TYPE,
			array(
				'labels'          => array(
					'name'          => 'Test orders',
					'singular_name' => 'Test order',
					'linked_orders_for_woocommerce_add_new_child' => 'Add new child test order',
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'woocommerce',
				'capability_type' => 'shop_order',
				'map_meta_cap'    => true,
				'class_name'      => \DWS_LOWC_Test_Order::class,
			)
		);
		add_filter( 'dws_lowc/post_type/supported_order_types', array( $this, 'support_order_type' ) );
	}

	public function support_order_type( array $types ): array {
		return \array_merge( $types, array( self::ORDER_TYPE ) );
	}

	protected function create_typed_order(): \WC_Order {
		$order = new \DWS_LOWC_Test_Order();
		$order->set_status( 'pending' );
		$order->save();
		$this->orders[] = $order->get_id();
		return $order;
	}

	// The type goes after the rows' orders, which load through its class, and unregistering it drops the meta capabilities it shares with WooCommerce's orders from the one map WordPress keeps.
	#[After( priority: -1 )]
	protected function unregister_order_type(): void {
		remove_filter( 'dws_lowc/post_type/supported_order_types', array( $this, 'support_order_type' ) );
		if ( post_type_exists( self::ORDER_TYPE ) ) {
			unregister_post_type( self::ORDER_TYPE );
			$GLOBALS['post_type_meta_caps'] = $this->meta_caps;
		}
		unset( $GLOBALS['wc_order_types'][ self::ORDER_TYPE ] );
	}
}
