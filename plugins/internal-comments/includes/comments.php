<?php declare( strict_types=1 );
/**
 * Keeps v1's comment API, which creates and reads internal comments: comments of their own type, stored under a status of their own that WordPress's queries, counts, feeds and REST API leave out.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\InternalComments
 */

use Automattic\WooCommerce\Utilities\OrderUtil;

defined( 'ABSPATH' ) || exit;

/**
 * The comment type of internal comments, which v1 stored them under.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_IC_COMMENT_TYPE = 'dws_internal';

/**
 * The status internal comments are stored under, which no WordPress query of comments includes unless it names it.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_IC_COMMENT_STATUS = 'dws_internal';

/**
 * The comment meta key that holds 'yes' on an internal comment its author marked private.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_IC_META_PRIVATE = 'dws_ic_is_private';

/**
 * Returns the comment that an id, a comment or an array holding a comment_ID names, or null when there is none.
 *
 * @since   1.0.0
 * @version 2.0.0
 *
 * @param   mixed $comment The comment's id, the comment, or an array with its comment_ID.
 *
 * @return  WP_Comment|null
 */
function dws_ic_get_comment( $comment ): ?WP_Comment {
	if ( is_array( $comment ) ) {
		$comment = $comment['comment_ID'] ?? null;
	}

	// get_comment() reads an empty id as the comment of the current loop.
	$comment = $comment instanceof WP_Comment || ( is_numeric( $comment ) && (int) $comment > 0 ) ? get_comment( $comment instanceof WP_Comment ? $comment : (int) $comment ) : null;

	return $comment instanceof WP_Comment ? $comment : null;
}

/**
 * Returns whether a comment is internal, or null when there is no such comment.
 *
 * @since   1.0.0
 * @version 2.0.0
 *
 * @param   mixed $comment The comment's id, the comment, or an array with its comment_ID.
 *
 * @return  bool|null
 */
function dws_ic_is_internal_comment( $comment ): ?bool {
	$comment = dws_ic_get_comment( $comment );

	return null === $comment ? null : DWS_IC_COMMENT_TYPE === $comment->comment_type;
}

/**
 * Returns whether a post's type, or an order's type in any of WooCommerce's storages, is one the settings select for internal comments.
 *
 * @since   1.1.0
 * @version 2.0.0
 *
 * @param   int|null $post_id Optional. The post's or order's id. Default is the current post.
 *
 * @return  bool
 */
function dws_ic_is_selected_post_type( ?int $post_id = null ): bool {
	return in_array( dws_ic_get_post_type( $post_id ), dws_ic_get_selected_post_types(), true );
}

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
function dws_ic_get_post_type( ?int $post_id = null ): ?string {
	$post_type = get_post_type( $post_id );
	$post_id   = $post_id ?? get_the_ID();

	// An order in the order tables has no post, or only a placeholder, so WooCommerce names its type.
	if ( ( false === $post_type || 'shop_order_placehold' === $post_type ) && is_int( $post_id ) && $post_id > 0 && class_exists( OrderUtil::class ) ) {
		$post_type = OrderUtil::get_order_type( $post_id );
	}

	return is_string( $post_type ) && '' !== $post_type ? $post_type : null;
}

/**
 * Stores an internal comment by an author, the current user by default, and returns its id, or null when it could not be stored.
 *
 * @since   1.1.0
 * @version 2.0.0
 *
 * @param   array<string, mixed> $args   Optional. The comment's fields, as wp_insert_comment() takes them unslashed, and its comment_meta. Default is an empty comment on the current post.
 * @param   WP_User|null         $author Optional. The comment's author. Default is the current user.
 *
 * @return  int|null
 */
function dws_ic_create_internal_comment( array $args = array(), ?WP_User $author = null ): ?int {
	$author = $author ?? wp_get_current_user();
	$data   = array(
		'comment_type'     => DWS_IC_COMMENT_TYPE,
		'comment_approved' => DWS_IC_COMMENT_STATUS,
	) + $args + array(
		'user_id'              => $author->ID,
		'comment_author'       => $author->display_name,
		'comment_author_email' => $author->user_email,
		'comment_author_url'   => $author->user_url,
		'comment_content'      => '',
		'comment_post_ID'      => get_the_ID(),
		'comment_parent'       => 0,
		'comment_meta'         => array(),
	);

	/**
	 * Filters the fields of an internal comment before it is stored.
	 *
	 * @since       1.0.0
	 * @version     2.0.0
	 * @deprecated  2.0.0 Use dws_ic/internal_comment/creation_data.
	 *
	 * @param       array<string, mixed> $data The comment's fields.
	 */
	$data = apply_filters_deprecated( 'internal_comments/create_comment_data', array( $data ), '2.0.0', 'dws_ic/internal_comment/creation_data' );

	/**
	 * Filters the fields of an internal comment before it is stored.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, mixed> $data The comment's fields, as wp_insert_comment() takes them unslashed, and its comment_meta.
	 */
	$data = apply_filters( 'dws_ic/internal_comment/creation_data', $data );

	$comment_id = wp_insert_comment( wp_slash( $data ) ); // @phpstan-ignore argument.type (The fields pass through public filters, which wp_insert_comment() accepts in any shape.)

	return false === $comment_id ? null : $comment_id;
}

