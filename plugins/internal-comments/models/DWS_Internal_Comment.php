<?php declare( strict_types=1 );

use DeepWebSolutions\InternalComments\Comments;

defined( 'ABSPATH' ) || exit;

/**
 * Wraps an internal comment with the checks of what a user may do with it.
 *
 * It keeps v1's global name, which other plugins type-hint, and reads of the comment's fields and calls to its methods reach the wrapped comment.
 *
 * @api
 *
 * @since   1.0.0
 * @version 2.0.0
 *
 * @mixin   WP_Comment
 */
final class DWS_Internal_Comment {
	// region FIELDS AND CONSTANTS

	/**
	 * The wrapped comment.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     WP_Comment
	 */
	protected WP_Comment $comment;

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $comment The comment's id, the comment, or an array with its comment_ID.
	 *
	 * @throws  InvalidArgumentException Thrown when the comment does not exist or is not internal.
	 */
	public function __construct( $comment ) {
		$comment = dws_ic_get_comment( $comment );
		if ( is_null( $comment ) || Comments::TYPE !== $comment->comment_type ) {
			throw new InvalidArgumentException( 'The comment is not an internal comment.' );
		}

		$this->comment = $comment;
	}

	/**
	 * Returns a field of the wrapped comment, or null for a field it does not have.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
	 *
	 * @param   string $name The field's name.
	 *
	 * @return  mixed
	 */
	public function __get( string $name ) {
		return property_exists( $this->comment, $name ) ? $this->comment->$name : null; // @phpstan-ignore property.dynamicName (v1 forwards every field of the comment by name.)
	}

	/**
	 * Returns whether the wrapped comment has a field that is set.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $name The field's name.
	 *
	 * @return  bool
	 */
	public function __isset( string $name ): bool {
		return isset( $this->comment->$name ); // @phpstan-ignore property.dynamicName (v1 forwards every field of the comment by name.)
	}

	/**
	 * Calls a method of the wrapped comment, or returns null for a method it does not have.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
	 *
	 * @param   string       $name The method's name.
	 * @param   array<mixed> $args The method's arguments.
	 *
	 * @return  mixed
	 */
	public function __call( string $name, array $args ) {
		return method_exists( $this->comment, $name ) ? $this->comment->$name( ...$args ) : null; // @phpstan-ignore method.dynamicName (v1 forwards every method of the comment by name.)
	}

	// endregion

	// region METHODS

	/**
	 * Returns whether a user, the current one by default, wrote the comment.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
	 *
	 * @param   int|null $user_id Optional. The user's id. Default is the current user.
	 *
	 * @return  bool
	 */
	public function is_author( ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();
		return 0 !== $user_id && (int) $this->comment->user_id === $user_id;
	}

	/**
	 * Returns whether a user, the current one by default, can edit the profile of the comment's author.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
	 *
	 * @param   int|null $user_id Optional. The user's id. Default is the current user.
	 *
	 * @return  bool
	 */
	public function can_edit_author( ?int $user_id = null ): bool {
		return user_can( $user_id ?? get_current_user_id(), 'edit_user', (int) $this->comment->user_id );
	}

	/**
	 * Returns whether a user, the current one by default, can read the comment.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
	 *
	 * @param   int|null $user_id Optional. The user's id. Default is the current user.
	 *
	 * @return  bool
	 */
	public function can_see( ?int $user_id = null ): bool {
		return user_can( $user_id ?? get_current_user_id(), 'see_dws_internal_comment', (int) $this->comment->comment_ID );
	}

	/**
	 * Returns whether a user, the current one by default, can edit the comment.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
	 *
	 * @param   int|null $user_id Optional. The user's id. Default is the current user.
	 *
	 * @return  bool
	 */
	public function can_edit( ?int $user_id = null ): bool {
		return user_can( $user_id ?? get_current_user_id(), 'edit_dws_internal_comment', (int) $this->comment->comment_ID );
	}

	/**
	 * Returns whether a user, the current one by default, can trash the comment.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
	 *
	 * @param   int|null $user_id Optional. The user's id. Default is the current user.
	 *
	 * @return  bool
	 */
	public function can_trash( ?int $user_id = null ): bool {
		return user_can( $user_id ?? get_current_user_id(), 'trash_dws_internal_comment', (int) $this->comment->comment_ID );
	}

	/**
	 * Returns the comment's earlier texts, newest first, each with the id of the user who replaced it and when, checking no capability.
	 *
	 * The caller checks whether the user may read them, through can_see() or can_edit().
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  list<array{content: string, user_id: int, time: int}>
	 */
	public function get_revisions(): array {
		$comment_id = (int) $this->comment->comment_ID;
		$revisions  = array();
		foreach ( array_reverse( (array) get_comment_meta( $comment_id, Comments::META_CHANGE_ID, false ) ) as $change_id ) {
			if ( ! is_string( $change_id ) || ! metadata_exists( 'comment', $comment_id, Comments::META_PREVIOUS_CONTENT . $change_id ) ) {
				continue;
			}

			$content     = get_comment_meta( $comment_id, Comments::META_PREVIOUS_CONTENT . $change_id, true );
			$revisions[] = array(
				'content' => is_string( $content ) ? $content : '',
				'user_id' => filter_var( get_comment_meta( $comment_id, Comments::META_CHANGED_BY . $change_id, true ), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE ) ?? 0,
				'time'    => filter_var( get_comment_meta( $comment_id, Comments::META_CHANGED_TIME . $change_id, true ), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE ) ?? 0,
			);
		}

		return $revisions;
	}

	/**
	 * Returns the link to the comment's edit screen, or null when the current user cannot edit it.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
	 *
	 * @return  string|null
	 */
	public function get_edit_comment_link(): ?string {
		$link = get_edit_comment_link( (int) $this->comment->comment_ID );
		return is_string( $link ) ? $link : null;
	}

	// endregion
}
