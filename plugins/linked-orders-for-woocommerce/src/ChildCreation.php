<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders;

use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\Notice;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Creates a linked child of an order from v1's admin-post action, which the meta box, the orders list and other plugins link to.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class ChildCreation implements ComponentInterface {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   AdminNotices $notices The notices that report a child that could not be created.
	 */
	public function __construct( protected AdminNotices $notices ) {}

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		add_action( 'admin_post_dws_lowc_create_linked_child', array( $this, 'create_linked_child' ) );
	}

	// endregion

	// region HOOKS

	/**
	 * Creates a linked child of the requested order and opens it, or returns to the referring screen, or else the parent, with a notice saying why it was not created.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  never
	 */
	public function create_linked_child(): never {
		check_admin_referer( 'dws_create_linked_child' );

		$parent_id = \filter_var(
			wp_unslash( $_GET['parent_id'] ?? '' ),
			\FILTER_VALIDATE_INT,
			array(
				'options' => array( 'min_range' => 1 ),
				'flags'   => \FILTER_NULL_ON_FAILURE,
			)
		) ?? 0;
		$node      = dws_lowc_get_order_node( $parent_id );
		if ( null === $node ) {
			/* translators: %d: The parent order's id. */
			$this->notices->add( new Notice( 'linked-child-missing-parent', esc_html( \sprintf( __( 'There is no order %d to create a linked child for.', 'linked-orders-for-woocommerce' ), $parent_id ) ) ) );
			wp_safe_redirect( wp_get_referer() ?: admin_url( 'index.php' ) );
			exit;
		}

		if ( ! $node->can_create_child() ) {
			/* translators: %d: The parent order's id. */
			$this->notices->add( new Notice( 'linked-child-not-allowed', esc_html( \sprintf( __( 'You may not create a linked child for order %d.', 'linked-orders-for-woocommerce' ), $parent_id ) ) ) );
		} else {
			$child = dws_lowc_create_linked_child( $parent_id );
			$order = \is_int( $child ) ? wc_get_order( $child ) : null;
			if ( $order instanceof \WC_Order ) {
				wp_safe_redirect( $order->get_edit_order_url() );
				exit;
			}

			$reason = is_wp_error( $child ) ? $child->get_error_message() : __( 'its creator returned no order.', 'linked-orders-for-woocommerce' );
			/* translators: 1: The parent order's id. 2: Why the child could not be created. */
			$this->notices->add( new Notice( 'linked-child-failed', esc_html( \sprintf( __( 'The linked child of order %1$d could not be created: %2$s', 'linked-orders-for-woocommerce' ), $parent_id, $reason ) ) ) );
		}

		wp_safe_redirect( wp_get_referer() ?: $node->get_order()->get_edit_order_url() );
		exit;
	}

	// endregion
}
