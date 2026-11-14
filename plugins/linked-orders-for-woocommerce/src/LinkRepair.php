<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders;

use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Migration\BatchedMigrationStepInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Repairs the links between orders that v1 left inconsistent, a page of linked orders per batch.
 *
 * Under the linking lock, a child whose parent no longer loads becomes the root of its own tree, a child its parent does not list joins the parent's children, and a child stored at any other depth than one below its parent moves there with its descendants.
 *
 * Children of an order v1 deleted still name it, v1's links could lose a child from its parent's list, and a request killed while a tree's depths are rewritten leaves some unwritten. A repair keeps every order on its page, so the pages hold still while the walk runs, and the option that holds its position is deleted once it ends. A batch that waits five seconds for the lock in vain runs again later.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class LinkRepair implements BatchedMigrationStepInterface {
	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @throws  \RuntimeException Thrown when a repair does not read back or the walk's position could not be stored.
	 */
	#[\Override]
	public function process_batch(): bool {
		$page = \filter_var( get_option( 'dws_lowc_link_repair_page', 1 ), \FILTER_VALIDATE_INT, \FILTER_NULL_ON_FAILURE ) ?? 1;

		$ids = wc_get_orders(
			Linking::get_meta_key_args( '_dws_lo_parent' ) + array(
				'type'    => dws_lowc_get_supported_order_types(),
				'status'  => \array_values( get_post_stati() ),
				'limit'   => 50,
				'paged'   => $page,
				'orderby' => 'ID',
				'order'   => 'ASC',
				'return'  => 'ids',
			)
		);

		$ids   = \is_array( $ids ) ? $ids : array();
		$owner = wp_generate_uuid4();
		$lock  = Linking::acquire_lock( $owner );
		if ( \is_null( $lock ) ) {
			return true;
		}

		try {
			foreach ( $ids as $id ) {
				$order = self::load( $id );
				if ( \is_null( $order ) ) {
					continue;
				}

				Linking::refresh_links( $order );
				$order_id = $order->get_id();
				$parent   = self::get_parent( $order, self::get_depth( $order ) );
				$depth    = 0;
				if ( ! \is_null( $parent ) ) {
					Linking::refresh_links( $parent );
					$depth    = self::get_depth( $parent ) + 1;
					$children = self::get_children( $parent );
					if ( ! \in_array( $order_id, self::get_ids( $children ), true ) ) {
						self::save( $parent, self::get_depth( $parent ), array( ...$children, $order ) );
						$check = self::load( $parent->get_id() );
						if ( ! \is_null( $check ) ) {
							Linking::refresh_links( $check );
						}

						if ( \is_null( $check ) || ! \in_array( $order_id, self::get_ids( self::get_children( $check ) ), true ) ) {
							throw new \RuntimeException( "The order $order_id could not be listed among its parent's children." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
						}
					}
				}

				if ( self::get_depth( $order ) !== $depth ) {
					self::store_depths( $order_id, $depth );
					$check = self::load( $order_id );
					if ( ! \is_null( $check ) ) {
						Linking::refresh_links( $check );
					}

					if ( \is_null( $check ) || self::get_depth( $check ) !== $depth ) {
						throw new \RuntimeException( "The order $order_id could not be stored at depth $depth." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
					}
				}
			}
		} finally {
			$lock->release( $owner );
		}

		if ( \count( $ids ) < 50 ) {
			delete_option( 'dws_lowc_link_repair_page' );
			return false;
		}

		if ( ! update_option( 'dws_lowc_link_repair_page', $page + 1, false ) ) {
			throw new \RuntimeException( "The repair of the links could not store its position after page $page." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
		}

		return true;
	}

	// endregion

	// region HELPERS

	/**
	 * Returns an order of a supported type with a registered post type, or null, as v1's node loads one.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $id The order's id.
	 *
	 * @return  \WC_Order|null
	 */
	protected static function load( mixed $id ): ?\WC_Order {
		$order = \is_int( $id ) ? wc_get_order( $id ) : false;
		return $order instanceof \WC_Order && true === dws_lowc_is_supported_order( $order ) && ! \is_null( get_post_type_object( $order->get_type() ) ) ? $order : null;
	}

	/**
	 * Returns an order's stored depth, 0 where none is stored.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \WC_Order $order The order.
	 *
	 * @return  int
	 */
	protected static function get_depth( \WC_Order $order ): int {
		return \filter_var( $order->get_meta( '_dws_lo_depth' ), \FILTER_VALIDATE_INT, \FILTER_NULL_ON_FAILURE ) ?? 0;
	}

	/**
	 * Returns the parent an order at a depth names, which only an order below the root has, where it loads.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \WC_Order $order The order.
	 * @param   int       $depth The order's depth.
	 *
	 * @return  \WC_Order|null
	 */
	protected static function get_parent( \WC_Order $order, int $depth ): ?\WC_Order {
		return $depth > 0 ? self::load( \filter_var( $order->get_meta( '_dws_lo_parent' ), \FILTER_VALIDATE_INT, \FILTER_NULL_ON_FAILURE ) ) : null;
	}

	/**
	 * Returns the children an order lists that load.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \WC_Order $order The order.
	 *
	 * @return  list<\WC_Order>
	 */
	protected static function get_children( \WC_Order $order ): array {
		$ids = $order->get_meta( '_dws_lo_children' );
		$ids = \is_array( $ids ) ? \filter_var( $ids, \FILTER_VALIDATE_INT, \FILTER_REQUIRE_ARRAY | \FILTER_NULL_ON_FAILURE ) : array();
		return \array_values( \array_filter( \array_map( self::load( ... ), \is_array( $ids ) ? $ids : array() ), static fn ( ?\WC_Order $child ): bool => ! \is_null( $child ) ) );
	}

	/**
	 * Returns the ids of orders.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   list<\WC_Order> $orders The orders.
	 *
	 * @return  list<int>
	 */
	protected static function get_ids( array $orders ): array {
		return \array_map( static fn ( \WC_Order $order ): int => $order->get_id(), $orders );
	}

	/**
	 * Stores an order at a depth with its children, and the parent that depth lets it keep, as v1's node saves it.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \WC_Order       $order    The order.
	 * @param   int             $depth    The order's depth.
	 * @param   list<\WC_Order> $children The order's children.
	 *
	 * @return  void
	 */
	protected static function save( \WC_Order $order, int $depth, array $children ): void {
		$order->update_meta_data( '_dws_lo_depth', (string) $depth );
		$order->update_meta_data( '_dws_lo_parent', (string) self::get_parent( $order, $depth )?->get_id() );
		$order->update_meta_data( '_dws_lo_children', self::get_ids( $children ) );
		$order->save();
	}

	/**
	 * Stores an order at a depth and each of its descendants one level below its parent, walking the tree breadth first.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $order_id The order's id.
	 * @param   int $depth    The order's new depth.
	 *
	 * @return  void
	 */
	protected static function store_depths( int $order_id, int $depth ): void {
		$depths  = array( $order_id => $depth );
		$pending = array( $order_id );
		while ( array() !== $pending ) {
			$id    = \array_shift( $pending );
			$order = self::load( $id );
			if ( \is_null( $order ) ) {
				continue;
			}

			Linking::refresh_links( $order );
			$children = self::get_children( $order );
			self::save( $order, $depths[ $id ], $children );
			foreach ( $children as $child ) {
				$child_id = $child->get_id();
				if ( ! isset( $depths[ $child_id ] ) ) {
					$depths[ $child_id ] = $depths[ $id ] + 1;
					$pending[]           = $child_id;
				}
			}
		}
	}

	// endregion
}
