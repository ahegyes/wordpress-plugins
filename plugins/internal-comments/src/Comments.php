<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments;

use Automattic\WooCommerce\Utilities\OrderUtil;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Keeps internal comments under their own status through every way WordPress changes a comment, and records each change to an internal comment's content.
 *
 * The status is what keeps them out of WordPress's queries, counts, feeds and REST API, so a comment stored, approved, held, marked spam or restored from the trash under another status is put back under it. Their type keeps them out of every comment query and feed that names neither it nor the status as well, which holds the comments v1 stored approved until the migration moves them, and a comment whose move back failed.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class Comments implements ComponentInterface {
	// region FIELDS AND CONSTANTS

	/**
	 * The comment type of internal comments, which v1 stored them under.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string TYPE = 'dws_internal';

	/**
	 * The status internal comments are stored under, which no WordPress query of comments includes unless it names it.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string STATUS = 'dws_internal';

	/**
	 * The comment meta key that holds 'yes' on an internal comment its author marked private.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string META_PRIVATE = 'dws_ic_is_private';

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
		add_action( 'wp_insert_comment', array( $this, 'keep_inserted_internal' ) );
		// Before wp_new_comment_notify_postauthor(), which wp_set_comment_status() hooks to mail an approved comment to the post's author.
		add_action( 'wp_set_comment_status', array( $this, 'keep_internal' ), 1 );
		// After every listener of WordPress's own announcement of the change the plugin undoes, such as WooCommerce's comment counts.
		add_action( 'transition_comment_status', array( $this, 'announce_kept_internal' ), \PHP_INT_MAX, 3 );
		add_action( 'untrash_comment', array( $this, 'prepare_restore' ) );
		add_action( 'untrashed_post_comments', array( $this, 'keep_post_comments_internal' ) );
		add_filter( 'wp_update_comment_data', array( $this, 'filter_update_data' ), \PHP_INT_MAX, 2 );
		add_filter( 'update_comment_metadata', array( $this, 'guard_privacy' ), 10, 4 );
		add_filter( 'delete_comment_metadata', array( $this, 'guard_privacy' ), 10, 3 );
		add_action( 'delete_comment', array( $this, 'keep_replies_private' ) );
		add_filter( 'comments_clauses', array( $this, 'leave_out_of_queries' ), 10, 2 );
		add_filter( 'comment_feed_where', array( $this, 'leave_out_of_feeds' ) );
	}

	// endregion

	// region METHODS

	/**
	 * Returns a post's type, or an order's type where WooCommerce stores the order in its order tables, or null when there is no such post or order.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int|null $post_id Optional. The post's or order's id. Default is the current post.
	 *
	 * @return  string|null
	 */
	public static function get_post_type( ?int $post_id = null ): ?string {
		$post_type = get_post_type( $post_id );
		$post_id   = $post_id ?? get_the_ID();

		// An order in the order tables has no post, or only a placeholder, so WooCommerce names its type.
		if ( ( false === $post_type || 'shop_order_placehold' === $post_type ) && \is_int( $post_id ) && $post_id > 0 && \class_exists( OrderUtil::class ) ) {
			$post_type = OrderUtil::get_order_type( $post_id );
		}

		return \is_string( $post_type ) && '' !== $post_type ? $post_type : null;
	}

	/**
	 * Returns the ids of the users who wrote the comments that make an internal comment private, being the comment and those of its ancestors marked private, or null for a comment that is not internal.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $comment The comment's id, the comment, or an array with its comment_ID.
	 *
	 * @return  list<int>|null
	 */
	public static function get_private_owners( $comment ): ?array {
		$comment = dws_ic_get_comment( $comment );
		if ( \is_null( $comment ) || self::TYPE !== $comment->comment_type ) {
			return null;
		}

		$owners = array();
		$node   = $comment;
		while ( ! \is_null( $node ) ) {
			if ( true === \filter_var( get_comment_meta( (int) $node->comment_ID, self::META_PRIVATE, true ), \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE ) ) {
				$owners[] = (int) $node->user_id;
			}

			$node = dws_ic_get_comment( $node->comment_parent );
		}

		return $owners;
	}

	/**
	 * Returns the types a comment query leaves out with internal comments added, keeping every type it already leaves out but the empty one, since that names the comments older WordPress stored without a type.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $excluded The query's type__not_in argument.
	 *
	 * @return  list<mixed>
	 */
	public static function exclude_internal_type( $excluded ): array {
		$types = array();
		foreach ( (array) $excluded as $type ) {
			if ( '' !== $type ) {
				$types[] = $type;
			}
		}

		$types[] = self::TYPE;
		return $types;
	}

	/**
	 * Returns whether a comment query names internal comments by their type, as the plugin's own queries do.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<mixed> $query_vars The comment query's arguments.
	 *
	 * @return  bool
	 */
	public static function query_names_internal_type( array $query_vars ): bool {
		return \in_array( self::TYPE, \array_merge( (array) ( $query_vars['type'] ?? array() ), (array) ( $query_vars['type__in'] ?? array() ) ), true );
	}

	/**
	 * Returns whether any internal comment replies to a comment, whatever its status.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $comment_id The comment's id.
	 *
	 * @return  bool
	 */
	public static function has_replies( int $comment_id ): bool {
		$replies = get_comments(
			array(
				'parent' => $comment_id,
				'type'   => self::TYPE,
				'status' => 'any',
				'count'  => true,
			)
		);
		return \is_int( $replies ) && 0 < $replies;
	}

	// endregion

	// region HOOKS

	/**
	 * Puts an internal comment whose status WordPress changed back under the internal status, before WordPress announces the change.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int|string $comment_id The comment's id.
	 *
	 * @return  void
	 */
	public function keep_internal( int|string $comment_id ): void {
		$this->put_back( $comment_id );
	}

	/**
	 * Puts an internal comment stored under another status that would show it, such as one imported from v1, back under the internal status, and announces the move as a status change, since WordPress announces none for a new comment.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int|string $comment_id The comment's id.
	 *
	 * @return  void
	 */
	public function keep_inserted_internal( int|string $comment_id ): void {
		$replaced = $this->put_back( $comment_id );
		if ( \is_null( $replaced ) ) {
			return;
		}

		$comment = dws_ic_get_comment( $comment_id );
		if ( ! \is_null( $comment ) ) {
			wp_transition_comment_status( self::STATUS, $replaced, $comment );
		}
	}

	/**
	 * Announces the move of an internal comment back under the internal status after WordPress announces the change the plugin undid, so whatever follows status changes, such as WooCommerce's comment counts, ends where the comment is.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int|string $new_status The status WordPress announced.
	 * @param   int|string $old_status The status the comment had before.
	 * @param   mixed      $comment    The comment.
	 *
	 * @return  void
	 */
	public function announce_kept_internal( int|string $new_status, int|string $old_status, mixed $comment ): void {
		if ( ! $comment instanceof \WP_Comment || self::TYPE !== $comment->comment_type || self::STATUS === $new_status ) {
			return;
		}

		$stored = dws_ic_get_comment( (int) $comment->comment_ID );
		if ( ! \is_null( $stored ) && self::STATUS === $stored->comment_approved ) {
			wp_transition_comment_status( self::STATUS, (string) $new_status, $stored );
		}
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
				'type'    => self::TYPE,
				'status'  => array( 'hold', 'approve', 'spam' ),
				'fields'  => 'ids',
			)
		);
		foreach ( \is_array( $comments ) ? $comments : array() as $comment_id ) {
			$this->put_back( $comment_id );
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
		if ( is_wp_error( $data ) || self::TYPE !== ( $comment['comment_type'] ?? null ) ) {
			return $data;
		}

		$comment_id = (int) \filter_var( $comment['comment_ID'] ?? 0, \FILTER_VALIDATE_INT );
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
		if ( true !== dws_ic_is_internal_comment( $comment_id ) || true !== \filter_var( get_comment_meta( (int) $comment_id, self::META_PRIVATE, true ), \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE ) ) {
			return;
		}

		$replies = get_comments(
			array(
				'parent' => (int) $comment_id,
				'type'   => self::TYPE,
				'status' => 'any',
				'fields' => 'ids',
			)
		);

		// The plugin carries the privacy over, whoever deletes the comment.
		remove_filter( 'update_comment_metadata', array( $this, 'guard_privacy' ) );
		try {
			foreach ( \is_array( $replies ) ? $replies : array() as $reply ) {
				update_comment_meta( (int) $reply, self::META_PRIVATE, 'yes' );
			}
		} finally {
			add_filter( 'update_comment_metadata', array( $this, 'guard_privacy' ), 10, 4 );
		}
	}

	/**
	 * Leaves internal comments out of a comment query that names neither their type nor their status.
	 *
	 * The condition goes in the query's WHERE clause, since WooCommerce leaves product reviews out of no query that names types to leave out.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, string> $clauses The query's clauses.
	 * @param   \WP_Comment_Query     $query   The comment query.
	 *
	 * @return  array<string, string>
	 */
	public function leave_out_of_queries( array $clauses, \WP_Comment_Query $query ): array {
		if ( self::query_names_internal_type( $query->query_vars ) || \in_array( self::STATUS, (array) ( $query->query_vars['status'] ?? array() ), true ) ) {
			return $clauses;
		}

		$where            = $clauses['where'] ?? '';
		$clauses['where'] = ( '' === $where ? '' : "$where AND " ) . self::get_type_condition();
		return $clauses;
	}

	/**
	 * Leaves internal comments out of comment feeds.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $where The feed query's WHERE clause.
	 *
	 * @return  string
	 */
	public function leave_out_of_feeds( string $where ): string {
		return $where . ' AND ' . self::get_type_condition();
	}

	/**
	 * Refuses a change to whether an internal comment is private by a signed-in user other than its author, and turning one private once anyone has replied to it.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed  $check      Null to let the change proceed, or the result that replaces it.
	 * @param   int    $comment_id The comment's id.
	 * @param   string $meta_key   The meta key changed.
	 * @param   mixed  $meta_value The value written, or null where the meta is deleted.
	 *
	 * @return  mixed
	 */
	public function guard_privacy( mixed $check, int $comment_id, string $meta_key, mixed $meta_value = null ): mixed {
		if ( self::META_PRIVATE !== $meta_key ) {
			return $check;
		}

		// A comment turns private only before anyone replies, since a reply's author would otherwise own the private thread once the comment above is deleted.
		$turns_private = true === \filter_var( $meta_value, \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE ) && true !== \filter_var( get_comment_meta( $comment_id, self::META_PRIVATE, true ), \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE );
		if ( $turns_private && self::has_replies( $comment_id ) ) {
			return false;
		}

		if ( 0 === get_current_user_id() ) {
			return $check;
		}

		$comment = dws_ic_get_comment( $comment_id );
		return ! \is_null( $comment ) && get_current_user_id() !== (int) $comment->user_id ? false : $check;
	}

	// endregion

	// region HELPERS

	/**
	 * Puts an internal comment stored under a status that would show it back under the internal status, and returns the status it replaced, or null where it moved nothing or its write failed.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @global  \wpdb $wpdb The WordPress database connection.
	 *
	 * @param   int|string $comment_id The comment's id.
	 *
	 * @return  string|null
	 */
	protected function put_back( int|string $comment_id ): ?string {
		global $wpdb;

		$comment = dws_ic_get_comment( $comment_id );
		if ( \is_null( $comment ) || self::TYPE !== $comment->comment_type || \in_array( $comment->comment_approved, array( self::STATUS, 'trash', 'post-trashed' ), true ) ) {
			return null;
		}

		$moved = $wpdb->update( $wpdb->comments, array( 'comment_approved' => self::STATUS ), array( 'comment_ID' => (int) $comment->comment_ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- wp_set_comment_status() refuses a status of its own, and the cache is cleaned below.
		if ( false === $moved ) {
			// WordPress has already approved the comment it is about to mail the post's author about.
			remove_action( 'wp_set_comment_status', 'wp_new_comment_notify_postauthor' );
			return null;
		}

		clean_comment_cache( (int) $comment->comment_ID );
		wp_update_comment_count( (int) $comment->comment_post_ID );
		return $comment->comment_approved;
	}

	/**
	 * Returns the SQL condition that leaves internal comments out of a comment query.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @global  \wpdb $wpdb The WordPress database connection.
	 *
	 * @return  string
	 */
	protected static function get_type_condition(): string {
		global $wpdb;
		return $wpdb->prepare( '%i.comment_type <> %s', $wpdb->comments, self::TYPE ) ?? '';
	}

	// endregion
}
