<?php declare( strict_types=1 );

// Stands in for another plugin's order class, whose type WooCommerce stores with each of its orders.
final class DWS_LOWC_Test_Order extends WC_Order {
	#[\Override]
	public function get_type(): string {
		return 'dws_lowc_test_order';
	}
}
