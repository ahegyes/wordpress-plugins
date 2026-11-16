<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments;

use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Shows an internal comment's revisions and privacy on WordPress's comment edit screen, and hides the fields that saving keeps as stored.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class CommentEditScreen implements ComponentInterface {
	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_comment_screen_style' ) );
		add_action( 'add_meta_boxes_comment', array( $this, 'add_revisions_meta_box' ) );
		add_filter( 'edit_comment_misc_actions', array( $this, 'add_privacy_field' ), 10, 2 );
	}

	// endregion

	// region HOOKS

	/**
	 * Hides the author, status and date of an internal comment on WordPress's comment edit screen, since saving keeps them as stored.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $hook_suffix The admin page's hook suffix.
	 *
	 * @return  void
	 */
	public function enqueue_comment_screen_style( string $hook_suffix ): void {
		// The comment screen loads its comment before it prints the page's head.
		$comment = $GLOBALS['comment'] ?? null;
		if ( 'comment.php' !== $hook_suffix || ! $comment instanceof \WP_Comment || Comments::TYPE !== $comment->comment_type ) {
			return;
		}

		wp_register_style( Plugin::SLUG . '-comment-screen', false ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- A style without a file has no version to bust.
		wp_enqueue_style( Plugin::SLUG . '-comment-screen' );
		wp_add_inline_style( Plugin::SLUG . '-comment-screen', '#namediv, #comment-status, .misc-pub-curtime { display: none; }' );
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
		if ( $comment instanceof \WP_Comment && Comments::TYPE === $comment->comment_type && current_user_can( 'see_dws_internal_comment', (int) $comment->comment_ID ) ) {
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
		$revisions = new \DWS_Internal_Comment( $comment )->get_revisions();
		if ( array() === $revisions ) {
			echo '<p>' . esc_html__( 'No earlier versions of this comment are recorded.', 'internal-comments' ) . '</p>';
			return;
		}

		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );
		echo '<ul class="dws-internal-comments__revisions">';
		foreach ( $revisions as $revision ) {
			$user = get_userdata( $revision['user_id'] );
			$name = false === $user ? __( 'an unknown user', 'internal-comments' ) : $user->display_name;
			?>
			<li>
				<?php echo wp_kses_post( wpautop( wptexturize( $revision['content'] ) ) ); ?>
				<p class="description">
					<?php
					echo esc_html(
						\sprintf(
							/* translators: 1: The date of the change. 2: Its time. 3: The name of the user who made it. */
							__( 'Replaced on %1$s at %2$s by %3$s', 'internal-comments' ),
							wp_date( \is_string( $date_format ) ? $date_format : 'Y-m-d', $revision['time'] ),
							wp_date( \is_string( $time_format ) ? $time_format : 'H:i', $revision['time'] ),
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
		if ( \is_null( $is_private ) || ( ! $is_private && ! Options::get_bool( Options::PRIVATE_COMMENTS ) ) ) {
			return $html;
		}

		$can_choose = get_current_user_id() === (int) $comment->user_id && current_user_can( Capabilities::INSERT_PRIVATE ) && true !== dws_ic_is_private_comment( (int) $comment->comment_parent ) && ( $is_private || ! Comments::has_replies( (int) $comment->comment_ID ) );
		\ob_start();
		?>
		<div class="misc-pub-section misc-pub-dws-ic-private">
			<?php if ( $can_choose ) : ?>
				<fieldset>
					<legend><?php esc_html_e( 'Private:', 'internal-comments' ); ?></legend>
					<label><input type="radio" name="comment_meta[<?php echo esc_attr( Comments::META_PRIVATE ); ?>]" value="yes" <?php checked( $is_private ); ?> /> <?php echo esc_html_x( 'Yes', 'private comment', 'internal-comments' ); ?></label>
					<label><input type="radio" name="comment_meta[<?php echo esc_attr( Comments::META_PRIVATE ); ?>]" value="no" <?php checked( ! $is_private ); ?> /> <?php echo esc_html_x( 'No', 'private comment', 'internal-comments' ); ?></label>
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
