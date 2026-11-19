<?php declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Unlocks locked payment methods by one rule, such as the customer's roles or the order being paid.
 *
 * @api
 *
 * @since   2.0.0
 * @version 2.0.0
 */
interface DWS_LPMWC_Unlock_Strategy {
	/**
	 * Returns the strategy's id, unique among the registered strategies.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  string
	 */
	public function get_id(): string;

	/**
	 * Returns the strategy's name, translated, which callers read from init on.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  string
	 */
	public function get_label(): string;

	/**
	 * Returns whether the strategy unlocks a payment method for a user and an order.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $gateway_id The payment method's id.
	 * @param   int    $user_id    The paying user, or 0 for a guest.
	 * @param   int    $order_id   The order being paid, or 0 off its payment page.
	 *
	 * @return  bool
	 */
	public function unlocks( string $gateway_id, int $user_id, int $order_id ): bool;
}
