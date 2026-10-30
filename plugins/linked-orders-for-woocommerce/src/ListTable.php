<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders;

use Automattic\WooCommerce\Utilities\OrderUtil;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Adds a depth column, filters by depth, guest email and tree, and row actions to the orders list, on either order storage.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class ListTable implements ComponentInterface {
	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		add_action( 'current_screen', array( $this, 'add_depth_column' ) );
		add_action( 'restrict_manage_posts', array( $this, 'render_filters' ), 20 );
		add_action( 'woocommerce_order_list_table_restrict_manage_orders', array( $this, 'render_filters' ), 20 );
		add_filter( 'request', array( $this, 'filter_posts_query' ), 999 );
		add_filter( 'woocommerce_order_list_table_prepare_items_query_args', array( $this, 'filter_orders_query' ), 999 );
		add_filter( 'woocommerce_admin_order_actions', array( $this, 'add_row_actions' ), 999, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	// endregion

	// region HOOKS

	/**
	 * Hooks the depth column into the list of a supported order type, for a user who may see it.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \WP_Screen $screen The current screen.
	 *
	 * @return  void
	 */
	public function add_depth_column( \WP_Screen $screen ): void {
		$type = $this->can_see() ? self::get_list_type( $screen ) : null;
		if ( null === $type ) {
			return;
		}

		add_filter( "manage_{$screen->id}_columns", array( $this, 'insert_depth_column' ) );
		add_action( OrderUtil::custom_orders_table_usage_is_enabled() ? "manage_{$screen->id}_custom_column" : "manage_{$type}_posts_custom_column", array( $this, 'render_depth_cell' ), 10, 2 );
	}

	/**
	 * Inserts the depth column after the order total, or after the column a filter names.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, string> $columns The list's columns, keyed by id.
	 *
	 * @return  array<string, string>
	 */
	public function insert_depth_column( array $columns ): array {
		$type = self::get_list_type( get_current_screen() );

		/**
		 * Filters the id of the column the depth column follows.
		 *
		 * @since       1.0.0
		 * @version     2.0.0
		 * @deprecated  2.0.0 Use dws_lowc/list_table/preceding_column.
		 *
		 * @param       string                $column  The column's id.
		 * @param       string|null           $type    The listed order type.
		 * @param       array<string, string> $columns The list's columns, keyed by id.
		 */
		$preceding = apply_filters_deprecated( 'linked_orders_for_woocommerce/list_table_output/column/insert_after', array( 'order_total', $type, $columns ), '2.0.0', 'dws_lowc/list_table/preceding_column' );

		/**
		 * Filters the id of the column the depth column follows.
		 *
		 * @since   2.0.0
		 * @version 2.0.0
		 *
		 * @param   string                $column  The column's id.
		 * @param   string|null           $type    The listed order type.
		 * @param   array<string, string> $columns The list's columns, keyed by id.
		 */
		$preceding = apply_filters( 'dws_lowc/list_table/preceding_column', $preceding, $type, $columns );
		$position  = \array_search( $preceding, \array_keys( $columns ), true );
		$position  = false === $position ? \count( $columns ) : $position + 1;

		return \array_slice( $columns, 0, $position, true ) + array( 'dws_lo_depth' => _x( 'Depth', 'list table heading', 'linked-orders-for-woocommerce' ) ) + \array_slice( $columns, $position, null, true );
	}

	/**
	 * Prints an order's depth as a root or a child level, counting the root as level 1.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $column        The column's id.
	 * @param   mixed  $post_or_order The row's order id, where orders are stored as posts, or its order.
	 *
	 * @return  void
	 */
	public function render_depth_cell( string $column, mixed $post_or_order ): void {
		$node = 'dws_lo_depth' === $column ? dws_lowc_get_order_node( $post_or_order ) : null;
		if ( null === $node ) {
			return;
		}

		$depth = $node->get_depth();
		$name  = dws_lowc_get_singular_name( $node->get_post_type() );
		/* translators: 1: The order type's singular name. 2: The order's level, where the root is level 1. */
		$label = 0 === $depth ? \sprintf( _x( 'Root %s', 'list table content', 'linked-orders-for-woocommerce' ), $name ) : \sprintf( _x( 'Child %1$s (level %2$d)', 'list table content', 'linked-orders-for-woocommerce' ), $name, $depth + 1 );

		/**
		 * Filters an order's depth label in the orders list.
		 *
		 * @since       1.0.0
		 * @version     2.0.0
		 * @deprecated  2.0.0 Use dws_lowc/list_table/column_content.
		 *
		 * @param       string          $label The label.
		 * @param       \DWS_Order_Node $node  The order's node.
		 */
		$label = apply_filters_deprecated( 'linked_orders_for_woocommerce/list_table_output/column/content', array( $label, $node ), '2.0.0', 'dws_lowc/list_table/column_content' );

		/**
		 * Filters an order's depth label in the orders list.
		 *
		 * @since   2.0.0
		 * @version 2.0.0
		 *
		 * @param   string          $label The label.
		 * @param   \DWS_Order_Node $node  The order's node.
		 */
		echo esc_html( apply_filters( 'dws_lowc/list_table/column_content', $label, $node ) );
	}

	/**
	 * Prints the depth filter, up to the deepest level stored or allowed, and keeps the guest and tree filters across the list's form.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $type The listed order type.
	 *
	 * @return  void
	 */
	public function render_filters( string $type ): void {
		$post_type = $this->can_see() && \in_array( $type, dws_lowc_get_supported_order_types(), true ) ? get_post_type_object( $type ) : null;
		if ( null === $post_type ) {
			return;
		}

		$selected = self::get_depth_filter();
		$name     = dws_lowc_get_singular_name( $post_type );
		?>
		<input type="hidden" name="_guest_customer_user" value="<?php echo esc_attr( self::get_query_arg( '_guest_customer_user' ) ); ?>"/>
		<input type="hidden" name="_dws_linked_order_id" value="<?php echo esc_attr( self::get_query_arg( '_dws_linked_order_id' ) ); ?>"/>
		<select name="_dws_lo_depth" id="dws_lo_depth_filter_dropdown">
			<?php /* translators: %s: The order type's singular name. */ ?>
			<option value=""><?php echo esc_html( \sprintf( __( 'All %s depths', 'linked-orders-for-woocommerce' ), $name ) ); ?></option>
			<?php /* translators: %s: The order type's plural name. */ ?>
			<option value="0" <?php selected( 0, $selected ); ?>><?php echo esc_html( \sprintf( _x( 'Root %s', 'list table filter', 'linked-orders-for-woocommerce' ), $post_type->label ) ); ?></option>
			<?php for ( $depth = 1, $deepest = self::get_deepest_level( $type ); $depth <= $deepest; $depth++ ) : ?>
				<?php /* translators: 1: The order type's plural name. 2: The level, where the root is level 1. */ ?>
				<option value="<?php echo esc_attr( (string) $depth ); ?>" <?php selected( $depth, $selected ); ?>><?php echo esc_html( \sprintf( _x( 'Child %1$s (level %2$d)', 'list table filter', 'linked-orders-for-woocommerce' ), $post_type->label, $depth + 1 ) ); ?></option>
			<?php endfor; ?>
		</select>
		<?php
	}

	/**
	 * Narrows the posts list of a supported order type by the depth, guest and tree filters.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, mixed> $query_vars The list's query variables.
	 *
	 * @return  array<string, mixed>
	 */
	public function filter_posts_query( array $query_vars ): array {
		$type = $GLOBALS['typenow'] ?? null;
		if ( ! $this->can_see() || ! \in_array( $type, dws_lowc_get_supported_order_types(), true ) ) {
			return $query_vars;
		}

		$meta_query = \is_array( $query_vars['meta_query'] ?? null ) ? $query_vars['meta_query'] : array();
		$email      = self::get_query_arg( '_guest_customer_user' );
		if ( '' !== $email ) {
			$meta_query[] = array(
				'key'   => '_billing_email',
				'value' => $email,
			);
		}

		$query_vars['meta_query'] = \array_merge( $meta_query, self::get_depth_clauses() ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The filters are meta values.
		$tree                     = self::get_tree_filter();
		if ( null !== $tree ) {
			$query_vars['post__in'] = $tree;
		}

		return $query_vars;
	}

	/**
	 * Narrows the order-tables list of a supported order type by the depth, guest and tree filters.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, mixed> $args The list's order query arguments.
	 *
	 * @return  array<string, mixed>
	 */
	public function filter_orders_query( array $args ): array {
		if ( ! $this->can_see() || ! \in_array( $args['type'] ?? 'shop_order', dws_lowc_get_supported_order_types(), true ) ) {
			return $args;
		}

		$email = self::get_query_arg( '_guest_customer_user' );
		if ( '' !== $email ) {
			$args['billing_email'] = $email;
		}

		$args['meta_query'] = \array_merge( \is_array( $args['meta_query'] ?? null ) ? $args['meta_query'] : array(), self::get_depth_clauses() ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The filters are meta values.
		$tree               = self::get_tree_filter();
		if ( null !== $tree ) {
			$args['id'] = $tree;
		}

		return $args;
	}

	/**
	 * Adds the actions that list the customer's orders, list the order's tree, and create a child, before the row's other actions.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, array<string, string>> $actions The row's actions, keyed by id.
	 * @param   \WC_Order                            $order   The row's order.
	 *
	 * @return  array<string, array<string, string>>
	 */
	public function add_row_actions( array $actions, \WC_Order $order ): array {
		$node = $this->can_see() ? dws_lowc_get_order_node( $order ) : null;
		if ( null === $node ) {
			return $actions;
		}

		$added     = array();
		$type      = $node->get_post_type();
		$list      = OrderUtil::custom_orders_table_usage_is_enabled() ? admin_url( 'admin.php?page=wc-orders' . ( 'shop_order' === $type->name ? '' : '--' . $type->name ) ) : admin_url( 'edit.php?post_type=' . $type->name );
		$customers = 0 !== $order->get_customer_id() ? array( '_customer_user', (string) $order->get_customer_id() ) : array( '_guest_customer_user', \rawurlencode( $order->get_billing_email() ) );
		if ( '' !== $customers[1] && '' === self::get_query_arg( $customers[0] ) ) {
			$added['view_all_customer_orders'] = array(
				'url'    => add_query_arg( $customers[0], $customers[1], $list ),
				/* translators: %s: The order type's plural name. */
				'name'   => \sprintf( __( 'View all customer %s', 'linked-orders-for-woocommerce' ), $type->label ),
				'action' => 'view-all-customer-orders',
			);
		}

		if ( '' === self::get_query_arg( '_dws_linked_order_id' ) && ( $node->has_parent() || $node->has_children() ) ) {
			$added['view_all_linked_orders'] = array(
				'url'    => add_query_arg( '_dws_linked_order_id', $order->get_id(), $list ),
				/* translators: %s: The order type's plural name. */
				'name'   => \sprintf( __( 'View all linked %s', 'linked-orders-for-woocommerce' ), $type->label ),
				'action' => 'view-all-linked-orders',
			);
		}

		if ( $node->can_create_child() ) {
			$added['create_empty_linked_child'] = array(
				'url'    => wp_nonce_url( admin_url( 'admin-post.php?action=dws_lowc_create_linked_child&parent_id=' . $order->get_id() ), 'dws_create_linked_child' ),
				/* translators: %s: The order type's singular name. */
				'name'   => \sprintf( __( 'Create empty linked %s', 'linked-orders-for-woocommerce' ), dws_lowc_get_singular_name( $type ) ),
				'action' => 'create-empty-linked-child',
			);
		}

		return \array_merge( $added, $actions );
	}

	/**
	 * Loads the script that opens the list actions in a new tab and the actions' icons, on the list of a supported order type.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public function enqueue_assets(): void {
		if ( ! $this->can_see() || null === self::get_list_type( get_current_screen() ) ) {
			return;
		}

		if ( dws_lowc_register_script( Plugin::SLUG . '-orders-list-table', 'assets/build/orders-list-table.js', array( 'strategy' => 'defer' ) ) ) {
			wp_enqueue_script( Plugin::SLUG . '-orders-list-table' );
		}

		if ( dws_lowc_register_style( Plugin::SLUG . '-orders-list-table-icons', 'assets/build/orders-list-table-icons.css' ) ) {
			wp_enqueue_style( Plugin::SLUG . '-orders-list-table-icons' );
		}
	}

	// endregion

	// region HELPERS

	/**
	 * Returns whether the current user may see the plugin's additions to the orders list.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  bool
	 */
	protected function can_see(): bool {
		return current_user_can( DWS_LOWC_CAP_SEE_ORDER_LINKS ) && current_user_can( DWS_LOWC_CAP_SEE_TABLE_COLUMN );
	}

	/**
	 * Returns the supported order type a screen lists, or null for any other screen.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \WP_Screen|null $screen The screen.
	 *
	 * @return  string|null
	 */
	protected static function get_list_type( ?\WP_Screen $screen ): ?string {
		foreach ( dws_lowc_get_supported_order_types() as $type ) {
			if ( null !== $screen && ( OrderUtil::custom_orders_table_usage_is_enabled() ? wc_get_page_screen_id( $type ) : "edit-$type" ) === $screen->id ) {
				return $type;
			}
		}

		return null;
	}

	/**
	 * Returns the deepest level the depth filter offers: the maximum depth, or the deepest stored order of the type where one is deeper.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $type The order type.
	 *
	 * @return  int
	 */
	protected static function get_deepest_level( string $type ): int {
		// The order tables take the depth key only in a meta query, which the posts store refuses, passing a top-level key to WP_Query instead.
		$depth   = OrderUtil::custom_orders_table_usage_is_enabled() ? array( 'meta_query' => array( array( 'key' => '_dws_lo_depth' ) ) ) : array( 'meta_key' => '_dws_lo_depth' ); // phpcs:ignore WordPress.DB.SlowDBQuery -- One row, ordered by the depth meta.
		$deepest = wc_get_orders(
			$depth + array(
				'type'    => $type,
				'limit'   => 1,
				'return'  => 'ids',
				'orderby' => 'meta_value_num',
				'order'   => 'DESC',
			)
		);
		$stored  = \is_array( $deepest ) && array() !== $deepest ? dws_lowc_get_order_node( \reset( $deepest ) )?->get_depth() : null;

		return \max( dws_lowc_get_max_depth(), $stored ?? 0 );
	}

	/**
	 * Returns the depth the list is filtered by, or null when it is not filtered by depth.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  int|null
	 */
	protected static function get_depth_filter(): ?int {
		$depth = \filter_var( self::get_query_arg( '_dws_lo_depth' ), \FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) );

		return false === $depth ? null : $depth;
	}

	/**
	 * Returns the meta query clauses of the depth filter, where a root also matches an order that was never linked.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  list<array<int|string, mixed>>
	 */
	protected static function get_depth_clauses(): array {
		$depth = self::get_depth_filter();
		if ( null === $depth ) {
			return array();
		}

		$clause = array(
			'key'   => '_dws_lo_depth',
			'value' => (string) $depth,
		);

		return array(
			0 === $depth ? array(
				'relation' => 'OR',
				$clause,
				array(
					'key'     => '_dws_lo_depth',
					'compare' => 'NOT EXISTS',
				),
			) : $clause,
		);
	}

	/**
	 * Returns the ids of the tree the list is filtered by, none for an order without a node, or null when the list is not filtered by tree.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  list<int>|null
	 */
	protected static function get_tree_filter(): ?array {
		$pivot = \filter_var( self::get_query_arg( '_dws_linked_order_id' ), \FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
		if ( false === $pivot ) {
			return null;
		}

		$root = dws_lowc_get_root_order( $pivot );

		return null === $root ? array( 0 ) : \array_values( dws_lowc_get_orders_tree( $root->get_order() ) ?? array( 0 ) );
	}

	/**
	 * Returns a list filter from the request, sanitized, or an empty string when it is absent.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $key The filter's query key.
	 *
	 * @return  string
	 */
	protected static function get_query_arg( string $key ): string {
		return isset( $_GET[ $key ] ) && \is_string( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A list filter, which changes nothing.
	}

	// endregion
}
