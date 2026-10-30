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
 * Links one order as the child of another, returning false unless the child is another order with neither a parent nor children, or while another request links orders, and null when either order has no node.
 *
 * A link reads and rewrites the parent's list of children, so one lock serializes links across requests, and each reads both orders' meta from the database once it holds the lock.
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

	$lock  = new Lock( Plugin::PREFIX . '_linking', 30 );
	$owner = wp_generate_uuid4();
	if ( LockClaim::Busy === $lock->acquire( $owner ) ) {
		return false;
	}

	try {
		// This request may hold either order as loaded before another request linked it, and WooCommerce's order-tables data caching answers even a forced read from that load, so the reads run with the caching off, and the saves, with it back on, clear what it kept.
		$no_data_caching = static fn (): string => 'no';
		add_filter( 'pre_option_woocommerce_hpos_datastore_caching_enabled', $no_data_caching, PHP_INT_MAX );
		try {
			$parent->get_order()->read_meta_data( true );
			$child->get_order()->read_meta_data( true );
		} finally {
			remove_filter( 'pre_option_woocommerce_hpos_datastore_caching_enabled', $no_data_caching, PHP_INT_MAX );
		}

		if ( ! $parent->add_child( $child ) ) {
			return false;
		}

		$parent->save();
		$child->save();
	} finally {
		$lock->release( $owner );
	}

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
