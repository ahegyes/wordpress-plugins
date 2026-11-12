<?php declare( strict_types=1 );
/**
 * Keeps v1's linking API, which links orders into trees and walks them.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LinkedOrders
 */

use Automattic\WooCommerce\Utilities\OrderUtil;
use DeepWebSolutions\LinkedOrders\Plugin;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Storage\Lock;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Storage\LockClaim;

defined( 'ABSPATH' ) || exit;

/**
 * The order meta key that holds an order's depth below its root, 0 for a root.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_LOWC_META_DEPTH = '_dws_lo_depth';

/**
 * The order meta key that holds the id of an order's parent, empty for a root.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_LOWC_META_PARENT = '_dws_lo_parent';

/**
 * The order meta key that holds the ids of an order's children.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_LOWC_META_CHILDREN = '_dws_lo_children';

/**
 * The order meta key that holds the id of the user who created a linked child.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_LOWC_META_CREATED_BY = '_dws_lo_created_by';

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
 * Links two orders as parent and child.
 *
 * It returns false when the child is the parent or has a parent or children, when another request still links orders after five seconds, or when the child's save does not store its parent, and null when either order has no node.
 *
 * A link reads and rewrites the parent's list of children, so it holds the linking lock and reads both orders' meta from the database under it. WooCommerce logs a save that fails and returns as if it had saved, so the parent lists the child only once the child's parent reads back from the database.
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

	$owner = wp_generate_uuid4();
	$lock  = dws_lowc_acquire_linking_lock( $owner );
	if ( is_null( $lock ) ) {
		return false;
	}

	try {
		dws_lowc_refresh_links( $parent->get_order(), $child->get_order() );
		if ( ! $parent->add_child( $child ) ) {
			return false;
		}

		$child->save();
		dws_lowc_refresh_links( $child->get_order() );
		if ( (string) $parent->get_order()->get_id() !== $child->get_order()->get_meta( DWS_LOWC_META_PARENT ) ) {
			return false;
		}

		$parent->save();
	} finally {
		$lock->release( $owner );
	}

	return true;
}

/**
 * Makes each child of an order the root of its own tree, as when the order is deleted for good.
 *
 * The children's and their descendants' stored depths decide the depth column, the depth filter and the maximum depth, so the rewrite holds the linking lock, and runs without it only once another request has held it for five seconds.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   int $order_id The id of the order.
 *
 * @return  void
 */
function dws_lowc_reroot_children( int $order_id ): void {
	$node = dws_lowc_get_order_node( $order_id );
	if ( is_null( $node ) || ! $node->has_children() ) {
		return;
	}

	$owner = wp_generate_uuid4();
	$lock  = dws_lowc_acquire_linking_lock( $owner );
	try {
		$node = dws_lowc_get_order_node( $order_id );
		if ( is_null( $node ) ) {
			return;
		}

		dws_lowc_refresh_links( $node->get_order() );
		foreach ( $node->get_children() as $child ) {
			$parent = $child->get_parent();
			if ( ! is_null( $parent ) && $order_id === $parent->get_order()->get_id() ) {
				dws_lowc_store_depths( $child->get_order()->get_id(), 0 );
			}
		}
	} finally {
		if ( ! is_null( $lock ) ) {
			$lock->release( $owner );
		}
	}
}

/**
 * Stores an order at a depth, 0 for the root of its tree, and each descendant one level below its parent.
 *
 * The caller holds the linking lock.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   int $order_id The id of the order.
 * @param   int $depth    The order's depth.
 *
 * @return  void
 */
function dws_lowc_store_depths( int $order_id, int $depth ): void {
	$depths  = array( $order_id => $depth );
	$pending = array( $order_id );
	while ( array() !== $pending ) {
		$id   = array_shift( $pending );
		$node = dws_lowc_get_order_node( $id );
		if ( is_null( $node ) ) {
			continue;
		}

		dws_lowc_refresh_links( $node->get_order() );
		$node->set_depth( $depths[ $id ] );
		$node->save();
		foreach ( $node->get_children() as $child ) {
			$child_id = $child->get_order()->get_id();
			if ( ! isset( $depths[ $child_id ] ) ) {
				$depths[ $child_id ] = $depths[ $id ] + 1;
				$pending[]           = $child_id;
			}
		}
	}
}

/**
 * Waits up to five seconds for the lock that serializes changes to links across requests, and returns it once the owner holds it, or null when another request still holds it.
 *
 * A change holds the lock for milliseconds, so the next one waits for it rather than refusing at once.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $owner The token that identifies the holder.
 *
 * @return  Lock|null
 */
function dws_lowc_acquire_linking_lock( string $owner ): ?Lock {
	$lock     = new Lock( Plugin::PREFIX . '_linking', 30 );
	$deadline = microtime( true ) + 5;
	while ( LockClaim::Busy === $lock->acquire( $owner ) ) {
		if ( microtime( true ) >= $deadline ) {
			return null;
		}

		usleep( 50000 );
	}

	return $lock;
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
 * Returns the wc_get_orders() arguments that match the orders storing a meta key, on the storage WooCommerce uses for orders.
 *
 * The order tables take a key only in a meta query, which the posts store refuses, so WP_Query gets it as a top-level key, which also lets it order by the key's value.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $key The meta key.
 *
 * @return  array<string, mixed>
 */
function dws_lowc_get_meta_key_args( string $key ): array {
	return OrderUtil::custom_orders_table_usage_is_enabled() ? array( 'meta_query' => array( array( 'key' => $key ) ) ) : array( 'meta_key' => $key ); // phpcs:ignore WordPress.DB.SlowDBQuery -- Callers read one row or walk the linked orders once.
}

/**
 * Returns the node at the top of an order's tree, or null when the order has no node.
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
