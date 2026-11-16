<?php declare( strict_types=1 );
/**
 * Keeps v1's linking API, which links orders into trees and walks them.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LinkedOrders
 */

use DeepWebSolutions\LinkedOrders\Linking;

defined( 'ABSPATH' ) || exit;

/**
 * Returns an order's node, or null when the value names no order of a supported type or the plugin has not booted.
 *
 * @api
 *
 * @since   1.0.0
 * @version 2.0.0
 *
 * @param   mixed $order The order, its post or its id.
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
 * Returns whether an order is at the top of its tree, or null when it has no node, as no order has until the plugin has booted.
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
function dws_lowc_is_root_order( $order ): ?bool {
	$node = dws_lowc_get_order_node( $order );
	return is_null( $node ) ? null : 0 === $node->get_depth();
}

/**
 * Links two orders as parent and child, then fires linked_orders_for_woocommerce/link/created.
 *
 * It returns false when the child is the parent or has a parent or children, when another request still links orders after five seconds, or when the child's save does not store its parent, and null when either order has no node, as no order has until the plugin has booted.
 *
 * @api
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
	return Linking::link( $parent_id, $child_id );
}

/**
 * Makes an order the root of its own tree, its descendants staying below it, and drops it from its parent's children, then fires linked_orders_for_woocommerce/link/removed.
 *
 * It returns true once the order is a root, an order already at the top of its tree included, false when another request still links orders after five seconds or when the order's save does not store it as a root, and null when the order has no node, as no order has until the plugin has booted.
 *
 * @api
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   int $child_id The id of the order to unlink from its parent.
 *
 * @return  bool|null
 */
function dws_lowc_unlink_order( int $child_id ): ?bool {
	return Linking::unlink( $child_id );
}

/**
 * Returns the node at the top of an order's tree, or null when the order has no node, as no order has until the plugin has booted.
 *
 * A root is its own top, and so is a child whose parent was deleted.
 *
 * The walk stops below an order it has already visited, since v1 stored links without refusing a cycle.
 *
 * @api
 *
 * @since   1.0.0
 * @version 2.0.0
 *
 * @param   WC_Order|WP_Post|int|string|false|null $order The order or its id.
 *
 * @return  DWS_Order_Node|null
 */
function dws_lowc_get_root_order( $order ): ?DWS_Order_Node {
	$node    = dws_lowc_get_order_node( $order );
	$visited = array();
	while ( ! is_null( $node ) ) {
		$visited[] = $node->get_order()->get_id();
		$parent    = $node->get_parent();
		if ( is_null( $parent ) || in_array( $parent->get_order()->get_id(), $visited, true ) ) {
			break;
		}

		$node = $parent;
	}

	return $node;
}

/**
 * Returns the ids of an order and its descendants, each child before the next with its own descendants, or null when the order has no node, as no order has until the plugin has booted.
 *
 * Each order is listed once, since v1 stored links without refusing a cycle.
 *
 * @api
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
	if ( is_null( $node ) ) {
		return null;
	}

	$tree    = array();
	$pending = array( $node );
	while ( array() !== $pending ) {
		$current = array_pop( $pending );
		$id      = $current->get_order()->get_id();
		if ( in_array( $id, $tree, true ) ) {
			continue;
		}

		$tree[]  = $id;
		$pending = array_merge( $pending, array_reverse( $current->get_children() ) );
	}

	return $tree;
}
