<?php declare( strict_types=1 );
/**
 * Tells which payment methods are locked, and whether one is locked for a user and an order.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LockedPaymentMethods
 */

use DeepWebSolutions\LockedPaymentMethods\Safeguards;

defined( 'ABSPATH' ) || exit;

/**
 * Returns the ids of the locked payment methods, the list the lock check reads.
 *
 * The list is the plugin's, or v1's while the plugin has stored none, with the ids v1's value filter and locked_payment_methods_for_woocommerce/payment_method/locked_ids add. At checkout, v1's deprecated locked_payment_methods_for_woocommerce/lock_manager/locked_payment_methods filter can lock more of the offered methods.
 *
 * @api
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  string[]
 */
function dws_lpmwc_get_locked_payment_method_ids(): array {
	return Safeguards::get_locked_payment_method_ids();
}

/**
 * Returns whether a payment method is locked for a user and an order: whether the locked list holds it and locked_payment_methods_for_woocommerce/payment_method/should_lock does not relax it.
 *
 * @api
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $gateway_id The payment method's id.
 * @param   int    $user_id    The user's id, or 0 for a guest.
 * @param   int    $order_id   Optional. The order being paid, or 0 for none. Default 0.
 *
 * @return  bool
 */
function dws_lpmwc_is_payment_method_locked( string $gateway_id, int $user_id, int $order_id = 0 ): bool {
	return in_array( $gateway_id, Safeguards::get_locked_payment_method_ids(), true ) && Safeguards::should_lock( true, $gateway_id, $user_id, $order_id );
}
