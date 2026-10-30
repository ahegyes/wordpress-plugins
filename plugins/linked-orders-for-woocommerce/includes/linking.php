<?php declare( strict_types=1 );
/**
 * Keeps v1's linking API, which links orders into trees and walks them.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LinkedOrders
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns an order's node, or null when the order is missing or of an unsupported type.
 *
 * @since   1.0.0
 * @version 2.0.0
 *
 * @param   WC_Order|WP_Post|int|string|false|null $order The order or its id.
 *
 * @return  DWS_Order_Node|null
 */
function dws_lowc_get_order_node( $order ): ?DWS_Order_Node {
	try {
		return new DWS_Order_Node( $order );
	} catch ( InvalidArgumentException $exception ) {
		return null;
	}
}

/**
 * Returns whether an order is at the top of its tree, or null when it has no node.
 *
 * @since   1.0.0
 * @version 2.0.0
 *
 * @param   WC_Order|WP_Post|int|string|false|null $order The order or its id.
 *
 * @return  bool|null
 */
function dws_lowc_is_root_order( $order ): ?bool {
	$node = dws_lowc_get_order_node( $order );

	return null === $node ? null : 0 === $node->get_depth();
}

/**
 * Links one order as the child of another, returning false when the child already has a parent or is the parent or one of its ancestors, and null when either order has no node.
 *
 * @since   1.0.0
 * @version 2.0.0
 *
 * @param   int $parent_id The id of the parent order.
 * @param   int $child_id  The id of the child order.
 *
 * @return  bool|null
 */
function dws_lowc_link_orders( int $parent_id, int $child_id ): ?bool {
	$parent = dws_lowc_get_order_node( $parent_id );
	$child  = dws_lowc_get_order_node( $child_id );
	if ( null === $parent || null === $child ) {
		return null;
	}

	if ( ! $parent->add_child( $child ) ) {
		return false;
	}

	$parent->save();
	$child->save();

	return true;
}

/**
 * Returns the node at the top of an order's tree, which is the order's own for a root or for a child whose parent was deleted, or null when the order has no node.
 *
 * @since   1.0.0
 * @version 2.0.0
 *
 * @param   WC_Order|WP_Post|int|string|false|null $order The order or its id.
 *
 * @return  DWS_Order_Node|null
 */
function dws_lowc_get_root_order( $order ): ?DWS_Order_Node {
	$node = dws_lowc_get_order_node( $order );
	while ( null !== $node && $node->has_parent() ) {
		$node = $node->get_parent();
	}

	return $node;
}

/**
 * Returns the ids of an order and its descendants, each child before the next with its own descendants, or null when the order has no node.
 *
 * @since   1.0.0
 * @version 2.0.0
 *
 * @param   WC_Order|WP_Post|int|string|false|null $order The order or its id.
 *
 * @return  int[]|null
 */
function dws_lowc_get_orders_tree( $order ): ?array {
	$node = dws_lowc_get_order_node( $order );
	if ( null === $node ) {
		return null;
	}

	$tree = array( $node->get_order()->get_id() );
	foreach ( $node->get_children() as $child ) {
		$tree = array_merge( $tree, dws_lowc_get_orders_tree( $child->get_order() ) ?? array() );
	}

	return $tree;
}
