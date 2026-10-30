<?php declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Wraps an order of a supported type with its place in a tree of linked orders, which it reads from the order's meta once and saves back to it.
 *
 * It keeps v1's global name, which other plugins type-hint, and calls to it that name no method of its own reach the order.
 *
 * @since   1.0.0
 * @version 2.0.0
 *
 * @mixin   WC_Order
 */
final class DWS_Order_Node {
	// region FIELDS AND CONSTANTS

	/**
	 * The order the node wraps.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     WC_Order
	 */
	protected WC_Order $order;

	/**
	 * The post type object of the order's type, which also exists where orders are stored in WooCommerce's order tables.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
	 *
	 * @var     WP_Post_Type
	 */
	protected WP_Post_Type $post_type;

	/**
	 * Whether the node has read its place in the tree from the order's meta.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     bool
	 */
	protected bool $is_read = false;

	/**
	 * The node's level in its tree, where a root is level 0.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     int
	 */
	protected int $depth = 0;

	/**
	 * The node of the order's parent, or null for a root or a child whose parent was deleted.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     DWS_Order_Node|null
	 */
	protected ?DWS_Order_Node $parent = null;

	/**
	 * The nodes of the order's children that still exist.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     list<DWS_Order_Node>
	 */
	protected array $children = array();

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $order The order or its id.
	 *
	 * @throws  InvalidArgumentException Thrown when the order is missing or of an unsupported type.
	 */
	public function __construct( mixed $order ) {
		$order     = wc_get_order( $order );
		$post_type = $order instanceof WC_Order && true === dws_lowc_is_supported_order( $order ) ? get_post_type_object( $order->get_type() ) : null;
		if ( ! $order instanceof WC_Order || null === $post_type ) {
			throw new InvalidArgumentException( 'The order is missing or of an unsupported type.' );
		}

		$this->order     = $order;
		$this->post_type = $post_type;
	}

	/**
	 * Returns a property of the order through WooCommerce's legacy getter, which reads every order property, or null when the order has none of that name.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $name The property's name.
	 *
	 * @return  mixed
	 */
	public function __get( string $name ): mixed {
		return property_exists( $this->order, $name ) ? $this->order->__get( $name ) : null;
	}

	/**
	 * Calls a public method of the order, or returns null when the order has none of that name.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string       $name      The method's name.
	 * @param   array<mixed> $arguments The method's arguments.
	 *
	 * @return  mixed
	 */
	public function __call( string $name, array $arguments ): mixed {
		$method = array( $this->order, $name );

		return is_callable( $method ) ? $method( ...$arguments ) : null;
	}

	// endregion

	// region GETTERS

	/**
	 * Returns the order the node wraps.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  WC_Order
	 */
	public function get_order(): WC_Order {
		return $this->order;
	}

	/**
	 * Returns the post type object of the order's type.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
	 *
	 * @return  WP_Post_Type
	 */
	public function get_post_type(): WP_Post_Type {
		return $this->post_type;
	}

	/**
	 * Returns the node's level in its tree, where a root is level 0.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  int
	 */
	public function get_depth(): int {
		$this->maybe_read();

		return $this->depth;
	}

	/**
	 * Returns the node of the order's parent, or null for a root or a child whose parent was deleted.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
	 *
	 * @return  DWS_Order_Node|null
	 */
	public function get_parent(): ?DWS_Order_Node {
		$this->maybe_read();

		return $this->parent;
	}

	/**
	 * Returns whether the order's parent exists.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
	 *
	 * @return  bool
	 */
	public function has_parent(): bool {
		return null !== $this->get_parent();
	}

	/**
	 * Returns the nodes of the order's children that still exist.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
	 *
	 * @return  list<DWS_Order_Node>
	 */
	public function get_children(): array {
		$this->maybe_read();

		return $this->children;
	}

	/**
	 * Returns whether any of the order's children exists.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
	 *
	 * @return  bool
	 */
	public function has_children(): bool {
		return array() !== $this->get_children();
	}

