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
	// region FIELDS AND CONSTANTS

	/**
	 * The admin-post action that creates a linked child.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	protected const string ACTION = 'dws_lowc_create_linked_child';

	/**
	 * The nonce action that guards the creation of a linked child.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	protected const string NONCE = 'dws_create_linked_child';

	// endregion

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
		add_action( 'admin_post_' . self::ACTION, array( $this, 'create_linked_child' ) );
	}

	// endregion

	// region METHODS

	/**
	 * Returns why no child can be created from an order, naming the setting where the order is at the maximum depth.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \DWS_Order_Node $node The order's node.
	 *
	 * @return  string
	 */
	public static function get_refusal( \DWS_Order_Node $node ): string {
		$label  = OrderTypes::get_singular_name( $node->get_post_type() );
		$number = $node->get_order()->get_order_number();
		return Linking::get_max_depth() <= $node->get_depth()
			? \sprintf( /* translators: 1: The order type's singular name. 2: The order number. */ __( '%1$s #%2$s is at the maximum linked orders depth, set under WooCommerce > Settings > Advanced > Linked Orders.', 'linked-orders-for-woocommerce' ), $label, $number )
			: \sprintf( /* translators: 1: The order type's singular name. 2: The order number. */ __( '%1$s #%2$s cannot have a new linked child.', 'linked-orders-for-woocommerce' ), $label, $number );
	}

	/**
	 * Returns the URL that creates a linked child of an order through the plugin's admin-post action, with its nonce.
	 *
	 * The URL is unescaped, as WooCommerce's order URLs are, so a caller can add query arguments to it or redirect to it, and escapes it where it prints it.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $parent_id The id of the parent order.
	 *
	 * @return  string
	 */
	public static function get_url( int $parent_id ): string {
		return add_query_arg(
			array(
				'action'    => self::ACTION,
				'parent_id' => $parent_id,
				'_wpnonce'  => wp_create_nonce( self::NONCE ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	// endregion

	// region HOOKS

	/**
	 * Creates a linked child of the requested order and opens it.
	 *
	 * Otherwise the request returns to the referring screen, or else the parent, with a notice that says why.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  never
	 */
	public function create_linked_child(): never {
		check_admin_referer( self::NONCE );

		$parent_id = \filter_var(
			wp_unslash( $_GET['parent_id'] ?? '' ),
			\FILTER_VALIDATE_INT,
			array(
				'options' => array( 'min_range' => 1 ),
				'flags'   => \FILTER_NULL_ON_FAILURE,
			)
		) ?? 0;
		$node      = dws_lowc_get_order_node( $parent_id );
		if ( \is_null( $node ) ) {
			$this->notices->add( new Notice( 'linked-child-missing-parent', esc_html( \sprintf( /* translators: %d: The parent order's id. */ __( 'There is no order %d to create a linked child for.', 'linked-orders-for-woocommerce' ), $parent_id ) ) ) );
			wp_safe_redirect( wp_get_referer() ?: admin_url( 'index.php' ) );
			exit;
		}

		if ( ! $node->can_create_child() ) {
			$this->notices->add( new Notice( 'linked-child-not-allowed', esc_html( self::get_refusal( $node ) ) ) );
		} else {
			$child = dws_lowc_create_linked_child( $parent_id );
			$order = \is_int( $child ) ? wc_get_order( $child ) : null;
			if ( $order instanceof \WC_Order ) {
				wp_safe_redirect( $order->get_edit_order_url() );
				exit;
			}

			$reason = is_wp_error( $child ) ? $child->get_error_message() : __( 'Its creator returned no order.', 'linked-orders-for-woocommerce' );
			$this->notices->add( new Notice( 'linked-child-failed', esc_html( \sprintf( /* translators: 1: The order type's singular name. 2: The parent order's number. 3: Why the child could not be created. */ __( '%1$s #%2$s could not get a linked child: %3$s', 'linked-orders-for-woocommerce' ), OrderTypes::get_singular_name( $node->get_post_type() ), $node->get_order()->get_order_number(), $reason ) ) ) );
		}

		wp_safe_redirect( wp_get_referer() ?: $node->get_order()->get_edit_order_url() );
		exit;
	}

	// endregion
}
