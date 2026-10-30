<?php declare( strict_types=1 );
/**
 * Prints the markup of internal comments, which the meta box shows and the plugin's REST routes answer with.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\InternalComments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the markup of a post's internal comments that the current user can see.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   int $post_id The post's id.
 *
 * @return  string
 */
function dws_ic_get_comments_list_html( int $post_id ): string {
	ob_start();
	dws_ic_print_comments_list( $post_id );

	return (string) ob_get_clean();
}

/**
 * Prints a post's internal comments that the current user can see, threaded, or as WordPress recorded them for a trashed post.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   int $post_id The post's id.
 *
 * @return  void
 */
function dws_ic_print_comments_list( int $post_id ): void {
	$comments = 'trash' === get_post_status( $post_id ) ? dws_ic_get_internal_comments_from_trash_meta( $post_id ) : dws_ic_get_internal_comments( $post_id );
	if ( array() !== $comments ) {
		echo '<ul class="dws-internal-comments__list">';
		dws_ic_print_comment_thread( $comments, 0 );
		echo '</ul>';

		return;
	}

	$post_type = get_post_type_object( (string) dws_ic_get_post_type( $post_id ) );
	$singular  = null === $post_type ? null : $post_type->labels->singular_name;
	?>
	<p class="dws-internal-comments__empty">
		<?php
		echo esc_html(
			sprintf(
				/* translators: %s: The post type's singular name. */
				__( 'There are no internal comments on this %s yet.', 'internal-comments' ),
				is_string( $singular ) ? $singular : __( 'item', 'internal-comments' )
			)
		);
		if ( current_user_can( DWS_IC_CAP_INSERT ) ) {
			echo ' ' . esc_html__( 'Be the first to post one!', 'internal-comments' );
		}
		?>
	</p>
	<?php
}

/**
 * Prints internal comments as list items, each followed by the list of its replies the current user can see.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   list<DWS_Internal_Comment> $comments The comments.
 * @param   int                        $level    The comments' depth in their thread, from 0.
 *
 * @return  void
 */
function dws_ic_print_comment_thread( array $comments, int $level ): void {
	foreach ( $comments as $comment ) {
		$classes = array( 'dws-internal-comment' );
		if ( $level > 0 ) {
			$classes[] = 'internal-comment-reply';
		}
		if ( true === dws_ic_is_private_comment( $comment->comment_ID ) ) {
			$classes[] = 'is-private';
		}

		/**
		 * Filters the classes of an internal comment's list item in the meta box.
		 *
		 * @since       1.0.0
		 * @version     2.0.0
		 * @deprecated  2.0.0 Use dws_ic/meta_box/comment_classes.
		 *
		 * @param       list<string>         $classes The classes the plugin adds to its own.
		 * @param       DWS_Internal_Comment $comment The comment.
		 */
		$extra = apply_filters_deprecated( 'internal_comments/metabox_output/comment_classes', array( array(), $comment ), '2.0.0', 'dws_ic/meta_box/comment_classes' );

		/**
		 * Filters the classes of an internal comment's list item in the meta box.
		 *
		 * @since   2.0.0
		 * @version 2.0.0
		 *
		 * @param   list<string>         $classes The item's classes.
		 * @param   DWS_Internal_Comment $comment The comment.
		 */
		$classes = apply_filters( 'dws_ic/meta_box/comment_classes', array_merge( $classes, is_array( $extra ) ? $extra : array() ), $comment );

		$replies = array();
		foreach ( (array) $comment->get_children() as $child ) {
			$child = new DWS_Internal_Comment( $child );
			if ( $child->can_see() ) {
				$replies[] = $child;
			}
		}
		?>
		<li id="<?php echo esc_attr( ( 0 === $level ? 'comment-' : 'reply-' ) . $comment->comment_ID ); ?>" class="<?php echo esc_attr( implode( ' ', array_filter( (array) $classes, 'is_string' ) ) ); ?>" data-comment-id="<?php echo esc_attr( (string) $comment->comment_ID ); ?>">
			<?php dws_ic_print_comment( $comment ); ?>
		</li>
		<?php if ( array() !== $replies ) : ?>
			<li id="replies-comment-<?php echo esc_attr( (string) $comment->comment_ID ); ?>" class="dws-internal-comment-replies">
				<ul class="dws-internal-comments-replies__list" data-level="<?php echo esc_attr( (string) ( $level + 1 ) ); ?>">
					<?php dws_ic_print_comment_thread( $replies, $level + 1 ); ?>
				</ul>
			</li>
		<?php endif; ?>
		<?php
	}
}