	// endregion

	// region SETTERS

	/**
	 * Sets the node's level in its tree, which is saved with the node.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
	 *
	 * @param   int $depth The level, where a root is level 0.
	 *
	 * @return  void
	 */
	public function set_depth( int $depth ): void {
		$this->maybe_read();
		$this->depth = $depth;
	}

	/**
	 * Sets the node's parent, and its level to the one below the parent's.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
	 *
	 * @param   DWS_Order_Node $parent_node The parent's node.
	 *
	 * @return  void
	 */
	public function set_parent( DWS_Order_Node $parent_node ): void {
		$this->maybe_read();
		$this->parent = $parent_node;
		$this->depth  = $parent_node->get_depth() + 1;
	}

	/**
	 * Sets the node's children, keeping only nodes.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
	 *
	 * @param   array<mixed> $children The children's nodes.
	 *
	 * @return  void
	 */
	public function set_children( array $children ): void {
		$this->maybe_read();
		$this->children = array_values( array_filter( $children, static fn ( mixed $child ): bool => $child instanceof self ) );
	}

	// endregion

	// region METHODS

	/**
	 * Adds a child to the node, returning false when the child already has a parent or is this node or one of its ancestors.
	 *
	 * The child must be a root, so it is an ancestor only as the root of this node's tree, and refusing it keeps every tree free of cycles.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
	 *
	 * @param   DWS_Order_Node $child The child's node.
	 *
	 * @return  bool
	 */
	public function add_child( DWS_Order_Node $child ): bool {
		$this->maybe_read();
		if ( $child->has_parent() || $child->get_order()->get_id() === dws_lowc_get_root_order( $this->order )?->get_order()->get_id() ) {
			return false;
		}

		$child->set_parent( $this );
		$this->children[] = $child;

		return true;
	}

	/**
	 * Returns the order's name as its type, number and status, such as "Order #123 - Processing".
	 *
	 * @since   1.0.0
	 * @version 2.0.0
	 *
	 * @return  string
	 */
	public function get_formatted_name(): string {
		/**
		 * Filters the status name in an order's formatted name.
		 *
		 * @since       1.0.0
		 * @version     2.0.0
		 * @deprecated  2.0.0 Use dws_lowc/node/status_name.
		 *
		 * @param       string         $status_name The status name.
		 * @param       WP_Post_Type   $post_type   The post type object of the order's type.
		 * @param       WC_Order       $order       The order.
		 * @param       DWS_Order_Node $node        The order's node.
		 */
		$status_name = apply_filters_deprecated( 'linked_orders_for_woocommerce/node/status_name', array( wc_get_order_status_name( $this->order->get_status() ), $this->post_type, $this->order, $this ), '2.0.0', 'dws_lowc/node/status_name' );

		/**
		 * Filters the status name in an order's formatted name.
		 *
		 * @since   2.0.0
		 * @version 2.0.0
		 *
		 * @param   string         $status_name The status name.
		 * @param   WP_Post_Type   $post_type   The post type object of the order's type.
		 * @param   WC_Order       $order       The order.
		 * @param   DWS_Order_Node $node        The order's node.
		 */
		$status_name = apply_filters( 'dws_lowc/node/status_name', $status_name, $this->post_type, $this->order, $this );
		$name        = sprintf(
			/* translators: 1: The order type's singular name. 2: The order number. 3: The order status's name. */
			__( '%1$s #%2$s - %3$s', 'linked-orders-for-woocommerce' ),
			dws_lowc_get_singular_name( $this->post_type ),
			$this->order->get_order_number(),
			$status_name
		);

		/**
		 * Filters an order's formatted name.
		 *
		 * @since       1.0.0
		 * @version     2.0.0
		 * @deprecated  2.0.0 Use dws_lowc/node/formatted_name.
		 *
		 * @param       string         $name The formatted name.
		 * @param       DWS_Order_Node $node The order's node.
		 */
		$name = apply_filters_deprecated( 'linked_orders_for_woocommerce/node/get_formatted_name', array( $name, $this ), '2.0.0', 'dws_lowc/node/formatted_name' );

		/**
		 * Filters an order's formatted name.
		 *
		 * @since   2.0.0
		 * @version 2.0.0
		 *
		 * @param   string         $name The formatted name.
		 * @param   DWS_Order_Node $node The order's node.
		 */
		return apply_filters( 'dws_lowc/node/formatted_name', $name, $this );
	}

