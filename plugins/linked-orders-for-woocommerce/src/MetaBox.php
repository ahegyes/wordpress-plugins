<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders;

use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Shows an order's parent and children beside its editor, with a button that creates a child, on either order storage.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class MetaBox implements ComponentInterface {
	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 999, 2 );
	}

	// endregion

	// region HOOKS

	/**
	 * Adds the box beside the editor of an order of a supported type, for a user who may see it.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $screen_id     The editor's screen id, which is the post type where orders are stored as posts.
	 * @param   mixed  $post_or_order The edited post or order.
	 *
	 * @return  void
	 */
	public function add_meta_box( string $screen_id, mixed $post_or_order ): void {
		$node = current_user_can( DWS_LOWC_CAP_SEE_ORDER_LINKS ) && current_user_can( DWS_LOWC_CAP_SEE_METABOX ) ? dws_lowc_get_order_node( $post_or_order ) : null;
		if ( null === $node ) {
			return;
		}

		/* translators: %s: The order type's plural name. */
		add_meta_box( 'dws-linked-orders', \sprintf( _x( 'Linked %s', 'metabox heading', 'linked-orders-for-woocommerce' ), $node->get_post_type()->label ), array( $this, 'render_meta_box' ), $screen_id, 'side' );
	}

	/**
	 * Prints the order's parent, its children or a note that it has none, and the button that creates a child where the user may create one.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $post_or_order The edited post or order.
	 *
	 * @return  void
	 */
	public function render_meta_box( mixed $post_or_order ): void {
		$node = dws_lowc_get_order_node( $post_or_order );
		if ( null === $node ) {
			return;
		}

		$parent     = $node->get_parent();
		$can_create = $node->can_create_child();
		$type_name  = dws_lowc_get_singular_name( $node->get_post_type() );
		if ( null !== $parent ) {
			?>
			<div class="dws-linked-orders__parent">
				<?php echo esc_html_x( 'Parent', 'metabox', 'linked-orders-for-woocommerce' ); ?>:
				<a href="<?php echo esc_url( $parent->get_order()->get_edit_order_url() ); ?>" target="_blank">
					<?php echo wp_kses_post( $parent->get_formatted_name() ); ?>
				</a>
			</div>
			<hr/>
			<?php
		}

		if ( ! $node->has_children() ) {
			?>
			<div class="dws-linked-orders__no-children">
				<?php esc_html_e( 'There are no attached children.', 'linked-orders-for-woocommerce' ); ?>
				<?php if ( ! $can_create ) : ?>
					<?php /* translators: %s: The order type's singular name. */ ?>
					<?php echo esc_html( \sprintf( _x( 'New children cannot be added to this %s.', 'metabox', 'linked-orders-for-woocommerce' ), $type_name ) ); ?>
				<?php endif; ?>
			</div>
			<?php
		} else {
			?>
			<div class="dws-linked-orders__children">
				<?php esc_html_e( 'Attached children', 'linked-orders-for-woocommerce' ); ?>:
				<ul class="dws-linked-orders__children-list">
					<?php foreach ( $node->get_children() as $child ) : ?>
						<li id="linked-order-<?php echo esc_attr( (string) $child->get_order()->get_id() ); ?>" class="dws-linked-order">
							<a href="<?php echo esc_url( $child->get_order()->get_edit_order_url() ); ?>" target="_blank">
								<?php echo wp_kses_post( $child->get_formatted_name() ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php
		}

		if ( ! $can_create ) {
			return;
		}

		$url = wp_nonce_url( admin_url( 'admin-post.php?action=dws_lowc_create_linked_child&parent_id=' . $node->get_order()->get_id() ), 'dws_create_linked_child' );
		?>
		<a class="button button-alt" href="<?php echo esc_url( $url ); ?>">
			<?php /* translators: %s: The order type's singular name. */ ?>
			<?php echo esc_html( \sprintf( _x( 'Add new child %s', 'metabox', 'linked-orders-for-woocommerce' ), $type_name ) ); ?>
		</a>
		<?php

		/**
		 * Fires after the meta box's button that creates a child.
		 *
		 * @since       1.0.0
		 * @version     2.0.0
		 * @deprecated  2.0.0 Use dws_lowc/meta_box/after_add_new_child_button.
		 *
		 * @param       \DWS_Order_Node $node The edited order's node.
		 */
		do_action_deprecated( 'linked_orders_for_woocommerce/metabox_output/after_add_new_child_button', array( $node ), '2.0.0', 'dws_lowc/meta_box/after_add_new_child_button' );

		/**
		 * Fires after the meta box's button that creates a child.
		 *
		 * @since   2.0.0
		 * @version 2.0.0
		 *
		 * @param   \DWS_Order_Node $node The edited order's node.
		 */
		do_action( 'dws_lowc/meta_box/after_add_new_child_button', $node );
	}

	// endregion
}