/**
 * Prints an internal comment's text, author and date, and the actions the current user may take on it.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   DWS_Internal_Comment $comment The comment.
 *
 * @return  void
 */
function dws_ic_print_comment( DWS_Internal_Comment $comment ): void {
	$comment_id = (int) $comment->comment_ID;
	$author     = get_comment_author( $comment_id );
	?>
	<div class="dws-internal-comment__content">
		<?php echo wp_kses_post( wpautop( wptexturize( (string) $comment->comment_content ) ) ); ?>
	</div>
	<div class="dws-internal-comment__other">
		<p class="dws-internal-comment__meta">
			<?php if ( true === dws_ic_is_private_comment( $comment_id ) ) : ?>
				<span class="dws-internal-comment__private"><?php esc_html_e( 'Private', 'internal-comments' ); ?></span>
			<?php endif; ?>
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: The comment's date. 2: The comment's time. */
					__( 'Added on %1$s at %2$s by', 'internal-comments' ),
					get_comment_date( '', $comment_id ),
					get_comment_time( '', false, true, $comment_id )
				)
			);
			?>
			<?php if ( $comment->can_edit_author() ) : ?>
				<a href="<?php echo esc_url( get_edit_user_link( (int) $comment->user_id ) ); ?>"><?php echo esc_html( $author ); ?></a>
			<?php else : ?>
				<?php echo esc_html( $author ); ?>
			<?php endif; ?>
		</p>
		<div class="dws-internal-comment__actions">
			<?php
			/**
			 * Fires before the actions of an internal comment in the meta box.
			 *
			 * @since       1.0.0
			 * @version     2.0.0
			 * @deprecated  2.0.0 Use dws_ic/meta_box/before_comment_actions.
			 *
			 * @param       DWS_Internal_Comment $comment The comment.
			 */
			do_action_deprecated( 'internal_comments/metabox_output/single_comment/actions/start', array( $comment ), '2.0.0', 'dws_ic/meta_box/before_comment_actions' );

			/**
			 * Fires before the actions of an internal comment in the meta box.
			 *
			 * @since   2.0.0
			 * @version 2.0.0
			 *
			 * @param   DWS_Internal_Comment $comment The comment.
			 */
			do_action( 'dws_ic/meta_box/before_comment_actions', $comment );
			?>
			<?php if ( $comment->can_edit() ) : ?>
				<button type="button" class="button-link" data-action="edit" data-comment-id="<?php echo esc_attr( (string) $comment_id ); ?>"><?php esc_html_e( 'Edit', 'internal-comments' ); ?></button>
			<?php endif; ?>
			<?php if ( current_user_can( DWS_IC_CAP_INSERT ) ) : ?>
				<button type="button" class="button-link" data-action="reply" data-comment-id="<?php echo esc_attr( (string) $comment_id ); ?>"><?php esc_html_e( 'Reply', 'internal-comments' ); ?></button>
			<?php endif; ?>
			<?php if ( $comment->can_trash() ) : ?>
				<button type="button" class="button-link button-link-delete" data-action="trash" data-comment-id="<?php echo esc_attr( (string) $comment_id ); ?>"<?php echo 0 === EMPTY_TRASH_DAYS ? ' data-confirm="' . esc_attr__( 'Delete this internal comment permanently? This cannot be undone.', 'internal-comments' ) . '"' : ''; ?>><?php 0 !== EMPTY_TRASH_DAYS ? esc_html_e( 'Move to Trash', 'internal-comments' ) : esc_html_e( 'Delete permanently', 'internal-comments' ); ?></button>
			<?php endif; ?>
			<?php
			/**
			 * Fires after the actions of an internal comment in the meta box.
			 *
			 * @since       1.0.0
			 * @version     2.0.0
			 * @deprecated  2.0.0 Use dws_ic/meta_box/after_comment_actions.
			 *
			 * @param       DWS_Internal_Comment $comment The comment.
			 */
			do_action_deprecated( 'internal_comments/metabox_output/single_comment/actions/end', array( $comment ), '2.0.0', 'dws_ic/meta_box/after_comment_actions' );

			/**
			 * Fires after the actions of an internal comment in the meta box.
			 *
			 * @since   2.0.0
			 * @version 2.0.0
			 *
			 * @param   DWS_Internal_Comment $comment The comment.
			 */
			do_action( 'dws_ic/meta_box/after_comment_actions', $comment );
			?>
		</div>
	</div>
	<?php
}

