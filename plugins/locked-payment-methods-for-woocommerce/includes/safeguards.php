<?php declare( strict_types=1 );
/**
 * Holds the plugin's safeguards, which load before its floor gate, so they hold on a runtime below its floors and when the rest of the build fails to load.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LockedPaymentMethods
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'woocommerce_available_payment_gateways', 'dws_lpmwc_remove_locked_payment_methods', 999 );

/**
 * Returns whether a safeguard's filtered value relaxes it, which takes an explicit false once the plugin has finished booting.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   mixed $filtered The value the safeguard's filter returned for true.
 *
 * @return  bool
 */
function dws_lpmwc_is_relaxed( $filtered ): bool {
	return 0 < did_action( 'dws_lpmwc/boot/succeeded' ) && false === $filtered;
}

/**
 * Returns the ids of the locked payment methods: the plugin's list, or v1's while the plugin has stored none, because the migration that copies v1's list runs only once the plugin boots.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  string[]
 */
function dws_lpmwc_get_locked_payment_method_ids(): array {
	$stored = get_option( 'dws_lpmwc_locked_payment_methods' );
	$ids    = dws_lpmwc_add_locked_ids( array(), false === $stored ? get_option( 'dws-wc-lpm_general_locked-payment-methods' ) : $stored );

	return dws_lpmwc_add_locked_ids( $ids, dws_lpmwc_apply_v1_setting_filter( $ids, 'general', 'locked-payment-methods', 'dws_lpmwc/payment_method/locked_ids' ) );
}

/**
 * Returns a setting's value after v1's value filter for its settings group, which v1 applied on every read of a setting through that group.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   mixed  $value       The setting's value.
 * @param   string $group       v1's id of the setting's group.
 * @param   string $field_id    v1's id of the setting.
 * @param   string $replacement The hook that replaces v1's filter.
 *
 * @return  mixed
 */
function dws_lpmwc_apply_v1_setting_filter( $value, string $group, string $field_id, string $replacement ) {
	/**
	 * Filters a setting's value as v1's settings group read it.
	 *
	 * @since       1.0.0
	 * @version     2.0.0
	 * @deprecated  2.0.0 Use the hook the deprecation notice names.
	 *
	 * @param       mixed  $value    The setting's value.
	 * @param       string $field_id v1's id of the setting.
	 */
	return apply_filters_deprecated( "locked_payment_methods_for_woocommerce/{$group}_settings/get_validated_option_value", array( $value, $field_id ), '2.0.0', $replacement );
}

/**
 * Returns the ids with the strings of a filter's value added, so a filter locks more payment methods but never unlocks one.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string[] $ids      The ids before the filter.
 * @param   mixed    $filtered The value the filter returned.
 *
 * @return  string[]
 */
function dws_lpmwc_add_locked_ids( array $ids, $filtered ): array {
	return is_array( $filtered ) ? array_values( array_unique( array_merge( $ids, array_filter( $filtered, 'is_string' ) ) ) ) : $ids;
}

/**
 * Returns whether a payment method stays locked for a user and an order, which only an explicit false from the plugin's filter relaxes, once the plugin has booted.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   bool   $lock       Whether the payment method is locked before the plugin's filter runs.
 * @param   string $gateway_id The payment method's id.
 * @param   int    $user_id    The paying user, or 0 for a guest.
 * @param   int    $order_id   The order being paid, or 0 off the order-pay page.
 *
 * @return  bool
 */
function dws_lpmwc_should_lock( bool $lock, string $gateway_id, int $user_id, int $order_id ): bool {
	/**
	 * Filters whether a payment method stays locked for a user and an order; an explicit false unlocks it once the plugin has booted.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   bool   $lock       Whether the payment method stays locked.
	 * @param   string $gateway_id The payment method's id.
	 * @param   int    $user_id    The paying user, or 0 for a guest.
	 * @param   int    $order_id   The order being paid, or 0 off the order-pay page.
	 */
	return ! dws_lpmwc_is_relaxed( apply_filters( 'dws_lpmwc/payment_method/should_lock', $lock, $gateway_id, $user_id, $order_id ) ); // @phpstan-ignore WPCompat.parameterNotAvailable.applyfilters.args (WordPress 6.0 only formalized the arguments that older versions already passed on.)
}

