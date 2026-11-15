<?php declare( strict_types=1 );
/**
 * Tells whether a payment method is locked for a user and an order.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LockedPaymentMethods
 */

use DeepWebSolutions\LockedPaymentMethods\Safeguards;

defined( 'ABSPATH' ) || exit;

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
