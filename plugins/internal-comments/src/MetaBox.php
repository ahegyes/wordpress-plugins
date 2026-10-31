<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments;

use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Adds the internal comments meta box to the edit screens of the selected post types, for users who may see it.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class MetaBox implements ComponentInterface {
	// region FIELDS AND CONSTANTS

	/**
	 * The meta box's id, which v1's stylesheet and the edit screen's screen options name.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string ID = 'dws-internal-comments';

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
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	// endregion

	// region HOOKS

	/**
	 * Adds the meta box to the edit screen of a post or order of a selected type, for a user who may see it and edit the post or order.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $screen_id The edit screen's id, which for a post is its type.
	 * @param   mixed  $edited    The edited post, or the edited order on WooCommerce's order tables screen.
	 *
	 * @return  void
	 */
	public function add_meta_box( string $screen_id, mixed $edited ): void {
		$object_id = $this->get_object_id( $edited );
		if ( 0 === $object_id || ! $this->is_shown_for( $object_id ) ) {
			return;
		}

		add_meta_box( self::ID, __( 'Internal Comments', 'internal-comments' ), array( $this, 'render_meta_box' ), $screen_id, 'normal', 'core' );
	}

	/**
	 * Loads the meta box's script and stylesheet on the edit screen of a post that shows it.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $hook_suffix The admin screen's hook suffix.
	 *
	 * @return  void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		// WooCommerce's order tables screen holds its edited order in a global of its own.
		$object_id = \in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ? $this->get_object_id( get_post() ) : $this->get_object_id( $GLOBALS['theorder'] ?? null );
		if ( 0 === $object_id || ! $this->is_shown_for( $object_id ) ) {
			return;
		}

		if ( dws_ic_register_script( Plugin::SLUG . '-meta-box', 'assets/build/meta-box.js', array( 'strategy' => 'defer' ) ) ) {
			wp_enqueue_script( Plugin::SLUG . '-meta-box' );
		}
		if ( dws_ic_register_style( Plugin::SLUG . '-meta-box', 'assets/build/meta-box.css' ) ) {
			wp_enqueue_style( Plugin::SLUG . '-meta-box' );
		}
	}

	/**
	 * Prints the meta box: the post's comments, the form for a new comment where the user may post, and the templates the script clones to edit and reply.
	 *
	 * Its forms are groups of unnamed fields whose buttons the script handles, because the edit screen wraps every meta box in the post's form, which would submit a named field with the post and save one named content as the post's body.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $edited The edited post or order.
	 *
	 * @return  void
	 */
	public function render_meta_box( mixed $edited ): void {
		$object_id   = $this->get_object_id( $edited );
		$can_post    = current_user_can( DWS_IC_CAP_INSERT );
		$can_private = $can_post && dws_ic_are_enabled_private_comments() && current_user_can( DWS_IC_CAP_INSERT_PRIVATE );
		?>
		<div class="dws-internal-comments" tabindex="-1" data-post-id="<?php echo esc_attr( (string) $object_id ); ?>">
			<div class="dws-internal-comments__comments">
				<?php dws_ic_print_comments_list( $object_id ); ?>
			</div>
			<p class="dws-internal-comments__status" role="status"></p>
			<?php if ( $can_post ) : ?>
				<div class="dws-add-internal-comment dws-internal-comments-inline-action" data-form="new">
					<p class="dws-internal-comments-inline-action__textarea">
						<label for="dws-ic-new-comment"><?php esc_html_e( 'New internal comment', 'internal-comments' ); ?></label>
						<textarea id="dws-ic-new-comment" rows="4"></textarea>
					</p>
					<?php if ( $can_private ) : ?>
						<p>
							<input type="checkbox" id="dws-ic-new-comment-private" aria-describedby="dws-ic-new-comment-private-description" />
							<label for="dws-ic-new-comment-private"><?php esc_html_e( 'Mark as private', 'internal-comments' ); ?></label>
							<span id="dws-ic-new-comment-private-description" class="description"><?php esc_html_e( 'Only you and the users allowed to read private comments will see it.', 'internal-comments' ); ?></span>
						</p>
					<?php endif; ?>
					<p class="dws-internal-comments-inline-action__actions">
						<?php dws_ic_print_form_buttons( 'new' ); ?>
					</p>
				</div>
				<template id="dws-ic-reply-form">
					<div class="dws-internal-comments-inline-action" data-form="reply">
						<p class="dws-internal-comments-inline-action__textarea">
							<label><?php esc_html_e( 'Reply', 'internal-comments' ); ?> <textarea rows="3"></textarea></label>
						</p>
						<p class="dws-internal-comments-inline-action__actions">
							<?php dws_ic_print_form_buttons( 'reply' ); ?>
						</p>
					</div>
				</template>
			<?php endif; ?>
			<template id="dws-ic-edit-form">
				<div class="dws-internal-comments-inline-action" data-form="edit">
					<p class="dws-internal-comments-inline-action__textarea">
						<label><?php esc_html_e( 'Edit internal comment', 'internal-comments' ); ?> <textarea rows="4"></textarea></label>
					</p>
					<p class="dws-internal-comments-inline-action__actions">
						<?php dws_ic_print_form_buttons( 'edit' ); ?>
					</p>
				</div>
			</template>
		</div>
		<?php
	}

	// endregion

	// region HELPERS

	/**
	 * Returns the id of an edited post or order, or 0 for anything else.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $edited The edited post or order.
	 *
	 * @return  int
	 */
	protected function get_object_id( mixed $edited ): int {
		if ( $edited instanceof \WP_Post ) {
			return $edited->ID;
		}

		return $edited instanceof \WC_Abstract_Order ? $edited->get_id() : 0;
	}

	/**
	 * Returns whether the current user may see the meta box on a post or order: one of a selected type that they can edit.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $post_id The post's id.
	 *
	 * @return  bool
	 */
	protected function is_shown_for( int $post_id ): bool {
		return dws_ic_is_selected_post_type( $post_id ) && current_user_can( DWS_IC_CAP_SEE_METABOX ) && current_user_can( 'edit_post', $post_id );
	}

	// endregion
}
