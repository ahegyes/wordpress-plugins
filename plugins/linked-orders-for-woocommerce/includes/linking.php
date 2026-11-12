<?php declare( strict_types=1 );
/**
 * Keeps v1's linking API, which links orders into trees and walks them.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LinkedOrders
 */

use DeepWebSolutions\LinkedOrders\Plugin;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Storage\Lock;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Storage\LockClaim;

defined( 'ABSPATH' ) || exit;

/**
 * Returns an order's node, or null when the value names no order of a supported type.
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
 * Returns whether an order is at the top of its tree, or null when it has no node.
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
 * Links one order as the child of another, returning false unless the child is another order with neither a parent nor children, when another request still links orders after five seconds, or when the child's save does not store its parent, and null when either order has no node.
 *
 * A link reads and rewrites the parent's list of children, so one lock serializes links across requests, and each reads both orders' meta from the database once it holds the lock. A link holds it for milliseconds, so the next one waits for it rather than refusing at once. WooCommerce logs a save that fails and returns as if it had saved, so the parent lists the child only once the child's parent reads back from the database.
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
	$parent = dws_lowc_get_order_node( $parent_id );
	$child  = dws_lowc_get_order_node( $child_id );
	if ( is_null( $parent ) || is_null( $child ) ) {
		return null;
	}

	$lock     = new Lock( Plugin::PREFIX . '_linking', 30 );
	$owner    = wp_generate_uuid4();
	$deadline = microtime( true ) + 5;
	while ( LockClaim::Busy === $lock->acquire( $owner ) ) {
		if ( microtime( true ) >= $deadline ) {
			return false;
		}

		usleep( 50000 );
	}

	try {
		dws_lowc_refresh_links( $parent->get_order(), $child->get_order() );
		if ( ! $parent->add_child( $child ) ) {
			return false;
		}

		$child->save();
		dws_lowc_refresh_links( $child->get_order() );
		if ( (string) $parent->get_order()->get_id() !== $child->get_order()->get_meta( '_dws_lo_parent' ) ) {
			return false;
		}

		$parent->save();
	} finally {
		$lock->release( $owner );
	}

	return true;
}

/**
 * Reads orders' meta again from the database, past WooCommerce's order-tables data caching.
 *
 * This request may hold an order as loaded before another request changed its links, and the caching answers even a forced read from that load, so the reads run with the caching off, and the saves that follow, with it back on, clear what it kept.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   WC_Order ...$orders The orders.
 *
 * @return  void
 */
function dws_lowc_refresh_links( WC_Order ...$orders ): void {
	$no_data_caching = static fn (): string => 'no';
	add_filter( 'pre_option_woocommerce_hpos_datastore_caching_enabled', $no_data_caching, PHP_INT_MAX );
	try {
		foreach ( $orders as $order ) {
			$order->read_meta_data( true );
		}
	} finally {
		remove_filter( 'pre_option_woocommerce_hpos_datastore_caching_enabled', $no_data_caching, PHP_INT_MAX );
	}
}

/**
 * Returns the node at the top of an order's tree, which is the order's own for a root or for a child whose parent was deleted, or null when the order has no node.
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
 * Returns the ids of an order and its descendants, each child before the next with its own descendants, or null when the order has no node.
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
