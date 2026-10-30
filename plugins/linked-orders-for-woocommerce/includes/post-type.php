<?php declare( strict_types=1 );
/**
 * Keeps v1's order-type API: which order types can be linked, which statuses allow a new child, and the creation of a linked child.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LinkedOrders
 */

use Automattic\WooCommerce\Utilities\OrderUtil;

defined( 'ABSPATH' ) || exit;

/**
 * Returns the order types whose orders can be linked.
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
	 * @since       1.0.0
	 * @version     2.0.0
	 * @deprecated  2.0.0 Use dws_lowc/post_type/supported_order_types.
	 *
	 * @param       string[] $types The order types.
	 */
	$types = apply_filters_deprecated( 'linked_orders_for_woocommerce/post_type/supported_order_types', array( array( 'shop_order' ) ), '2.0.0', 'dws_lowc/post_type/supported_order_types' );

	/**
	 * Filters the order types whose orders can be linked.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string[] $types The order types.
	 */
	return apply_filters( 'dws_lowc/post_type/supported_order_types', $types );
}

/**
 * Returns an order type's singular name, or its plural name where the type registered no singular one.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   WP_Post_Type $post_type The post type object of the order type.
 *
 * @return  string
 */
function dws_lowc_get_singular_name( WP_Post_Type $post_type ): string {
	$name = $post_type->labels->singular_name ?? null;

	return is_string( $name ) ? $name : $post_type->label;
}

/**
 * Returns whether an order is of a type that can be linked, or null when it is not an order.
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

	$order = wc_get_order( $order );

	return $order instanceof WC_Order ? in_array( $order->get_type(), dws_lowc_get_supported_order_types(), true ) : null;
}

/**
 * Returns the statuses, without their 'wc-' prefix, that an order must have for a child to be created from it.
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
	$statuses   = array_map( array( OrderUtil::class, 'remove_status_prefix' ), array_keys( wc_get_order_statuses() ) );
	$order_type = null === $order ? $order_type : $order->get_type();

	/**
	 * Filters the statuses an order must have for a child to be created from it.
	 *
	 * @since       1.0.0
	 * @version     2.0.0
	 * @deprecated  2.0.0 Use dws_lowc/post_type/valid_statuses_for_new_child.
	 *
	 * @param       string[]      $statuses   The statuses, without their 'wc-' prefix.
	 * @param       string        $order_type The order type.
	 * @param       WC_Order|null $order      The order the child would be created from.
	 */
	$statuses = apply_filters_deprecated( 'linked_orders_for_woocommerce/post_type/valid_statuses_for_new_child', array( $statuses, $order_type, $order ), '2.0.0', 'dws_lowc/post_type/valid_statuses_for_new_child' );

	/**
	 * Filters the statuses an order must have for a child to be created from it.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string[]      $statuses   The statuses, without their 'wc-' prefix.
	 * @param   string        $order_type The order type.
	 * @param   WC_Order|null $order      The order the child would be created from.
	 */
	return apply_filters( 'dws_lowc/post_type/valid_statuses_for_new_child', $statuses, $order_type, $order );
}

/**
 * Creates an order linked as the child of a supported order, with the parent's customer and addresses, and returns its id.
 *
 * Returns null when the parent is missing or unsupported, or when the creator is not callable or creates no supported order, and the creator's error when it fails.
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
	$parent = wc_get_order( $parent_id );
	if ( ! $parent instanceof WC_Order || true !== dws_lowc_is_supported_order( $parent ) ) {
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
	 * @deprecated  2.0.0 Use dws_lowc/linked_order/creation_args.
	 *
	 * @param       array<string, mixed> $args   The creator's arguments.
	 * @param       WC_Order             $parent The parent order.
	 */
	$args = apply_filters_deprecated( 'linked_orders_for_woocommerce/create_linked_order_args', array( $args, $parent ), '2.0.0', 'dws_lowc/linked_order/creation_args' );

	/**
	 * Filters the arguments a linked child is created with, which name the creator under 'create_function'.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, mixed> $args   The creator's arguments.
	 * @param   WC_Order             $parent The parent order.
	 */
	$args = apply_filters( 'dws_lowc/linked_order/creation_args', $args, $parent );
	if ( ! is_callable( $args['create_function'] ?? null ) ) {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'The linked child\'s create_function argument is not callable.', 'linked-orders-for-woocommerce' ), '1.0.0' );
		return null;
	}

	$child = call_user_func( $args['create_function'], $args );
	if ( is_wp_error( $child ) ) {
		return $child;
	}

	if ( ! $child instanceof WC_Order || true !== dws_lowc_is_supported_order( $child ) ) {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'The linked child\'s create_function returned no order of a supported type.', 'linked-orders-for-woocommerce' ), '1.0.0' );
		return null;
	}

	$child->set_address( $parent->get_address( 'billing' ), 'billing' );
	$child->set_address( $parent->get_address( 'shipping' ), 'shipping' );
	$child->add_meta_data( '_dws_lo_created_by', (string) get_current_user_id() );

	/**
	 * Fires when a linked child has been created, before it is saved and linked.
	 *
	 * @since       1.0.0
	 * @version     2.0.0
	 * @deprecated  2.0.0 Use dws_lowc/linked_order/created.
	 *
	 * @param       WC_Order $child  The child order.
	 * @param       WC_Order $parent The parent order.
	 */
	do_action_deprecated( 'linked_orders_for_woocommerce/created_linked_order', array( $child, $parent ), '2.0.0', 'dws_lowc/linked_order/created' );

	/**
	 * Fires when a linked child has been created, before it is saved and linked.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   WC_Order $child  The child order.
	 * @param   WC_Order $parent The parent order.
	 */
	do_action( 'dws_lowc/linked_order/created', $child, $parent );

	$child->save();
	dws_lowc_link_orders( $parent_id, $child->get_id() );

	return $child->get_id();
}