	/**
	 * Returns whether a user may create a child of the order: the user may edit the order and create linked children, the order is above the maximum depth, and its status allows a new child.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
	 *
	 * @param   int|null $user_id The user's id, or null for the current user.
	 *
	 * @return  bool
	 */
	public function can_create_child( ?int $user_id = null ): bool {
		$user       = $user_id ?? get_current_user_id();
		$permission = user_can( $user, DWS_LOWC_CAP_CREATE_LINKED_CHILDREN, $this->order->get_id() ) && user_can( $user, 'edit_shop_orders', $this->order->get_id() );
		$depth      = dws_lowc_get_max_depth() > $this->get_depth();
		$status     = $this->order->has_status( dws_lowc_get_valid_statuses_for_new_child( $this->post_type->name, $this->order ) );

		/**
		 * Filters whether a user may create a child of an order.
		 *
		 * @since       1.0.0
		 * @version     2.0.0
		 * @deprecated  2.0.0 Use dws_lowc/node/can_create_child.
		 *
		 * @param       bool           $can_create Whether the user may create a child.
		 * @param       int|null       $user_id    The user's id, or null for the current user.
		 * @param       WC_Order       $order      The order.
		 * @param       DWS_Order_Node $node       The order's node.
		 */
		$can_create = apply_filters_deprecated( 'linked_orders_for_woocommerce/node/can_create_child', array( $permission && $depth && $status, $user_id, $this->order, $this ), '2.0.0', 'dws_lowc/node/can_create_child' );

		/**
		 * Filters whether a user may create a child of an order.
		 *
		 * @since   2.0.0
		 * @version 2.0.0
		 *
		 * @param   bool           $can_create Whether the user may create a child.
		 * @param   int|null       $user_id    The user's id, or null for the current user.
		 * @param   WC_Order       $order      The order.
		 * @param   DWS_Order_Node $node       The order's node.
		 */
		return apply_filters( 'dws_lowc/node/can_create_child', $can_create, $user_id, $this->order, $this );
	}

	/**
	 * Saves the node's depth, parent and children to the order's meta, as v1 stored them, and saves the order.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public function save(): void {
		$this->order->update_meta_data( '_dws_lo_depth', (string) $this->get_depth() );
		$this->order->update_meta_data( '_dws_lo_parent', (string) $this->get_parent()?->get_order()->get_id() );
		$this->order->update_meta_data( '_dws_lo_children', array_map( static fn ( DWS_Order_Node $child ): int => $child->get_order()->get_id(), $this->get_children() ) );
		$this->order->save();
	}

	// endregion

	// region HELPERS

	/**
	 * Reads the node's depth, parent and children from the order's meta, once.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	protected function maybe_read(): void {
		if ( $this->is_read ) {
			return;
		}

		$this->is_read = true;
		$this->depth   = filter_var( $this->order->get_meta( '_dws_lo_depth' ), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE ) ?? 0;
		if ( $this->depth > 0 ) {
			$this->parent = dws_lowc_get_order_node( filter_var( $this->order->get_meta( '_dws_lo_parent' ), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE ) );
		}

		$children       = $this->order->get_meta( '_dws_lo_children' );
		$children       = is_array( $children ) ? filter_var( $children, FILTER_VALIDATE_INT, FILTER_REQUIRE_ARRAY | FILTER_NULL_ON_FAILURE ) : array();
		$this->children = array_values( array_filter( array_map( static fn ( ?int $id ): ?DWS_Order_Node => dws_lowc_get_order_node( $id ), $children ) ) );
	}

	// endregion
}
