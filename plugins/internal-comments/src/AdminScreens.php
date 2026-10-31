<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments;

use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Shows internal comments on WordPress's own screens: a column with a quick view in the lists of the selected post types, a view of the Comments screen, and a comment's revisions and privacy on its edit screen.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class AdminScreens implements ComponentInterface {
	// region FIELDS AND CONSTANTS

	/**
	 * The id of the posts list column.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string COLUMN = 'dws-internal-comments';

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
		foreach ( dws_ic_get_selected_post_types() as $post_type ) {
			// WooCommerce rebuilds its orders' and coupons' columns on this hook at the default priority.
			add_filter( "manage_{$post_type}_posts_columns", array( $this, 'add_posts_column' ), 20 );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_cell' ), 10, 2 );

			// WooCommerce's order tables list an order type on a screen of its own, which other post types never reach.
			$orders_screen = 'woocommerce_page_wc-orders' . ( 'shop_order' === $post_type ? '' : "--$post_type" );
			add_filter( "manage_{$orders_screen}_columns", array( $this, 'add_orders_column' ) );
			add_action( "manage_{$orders_screen}_custom_column", array( $this, 'render_order_cell' ), 10, 2 );
		}
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_list_assets' ) );
		add_action( 'admin_menu', array( $this, 'add_comments_view' ) );
		add_filter( 'admin_comment_types_dropdown', array( $this, 'add_comment_type' ) );
		add_filter( 'comments_list_table_query_args', array( $this, 'filter_list_table_args' ) );
		add_filter( 'the_comments', array( $this, 'filter_list_table_comments' ), 10, 2 );
		add_filter( 'comment_row_actions', array( $this, 'filter_row_actions' ), \PHP_INT_MAX, 2 );
		add_filter( 'bulk_actions-edit-comments', array( $this, 'filter_bulk_actions' ), \PHP_INT_MAX );
		add_action( 'add_meta_boxes_comment', array( $this, 'add_revisions_meta_box' ) );
		add_filter( 'edit_comment_misc_actions', array( $this, 'add_privacy_field' ), 10, 2 );
	}

	// endregion

	// region METHODS

	/**
	 * Returns a list's columns with the internal comments column after the comments column, or after the title where the list has none, for a selected post type and a user who may see the column.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, string> $columns   The list's columns.
	 * @param   string                $post_type The list's post type.
	 *
	 * @return  array<string, string>
	 */
	public function add_column( array $columns, string $post_type ): array {
		if ( ! \in_array( $post_type, dws_ic_get_selected_post_types(), true ) || ! current_user_can( DWS_IC_CAP_SEE_COLUMN ) ) {
			return $columns;
		}

		$preceding = isset( $columns['comments'] ) ? 'comments' : 'title';

		/**
		 * Filters the column the internal comments column follows in a posts list.
		 *
		 * @since       1.0.0
		 * @version     2.0.0
		 * @deprecated  2.0.0 Use dws_ic/list_table/preceding_column.
		 *
		 * @param       string      $preceding The id of the column it follows.
		 * @param       string|null $post_type The list's post type.
		 */
		$preceding = apply_filters_deprecated( 'internal_comments/wp_table_output/insert_after', array( $preceding, $post_type ), '2.0.0', 'dws_ic/list_table/preceding_column' );

		/**
		 * Filters the column the internal comments column follows in a posts list, which it ends where that column is missing.
		 *
		 * @since   2.0.0
		 * @version 2.0.0
		 *
		 * @param   string $preceding The id of the column it follows.
		 * @param   string $post_type The list's post type.
		 */
		$preceding = apply_filters( 'dws_ic/list_table/preceding_column', $preceding, $post_type );

		$position = \array_search( $preceding, \array_keys( $columns ), true );
		$position = false === $position ? \count( $columns ) : $position + 1;

		return \array_slice( $columns, 0, $position, true ) + array( self::COLUMN => __( 'Internal Comments', 'internal-comments' ) ) + \array_slice( $columns, $position, null, true );
	}

	// endregion

	// region HOOKS

	/**
	 * Adds the internal comments column to the list of the post type whose columns hook runs.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, string> $columns The list's columns.
	 *
	 * @return  array<string, string>
	 */
	public function add_posts_column( array $columns ): array {
		// One callback serves every selected post type, so the running hook names the list's.
		return 1 === \preg_match( '/^manage_(.+)_posts_columns$/', (string) current_filter(), $matches ) ? $this->add_column( $columns, $matches[1] ) : $columns;
	}

	/**
	 * Adds the internal comments column to the order tables' list of the order type whose columns hook runs.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, string> $columns The list's columns.
	 *
	 * @return  array<string, string>
	 */
	public function add_orders_column( array $columns ): array {
		if ( 1 !== \preg_match( '/^manage_woocommerce_page_wc-orders(?:--(.+))?_columns$/', (string) current_filter(), $matches ) ) {
			return $columns;
		}

		return $this->add_column( $columns, $matches[1] ?? 'shop_order' );
	}

	/**
	 * Prints an order's cell of the internal comments column in the order tables' list.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $column The column's id.
	 * @param   mixed  $order  The row's order.
	 *
	 * @return  void
	 */
	public function render_order_cell( string $column, mixed $order ): void {
		if ( $order instanceof \WC_Abstract_Order ) {
			$this->render_cell( $column, $order->get_id() );
		}
	}

	/**
	 * Prints a post's cell of the internal comments column: the number of its comments the user can see, linked to them on the Comments screen where the user may list them, and a button that opens their quick view.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $column  The column's id.
	 * @param   int    $post_id The row's post id.
	 *
	 * @return  void
	 */
	public function render_cell( string $column, int $post_id ): void {
		if ( self::COLUMN !== $column ) {
			return;
		}

		$is_trashed = 'trash' === get_post_status( $post_id );
		if ( $is_trashed ) {
			$count = \count( dws_ic_get_internal_comments_from_trash_meta( $post_id ) );
		} elseif ( current_user_can( DWS_IC_CAP_SEE ) && current_user_can( DWS_IC_CAP_SEE_PRIVATE ) && current_user_can( 'edit_post', $post_id ) ) {
			// A user who sees every comment of the post needs no check per comment.
			$count = \count(
				get_comments(
					array(
						'post_id' => $post_id,
						'type'    => DWS_IC_COMMENT_TYPE,
						'status'  => DWS_IC_COMMENT_STATUS,
						'fields'  => 'ids',
					)
				)
			);
		} else {
			$count = \count( dws_ic_get_internal_comments( $post_id, array( 'hierarchical' => false ) ) );
		}
		if ( 0 === $count ) {
			echo '<span aria-hidden="true">&#8212;</span><span class="screen-reader-text">' . esc_html__( 'No internal comments', 'internal-comments' ) . '</span>';

			return;
		}

		$label = \sprintf(
			/* translators: %s: The number of internal comments. */
			_n( '%s internal comment', '%s internal comments', $count, 'internal-comments' ),
			number_format_i18n( $count )
		);

		$number  = '<span class="comment-count" aria-hidden="true">' . esc_html( number_format_i18n( $count ) ) . '</span><span class="screen-reader-text">' . esc_html( $label ) . '</span>';
		$popover = 'dws-ic-quick-view-' . $post_id;
		$link    = add_query_arg(
			array(
				'p'            => $post_id,
				'comment_type' => DWS_IC_COMMENT_TYPE,
			),
			admin_url( 'edit-comments.php' )
		);
		?>
		<div class="post-com-count-wrapper dws-ic-column">
			<?php if ( ! $is_trashed && current_user_can( DWS_IC_CAP_SEE_TABLE ) ) : ?>
				<a class="post-com-count" href="<?php echo esc_url( $link ); ?>"><?php echo $number; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?></a>
			<?php else : ?>
				<span class="post-com-count"><?php echo $number; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?></span>
			<?php endif; ?>
			<button type="button" class="button-link" popovertarget="<?php echo esc_attr( $popover ); ?>"><?php esc_html_e( 'Quick view', 'internal-comments' ); ?></button>
			<div id="<?php echo esc_attr( $popover ); ?>" class="dws-ic-quick-view" popover role="dialog" aria-label="<?php echo esc_attr( $label ); ?>" data-post-id="<?php echo esc_attr( (string) $post_id ); ?>">
				<button type="button" class="button-link dws-ic-quick-view__close" popovertarget="<?php echo esc_attr( $popover ); ?>" popovertargetaction="hide"><?php esc_html_e( 'Close', 'internal-comments' ); ?></button>
				<div class="dws-ic-quick-view__comments" aria-live="polite"><?php esc_html_e( 'Loading…', 'internal-comments' ); ?></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Loads the quick view's script and stylesheet on the list of a selected post type, for a user who may see its column.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $hook_suffix The admin screen's hook suffix.
	 *
	 * @return  void
	 */
	public function enqueue_list_assets( string $hook_suffix ): void {
		$screen    = \function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$post_type = null === $screen || '' === $screen->post_type ? 'post' : $screen->post_type;
		if ( 1 === \preg_match( '/^woocommerce_page_wc-orders(?:--(.+))?$/', $hook_suffix, $matches ) && ! isset( $_GET['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tells the list from the edit screen.
			$post_type = $matches[1] ?? 'shop_order';
		} elseif ( 'edit.php' !== $hook_suffix ) {
			return;
		}

		if ( ! \in_array( $post_type, dws_ic_get_selected_post_types(), true ) || ! current_user_can( DWS_IC_CAP_SEE_COLUMN ) ) {
			return;
		}

		if ( dws_ic_register_script( Plugin::SLUG . '-quick-view', 'assets/build/quick-view.js', array( 'strategy' => 'defer' ) ) ) {
			wp_enqueue_script( Plugin::SLUG . '-quick-view' );
		}
		if ( dws_ic_register_style( Plugin::SLUG . '-quick-view', 'assets/build/quick-view.css' ) ) {
			wp_enqueue_style( Plugin::SLUG . '-quick-view' );
		}
	}

	/**
	 * Adds the Comments screen's internal comments view to the Comments menu, for users who may list internal comments.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public function add_comments_view(): void {
		add_comments_page( __( 'Internal Comments', 'internal-comments' ), __( 'Internal Comments', 'internal-comments' ), DWS_IC_CAP_SEE_TABLE, 'edit-comments.php?comment_type=' . DWS_IC_COMMENT_TYPE );
	}

	/**
	 * Adds internal comments to the Comments screen's types, for users who may list them.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, string> $types The comment types, keyed by type.
	 *
	 * @return  array<string, string>
	 */
	public function add_comment_type( array $types ): array {
		if ( current_user_can( DWS_IC_CAP_SEE_TABLE ) ) {
			$types[ DWS_IC_COMMENT_TYPE ] = __( 'Internal Comments', 'internal-comments' );
		}

		return $types;
	}

	/**
	 * Lists internal comments under their type's view of the Comments screen, which WordPress's All view leaves out by their status, and leaves them out of every view, trash included, for users who may not list them.
	 *
	 * It marks the query, so the rows a user cannot see are left out of this query alone.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, mixed> $args The list table's comment query arguments.
	 *
	 * @return  array<string, mixed>
	 */
	public function filter_list_table_args( array $args ): array {
		$args['dws_ic_list_table'] = true;
		if ( ! current_user_can( DWS_IC_CAP_SEE_TABLE ) ) {
			$excluded             = $args['type__not_in'] ?? array();
			$args['type__not_in'] = \array_merge( \is_array( $excluded ) ? $excluded : array( $excluded ), array( DWS_IC_COMMENT_TYPE ) );

			return $args;
		}

		// The list table queries its All and Mine views under an empty status.
		if ( DWS_IC_COMMENT_TYPE === ( $args['type'] ?? '' ) && \in_array( $args['status'] ?? '', array( '', 'all' ), true ) ) {
			$args['status'] = DWS_IC_COMMENT_STATUS;
		}

		return $args;
	}

	/**
	 * Leaves the internal comments the current user cannot see out of the Comments screen's rows.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<mixed>      $comments The queried comments.
	 * @param   \WP_Comment_Query $query    The query.
	 *
	 * @return  array<mixed>
	 */
	public function filter_list_table_comments( array $comments, \WP_Comment_Query $query ): array {
		if ( true !== ( $query->query_vars['dws_ic_list_table'] ?? null ) ) {
			return $comments;
		}

		return \array_values(
			\array_filter(
				$comments,
				static fn ( mixed $comment ): bool => ! $comment instanceof \WP_Comment || DWS_IC_COMMENT_TYPE !== $comment->comment_type || current_user_can( 'see_dws_internal_comment', (int) $comment->comment_ID )
			)
		);
	}

	/**
	 * Keeps only the row actions an internal comment supports on the Comments screen: editing it, and trashing, restoring or deleting it.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, string> $actions The row's actions.
	 * @param   \WP_Comment           $comment The row's comment.
	 *
	 * @return  array<string, string>
	 */
	public function filter_row_actions( array $actions, \WP_Comment $comment ): array {
		return DWS_IC_COMMENT_TYPE === $comment->comment_type ? \array_intersect_key( $actions, \array_flip( array( 'edit', 'trash', 'untrash', 'delete' ) ) ) : $actions;
	}

	/**
	 * Keeps only the bulk actions internal comments support on their view of the Comments screen.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, string> $actions The bulk actions.
	 *
	 * @return  array<string, string>
	 */
	public function filter_bulk_actions( array $actions ): array {
		$type = isset( $_GET['comment_type'] ) && \is_string( $_GET['comment_type'] ) ? sanitize_key( wp_unslash( $_GET['comment_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reads the screen's view.

		return DWS_IC_COMMENT_TYPE === $type ? \array_intersect_key( $actions, \array_flip( array( 'trash', 'untrash', 'delete' ) ) ) : $actions;
	}

	/**
	 * Adds the revision history meta box to the edit screen of an internal comment the user can see.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $comment The edited comment.
	 *
	 * @return  void
	 */
	public function add_revisions_meta_box( mixed $comment ): void {
		if ( $comment instanceof \WP_Comment && DWS_IC_COMMENT_TYPE === $comment->comment_type && current_user_can( 'see_dws_internal_comment', (int) $comment->comment_ID ) ) {
			add_meta_box( 'dws-internal-comments-revisions', __( 'Revision history', 'internal-comments' ), array( $this, 'render_revisions' ), 'comment', 'normal' );
		}
	}

	/**
	 * Prints an internal comment's earlier texts, newest first, each with who replaced it and when.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \WP_Comment $comment The edited comment.
	 *
	 * @return  void
	 */
	public function render_revisions( \WP_Comment $comment ): void {
		$comment_id = (int) $comment->comment_ID;
		$changes    = \array_reverse( \array_filter( (array) get_comment_meta( $comment_id, '_dws_ic_change_id', false ), 'is_string' ) );
		if ( array() === $changes ) {
			echo '<p>' . esc_html__( 'No earlier versions of this comment are recorded.', 'internal-comments' ) . '</p>';

			return;
		}

		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );
		echo '<ul class="dws-internal-comments__revisions">';
		foreach ( $changes as $change_id ) {
			$content = get_comment_meta( $comment_id, "_dws_ic_previous_content_$change_id", true );
			$user    = get_userdata( filter_var( get_comment_meta( $comment_id, "_dws_ic_changed_by_$change_id", true ), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE ) ?? 0 );
			$time    = filter_var( get_comment_meta( $comment_id, "_dws_ic_changed_time_$change_id", true ), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE ) ?? 0;
			$name    = false === $user ? __( 'an unknown user', 'internal-comments' ) : $user->display_name;
			?>
			<li>
				<?php echo wp_kses_post( wpautop( wptexturize( \is_string( $content ) ? $content : '' ) ) ); ?>
				<p class="description">
					<?php
					echo esc_html(
						\sprintf(
							/* translators: 1: The date of the change. 2: Its time. 3: The name of the user who made it. */
							__( 'Replaced on %1$s at %2$s by %3$s', 'internal-comments' ),
							wp_date( \is_string( $date_format ) ? $date_format : 'Y-m-d', $time ),
							wp_date( \is_string( $time_format ) ? $time_format : 'H:i', $time ),
							$name
						)
					);
					?>
				</p>
			</li>
			<?php
		}
		echo '</ul>';
	}

	/**
	 * Adds whether an internal comment is private to its edit screen's status box, as a choice for its author where they may mark comments private and no comment it replies to already is.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string      $html    The status box's further markup.
	 * @param   \WP_Comment $comment The edited comment.
	 *
	 * @return  string
	 */
	public function add_privacy_field( string $html, \WP_Comment $comment ): string {
		$is_private = dws_ic_is_private_comment( $comment );
		if ( null === $is_private || ( ! $is_private && ! dws_ic_are_enabled_private_comments() ) ) {
			return $html;
		}

		$can_choose = get_current_user_id() === (int) $comment->user_id && current_user_can( DWS_IC_CAP_INSERT_PRIVATE ) && true !== dws_ic_is_private_comment( (int) $comment->comment_parent );
		\ob_start();
		?>
		<div class="misc-pub-section misc-pub-dws-ic-private">
			<?php if ( $can_choose ) : ?>
				<fieldset>
					<legend><?php esc_html_e( 'Private:', 'internal-comments' ); ?></legend>
					<label><input type="radio" name="comment_meta[<?php echo esc_attr( DWS_IC_META_PRIVATE ); ?>]" value="yes" <?php checked( $is_private ); ?> /> <?php echo esc_html_x( 'Yes', 'private comment', 'internal-comments' ); ?></label>
					<label><input type="radio" name="comment_meta[<?php echo esc_attr( DWS_IC_META_PRIVATE ); ?>]" value="no" <?php checked( ! $is_private ); ?> /> <?php echo esc_html_x( 'No', 'private comment', 'internal-comments' ); ?></label>
				</fieldset>
			<?php else : ?>
				<?php esc_html_e( 'Private:', 'internal-comments' ); ?>
				<strong><?php echo $is_private ? esc_html_x( 'Yes', 'private comment', 'internal-comments' ) : esc_html_x( 'No', 'private comment', 'internal-comments' ); ?></strong>
			<?php endif; ?>
		</div>
		<?php
		return $html . \ob_get_clean();
	}

	// endregion
}
