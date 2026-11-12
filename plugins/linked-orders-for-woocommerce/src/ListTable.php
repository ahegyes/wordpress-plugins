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
		$type = $this->can_see() ? $this->get_list_type( $screen ) : null;
		if ( \is_null( $type ) ) {
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
		$type = $this->get_list_type( get_current_screen() );

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
		if ( \is_null( $node ) ) {
			return;
		}

		$depth = $node->get_depth();
		$label = 0 === $depth ? _x( 'Root order', 'list table content', 'linked-orders-for-woocommerce' ) : \sprintf(
			/* translators: %d: The order's level, where the root is level 1. */
			_x( 'Child order (level %d)', 'list table content', 'linked-orders-for-woocommerce' ),
			$depth + 1
		);

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
		$post_type = \in_array( $type, dws_lowc_get_supported_order_types(), true ) ? get_post_type_object( $type ) : null;
		if ( ! $this->can_see() || \is_null( $post_type ) ) {
			return;
		}

		// The Trash view lists trashed orders, and another plugin's order type may register its statuses only as post statuses, so the deepest stored order is read in every status.
		$depth_key = OrderUtil::custom_orders_table_usage_is_enabled() ? array( 'meta_query' => array( array( 'key' => '_dws_lo_depth' ) ) ) : array( 'meta_key' => '_dws_lo_depth' ); // phpcs:ignore WordPress.DB.SlowDBQuery -- One row, ordered by the depth meta.
		$deepest   = wc_get_orders(
			$depth_key + array(
				'type'    => $type,
				'status'  => \array_values( get_post_stati() ),
				'limit'   => 1,
				'return'  => 'ids',
				'orderby' => 'meta_value_num',
				'order'   => 'DESC',
			)
		);
		$deepest   = \max( dws_lowc_get_max_depth(), ( \is_array( $deepest ) && array() !== $deepest ? dws_lowc_get_order_node( \reset( $deepest ) )?->get_depth() : null ) ?? 0 );
		$selected  = $this->get_depth_filter();
		$email     = $this->get_guest_email();
		$pivot     = $this->get_tree_pivot();
		?>
		<label class="screen-reader-text" for="dws_lo_depth_filter_dropdown"><?php echo esc_html( \sprintf( /* translators: %s: The order type's singular name. */ __( '%s depth', 'linked-orders-for-woocommerce' ), dws_lowc_get_singular_name( $post_type ) ) ); ?></label>
		<input type="hidden" name="_guest_customer_user" value="<?php echo esc_attr( $email ); ?>"/>
		<input type="hidden" name="_dws_linked_order_id" value="<?php echo esc_attr( (string) $pivot ); ?>"/>
		<select name="_dws_lo_depth" id="dws_lo_depth_filter_dropdown">
			<option value=""><?php esc_html_e( 'All depths', 'linked-orders-for-woocommerce' ); ?></option>
			<option value="0" <?php selected( 0, $selected ); ?>><?php echo esc_html_x( 'Root orders', 'list table filter', 'linked-orders-for-woocommerce' ); ?></option>
			<?php for ( $depth = 1; $depth <= $deepest; $depth++ ) : ?>
				<option value="<?php echo esc_attr( (string) $depth ); ?>" <?php selected( $depth, $selected ); ?>><?php echo esc_html( \sprintf( /* translators: %d: The level, where the root is level 1. */ _x( 'Child orders (level %d)', 'list table filter', 'linked-orders-for-woocommerce' ), $depth + 1 ) ); ?></option>
			<?php endfor; ?>
		</select>
		<?php
		$lines = array();
		if ( '' !== $email ) {
			$lines['_guest_customer_user'] = \sprintf(
				/* translators: %s: The billing email address. */
				__( 'Showing orders billed to %s.', 'linked-orders-for-woocommerce' ),
				$email
			);
		}

		if ( ! \is_null( $pivot ) ) {
			$order                         = wc_get_order( $pivot );
			$lines['_dws_linked_order_id'] = \sprintf(
				/* translators: %s: The order number. */
				__( 'Showing orders linked to #%s.', 'linked-orders-for-woocommerce' ),
				$order instanceof \WC_Order ? $order->get_order_number() : $pivot
			);
		}

		foreach ( $lines as $key => $line ) {
			?>
			<span class="dws-linked-orders__filter">
				<?php echo esc_html( $line ); ?>
				<a href="<?php echo esc_url( remove_query_arg( array( $key, 'paged' ) ) ); ?>"><?php esc_html_e( 'Remove this filter', 'linked-orders-for-woocommerce' ); ?></a>
			</span>
			<?php
		}
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

		$clauses = $this->get_depth_clauses();
		$email   = $this->get_guest_email();
		if ( '' !== $email ) {
			$clauses[] = array(
				'key'   => '_billing_email',
				'value' => $email,
			);
		}

		if ( array() !== $clauses ) {
			$query_vars['meta_query'] = $this->add_meta_clauses( $query_vars['meta_query'] ?? null, $clauses ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The filters are meta values.
		}
		$tree = $this->get_tree_filter( $query_vars['post__in'] ?? null );
		if ( ! \is_null( $tree ) ) {
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

		$email = $this->get_guest_email();
		if ( '' !== $email ) {
			$args['billing_email'] = $email;
		}

		// WooCommerce counts the list from its cache only when the query holds none but its own arguments, so an unfiltered list gets no meta query.
		$clauses = $this->get_depth_clauses();
		if ( array() !== $clauses ) {
			$args['meta_query'] = $this->add_meta_clauses( $args['meta_query'] ?? null, $clauses ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The filters are meta values.
		}
		$tree = $this->get_tree_filter( $args['id'] ?? null );
		if ( ! \is_null( $tree ) ) {
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
		if ( ! $this->can_see() ) {
			return $actions;
		}

		$node = dws_lowc_get_order_node( $order );
		if ( \is_null( $node ) ) {
			return $actions;
		}

		$added          = array();
		$new_tab        = __( '(opens in a new tab)' ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- Core's translation of the cue.
		$list           = dws_lowc_get_list_url( $node->get_post_type() );
		$customer_key   = 0 !== $order->get_customer_id() ? '_customer_user' : '_guest_customer_user';
		$customer_value = 0 !== $order->get_customer_id() ? (string) $order->get_customer_id() : \rawurlencode( $order->get_billing_email() );
		if ( '' !== $customer_value && ( ! isset( $_GET[ $customer_key ] ) || '' === $_GET[ $customer_key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A list filter, which changes nothing.
			$name                              = __( 'View all customer orders', 'linked-orders-for-woocommerce' );
			$added['view_all_customer_orders'] = array(
				'url'    => add_query_arg( $customer_key, $customer_value, $list ),
				'name'   => $name,
				'title'  => "{$name} {$new_tab}",
				'action' => 'view-all-customer-orders',
			);
		}

		// A list page builds every row, so the stored links decide, and a tree whose related order was deleted shows as one order.
		$children = $order->get_meta( '_dws_lo_children' );
		if ( \is_null( $this->get_tree_pivot() ) && ( 0 < $node->get_depth() || ( \is_array( $children ) && array() !== $children ) ) ) {
			$name                            = __( 'View all linked orders', 'linked-orders-for-woocommerce' );
			$added['view_all_linked_orders'] = array(
				'url'    => add_query_arg( '_dws_linked_order_id', $order->get_id(), $list ),
				'name'   => $name,
				'title'  => "{$name} {$new_tab}",
				'action' => 'view-all-linked-orders',
			);
		}

		if ( $node->can_create_child() ) {
			$added['create_empty_linked_child'] = array(
				'url'    => dws_lowc_get_create_child_url( $order->get_id() ),
				'name'   => __( 'Create empty linked order', 'linked-orders-for-woocommerce' ),
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
		if ( ! $this->can_see() || \is_null( $this->get_list_type( get_current_screen() ) ) ) {
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
	protected function get_list_type( ?\WP_Screen $screen ): ?string {
		foreach ( dws_lowc_get_supported_order_types() as $type ) {
			if ( ! \is_null( $screen ) && ( OrderUtil::custom_orders_table_usage_is_enabled() ? wc_get_page_screen_id( $type ) : "edit-$type" ) === $screen->id ) {
				return $type;
			}
		}

		return null;
	}

	/**
	 * Returns the depth the list is filtered by, or null when it is not filtered by depth.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  int|null
	 */
	protected function get_depth_filter(): ?int {
		return \filter_var(
			wp_unslash( $_GET['_dws_lo_depth'] ?? '' ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A list filter, which changes nothing.
			\FILTER_VALIDATE_INT,
			array(
				'options' => array( 'min_range' => 0 ),
				'flags'   => \FILTER_NULL_ON_FAILURE,
			)
		);
	}

	/**
	 * Returns the meta query clauses of the depth filter, where a root also matches an order that was never linked.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  list<array<int|string, mixed>>
	 */
	protected function get_depth_clauses(): array {
		$depth = $this->get_depth_filter();
		if ( \is_null( $depth ) ) {
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
	 * Returns a meta query that requires the query in place and each of the list's clauses.
	 *
	 * Joining both under AND holds whatever relation the query in place uses.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed                          $meta_query The meta query already in place, if any.
	 * @param   list<array<int|string, mixed>> $clauses    The list's clauses.
	 *
	 * @return  array<int|string, mixed>
	 */
	protected function add_meta_clauses( mixed $meta_query, array $clauses ): array {
		return \array_merge( array( 'relation' => 'AND' ), \is_array( $meta_query ) && array() !== $meta_query ? array( $meta_query ) : array(), $clauses );
	}

	/**
	 * Returns the ids of the tree the list is filtered by, or null when the list is not filtered by tree.
	 *
	 * The ids narrow to those a search found, and an order without a node yields none.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $found The ids a search already narrowed the list to, if any.
	 *
	 * @return  list<int>|null
	 */
	protected function get_tree_filter( mixed $found ): ?array {
		$pivot = $this->get_tree_pivot();
		if ( \is_null( $pivot ) ) {
			return null;
		}

		$root  = dws_lowc_get_root_order( $pivot );
		$tree  = \is_null( $root ) ? array() : dws_lowc_get_orders_tree( $root->get_order() ) ?? array();
		$found = \is_array( $found ) ? \filter_var( $found, \FILTER_VALIDATE_INT, \FILTER_REQUIRE_ARRAY | \FILTER_NULL_ON_FAILURE ) : null;
		if ( \is_array( $found ) && array() !== $found ) {
			$tree = \array_intersect( $tree, $found );
		}

		// An empty list would drop the filter, so an id no order has keeps the list empty.
		return array() === $tree ? array( 0 ) : \array_values( $tree );
	}

	/**
	 * Returns the id of the order whose tree the list is filtered by, or null when it is not filtered by a tree.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  int|null
	 */
	protected function get_tree_pivot(): ?int {
		return \filter_var(
			wp_unslash( $_GET['_dws_linked_order_id'] ?? '' ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A list filter, which changes nothing.
			\FILTER_VALIDATE_INT,
			array(
				'options' => array( 'min_range' => 1 ),
				'flags'   => \FILTER_NULL_ON_FAILURE,
			)
		);
	}

	/**
	 * Returns the billing email the list is filtered by, or an empty string when it is not filtered by one.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  string
	 */
	protected function get_guest_email(): string {
		return \is_string( $_GET['_guest_customer_user'] ?? null ) ? sanitize_text_field( wp_unslash( $_GET['_guest_customer_user'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A list filter, which changes nothing.
	}

	// endregion
}
