<?php declare( strict_types=1 );
/**
 * Holds the plugin's migration steps, which bring its stored data to the revision its code expects.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LinkedOrders
 */

use Automattic\WooCommerce\Utilities\OrderUtil;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Migration\BatchedMigrationStepInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Returns the migration steps, append-only, where index 0 brings the data to revision 1.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  list<(callable(): void)|BatchedMigrationStepInterface>
 */
function dws_lowc_get_migration_steps(): array {
	return array(
		'dws_lowc_add_settings',
		new class() implements BatchedMigrationStepInterface {
			// region INHERITED METHODS

			/**
			 * {@inheritDoc}
			 *
			 * @since   2.0.0
			 * @version 2.0.0
			 */
			#[\Override]
			public function process_batch(): bool {
				return dws_lowc_repair_links();
			}

			// endregion
		},
	);
}

/**
 * Stores the settings' defaults.
 *
 * Every value a v1 site stored stays as it is.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @throws  RuntimeException Thrown when a default could not be stored.
 *
 * @return  void
 */
function dws_lowc_add_settings(): void {
	$defaults = array(
		DWS_LOWC_OPTION_MAX_DEPTH    => (string) DWS_LOWC_OPTION_DEFAULTS[ DWS_LOWC_OPTION_MAX_DEPTH ],
		DWS_LOWC_OPTION_AUTOCOMPLETE => wc_bool_to_string( DWS_LOWC_OPTION_DEFAULTS[ DWS_LOWC_OPTION_AUTOCOMPLETE ] ),
		DWS_LOWC_OPTION_DELETE_DATA  => wc_bool_to_string( DWS_LOWC_OPTION_DEFAULTS[ DWS_LOWC_OPTION_DELETE_DATA ] ),
	);

	foreach ( $defaults as $key => $value ) {
		if ( false === get_option( $key ) && ! add_option( $key, $value, '', true ) ) {
			throw new RuntimeException( "The option $key could not be stored." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
		}
	}
}

/**
 * Repairs the next page of linked orders and returns whether pages remain.
 *
 * Under the linking lock, a child whose parent no longer loads becomes the root of its own tree, a child its parent does not list joins the parent's children, and a child stored at any other depth than one below its parent moves there with its descendants.
 *
 * Children of an order v1 deleted still name it, v1's links could lose a child from its parent's list, and a request killed while a tree's depths are rewritten leaves some unwritten. A repair keeps every order on its page, so the pages hold still while the walk runs, and the option that holds its position is deleted once it ends. A batch that waits five seconds for the lock in vain runs again later.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @throws  RuntimeException Thrown when a repair does not read back or the walk's position could not be stored.
 *
 * @return  bool
 */
function dws_lowc_repair_links(): bool {
	$page = filter_var( get_option( 'dws_lowc_link_repair_page', 1 ), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE ) ?? 1;

	// The order tables take the parent key only in a meta query, which the posts store refuses, passing a top-level key to WP_Query instead.
	$linked = OrderUtil::custom_orders_table_usage_is_enabled() ? array( 'meta_query' => array( array( 'key' => '_dws_lo_parent' ) ) ) : array( 'meta_key' => '_dws_lo_parent' ); // phpcs:ignore WordPress.DB.SlowDBQuery -- A one-time walk of the linked orders.
	$ids    = wc_get_orders(
		$linked + array(
			'type'    => dws_lowc_get_supported_order_types(),
			'status'  => array_values( get_post_stati() ),
			'limit'   => 50,
			'paged'   => $page,
			'orderby' => 'ID',
			'order'   => 'ASC',
			'return'  => 'ids',
		)
	);

	$ids       = is_array( $ids ) ? $ids : array();
	$child_ids = static fn ( DWS_Order_Node $node ): array => array_map( static fn ( DWS_Order_Node $child ): int => $child->get_order()->get_id(), $node->get_children() );
	$owner     = wp_generate_uuid4();
	$lock      = dws_lowc_acquire_linking_lock( $owner );
	if ( is_null( $lock ) ) {
		return true;
	}

	try {
		foreach ( $ids as $id ) {
			$node = dws_lowc_get_order_node( $id );
			if ( is_null( $node ) ) {
				continue;
			}

			dws_lowc_refresh_links( $node->get_order() );
			$order_id = $node->get_order()->get_id();
			$parent   = $node->get_parent();
			$depth    = 0;
			if ( ! is_null( $parent ) ) {
				dws_lowc_refresh_links( $parent->get_order() );
				$depth = $parent->get_depth() + 1;
				if ( ! in_array( $order_id, $child_ids( $parent ), true ) ) {
					$parent->set_children( array( ...$parent->get_children(), $node ) );
					$parent->save();
					$check = dws_lowc_get_order_node( $parent->get_order()->get_id() );
					if ( ! is_null( $check ) ) {
						dws_lowc_refresh_links( $check->get_order() );
					}

					if ( is_null( $check ) || ! in_array( $order_id, $child_ids( $check ), true ) ) {
						throw new RuntimeException( "The order $order_id could not be listed among its parent's children." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
					}
				}
			}

			if ( $depth !== $node->get_depth() ) {
				dws_lowc_store_depths( $order_id, $depth );
				$check = dws_lowc_get_order_node( $order_id );
				if ( ! is_null( $check ) ) {
					dws_lowc_refresh_links( $check->get_order() );
				}

				if ( is_null( $check ) || $depth !== $check->get_depth() ) {
					throw new RuntimeException( "The order $order_id could not be stored at depth $depth." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
				}
			}
		}
	} finally {
		$lock->release( $owner );
	}

	if ( count( $ids ) < 50 ) {
		delete_option( 'dws_lowc_link_repair_page' );
		return false;
	}

	if ( ! update_option( 'dws_lowc_link_repair_page', $page + 1, false ) ) {
		throw new RuntimeException( "The repair of the links could not store its position after page $page." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
	}

	return true;
}
