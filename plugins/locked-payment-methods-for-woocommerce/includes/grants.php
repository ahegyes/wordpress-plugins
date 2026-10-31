<?php declare( strict_types=1 );
/**
 * Holds the keys under which payment methods are unlocked for a subject, and reads each as a list of payment method ids.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LockedPaymentMethods
 */

defined( 'ABSPATH' ) || exit;

/**
 * The user option, under each site's table prefix, that holds the ids of the payment methods unlocked for the user on that site.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_LPMWC_USER_OPTION_UNLOCKED_METHODS = 'dws_lpmwc_unlocked_payment_methods';

/**
 * Returns the current site's user meta key for the payment methods unlocked for a user.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @global  wpdb $wpdb WordPress's database access.
 *
 * @return  string
 */
function dws_lpmwc_get_user_grants_key(): string {
	global $wpdb;

	return $wpdb->get_blog_prefix() . DWS_LPMWC_USER_OPTION_UNLOCKED_METHODS;
}

/**
 * Returns the ids of the payment methods unlocked for a user on the current site, read from the site's own key, since get_user_option() falls back to a key every site shares.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   int $user_id The user's id.
 *
 * @return  string[]
 */
function dws_lpmwc_get_user_unlocked_payment_method_ids( int $user_id ): array {
	$ids = get_user_meta( $user_id, dws_lpmwc_get_user_grants_key(), true );

	return is_array( $ids ) ? array_values( array_filter( $ids, 'is_string' ) ) : array();
}

/**
 * The order meta that holds the ids of the payment methods unlocked for paying the order.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_LPMWC_ORDER_META_UNLOCKED_METHODS = '_dws_lpmwc_unlocked_payment_methods';

/**
 * Returns the ids of the payment methods unlocked for paying an order.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   int $order_id The order's id.
 *
 * @return  string[]
 */
function dws_lpmwc_get_order_unlocked_payment_method_ids( int $order_id ): array {
	$order = 0 === $order_id ? false : wc_get_order( $order_id );
	$ids   = $order instanceof WC_Order ? $order->get_meta( DWS_LPMWC_ORDER_META_UNLOCKED_METHODS ) : array();

	return is_array( $ids ) ? array_values( array_filter( $ids, 'is_string' ) ) : array();
}

/**
 * Returns a payment method's title, or its id for a payment method no longer registered.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $gateway_id The payment method's id.
 *
 * @return  string
 */
function dws_lpmwc_get_payment_method_title( string $gateway_id ): string {
	$gateway = WC()->payment_gateways()->payment_gateways()[ $gateway_id ] ?? null;

	return $gateway instanceof WC_Payment_Gateway ? $gateway->get_title() : $gateway_id;
}

/**
 * Returns the choices of a grants field by payment method id: the locked methods and the subject's other grants, since a save keeps only the choices a field offers, each marked when it is not locked or when another rule already unlocks it for the user.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string[] $granted The ids of the payment methods the subject unlocks.
 * @param   int      $user_id The user other rules are judged for, or 0 for none.
 *
 * @return  array<string, string>
 */
function dws_lpmwc_get_grant_choices( array $granted, int $user_id ): array {
	$locked  = dws_lpmwc_get_locked_payment_method_ids();
	$choices = array();
	foreach ( array_unique( array_merge( $locked, $granted ) ) as $id ) {
		$title = dws_lpmwc_get_payment_method_title( $id );
		if ( ! in_array( $id, $locked, true ) ) {
			/* translators: %s: payment method title. */
			$title = sprintf( __( '%s (not locked)', 'locked-payment-methods-for-woocommerce' ), $title );
		} elseif ( 0 !== $user_id && ! in_array( $id, $granted, true ) && ! dws_lpmwc_should_lock( true, $id, $user_id, 0 ) ) {
			/* translators: %s: payment method title. */
			$title = sprintf( __( '%s (unlocked by another rule)', 'locked-payment-methods-for-woocommerce' ), $title );
		}
		$choices[ $id ] = $title;
	}

	return $choices;
}

/**
 * The option that maps each Groups group's id to the ids of the payment methods unlocked for its members.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_LPMWC_OPTION_GROUPS_UNLOCKED_METHODS = 'dws_lpmwc_groups_unlocked_payment_methods';

/**
 * Returns the ids of the payment methods unlocked for each Groups group's members, by group id.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  array<int, string[]>
 */
function dws_lpmwc_get_groups_unlocked_payment_method_ids(): array {
	$stored = get_option( DWS_LPMWC_OPTION_GROUPS_UNLOCKED_METHODS, array() );
	$groups = array();
	foreach ( is_array( $stored ) ? $stored : array() as $group_id => $ids ) {
		if ( is_int( $group_id ) && is_array( $ids ) ) {
			$groups[ $group_id ] = array_values( array_filter( $ids, 'is_string' ) );
		}
	}

	return $groups;
}
