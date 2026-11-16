<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments;

\defined( 'ABSPATH' ) || exit;

/**
 * Loads the templates of a post's internal comments list and of the meta box's forms.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class CommentsList {
	// region FIELDS AND CONSTANTS

	/**
	 * The folder of the admin templates, relative to the plugin's root.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	protected const string TEMPLATES = 'templates/admin/';

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	protected function __construct() {}

	// endregion

	// region METHODS

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
	public static function get_html( int $post_id ): string {
		\ob_start();
		self::render( $post_id );
		return (string) \ob_get_clean();
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
	public static function render( int $post_id ): void {
		load_template(
			plugin_dir_path( DWS_IC_FILE ) . self::TEMPLATES . 'html-comments-list.php',
			false,
			array(
				'comments' => 'trash' === get_post_status( $post_id ) ? dws_ic_get_internal_comments_from_trash_meta( $post_id ) : dws_ic_get_internal_comments( $post_id ),
				'empty'    => __( 'No internal comments yet.', 'internal-comments' ),
			)
		);
	}

	/**
	 * Prints a form of the meta box: the one for a new comment, or one the script clones to edit or reply to a comment.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   'new'|'edit'|'reply' $form        The form.
	 * @param   bool                 $can_private Optional. Whether the current user may mark a new comment private. Default false.
	 *
	 * @return  void
	 */
	public static function render_form( string $form, bool $can_private = false ): void {
		load_template(
			plugin_dir_path( DWS_IC_FILE ) . self::TEMPLATES . 'html-comment-form.php',
			false,
			array(
				'form'        => $form,
				'can_private' => $can_private,
			)
		);
	}

	// endregion
}
