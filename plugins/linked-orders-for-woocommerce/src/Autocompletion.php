<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders;

use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Completes an order's descendants when the order is completed, where the site turned the setting on.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class Autocompletion implements ComponentInterface {
	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		add_action( 'woocommerce_order_status_completed', array( $this, 'complete_descendants' ) );
	}

	// endregion

	// region HOOKS

	/**
	 * Sets each of the order's descendants to completed, with a note, unless a filter keeps it or names another status.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $order_id The id of the completed order.
	 *
	 * @return  void
	 */
	public function complete_descendants( int $order_id ): void {
		if ( true !== dws_lowc_get_validated_setting( 'autocomplete-descendants', 'general' ) ) {
			return;
		}

		foreach ( \array_slice( dws_lowc_get_orders_tree( $order_id ) ?? array(), 1 ) as $descendant_id ) {
			/**
			 * Filters whether a descendant of a completed order is completed too.
			 *
			 * @since       1.0.0
			 * @version     2.0.0
			 * @deprecated  2.0.0 Use dws_lowc/autocompletion/should_autocomplete.
			 *
			 * @param       bool $should_autocomplete Whether the descendant is completed.
			 * @param       int  $descendant_id       The descendant's id.
			 */
			$should_autocomplete = apply_filters_deprecated( 'linked_orders_for_woocommerce/autocompletion/should_autocomplete', array( true, $descendant_id ), '2.0.0', 'dws_lowc/autocompletion/should_autocomplete' );

			/**
			 * Filters whether a descendant of a completed order is completed too.
			 *
			 * @since   2.0.0
			 * @version 2.0.0
			 *
			 * @param   bool $should_autocomplete Whether the descendant is completed.
			 * @param   int  $descendant_id       The descendant's id.
			 */
			$node = true === apply_filters( 'dws_lowc/autocompletion/should_autocomplete', $should_autocomplete, $descendant_id ) ? dws_lowc_get_order_node( $descendant_id ) : null;
			if ( null === $node ) {
				continue;
			}

			/**
			 * Filters the status a descendant of a completed order is set to.
			 *
			 * @since       1.0.0
			 * @version     2.0.0
			 * @deprecated  2.0.0 Use dws_lowc/autocompletion/completed_status.
			 *
			 * @param       string    $status     The status, without its 'wc-' prefix.
			 * @param       \WC_Order $descendant The descendant.
			 */
			$status = apply_filters_deprecated( 'linked_orders_for_woocommerce/autocompletion/completed_status', array( 'completed', $node->get_order() ), '2.0.0', 'dws_lowc/autocompletion/completed_status' );

			/**
			 * Filters the status a descendant of a completed order is set to.
			 *
			 * @since   2.0.0
			 * @version 2.0.0
			 *
			 * @param   string    $status     The status, without its 'wc-' prefix.
			 * @param   \WC_Order $descendant The descendant.
			 */
			$status = apply_filters( 'dws_lowc/autocompletion/completed_status', $status, $node->get_order() );

			/* translators: %s: The order type's singular name. */
			$node->get_order()->update_status( $status, \sprintf( __( 'Child %s autocompleted.', 'linked-orders-for-woocommerce' ), dws_lowc_get_singular_name( $node->get_post_type() ) ) );
		}
	}

	// endregion
}
