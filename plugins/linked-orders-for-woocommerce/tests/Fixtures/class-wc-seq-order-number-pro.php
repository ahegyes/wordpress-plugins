<?php declare( strict_types=1 );
/**
 * Stands in for WooCommerce Sequential Order Numbers Pro's main class, recording the orders it numbers; PHPStan reads it as the plugin's API.
 *
 * @package DeepWebSolutions\LinkedOrders
 */

/**
 * Stands in for the plugin's main class.
 */
final class WC_Seq_Order_Number_Pro {
	/**
	 * The orders the stand-in was asked to number.
	 *
	 * @var list<WC_Order>
	 */
	public array $numbered = array();

	/**
	 * Records an order the plugin would number, and numbers it as the plugin does, on the object, for its next save to store.
	 *
	 * @param WC_Order $order The order.
	 *
	 * @return void
	 */
	public function set_sequential_order_number( WC_Order $order ): void {
		$this->numbered[] = $order;
		$order->update_meta_data( '_order_number', 'SOS-' . $order->get_id() );
	}
}