/**
 * Returns a post's internal comments the current user can see, threaded by default, each wrapped in the plugin's model.
 *
 * @since   1.0.0
 * @version 2.0.0
 *
 * @param   int                  $post_id        The post's id.
 * @param   array<string, mixed> $args           Optional. Further WP_Comment_Query arguments, other than count and fields. Default is the post's internal comments, threaded.
 * @param   bool                 $include_hidden Optional. Whether to include the comments the current user cannot see. Default false.
 *
 * @return  list<DWS_Internal_Comment>
 */
function dws_ic_get_internal_comments( int $post_id, array $args = array(), bool $include_hidden = false ): array {
	$args = array(
		'post_id' => $post_id,
		'type'    => DWS_IC_COMMENT_TYPE,
	) + $args + array(
		'status'       => DWS_IC_COMMENT_STATUS,
		'hierarchical' => 'threaded',
	);
	unset( $args['count'], $args['fields'] );

	$comments = array();
	foreach ( (array) get_comments( $args ) as $comment ) { // @phpstan-ignore argument.type (The caller's arguments are WP_Comment_Query's own.)
		$comment = new DWS_Internal_Comment( $comment );
		if ( $include_hidden || $comment->can_see() ) {
			$comments[] = $comment;
		}
	}

	return $comments;
}

/**
 * Returns the internal comments a trashed post held under a status, as WordPress recorded them when it trashed the post, which the current user can see.
 *
 * @since   1.0.0
 * @version 2.0.0
 *
 * @param   int    $post_id        The trashed post's id.
 * @param   string $status         Optional. The status the comments had, or 'any'. Default is the internal status.
 * @param   bool   $include_hidden Optional. Whether to include the comments the current user cannot see. Default false.
 *
 * @return  list<DWS_Internal_Comment>
 */
function dws_ic_get_internal_comments_from_trash_meta( int $post_id, string $status = DWS_IC_COMMENT_STATUS, bool $include_hidden = false ): array {
	$statuses = get_post_meta( $post_id, '_wp_trash_meta_comments_status', true );
	// v1 recorded the internal comments of a post it trashed as approved.
	$accepted = DWS_IC_COMMENT_STATUS === $status ? array( DWS_IC_COMMENT_STATUS, '1' ) : array( $status );

	$comments = array();
	foreach ( is_array( $statuses ) ? $statuses : array() as $comment_id => $comment_status ) {
		if ( ( 'any' !== $status && ! in_array( $comment_status, $accepted, true ) ) || true !== dws_ic_is_internal_comment( $comment_id ) ) {
			continue;
		}

		$comment = new DWS_Internal_Comment( $comment_id );
		if ( $include_hidden || $comment->can_see() ) {
			$comments[] = $comment;
		}
	}

	return $comments;
}

/**
 * Returns whether an internal comment is private, because its author or the author of one of its ancestors marked it so, or null for a comment that is not internal.
 *
 * @since   1.0.0
 * @version 2.0.0
 *
 * @param   mixed $comment The comment's id, the comment, or an array with its comment_ID.
 *
 * @return  bool|null
 */
function dws_ic_is_private_comment( $comment ): ?bool {
	$owners = dws_ic_get_private_comment_owners( $comment );

	return null === $owners ? null : array() !== $owners;
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
function dws_ic_get_private_comment_owners( $comment ): ?array {
	$comment = dws_ic_get_comment( $comment );
	if ( null === $comment || DWS_IC_COMMENT_TYPE !== $comment->comment_type ) {
		return null;
	}

	$owners = array();
	for ( $node = $comment; null !== $node; $node = dws_ic_get_comment( $node->comment_parent ) ) {
		if ( true === filter_var( get_comment_meta( (int) $node->comment_ID, DWS_IC_META_PRIVATE, true ), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE ) ) {
			$owners[] = (int) $node->user_id;
		}
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
function dws_ic_exclude_internal_type( $excluded ): array {
	$types = array();
	foreach ( (array) $excluded as $type ) {
		if ( '' !== $type ) {
			$types[] = $type;
		}
	}

	$types[] = DWS_IC_COMMENT_TYPE;

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
function dws_ic_query_names_internal_type( array $query_vars ): bool {
	return in_array( DWS_IC_COMMENT_TYPE, array_merge( (array) ( $query_vars['type'] ?? array() ), (array) ( $query_vars['type__in'] ?? array() ) ), true );
}
