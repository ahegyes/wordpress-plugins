<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments;

use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Lists internal comments on WordPress's Comments screen under a view of their own, for users who may list them, and keeps them out of its other views, its counts and the actions they do not support.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class CommentsScreen implements ComponentInterface {
	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_comments_view' ) );
		add_filter( 'submenu_file', array( $this, 'mark_comments_view' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_style' ) );
		add_filter( 'admin_comment_types_dropdown', array( $this, 'add_comment_type' ) );
		add_filter( 'comments_list_table_query_args', array( $this, 'filter_list_table_args' ) );
		add_action( 'pre_get_comments', array( $this, 'count_dropdown_type' ) );
		add_filter( 'comment_status_links', array( $this, 'filter_status_links' ) );
		add_filter( 'the_title', array( $this, 'name_order' ), 10, 2 );
		add_filter( 'comment_class', array( $this, 'mark_order_comment' ), 10, 4 );
		// After WooCommerce, which answers with counts of its own at the default priority.
		add_filter( 'wp_count_comments', array( $this, 'count_listed_comments' ), 20, 2 );
		add_filter( 'the_comments', array( $this, 'filter_list_table_comments' ), 10, 2 );
		add_filter( 'comment_row_actions', array( $this, 'filter_row_actions' ), \PHP_INT_MAX, 2 );
		add_filter( 'bulk_actions-edit-comments', array( $this, 'filter_bulk_actions' ), \PHP_INT_MAX );
	}

	// endregion

	// region HOOKS

	/**
	 * Adds the Comments screen's internal comments view to the Comments menu, for users who may list internal comments.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public function add_comments_view(): void {
		add_comments_page( __( 'Internal Comments', 'internal-comments' ), __( 'Internal Comments', 'internal-comments' ), Capabilities::SEE_TABLE, 'edit-comments.php?comment_type=' . Comments::TYPE );
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
		if ( current_user_can( Capabilities::SEE_TABLE ) ) {
			$types[ Comments::TYPE ] = __( 'Internal Comments', 'internal-comments' );
		}

		return $types;
	}

	/**
	 * Lists internal comments under their type's view of the Comments screen, which WordPress's other views leave out, and leaves them out of that view, trash included, for users who may not list them.
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
		if ( ! current_user_can( Capabilities::SEE_TABLE ) ) {
			// Every other view already leaves them out, and WooCommerce leaves product reviews out of no query that names types to leave out.
			if ( Comments::query_names_internal_type( $args ) ) {
				$args['type__not_in'] = Comments::exclude_internal_type( $args['type__not_in'] ?? array() );
			}

			return $args;
		}

		// The list table queries its All and Mine views under an empty status.
		if ( Comments::TYPE === ( $args['type'] ?? '' ) && \in_array( $args['status'] ?? '', array( '', 'all' ), true ) ) {
			$args['status'] = Comments::STATUS;
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
				static fn ( mixed $comment ): bool => ! $comment instanceof \WP_Comment || Comments::TYPE !== $comment->comment_type || current_user_can( 'see_dws_internal_comment', (int) $comment->comment_ID )
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
		return Comments::TYPE === $comment->comment_type ? \array_intersect_key( $actions, \array_flip( array( 'edit', 'trash', 'untrash', 'delete' ) ) ) : $actions;
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
		return self::is_internal_view() ? \array_intersect_key( $actions, \array_flip( array( 'trash', 'untrash', 'delete' ) ) ) : $actions;
	}

	/**
	 * Returns the Comments screen's counts with a Trash count that leaves out the trashed internal comments its general views leave out.
	 *
	 * WooCommerce answers with counts of its own, which include them, and it and WordPress cache the counts for every user, so the plugin answers with an adjusted copy and leaves their caches alone.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $count   The counts another filter answered with, or an empty array.
	 * @param   int   $post_id The post whose comments are counted, or 0 for every post.
	 *
	 * @return  mixed
	 */
	public function count_listed_comments( mixed $count, int $post_id ): mixed {
		if ( ! self::is_comments_screen() ) {
			return $count;
		}

		if ( ! \is_object( $count ) ) {
			remove_filter( 'wp_count_comments', array( $this, 'count_listed_comments' ), 20 );
			try {
				$count = wp_count_comments( $post_id );
			} finally {
				add_filter( 'wp_count_comments', array( $this, 'count_listed_comments' ), 20, 2 );
			}
		}

		$listed          = get_comments(
			array(
				'post_id' => $post_id,
				'status'  => 'trash',
				'count'   => true,
			)
		);
		$counts          = \get_object_vars( $count );
		$counts['trash'] = \is_int( $listed ) ? $listed : 0;
		return (object) $counts;
	}

	/**
	 * Marks the Internal Comments submenu current on its view of the Comments screen, which WordPress marks as All Comments.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed  $submenu_file The current submenu's slug, or null.
	 * @param   string $parent_file  The current menu's slug.
	 *
	 * @return  mixed
	 */
	public function mark_comments_view( mixed $submenu_file, string $parent_file ): mixed {
		return 'edit-comments.php' === $parent_file && self::is_internal_view() ? 'edit-comments.php?comment_type=' . Comments::TYPE : $submenu_file;
	}

	/**
	 * Lets the Comments screen's type dropdown find internal comments, whose count query names their type under the public statuses.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \WP_Comment_Query $query The comment query.
	 *
	 * @return  void
	 */
	public function count_dropdown_type( \WP_Comment_Query $query ): void {
		$vars = $query->query_vars;
		if ( true === (bool) ( $vars['count'] ?? false ) && Comments::TYPE === ( $vars['type'] ?? null ) && \in_array( $vars['status'] ?? '', array( '', 'all' ), true ) && self::is_comments_screen() && current_user_can( Capabilities::SEE_TABLE ) ) {
			$query->query_vars['status'] = Comments::STATUS;
		}
	}

	/**
	 * Returns the Comments screen's status links on the internal view with only the statuses internal comments take, each counting internal comments for users who may list them.
	 *
	 * WordPress counts its views for every comment and offers Pending, Approved and Spam, which internal comments never reach.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, string> $links The status links, by status.
	 *
	 * @return  array<string, string>
	 */
	public function filter_status_links( array $links ): array {
		if ( ! self::is_internal_view() ) {
			return $links;
		}

		$post_id = \is_numeric( $GLOBALS['post_id'] ?? null ) ? (int) $GLOBALS['post_id'] : 0;
		$listed  = current_user_can( Capabilities::SEE_TABLE );
		$links   = \array_diff_key( $links, \array_flip( array( 'moderated', 'approved', 'spam' ) ) );
		$views   = array(
			'all'   => array(),
			'mine'  => array( 'user_id' => get_current_user_id() ),
			'trash' => array( 'status' => 'trash' ),
		);
		foreach ( $views as $status => $args ) {
			if ( ! isset( $links[ $status ] ) ) {
				continue;
			}

			$count            = $listed ? get_comments( $args + array( 'post_id' => $post_id, 'type' => Comments::TYPE, 'status' => Comments::STATUS, 'count' => true ) ) : 0; // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- A count query's four arguments.
			$links[ $status ] = (string) \preg_replace( '/(<span class="' . $status . '-count">)[^<]*/', '${1}' . number_format_i18n( \is_int( $count ) ? $count : 0 ), $links[ $status ], 1 );
		}

		return $links;
	}

	/**
	 * Returns an order's title on the Comments screen as its number, since an order's title, or its placeholder's in the posts table, does not name it.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $title   The post's title.
	 * @param   mixed $post_id The post's id.
	 *
	 * @return  mixed
	 */
	public function name_order( mixed $title, mixed $post_id = 0 ): mixed {
		if ( ! \is_numeric( $post_id ) || ! self::is_comments_screen() || ! Comments::is_order( (int) $post_id ) ) {
			return $title;
		}

		$order = wc_get_order( (int) $post_id );
		/* translators: %s: The order's number. */
		return $order instanceof \WC_Order ? \sprintf( __( 'Order #%s', 'internal-comments' ), $order->get_order_number() ) : $title;
	}

	/**
	 * Adds a class to the Comments screen's row of a comment on an order, which the screen's style hides the View link of, since orders have no front end.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string> $classes    The row's classes.
	 * @param   mixed         $css_class  The classes the caller asked for.
	 * @param   mixed         $comment_id The comment's id.
	 * @param   mixed         $comment    The comment.
	 *
	 * @return  array<string>
	 */
	public function mark_order_comment( array $classes, mixed $css_class, mixed $comment_id, mixed $comment ): array {
		if ( $comment instanceof \WP_Comment && self::is_comments_screen() && Comments::is_order( (int) $comment->comment_post_ID ) ) {
			$classes[] = 'dws-ic-order-comment';
		}

		return $classes;
	}

	/**
	 * Hides the View link of the Comments screen's rows of comments on orders.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $hook_suffix The admin page's hook suffix.
	 *
	 * @return  void
	 */
	public function enqueue_style( string $hook_suffix ): void {
		if ( 'edit-comments.php' !== $hook_suffix ) {
			return;
		}

		wp_register_style( Plugin::SLUG . '-comments-screen', false ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- A style without a file has no version to bust.
		wp_enqueue_style( Plugin::SLUG . '-comments-screen' );
		wp_add_inline_style( Plugin::SLUG . '-comments-screen', '.dws-ic-order-comment .comments-view-item-link { display: none; }' );
	}

	// endregion

	// region HELPERS

	/**
	 * Returns whether the current screen is the Comments screen.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  bool
	 */
	protected static function is_comments_screen(): bool {
		$screen = \function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return 'edit-comments' === $screen?->id;
	}

	/**
	 * Returns whether the request shows the Comments screen's view of internal comments.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  bool
	 */
	protected static function is_internal_view(): bool {
		return isset( $_GET['comment_type'] ) && \is_string( $_GET['comment_type'] ) && Comments::TYPE === sanitize_key( wp_unslash( $_GET['comment_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reads the screen's view.
	}

	// endregion
}
