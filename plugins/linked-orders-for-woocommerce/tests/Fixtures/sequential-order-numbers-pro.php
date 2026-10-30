<?php declare( strict_types=1 );
/**
 * Stands in for WooCommerce Sequential Order Numbers Pro's accessor, recording the orders it numbers.
 *
 * @package DeepWebSolutions\LinkedOrders
 */

/**
 * Returns the stand-in plugin, which records each order it is asked to number.
 *
 * @return  object
 */
function wc_seq_order_number_pro(): object {
	static $plugin = null;
	$plugin      ??= new class() {
		public array $numbered = array();

		public function set_sequential_order_number( WC_Order $order ): void {
			$this->numbered[] = $order;
		}
	};

	return $plugin;
}
