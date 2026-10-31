<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments;

use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\Migration\MigrationRunner;

\defined( 'ABSPATH' ) || exit;

/**
 * Keeps internal comments under their own status through every way WordPress changes a comment, and records each change to an internal comment's content.
 *
 * The status is what keeps them out of WordPress's queries, counts, feeds and REST API, so a comment stored, approved, held, marked spam or restored from the trash under another status is put back under it. Until the migration has moved the comments v1 stored approved, their type keeps them out of comment queries and feeds instead.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class Comments implements ComponentInterface {
	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		add_action( 'wp_insert_comment', array( $this, 'keep_internal' ) );
		// Before wp_new_comment_notify_postauthor(), which wp_set_comment_status() hooks to mail an approved comment to the post's author.
		add_action( 'wp_set_comment_status', array( $this, 'keep_internal' ), 1 );
		add_action( 'untrash_comment', array( $this, 'prepare_restore' ) );
		add_action( 'untrashed_post_comments', array( $this, 'keep_post_comments_internal' ) );
		add_filter( 'wp_update_comment_data', array( $this, 'filter_update_data' ), \PHP_INT_MAX, 2 );
		add_filter( 'update_comment_metadata', array( $this, 'guard_privacy' ), 10, 3 );
		add_filter( 'delete_comment_metadata', array( $this, 'guard_privacy' ), 10, 3 );
		add_action( 'delete_comment', array( $this, 'keep_replies_private' ) );
		add_action( 'pre_get_comments', array( $this, 'hide_unmoved_comments' ) );
		add_filter( 'comment_feed_where', array( $this, 'hide_unmoved_feed_comments' ) );
	}

	// endregion

	// region HOOKS

	/**
	 * Puts an internal comment stored under another status that would show it, such as one imported from v1, back under the internal status.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @global  \wpdb $wpdb The WordPress database connection.
	 *
	 * @param   int|string $comment_id The comment's id.
	 *
	 * @return  void
	 */
	public function keep_internal( int|string $comment_id ): void {
		global $wpdb;

		$comment = dws_ic_get_comment( $comment_id );
		if ( null === $comment || DWS_IC_COMMENT_TYPE !== $comment->comment_type || \in_array( $comment->comment_approved, array( DWS_IC_COMMENT_STATUS, 'trash', 'post-trashed' ), true ) ) {
			return;
		}

		$wpdb->update( $wpdb->comments, array( 'comment_approved' => DWS_IC_COMMENT_STATUS ), array( 'comment_ID' => (int) $comment->comment_ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- wp_set_comment_status() refuses a status of its own, and the cache is cleaned below.
		clean_comment_cache( (int) $comment->comment_ID );
		wp_update_comment_count( (int) $comment->comment_post_ID );
	}

	/**
	 * Lets WordPress restore a trashed internal comment, which it does only to a status of its own, after which keep_internal() puts it back under the internal status.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int|string $comment_id The comment's id.
	 *
	 * @return  void
	 */
	public function prepare_restore( int|string $comment_id ): void {
		if ( true === dws_ic_is_internal_comment( $comment_id ) ) {
			update_comment_meta( (int) $comment_id, '_wp_trash_meta_status', '0' );
		}
	}

	/**
	 * Puts a restored post's internal comments back under the internal status, which WordPress restores from what it recorded at trashing, approved for a post v1 trashed.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $post_id The restored post's id.
	 *
	 * @return  void
	 */
	public function keep_post_comments_internal( int $post_id ): void {
		$comments = get_comments(
			array(
				'post_id' => $post_id,
				'type'    => DWS_IC_COMMENT_TYPE,
				'status'  => array( 'hold', 'approve', 'spam' ),
				'fields'  => 'ids',
			)
		);
		foreach ( \is_array( $comments ) ? $comments : array() as $comment_id ) {
			$this->keep_internal( $comment_id );
		}
	}

	/**
	 * Keeps every field of an internal comment but its content as stored, and records the content it replaces.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, mixed>|\WP_Error $data    The comment's fields to store, unslashed.
	 * @param   array<string, mixed>           $comment The comment's stored fields, slashed.
	 *
	 * @return  array<string, mixed>|\WP_Error
	 */
	public function filter_update_data( array|\WP_Error $data, array $comment ): array|\WP_Error {
		if ( \is_wp_error( $data ) || DWS_IC_COMMENT_TYPE !== ( $comment['comment_type'] ?? null ) ) {
			return $data;
		}

		$comment_id = (int) filter_var( $comment['comment_ID'] ?? 0, FILTER_VALIDATE_INT );
		$previous   = wp_unslash( $comment['comment_content'] ?? '' );
		if ( ( $data['comment_content'] ?? '' ) !== $previous ) {
			$change_id = \uniqid( (string) get_current_user_id(), true );
			add_comment_meta( $comment_id, '_dws_ic_change_id', wp_slash( $change_id ) );
			add_comment_meta( $comment_id, "_dws_ic_previous_content_$change_id", wp_slash( $previous ) );
			add_comment_meta( $comment_id, "_dws_ic_changed_by_$change_id", get_current_user_id() );
			add_comment_meta( $comment_id, "_dws_ic_changed_time_$change_id", \time() );
		}

		foreach ( array( 'comment_post_ID', 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_author_IP', 'comment_date', 'comment_date_gmt', 'comment_karma', 'comment_approved', 'comment_agent', 'comment_type', 'comment_parent', 'user_id' ) as $field ) {
			$data[ $field ] = wp_unslash( $comment[ $field ] ?? '' );
		}

		return $data;
	}

	/**
	 * Marks the replies of an internal comment marked private as private themselves before WordPress deletes it, as WordPress then hands them to its parent, which would drop the privacy they took from it.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int|string $comment_id The id of the comment WordPress deletes.
	 *
	 * @return  void
	 */
	public function keep_replies_private( int|string $comment_id ): void {
		if ( true !== dws_ic_is_internal_comment( $comment_id ) || true !== filter_var( get_comment_meta( (int) $comment_id, DWS_IC_META_PRIVATE, true ), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE ) ) {
			return;
		}

		$replies = get_comments(
			array(
				'parent' => (int) $comment_id,
				'type'   => DWS_IC_COMMENT_TYPE,
				'status' => 'any',
				'fields' => 'ids',
			)
		);

		// The plugin carries the privacy over, whoever deletes the comment.
		remove_filter( 'update_comment_metadata', array( $this, 'guard_privacy' ) );
		try {
			foreach ( \is_array( $replies ) ? $replies : array() as $reply ) {
				update_comment_meta( (int) $reply, DWS_IC_META_PRIVATE, 'yes' );
			}
		} finally {
			add_filter( 'update_comment_metadata', array( $this, 'guard_privacy' ), 10, 3 );
		}
	}

	/**
	 * Leaves internal comments out of a comment query that names neither their type nor their status, while the migration has not yet moved the ones v1 stored approved.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \WP_Comment_Query $query The comment query.
	 *
	 * @return  void
	 */
	public function hide_unmoved_comments( \WP_Comment_Query $query ): void {
		if ( ! $this->is_move_pending() || dws_ic_query_names_internal_type( $query->query_vars ) || \in_array( DWS_IC_COMMENT_STATUS, (array) ( $query->query_vars['status'] ?? array() ), true ) ) {
			return;
		}

		$query->query_vars['type__not_in'] = dws_ic_exclude_internal_type( $query->query_vars['type__not_in'] ?? array() );
	}

	/**
	 * Leaves internal comments out of comment feeds while the migration has not yet moved the ones v1 stored approved.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @global  \wpdb $wpdb The WordPress database connection.
	 *
	 * @param   string $where The feed query's WHERE clause.
	 *
	 * @return  string
	 */
	public function hide_unmoved_feed_comments( string $where ): string {
		global $wpdb;

		return $this->is_move_pending() ? $where . ( $wpdb->prepare( ' AND %i.comment_type <> %s', $wpdb->comments, DWS_IC_COMMENT_TYPE ) ?? '' ) : $where;
	}

	/**
	 * Refuses a change to whether an internal comment is private by a signed-in user other than its author.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed  $check      Null to let the change proceed, or the result that replaces it.
	 * @param   int    $comment_id The comment's id.
	 * @param   string $meta_key   The meta key changed.
	 *
	 * @return  mixed
	 */
	public function guard_privacy( mixed $check, int $comment_id, string $meta_key ): mixed {
		if ( DWS_IC_META_PRIVATE !== $meta_key || 0 === get_current_user_id() ) {
			return $check;
		}

		$comment = dws_ic_get_comment( $comment_id );

		return null !== $comment && get_current_user_id() !== (int) $comment->user_id ? false : $check;
	}

	// endregion

	// region HELPERS

	/**
	 * Returns whether the migration has yet to move the comments v1 stored approved under the internal status.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  bool
	 */
	protected function is_move_pending(): bool {
		return MigrationRunner::get_revision( Plugin::PREFIX . '_revision' ) < DWS_IC_REVISION_V1_COMMENTS_MOVED;
	}

	// endregion
}
