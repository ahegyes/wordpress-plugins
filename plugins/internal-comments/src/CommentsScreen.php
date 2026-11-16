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
		add_filter( 'admin_comment_types_dropdown', array( $this, 'add_comment_type' ) );
		add_filter( 'comments_list_table_query_args', array( $this, 'filter_list_table_args' ) );
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
		$type = isset( $_GET['comment_type'] ) && \is_string( $_GET['comment_type'] ) ? sanitize_key( wp_unslash( $_GET['comment_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reads the screen's view.
		return Comments::TYPE === $type ? \array_intersect_key( $actions, \array_flip( array( 'trash', 'untrash', 'delete' ) ) ) : $actions;
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
		$screen = \function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( 'edit-comments' !== $screen?->id ) {
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

	// endregion
}
