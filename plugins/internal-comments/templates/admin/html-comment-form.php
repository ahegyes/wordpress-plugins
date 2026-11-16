<?php declare( strict_types=1 );
/**
 * Admin view: a form of the meta box, the one for a new comment or one the script clones to edit or reply to a comment, with the hooks that print before its buttons.
 *
 * It reads only $args and assigns no WordPress global, since load_template() declares several in its scope.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\InternalComments
 *
 * @var     array{form: 'new'|'edit'|'reply', can_private: bool} $args The form, and whether the current user may mark a new comment private.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="<?php echo 'new' === $args['form'] ? 'dws-add-internal-comment ' : ''; ?>dws-internal-comments-inline-action" data-form="<?php echo esc_attr( $args['form'] ); ?>">
	<p class="dws-internal-comments-inline-action__textarea">
		<?php if ( 'new' === $args['form'] ) : ?>
			<label for="dws-ic-new-comment"><?php esc_html_e( 'New internal comment', 'internal-comments' ); ?></label>
			<textarea id="dws-ic-new-comment" rows="4"></textarea>
		<?php elseif ( 'edit' === $args['form'] ) : ?>
			<label><?php esc_html_e( 'Edit internal comment', 'internal-comments' ); ?> <textarea rows="4"></textarea></label>
		<?php else : ?>
			<label><?php esc_html_e( 'Reply', 'internal-comments' ); ?> <textarea rows="3"></textarea></label>
		<?php endif; ?>
	</p>
	<?php if ( 'new' === $args['form'] && $args['can_private'] ) : ?>
		<p>
			<input type="checkbox" id="dws-ic-new-comment-private" aria-describedby="dws-ic-new-comment-private-description" />
			<label for="dws-ic-new-comment-private"><?php esc_html_e( 'Mark as private', 'internal-comments' ); ?></label>
			<span id="dws-ic-new-comment-private-description" class="description"><?php esc_html_e( 'Only you and the users allowed to read private comments will see it.', 'internal-comments' ); ?></span>
		</p>
	<?php endif; ?>
	<p class="dws-internal-comments-inline-action__actions">
		<?php
		/**
		 * Fires before the buttons of each form of the meta box.
		 *
		 * @since       1.0.0
		 * @version     2.0.0
		 * @deprecated  2.0.0 Use internal_comments/meta_box/before_form_buttons.
		 */
		do_action_deprecated( 'internal_comments/metabox_output/inline_actions', array(), '2.0.0', 'internal_comments/meta_box/before_form_buttons' );

		/**
		 * Fires before the buttons of each form of the meta box.
		 *
		 * @since   2.0.0
		 * @version 2.0.0
		 *
		 * @param   string $form The form, 'new', 'edit' or 'reply'.
		 */
		do_action( 'internal_comments/meta_box/before_form_buttons', $args['form'] );

		if ( 'new' === $args['form'] ) {
			/**
			 * Fires before the buttons of the meta box's form for a new comment.
			 *
			 * @since       1.0.0
			 * @version     2.0.0
			 * @deprecated  2.0.0 Use internal_comments/meta_box/before_new_comment_buttons.
			 */
			do_action_deprecated( 'internal_comments/metabox_output/inline_actions/insert', array(), '2.0.0', 'internal_comments/meta_box/before_new_comment_buttons' );

			/**
			 * Fires before the buttons of the meta box's form for a new comment.
			 *
			 * @since   2.0.0
			 * @version 2.0.0
			 */
			do_action( 'internal_comments/meta_box/before_new_comment_buttons' );
		} elseif ( 'edit' === $args['form'] ) {
			/**
			 * Fires before the buttons of the meta box's form that edits a comment.
			 *
			 * @since       1.0.0
			 * @version     2.0.0
			 * @deprecated  2.0.0 Use internal_comments/meta_box/before_edit_comment_buttons.
			 */
			do_action_deprecated( 'internal_comments/metabox_output/inline_actions/edit', array(), '2.0.0', 'internal_comments/meta_box/before_edit_comment_buttons' );

			/**
			 * Fires before the buttons of the meta box's form that edits a comment.
			 *
			 * @since   2.0.0
			 * @version 2.0.0
			 */
			do_action( 'internal_comments/meta_box/before_edit_comment_buttons' );
		} else {
			/**
			 * Fires before the buttons of the meta box's form that replies to a comment.
			 *
			 * @since       1.0.0
			 * @version     2.0.0
			 * @deprecated  2.0.0 Use internal_comments/meta_box/before_reply_buttons.
			 */
			do_action_deprecated( 'internal_comments/metabox_output/reply', array(), '2.0.0', 'internal_comments/meta_box/before_reply_buttons' );

			/**
			 * Fires before the buttons of the meta box's form that replies to a comment.
			 *
			 * @since   2.0.0
			 * @version 2.0.0
			 */
			do_action( 'internal_comments/meta_box/before_reply_buttons' );
		}
		?>
		<?php if ( 'new' === $args['form'] ) : ?>
			<button type="button" class="button button-primary" data-action="post"><?php esc_html_e( 'Post comment', 'internal-comments' ); ?></button>
		<?php else : ?>
			<button type="button" class="button" data-action="cancel"><?php esc_html_e( 'Cancel', 'internal-comments' ); ?></button>
			<button type="button" class="button button-primary" data-action="save"><?php 'edit' === $args['form'] ? esc_html_e( 'Save comment', 'internal-comments' ) : esc_html_e( 'Post reply', 'internal-comments' ); ?></button>
		<?php endif; ?>
	</p>
</div>
