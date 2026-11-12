<?php declare( strict_types=1 );

// Stands in for WooCommerce Sequential Order Numbers Pro's main class, recording the orders it numbers; PHPStan reads it as the plugin's API.
final class WC_Seq_Order_Number_Pro {
	/**
	 * @var     list<WC_Order>
	 */
	public array $numbered = array();

	// Numbers the order on the object, as the plugin does, for its next save to store.
	public function set_sequential_order_number( WC_Order $order ): void {
		$this->numbered[] = $order;
		$order->update_meta_data( '_order_number', 'SOS-' . $order->get_id() );
	}
}