/**
 * Prints the buttons of a form of the meta box, after the hooks that print before them: the form for a new comment, or the templates the script clones to edit or reply to one.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $form The form, 'new', 'edit' or 'reply'.
 *
 * @return  void
 */
function dws_ic_print_form_buttons( string $form ): void {
	/**
	 * Fires before the buttons of each form of the meta box.
	 *
	 * @since       1.0.0
	 * @version     2.0.0
	 * @deprecated  2.0.0 Use dws_ic/meta_box/before_form_buttons.
	 */
	do_action_deprecated( 'internal_comments/metabox_output/inline_actions', array(), '2.0.0', 'dws_ic/meta_box/before_form_buttons' );

	/**
	 * Fires before the buttons of each form of the meta box.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $form The form, 'new', 'edit' or 'reply'.
	 */
	do_action( 'dws_ic/meta_box/before_form_buttons', $form );

	if ( 'new' === $form ) {
		/**
		 * Fires before the buttons of the meta box's form for a new comment.
		 *
		 * @since       1.0.0
		 * @version     2.0.0
		 * @deprecated  2.0.0 Use dws_ic/meta_box/before_new_comment_buttons.
		 */
		do_action_deprecated( 'internal_comments/metabox_output/inline_actions/insert', array(), '2.0.0', 'dws_ic/meta_box/before_new_comment_buttons' );

		/**
		 * Fires before the buttons of the meta box's form for a new comment.
		 *
		 * @since   2.0.0
		 * @version 2.0.0
		 */
		do_action( 'dws_ic/meta_box/before_new_comment_buttons' );
		?>
		<button type="button" class="button button-primary" data-action="post"><?php esc_html_e( 'Post comment', 'internal-comments' ); ?></button>
		<?php
		return;
	}

	if ( 'edit' === $form ) {
		/**
		 * Fires before the buttons of the meta box's form that edits a comment.
		 *
		 * @since       1.0.0
		 * @version     2.0.0
		 * @deprecated  2.0.0 Use dws_ic/meta_box/before_edit_comment_buttons.
		 */
		do_action_deprecated( 'internal_comments/metabox_output/inline_actions/edit', array(), '2.0.0', 'dws_ic/meta_box/before_edit_comment_buttons' );

		/**
		 * Fires before the buttons of the meta box's form that edits a comment.
		 *
		 * @since   2.0.0
		 * @version 2.0.0
		 */
		do_action( 'dws_ic/meta_box/before_edit_comment_buttons' );
	} else {
		/**
		 * Fires before the buttons of the meta box's form that replies to a comment.
		 *
		 * @since       1.0.0
		 * @version     2.0.0
		 * @deprecated  2.0.0 Use dws_ic/meta_box/before_reply_buttons.
		 */
		do_action_deprecated( 'internal_comments/metabox_output/reply', array(), '2.0.0', 'dws_ic/meta_box/before_reply_buttons' );

		/**
		 * Fires before the buttons of the meta box's form that replies to a comment.
		 *
		 * @since   2.0.0
		 * @version 2.0.0
		 */
		do_action( 'dws_ic/meta_box/before_reply_buttons' );
	}
	?>
	<button type="button" class="button" data-action="cancel"><?php esc_html_e( 'Cancel', 'internal-comments' ); ?></button>
	<button type="button" class="button button-primary" data-action="save"><?php 'edit' === $form ? esc_html_e( 'Save comment', 'internal-comments' ) : esc_html_e( 'Post reply', 'internal-comments' ); ?></button>
	<?php
}