/**
 * Returns the order the order-pay page pays, or 0 elsewhere or without that order's key, which WooCommerce requires before it takes the payment, so a request naming another order's id gains none of its grants.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @global  WP $wp WordPress's request.
 *
 * @return  int
 */
function dws_lpmwc_get_paid_order_id(): int {
	global $wp;

	if ( ! function_exists( 'is_checkout_pay_page' ) || ! is_checkout_pay_page() ) {
		return 0;
	}

	$order = wc_get_order( $wp->query_vars['order-pay'] ?? 0 );
	$key   = isset( $_GET['key'] ) && is_string( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The order key authorizes paying the order, as on WooCommerce's order-pay page.

	return $order instanceof WC_Order && '' !== $key && hash_equals( $order->get_order_key(), $key ) ? $order->get_id() : 0;
}

/**
 * Removes the locked payment methods from the payment gateways offered, keeping each one the plugin relaxes for the current user and the order being paid.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   array<string, mixed> $gateways The payment gateways offered, by id.
 *
 * @return  array<string, mixed>
 */
function dws_lpmwc_remove_locked_payment_methods( array $gateways ): array {
	$ids = dws_lpmwc_get_locked_payment_method_ids();

	/**
	 * Filters the locked payment methods' ids.
	 *
	 * @since       1.0.0
	 * @version     2.0.0
	 * @deprecated  2.0.0 Use dws_lpmwc/payment_method/locked_ids.
	 *
	 * @param       string[]             $ids      The locked payment methods' ids.
	 * @param       array<string, mixed> $gateways The payment gateways offered, by id.
	 */
	$ids = dws_lpmwc_add_locked_ids( $ids, apply_filters_deprecated( 'locked_payment_methods_for_woocommerce/lock_manager/locked_payment_methods', array( $ids, $gateways ), '2.0.0', 'dws_lpmwc/payment_method/locked_ids' ) );

	/**
	 * Filters the locked payment methods' ids, to which a callback adds the ids it locks; an id it leaves out stays locked.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string[]             $ids      The locked payment methods' ids.
	 * @param   array<string, mixed> $gateways The payment gateways offered, by id.
	 */
	$ids = dws_lpmwc_add_locked_ids( $ids, apply_filters( 'dws_lpmwc/payment_method/locked_ids', $ids, $gateways ) ); // @phpstan-ignore WPCompat.parameterNotAvailable.applyfilters.args (WordPress 6.0 only formalized the arguments that older versions already passed on.)

	$user_id  = get_current_user_id();
	$order_id = dws_lpmwc_get_paid_order_id();
	foreach ( $ids as $id ) {
		if ( ! isset( $gateways[ $id ] ) ) {
			continue;
		}

		/**
		 * Filters whether a payment method stays locked.
		 *
		 * @since       1.0.0
		 * @version     2.0.0
		 * @deprecated  2.0.0 Use dws_lpmwc/payment_method/should_lock.
		 *
		 * @param       bool   $is_locked Whether the payment method stays locked.
		 * @param       string $id        The payment method's id.
		 */
		$lock = apply_filters_deprecated( 'locked_payment_methods_for_woocommerce/lock_manager/is_locked_payment_method', array( true, $id ), '2.0.0', 'dws_lpmwc/payment_method/should_lock' );

		/**
		 * Filters whether a payment method stays locked, under its id lowercased and stripped of control and non-ASCII bytes, as v1 named it.
		 *
		 * @since       1.0.0
		 * @version     2.0.0
		 * @deprecated  2.0.0 Use dws_lpmwc/payment_method/should_lock.
		 *
		 * @param       bool $is_locked Whether the payment method stays locked.
		 */
		$lock = apply_filters_deprecated( 'locked_payment_methods_for_woocommerce/lock_manager/is_locked_payment_method/' . strtolower( (string) filter_var( $id, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ) ), array( $lock ), '2.0.0', 'dws_lpmwc/payment_method/should_lock' );

		if ( dws_lpmwc_should_lock( false !== $lock, $id, $user_id, $order_id ) ) {
			unset( $gateways[ $id ] );
		}
	}

	return $gateways;
}
