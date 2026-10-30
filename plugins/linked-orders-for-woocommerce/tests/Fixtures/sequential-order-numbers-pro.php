<?php declare( strict_types=1 );
/**
 * Stands in for WooCommerce Sequential Order Numbers Pro's accessor; PHPStan reads it as the plugin's API.
 *
 * @package DeepWebSolutions\LinkedOrders
 */

require_once __DIR__ . '/class-wc-seq-order-number-pro.php';

/**
 * Returns the stand-in plugin.
 *
 * @return WC_Seq_Order_Number_Pro
 */
function wc_seq_order_number_pro(): WC_Seq_Order_Number_Pro {
	static $plugin = null;
	$plugin      ??= new WC_Seq_Order_Number_Pro();

	return $plugin;
}
