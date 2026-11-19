<?php declare( strict_types=1 );
/**
 * Keeps v1's order-type API: which order types can be linked, which statuses allow a new child, and the creation of a linked child.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LinkedOrders
 */

use DeepWebSolutions\LinkedOrders\Linking;
use DeepWebSolutions\LinkedOrders\OrderTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Returns the order types whose orders can be linked.
 *
 * @api
 *
 * @since   1.0.0
 * @version 2.0.0
 *
 * @return  string[]
 */
function dws_lowc_get_supported_order_types(): array {
	/**
	 * Filters the order types whose orders can be linked.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
	 *
	 * @param   string[] $types The order types.
	 */
	return apply_filters( 'linked_orders_for_woocommerce/post_type/supported_order_types', array( 'shop_order' ) );
}

/**
 * Returns whether an order is of a type that can be linked, or null when it is not an order or the plugin has not booted.
 *
 * @api
 *
 * @since   1.0.0
 * @version 2.0.0
 *
 * @param   WC_Order|WP_Post|int|string|false|null $order The order or its id.
 *
 * @return  bool|null
 */
function dws_lowc_is_supported_order( $order ): ?bool {
	if ( false === $order ) { // wc_get_order() reads the global post for false.
		return null;
	}

	$order = OrderTypes::get_order( $order );
	return $order instanceof WC_Order ? in_array( $order->get_type(), dws_lowc_get_supported_order_types(), true ) : null;
}

/**
 * Returns the statuses, without their 'wc-' prefix, that an order must have for a child to be created from it.
 *
 * Until the plugin has booted, the filter starts from no statuses.
 *
 * @api
 *
 * @since   1.0.0
 * @version 2.0.0
 *
 * @param   string        $order_type The order type, which the order's type replaces when an order is given.
 * @param   WC_Order|null $order      The order the child would be created from.
 *
 * @return  string[]
 */
function dws_lowc_get_valid_statuses_for_new_child( string $order_type = 'shop_order', ?WC_Order $order = null ): array {
	$statuses   = OrderTypes::get_statuses();
	$order_type = is_null( $order ) ? $order_type : $order->get_type();

	/**
	 * Filters the statuses an order must have for a child to be created from it.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
	 *
	 * @param   string[]      $statuses   The statuses, without their 'wc-' prefix.
	 * @param   string        $order_type The order type.
	 * @param   WC_Order|null $order      The order the child would be created from.
	 */
	return apply_filters( 'linked_orders_for_woocommerce/post_type/valid_statuses_for_new_child', $statuses, $order_type, $order );
}

/**
 * Creates an order linked as the child of a supported order, with the parent's customer and addresses, and returns its id.
 *
 * The creator must create a new order, because a refused link deletes what it returned, so the function refuses the parent and any order with a parent or children before it changes them. An existing order without links that a creator returns is still deleted when the link gives up waiting for another request's. Returns null when the parent is missing or unsupported or the plugin has not booted, or when the creator is not callable or returns no such order, the creator's error when it fails, and an error when the link is refused, after deleting the child.
 *
 * @api
 *
 * @since   1.0.0
 * @version 2.0.0
 *
 * @param   int                  $parent_id The id of the parent order.
 * @param   array<string, mixed> $args      The creator's arguments, which also name the creator under 'create_function'.
 *
 * @return  int|WP_Error|null
 */
function dws_lowc_create_linked_child( int $parent_id, array $args = array() ) {
	$parent = OrderTypes::get_order( $parent_id );
	if ( ! $parent instanceof WC_Order || ! in_array( $parent->get_type(), dws_lowc_get_supported_order_types(), true ) ) {
		return null;
	}

	$args = wp_parse_args(
		$args,
		array(
			'status'          => 'pending',
			'customer_id'     => $parent->get_customer_id(),
			'created_via'     => 'dws-linking',
			'create_function' => 'wc_create_order',
		)
	);

	/**
	 * Filters the arguments a linked child is created with, which name the creator under 'create_function'.
	 *
	 * @since       1.0.0
	 * @version     2.0.0
	 * @deprecated  2.0.0 Use linked_orders_for_woocommerce/linked_order/creation_args.
	 *
	 * @param       array<string, mixed> $args   The creator's arguments.
	 * @param       WC_Order             $parent The parent order.
	 */
	$args = apply_filters_deprecated( 'linked_orders_for_woocommerce/create_linked_order_args', array( $args, $parent ), '2.0.0', 'linked_orders_for_woocommerce/linked_order/creation_args' );

	/**
	 * Filters the arguments a linked child is created with, which name the creator under 'create_function'.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, mixed> $args   The creator's arguments.
	 * @param   WC_Order             $parent The parent order.
	 */
	$args = apply_filters( 'linked_orders_for_woocommerce/linked_order/creation_args', $args, $parent );
	if ( ! is_callable( $args['create_function'] ?? null ) ) {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'The linked child\'s create_function argument is not callable.', 'linked-orders-for-woocommerce' ), '1.0.0' );
		return null;
	}

	$child = call_user_func( $args['create_function'], $args );
	if ( is_wp_error( $child ) ) {
		return $child;
	}

	$node = $child instanceof WC_Order ? dws_lowc_get_order_node( $child ) : null;
	if ( ! $child instanceof WC_Order || is_null( $node ) || $parent_id === $child->get_id() || $node->has_parent() || $node->has_children() ) {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'The linked child\'s create_function returned no new order of a supported type.', 'linked-orders-for-woocommerce' ), '1.0.0' );
		return null;
	}

	$child->set_address( $parent->get_address( 'billing' ), 'billing' );
	$child->set_address( $parent->get_address( 'shipping' ), 'shipping' );
	$child->add_meta_data( Linking::META_CREATED_BY, (string) get_current_user_id() );

	/**
	 * Fires when a linked child has been created, before it is saved and linked.
	 *
	 * @since       1.0.0
	 * @version     2.0.0
	 * @deprecated  2.0.0 Use linked_orders_for_woocommerce/linked_order/created.
	 *
	 * @param       WC_Order $child  The child order.
	 * @param       WC_Order $parent The parent order.
	 */
	do_action_deprecated( 'linked_orders_for_woocommerce/created_linked_order', array( $child, $parent ), '2.0.0', 'linked_orders_for_woocommerce/linked_order/created' );

	/**
	 * Fires when a linked child has been created, before it is saved and linked.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   WC_Order $child  The child order.
	 * @param   WC_Order $parent The parent order.
	 */
	do_action( 'linked_orders_for_woocommerce/linked_order/created', $child, $parent );

	$child->save();
	if ( true !== dws_lowc_link_orders( $parent_id, $child->get_id() ) ) {
		$child->delete( true );
		return new WP_Error( 'dws_lowc_link_refused', __( 'Another link was being saved at the same time. Try again.', 'linked-orders-for-woocommerce' ) );
	}

	return $child->get_id();
}
