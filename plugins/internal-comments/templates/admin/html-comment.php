<?php declare( strict_types=1 );
/**
 * Admin view: an internal comment's list item in the meta box and the quick view, with its text, author, date and the actions the current user may take, followed by its replies.
 *
 * It reads only $args and assigns no WordPress global, since load_template() declares several in its scope.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\InternalComments
 *
 * @var     array{comment: DWS_Internal_Comment, level: int} $args The comment, and its depth in its thread, from 0.
 */

use DeepWebSolutions\InternalComments\Capabilities;

defined( 'ABSPATH' ) || exit;

$dws_ic_comment    = $args['comment'];
$dws_ic_comment_id = (int) $dws_ic_comment->comment_ID;
$dws_ic_classes    = array( 'dws-internal-comment' );
if ( $args['level'] > 0 ) {
	$dws_ic_classes[] = 'internal-comment-reply';
}
if ( true === dws_ic_is_private_comment( $dws_ic_comment_id ) ) {
	$dws_ic_classes[] = 'is-private';
}

/**
 * Filters the classes of an internal comment's list item in the meta box.
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use internal_comments/meta_box/comment_classes.
 *
 * @param       list<string>         $classes The classes the plugin adds to its own.
 * @param       DWS_Internal_Comment $comment The comment.
 */
$dws_ic_extra = apply_filters_deprecated( 'internal_comments/metabox_output/comment_classes', array( array(), $dws_ic_comment ), '2.0.0', 'internal_comments/meta_box/comment_classes' );

/**
 * Filters the classes of an internal comment's list item in the meta box.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   list<string>         $classes The item's classes.
 * @param   DWS_Internal_Comment $comment The comment.
 */
$dws_ic_classes = apply_filters( 'internal_comments/meta_box/comment_classes', array_merge( $dws_ic_classes, is_array( $dws_ic_extra ) ? $dws_ic_extra : array() ), $dws_ic_comment );

$dws_ic_replies = array();
foreach ( (array) $dws_ic_comment->get_children() as $dws_ic_child ) {
	$dws_ic_replies[] = new DWS_Internal_Comment( $dws_ic_child );
}

$dws_ic_author      = get_comment_author( $dws_ic_comment_id );
$dws_ic_keeps_trash = (bool) EMPTY_TRASH_DAYS; // WordPress deletes at once wherever the constant is falsy.
?>
<li id="<?php echo esc_attr( ( 0 === $args['level'] ? 'comment-' : 'reply-' ) . $dws_ic_comment_id ); ?>" class="<?php echo esc_attr( implode( ' ', array_filter( (array) $dws_ic_classes, 'is_string' ) ) ); ?>" data-comment-id="<?php echo esc_attr( (string) $dws_ic_comment_id ); ?>">
	<div class="dws-internal-comment__content">
		<?php echo wp_kses_post( wpautop( wptexturize( (string) $dws_ic_comment->comment_content ) ) ); ?>
	</div>
	<div class="dws-internal-comment__other">
		<p class="dws-internal-comment__meta">
			<?php if ( true === dws_ic_is_private_comment( $dws_ic_comment_id ) ) : ?>
				<span class="dws-internal-comment__private"><?php esc_html_e( 'Private', 'internal-comments' ); ?></span>
			<?php endif; ?>
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: The comment's date. 2: The comment's time. */
					__( 'Added on %1$s at %2$s by', 'internal-comments' ),
					get_comment_date( '', $dws_ic_comment_id ),
					get_comment_time( '', false, true, $dws_ic_comment_id )
				)
			);
			?>
			<?php if ( $dws_ic_comment->can_edit_author() ) : ?>
				<a href="<?php echo esc_url( get_edit_user_link( (int) $dws_ic_comment->user_id ) ); ?>"><?php echo esc_html( $dws_ic_author ); ?></a>
			<?php else : ?>
				<?php echo esc_html( $dws_ic_author ); ?>
			<?php endif; ?>
		</p>
		<div class="dws-internal-comment__actions">
			<?php
			/**
			 * Fires before the actions of an internal comment in the meta box.
			 *
			 * @since       1.0.0
			 * @version     2.0.0
			 * @deprecated  2.0.0 Use internal_comments/meta_box/before_comment_actions.
			 *
			 * @param       DWS_Internal_Comment $comment The comment.
			 */
			do_action_deprecated( 'internal_comments/metabox_output/single_comment/actions/start', array( $dws_ic_comment ), '2.0.0', 'internal_comments/meta_box/before_comment_actions' );

			/**
			 * Fires before the actions of an internal comment in the meta box.
			 *
			 * @since   2.0.0
			 * @version 2.0.0
			 *
			 * @param   DWS_Internal_Comment $comment The comment.
			 */
			do_action( 'internal_comments/meta_box/before_comment_actions', $dws_ic_comment );
			?>
			<?php if ( $dws_ic_comment->can_edit() ) : ?>
				<button type="button" class="button-link" data-action="edit" data-comment-id="<?php echo esc_attr( (string) $dws_ic_comment_id ); ?>"><?php esc_html_e( 'Edit', 'internal-comments' ); ?></button>
			<?php endif; ?>
			<?php if ( current_user_can( Capabilities::INSERT ) ) : ?>
				<button type="button" class="button-link" data-action="reply" data-comment-id="<?php echo esc_attr( (string) $dws_ic_comment_id ); ?>"><?php esc_html_e( 'Reply', 'internal-comments' ); ?></button>
			<?php endif; ?>
			<?php if ( $dws_ic_comment->can_trash() ) : ?>
				<button type="button" class="button-link button-link-delete" data-action="trash" data-comment-id="<?php echo esc_attr( (string) $dws_ic_comment_id ); ?>"<?php echo ! $dws_ic_keeps_trash ? ' data-confirm="' . esc_attr__( 'Delete this internal comment permanently? This cannot be undone.', 'internal-comments' ) . '"' : ''; ?>><?php $dws_ic_keeps_trash ? esc_html_e( 'Move to Trash', 'internal-comments' ) : esc_html_e( 'Delete permanently', 'internal-comments' ); ?></button>
			<?php endif; ?>
			<?php
			/**
			 * Fires after the actions of an internal comment in the meta box.
			 *
			 * @since       1.0.0
			 * @version     2.0.0
			 * @deprecated  2.0.0 Use internal_comments/meta_box/after_comment_actions.
			 *
			 * @param       DWS_Internal_Comment $comment The comment.
			 */
			do_action_deprecated( 'internal_comments/metabox_output/single_comment/actions/end', array( $dws_ic_comment ), '2.0.0', 'internal_comments/meta_box/after_comment_actions' );

			/**
			 * Fires after the actions of an internal comment in the meta box.
			 *
			 * @since   2.0.0
			 * @version 2.0.0
			 *
			 * @param   DWS_Internal_Comment $comment The comment.
			 */
			do_action( 'internal_comments/meta_box/after_comment_actions', $dws_ic_comment );
			?>
		</div>
	</div>
</li>
<?php if ( array() !== $dws_ic_replies ) : ?>
	<li id="replies-comment-<?php echo esc_attr( (string) $dws_ic_comment_id ); ?>" class="dws-internal-comment-replies">
		<ul class="dws-internal-comments-replies__list" data-level="<?php echo esc_attr( (string) ( $args['level'] + 1 ) ); ?>">
			<?php
			foreach ( $dws_ic_replies as $dws_ic_reply ) {
				load_template(
					__FILE__,
					false,
					array(
						'comment' => $dws_ic_reply,
						'level'   => $args['level'] + 1,
					)
				);
			}
			?>
		</ul>
	</li>
	<?php
endif;
