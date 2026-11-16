<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders;

use Automattic\WooCommerce\Utilities\OrderUtil;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\PluginIdentity;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Storage\Lock;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Storage\LockClaim;

\defined( 'ABSPATH' ) || exit;

/**
 * Links and unlinks orders, and holds what keeps the links consistent: their stored names, rerooting, depths, the linking lock and the order queries by link.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class Linking {
	// region FIELDS AND CONSTANTS

	/**
	 * The order meta key that holds an order's depth below its root, 0 for a root.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string META_DEPTH = '_dws_lo_depth';

	/**
	 * The order meta key that holds the id of an order's parent, empty for a root.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string META_PARENT = '_dws_lo_parent';

	/**
	 * The order meta key that holds the ids of an order's children.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string META_CHILDREN = '_dws_lo_children';

	/**
	 * The order meta key that holds the id of the user who created a linked child.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string META_CREATED_BY = '_dws_lo_created_by';

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	protected function __construct() {}

	// endregion

	// region METHODS

	/**
	 * Links two orders as parent and child, then fires linked_orders_for_woocommerce/link/created, and returns whether they are linked, or null when either order has no node.
	 *
	 * A link reads and rewrites the parent's list of children, so it holds the linking lock and reads both orders' meta from the database under it. WooCommerce logs a save that fails and returns as if it had saved, so the parent lists the child only once the child's parent reads back from the database.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $parent_id The id of the parent order.
	 * @param   int $child_id  The id of the child order.
	 *
	 * @return  bool|null
	 */
	public static function link( int $parent_id, int $child_id ): ?bool {
		$parent = dws_lowc_get_order_node( $parent_id );
		$child  = dws_lowc_get_order_node( $child_id );
		if ( \is_null( $parent ) || \is_null( $child ) ) {
			return null;
		}

		$owner = wp_generate_uuid4();
		$lock  = self::acquire_lock( $owner );
		if ( \is_null( $lock ) ) {
			return false;
		}

		try {
			self::refresh_links( $parent->get_order(), $child->get_order() );
			if ( $child->has_parent() || $child->has_children() || $child->get_order()->get_id() === $parent->get_order()->get_id() ) {
				return false;
			}

			self::store_links( $child->get_order(), $parent->get_depth() + 1, $parent, array() );
			self::refresh_links( $child->get_order() );
			if ( (string) $parent->get_order()->get_id() !== $child->get_order()->get_meta( self::META_PARENT ) ) {
				return false;
			}

			self::store_links( $parent->get_order(), $parent->get_depth(), $parent->get_parent(), array( ...$parent->get_children(), $child ) );
		} finally {
			$lock->release( $owner );
		}

		/**
		 * Fires once two orders are linked as parent and child.
		 *
		 * @since   2.0.0
		 * @version 2.0.0
		 *
		 * @param   int $parent_id The id of the parent order.
		 * @param   int $child_id  The id of the child order.
		 */
		do_action( 'linked_orders_for_woocommerce/link/created', $parent->get_order()->get_id(), $child->get_order()->get_id() );
		return true;
	}

	/**
	 * Makes an order the root of its own tree with its descendants below it, then fires linked_orders_for_woocommerce/link/removed, and returns whether it is a root, or null when it has no node.
	 *
	 * An unlink rewrites the parent's list of children, so it holds the linking lock and reads the orders' meta from the database under it. It rewrites the order's descendants and its parent's children only once the order reads back as a root, so a refused unlink leaves the tree as it was.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $order_id The id of the order.
	 *
	 * @return  bool|null
	 */
	public static function unlink( int $order_id ): ?bool {
		$node = dws_lowc_get_order_node( $order_id );
		if ( \is_null( $node ) ) {
			return null;
		}

		$owner = wp_generate_uuid4();
		$lock  = self::acquire_lock( $owner );
		if ( \is_null( $lock ) ) {
			return false;
		}

		$parent_id = null;
		try {
			$order = $node->get_order();
			self::refresh_links( $order );
			if ( 0 === $node->get_depth() ) {
				return true;
			}

			$parent    = $node->get_parent();
			$parent_id = \filter_var( $order->get_meta( self::META_PARENT ), \FILTER_VALIDATE_INT, \FILTER_NULL_ON_FAILURE );
			if ( ! self::store_depths( $order->get_id(), 0 ) ) {
				return false;
			}

			if ( ! \is_null( $parent ) ) {
				self::refresh_links( $parent->get_order() );
				$children = \array_filter( $parent->get_children(), static fn ( \DWS_Order_Node $child ): bool => $child->get_order()->get_id() !== $order->get_id() );
				self::store_links( $parent->get_order(), $parent->get_depth(), $parent->get_parent(), \array_values( $children ) );
			}
		} finally {
			$lock->release( $owner );
		}

		if ( ! \is_null( $parent_id ) ) {
			self::fire_link_removed( $parent_id, $order_id );
		}

		return true;
	}

	/**
	 * Makes each child of an order the root of its own tree, as when the order is deleted for good, then fires linked_orders_for_woocommerce/link/removed for each child stored as a root.
	 *
	 * The children's and their descendants' stored depths decide the depth column, the depth filter and the maximum depth, so the rewrite holds the linking lock, and runs without it only once another request has held it for five seconds. The order is being deleted, so its own list of children stays as it is.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $order_id The id of the order.
	 *
	 * @return  void
	 */
	public static function reroot_children( int $order_id ): void {
		$node = dws_lowc_get_order_node( $order_id );
		if ( \is_null( $node ) || ! $node->has_children() ) {
			return;
		}

		$owner    = wp_generate_uuid4();
		$lock     = self::acquire_lock( $owner );
		$rerooted = array();
		try {
			$node = dws_lowc_get_order_node( $order_id );
			if ( \is_null( $node ) ) {
				return;
			}

			self::refresh_links( $node->get_order() );
			foreach ( $node->get_children() as $child ) {
				$parent = $child->get_parent();
				if ( ! \is_null( $parent ) && $order_id === $parent->get_order()->get_id() && self::store_depths( $child->get_order()->get_id(), 0 ) ) {
					$rerooted[] = $child->get_order()->get_id();
				}
			}
		} finally {
			if ( ! \is_null( $lock ) ) {
				$lock->release( $owner );
			}
		}

		foreach ( $rerooted as $child_id ) {
			self::fire_link_removed( $order_id, $child_id );
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
	public static function acquire_lock( string $owner ): ?Lock {
		$lock     = new Lock( new PluginIdentity( DWS_LOWC_FILE, Plugin::SLUG, Plugin::PREFIX ), 'linking', 30 );
		$deadline = \microtime( true ) + 5;
		while ( LockClaim::Busy === $lock->acquire( $owner ) ) {
			if ( \microtime( true ) >= $deadline ) {
				return null;
			}

			\usleep( 50000 );
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
	 * @param   \WC_Order ...$orders The orders.
	 *
	 * @return  void
	 */
	public static function refresh_links( \WC_Order ...$orders ): void {
		$no_data_caching = static fn (): string => 'no';
		add_filter( 'pre_option_woocommerce_hpos_datastore_caching_enabled', $no_data_caching, \PHP_INT_MAX );
		try {
			foreach ( $orders as $order ) {
				$order->read_meta_data( true );
			}
		} finally {
			remove_filter( 'pre_option_woocommerce_hpos_datastore_caching_enabled', $no_data_caching, \PHP_INT_MAX );
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
	public static function get_meta_key_args( string $key ): array {
		return OrderUtil::custom_orders_table_usage_is_enabled() ? array( 'meta_query' => array( array( 'key' => $key ) ) ) : array( 'meta_key' => $key ); // phpcs:ignore WordPress.DB.SlowDBQuery -- Callers read one row or walk the linked orders once.
	}

	/**
	 * Returns the deepest level a linked order can be created at.
	 *
	 * It reads the setting through v1's validation, which holds it at least 1, and v1's filter.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  int
	 */
	public static function get_max_depth(): int {
		return Options::get_int( Options::MAX_DEPTH );
	}

	/**
	 * Stores an order's depth, parent and children in its meta, as v1 stored them, and saves the order.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \WC_Order             $order       The order.
	 * @param   int                   $depth       The order's depth, 0 for a root.
	 * @param   \DWS_Order_Node|null  $parent_node The parent's node, or null for a root.
	 * @param   list<\DWS_Order_Node> $children    The children's nodes.
	 *
	 * @return  void
	 */
	public static function store_links( \WC_Order $order, int $depth, ?\DWS_Order_Node $parent_node, array $children ): void {
		$order->update_meta_data( self::META_DEPTH, (string) $depth );
		$order->update_meta_data( self::META_PARENT, (string) $parent_node?->get_order()->get_id() );
		$order->update_meta_data( self::META_CHILDREN, \array_map( static fn ( \DWS_Order_Node $child ): int => $child->get_order()->get_id(), $children ) );
		$order->save();
	}

	// endregion

	// region HELPERS

	/**
	 * Stores an order at a depth, 0 for the root of its tree, and each descendant one level below its parent, and returns false when the order's depth does not read back as stored.
	 *
	 * The caller holds the linking lock. WooCommerce logs a save that fails and returns as if it had saved, so the order's depth reads back before any descendant is rewritten, and one that does not leaves them as they were.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $order_id The id of the order.
	 * @param   int $depth    The order's depth.
	 *
	 * @return  bool
	 */
	protected static function store_depths( int $order_id, int $depth ): bool {
		$depths  = array( $order_id => $depth );
		$pending = array( $order_id );
		while ( array() !== $pending ) {
			$id   = \array_shift( $pending );
			$node = dws_lowc_get_order_node( $id );
			if ( \is_null( $node ) ) {
				if ( $order_id === $id ) {
					return false;
				}

				continue;
			}

			$order = $node->get_order();
			self::refresh_links( $order );
			$parent_id = \filter_var( $order->get_meta( self::META_PARENT ), \FILTER_VALIDATE_INT, \FILTER_NULL_ON_FAILURE );
			$parent    = $depths[ $id ] > 0 && ! \is_null( $parent_id ) ? dws_lowc_get_order_node( $parent_id ) : null;
			self::store_links( $order, $depths[ $id ], $parent, $node->get_children() );
			if ( $order_id === $id ) {
				self::refresh_links( $order );
				if ( \filter_var( $order->get_meta( self::META_DEPTH ), \FILTER_VALIDATE_INT, \FILTER_NULL_ON_FAILURE ) !== $depth ) {
					return false;
				}
			}

			foreach ( $node->get_children() as $child ) {
				$child_id = $child->get_order()->get_id();
				if ( ! isset( $depths[ $child_id ] ) ) {
					$depths[ $child_id ] = $depths[ $id ] + 1;
					$pending[]           = $child_id;
				}
			}
		}

		return true;
	}

	/**
	 * Announces a removed link, which its callers do once they have released the linking lock, so a listener can link or unlink in turn.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $parent_id The id of the order that was the parent.
	 * @param   int $child_id  The id of the order that was the child.
	 *
	 * @return  void
	 */
	protected static function fire_link_removed( int $parent_id, int $child_id ): void {
		/**
		 * Fires once an order is no longer the child of another, through an unlink or because the parent is deleted for good.
		 *
		 * @since   2.0.0
		 * @version 2.0.0
		 *
		 * @param   int $parent_id The id of the order that was the parent.
		 * @param   int $child_id  The id of the order that was the child.
		 */
		do_action( 'linked_orders_for_woocommerce/link/removed', $parent_id, $child_id );
	}

	// endregion
}
