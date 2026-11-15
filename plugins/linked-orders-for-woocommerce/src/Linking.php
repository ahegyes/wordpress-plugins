<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders;

use Automattic\WooCommerce\Utilities\OrderUtil;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\PluginIdentity;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Storage\Lock;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Storage\LockClaim;

\defined( 'ABSPATH' ) || exit;

/**
 * Holds the links between orders' stored names and the internals that keep them consistent: rerooting, depths, the linking lock and the order queries by link.
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
	public static function reroot_children( int $order_id ): void {
		$node = dws_lowc_get_order_node( $order_id );
		if ( \is_null( $node ) || ! $node->has_children() ) {
			return;
		}

		$owner = wp_generate_uuid4();
		$lock  = self::acquire_lock( $owner );
		try {
			$node = dws_lowc_get_order_node( $order_id );
			if ( \is_null( $node ) ) {
				return;
			}

			self::refresh_links( $node->get_order() );
			foreach ( $node->get_children() as $child ) {
				$parent = $child->get_parent();
				if ( ! \is_null( $parent ) && $order_id === $parent->get_order()->get_id() ) {
					self::store_depths( $child->get_order()->get_id(), 0 );
				}
			}
		} finally {
			if ( ! \is_null( $lock ) ) {
				$lock->release( $owner );
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
	 * It reads the validated setting, at least 1 as stored, through v1's and v2's filters.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  int
	 */
	public static function get_max_depth(): int {
		return \filter_var( dws_lowc_get_validated_setting( 'max-depth', 'general' ), \FILTER_VALIDATE_INT, \FILTER_NULL_ON_FAILURE ) ?? (int) Options::DEFAULTS[ Options::MAX_DEPTH ];
	}

	// endregion

	// region HELPERS

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
	protected static function store_depths( int $order_id, int $depth ): void {
		$depths  = array( $order_id => $depth );
		$pending = array( $order_id );
		while ( array() !== $pending ) {
			$id   = \array_shift( $pending );
			$node = dws_lowc_get_order_node( $id );
			if ( \is_null( $node ) ) {
				continue;
			}

			self::refresh_links( $node->get_order() );
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

	// endregion
}
