<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders;

use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Makes the children of an order deleted for good the roots of their own trees, on either order storage.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class OrderDeletion implements ComponentInterface {
	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		add_action( 'woocommerce_before_delete_order', array( $this, 'reroot_children' ) );
		add_action( 'before_delete_post', array( $this, 'reroot_post_children' ), 10, 2 );
	}

	// endregion

	// region HOOKS

	/**
	 * Makes the children of the order being deleted the roots of their own trees.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $order_id The id of the order being deleted.
	 *
	 * @return  void
	 */
	public function reroot_children( int $order_id ): void {
		dws_lowc_reroot_children( $order_id );
	}

	/**
	 * Makes the children of the order whose post is being deleted the roots of their own trees.
	 *
	 * The posts storage's orders list and WordPress's scheduled trash emptying delete an order through the posts API, which fires no WooCommerce action. On the order tables, an order's post is a placeholder of another post type, which WordPress can delete while the order stays, or a copy WooCommerce keeps while the order exists.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int      $post_id The id of the post being deleted.
	 * @param   \WP_Post $post    The post being deleted.
	 *
	 * @return  void
	 */
	public function reroot_post_children( int $post_id, \WP_Post $post ): void {
		if ( \in_array( $post->post_type, dws_lowc_get_supported_order_types(), true ) ) {
			dws_lowc_reroot_children( $post_id );
		}
	}

	// endregion
}
