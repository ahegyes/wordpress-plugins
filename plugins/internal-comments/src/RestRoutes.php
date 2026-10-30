<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments;

use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Registers the dws_ic/v1 REST routes, through which the meta box lists, posts, edits and trashes internal comments, each answering with the post's comments rendered for the current user.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class RestRoutes implements ComponentInterface {
	// region FIELDS AND CONSTANTS

	/**
	 * The routes' namespace.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string NAMESPACE = 'dws_ic/v1';

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
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	// endregion

	// region HOOKS

	/**
	 * Registers the routes of a post's comments and of a single comment.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public function register_routes(): void {
		$content = array(
			'type'              => 'string',
			'required'          => true,
			'sanitize_callback' => 'sanitize_textarea_field',
		);

		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<post>\d+)/comments',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_post_comments' ),
					'permission_callback' => array( $this, 'can_read_post_comments' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_comment' ),
					'permission_callback' => array( $this, 'can_create_comment' ),
					'args'                => array(
						'content' => $content,
						'parent'  => array(
							'type'    => 'integer',
							'minimum' => 0,
							'default' => 0,
						),
						'private' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/comments/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_comment' ),
					'permission_callback' => array( $this, 'can_edit_comment' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_comment' ),
					'permission_callback' => array( $this, 'can_edit_comment' ),
					'args'                => array( 'content' => $content ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'trash_comment' ),
					'permission_callback' => array( $this, 'can_trash_comment' ),
				),
			)
		);
	}

	/**
	 * Returns whether the current user may read a post's comments: on a post of a selected type that they can edit, with the meta box's or the posts list's capability.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \WP_REST_Request<array{post: numeric-string}> $request The request.
	 *
	 * @return  bool
	 */
	public function can_read_post_comments( \WP_REST_Request $request ): bool {
		$post_id = absint( $request['post'] );

		return $this->can_use_post( $post_id ) && ( current_user_can( DWS_IC_CAP_SEE_METABOX ) || current_user_can( DWS_IC_CAP_SEE_COLUMN ) );
	}

	/**
	 * Returns whether the current user may post a comment on a post: one of a selected type that they can edit, with the capability to post.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \WP_REST_Request<array{post: numeric-string}> $request The request.
	 *
	 * @return  bool
	 */
	public function can_create_comment( \WP_REST_Request $request ): bool {
		return $this->can_use_post( absint( $request['post'] ) ) && current_user_can( DWS_IC_CAP_INSERT );
	}

	/**
	 * Returns whether the current user may edit the internal comment a request names.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \WP_REST_Request<array{id: numeric-string}> $request The request.
	 *
	 * @return  bool
	 */
	public function can_edit_comment( \WP_REST_Request $request ): bool {
		return current_user_can( 'edit_dws_internal_comment', absint( $request['id'] ) );
	}

	/**
	 * Returns whether the current user may trash the internal comment a request names.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \WP_REST_Request<array{id: numeric-string}> $request The request.
	 *
	 * @return  bool
	 */
	public function can_trash_comment( \WP_REST_Request $request ): bool {
		return current_user_can( 'trash_dws_internal_comment', absint( $request['id'] ) );
	}

	/**
	 * Returns a post's comments, rendered for the current user.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \WP_REST_Request<array{post: numeric-string}> $request The request.
	 *
	 * @return  \WP_REST_Response
	 */
	public function get_post_comments( \WP_REST_Request $request ): \WP_REST_Response {
		return $this->respond( absint( $request['post'] ) );
	}

	/**
	 * Stores a comment, or a reply to an internal comment of the same post the current user can see, and returns the post's comments.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \WP_REST_Request<array{post: numeric-string, content: string, parent: int, private: bool}> $request The request.
	 *
	 * @return  \WP_REST_Response|\WP_Error
	 */
	public function create_comment( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id   = absint( $request['post'] );
		$parent_id = absint( $request['parent'] );
		$content   = (string) $request['content'];
		if ( '' === \trim( $content ) ) {
			return $this->empty_content();
		}

		$parent = 0 === $parent_id ? null : dws_ic_get_comment( $parent_id );
		if ( 0 !== $parent_id && ( null === $parent || DWS_IC_COMMENT_TYPE !== $parent->comment_type || (int) $parent->comment_post_ID !== $post_id || ! current_user_can( 'see_dws_internal_comment', $parent_id ) ) ) {
			return new \WP_Error( 'dws_ic_invalid_parent', __( 'The comment you are replying to is not one of this item\'s internal comments.', 'internal-comments' ), array( 'status' => 400 ) );
		}

		/**
		 * Fires before the meta box stores an internal comment.
		 *
		 * @since       1.0.0
		 * @version     2.0.0
		 * @deprecated  2.0.0 Use dws_ic/internal_comment/before_insert.
		 *
		 * @param       int $post_id The id of the comment's post.
		 */
		do_action_deprecated( 'internal_comments/ajax/before_insert_comment', array( $post_id ), '2.0.0', 'dws_ic/internal_comment/before_insert' );

		/**
		 * Fires before the meta box stores an internal comment.
		 *
		 * @since   2.0.0
		 * @version 2.0.0
		 *
		 * @param   int $post_id The id of the comment's post.
		 */
		do_action( 'dws_ic/internal_comment/before_insert', $post_id );

		$private    = true === $request['private'] && dws_ic_are_enabled_private_comments() && current_user_can( DWS_IC_CAP_INSERT_PRIVATE );
		$comment_id = dws_ic_create_internal_comment(
			array(
				'comment_post_ID' => $post_id,
				'comment_content' => $content,
				'comment_parent'  => $parent_id,
				'comment_meta'    => array( DWS_IC_META_PRIVATE => $private ? 'yes' : 'no' ),
			)
		);
		if ( null === $comment_id ) {
			return new \WP_Error( 'dws_ic_not_stored', __( 'The internal comment could not be stored. Please try again.', 'internal-comments' ), array( 'status' => 500 ) );
		}

		return $this->respond( $post_id, 201 );
	}

	/**
	 * Returns the text of an internal comment as stored, which the meta box's edit form starts from.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \WP_REST_Request<array{id: numeric-string}> $request The request.
	 *
	 * @return  \WP_REST_Response
	 */
	public function get_comment( \WP_REST_Request $request ): \WP_REST_Response {
		$comment = dws_ic_get_comment( absint( $request['id'] ) );

		return new \WP_REST_Response(
			array(
				'id'      => absint( $request['id'] ),
				'content' => null === $comment ? '' : $comment->comment_content,
			)
		);
	}

	/**
	 * Replaces the text of an internal comment and returns its post's comments.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \WP_REST_Request<array{id: numeric-string, content: string}> $request The request.
	 *
	 * @return  \WP_REST_Response|\WP_Error
	 */
	public function update_comment( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$comment_id = absint( $request['id'] );
		$content    = (string) $request['content'];
		if ( '' === \trim( $content ) ) {
			return $this->empty_content();
		}

		$stored  = dws_ic_get_comment( $comment_id );
		$comment = null === $stored ? array() : $stored->to_array();
		$data    = array(
			'comment_ID'      => $comment_id,
			'comment_content' => $content,
		);

		/**
		 * Filters further fields of an internal comment the meta box updates, which never replace its id or text.
		 *
		 * @since       1.0.0
		 * @version     2.0.0
		 * @deprecated  2.0.0 Use dws_ic/internal_comment/update_data.
		 *
		 * @param       array<string, mixed> $extra The further fields.
		 * @param       array<string, mixed> $data  The comment's id and new text.
		 */
		$extra = apply_filters_deprecated( 'internal_comments/ajax/update_internal_comment_data', array( array(), $data ), '2.0.0', 'dws_ic/internal_comment/update_data' );

		/**
		 * Filters further fields of an internal comment the meta box updates, which never replace its id or text.
		 *
		 * @since   2.0.0
		 * @version 2.0.0
		 *
		 * @param   array<string, mixed> $extra The further fields.
		 * @param   array<string, mixed> $data  The comment's id and new text.
		 */
		$extra = apply_filters( 'dws_ic/internal_comment/update_data', \is_array( $extra ) ? $extra : array(), $data );
		$data += \is_array( $extra ) ? $extra : array();

		/**
		 * Fires before the meta box updates an internal comment.
		 *
		 * @since       1.0.0
		 * @version     2.0.0
		 * @deprecated  2.0.0 Use dws_ic/internal_comment/before_update.
		 *
		 * @param       array<string, mixed> $comment The comment's stored fields.
		 * @param       array<string, mixed> $data    The fields it updates.
		 */
		do_action_deprecated( 'internal_comments/ajax/before_update_comment', array( $comment, $data ), '2.0.0', 'dws_ic/internal_comment/before_update' );

		/**
		 * Fires before the meta box updates an internal comment.
		 *
		 * @since   2.0.0
		 * @version 2.0.0
		 *
		 * @param   array<string, mixed> $comment The comment's stored fields.
		 * @param   array<string, mixed> $data    The fields it updates.
		 */
		do_action( 'dws_ic/internal_comment/before_update', $comment, $data );

		$updated = wp_update_comment( wp_slash( $data ), true );
		if ( \is_wp_error( $updated ) || false === $updated ) {
			return new \WP_Error( 'dws_ic_not_updated', __( 'The internal comment could not be updated. Please try again.', 'internal-comments' ), array( 'status' => 500 ) );
		}

		return $this->respond( null === $stored ? 0 : (int) $stored->comment_post_ID );
	}

	/**
	 * Moves an internal comment to the trash, or deletes it where the site keeps no trash, and returns its post's comments.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \WP_REST_Request<array{id: numeric-string}> $request The request.
	 *
	 * @return  \WP_REST_Response|\WP_Error
	 */
	public function trash_comment( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$comment_id = absint( $request['id'] );
		$stored     = dws_ic_get_comment( $comment_id );
		$comment    = null === $stored ? array() : $stored->to_array();

		/**
		 * Fires before the meta box trashes an internal comment.
		 *
		 * @since       1.0.0
		 * @version     2.0.0
		 * @deprecated  2.0.0 Use dws_ic/internal_comment/before_trash.
		 *
		 * @param       array<string, mixed> $comment The comment's stored fields.
		 */
		do_action_deprecated( 'internal_comments/ajax/before_trash_comment', array( $comment ), '2.0.0', 'dws_ic/internal_comment/before_trash' );

		/**
		 * Fires before the meta box trashes an internal comment.
		 *
		 * @since   2.0.0
		 * @version 2.0.0
		 *
		 * @param   array<string, mixed> $comment The comment's stored fields.
		 */
		do_action( 'dws_ic/internal_comment/before_trash', $comment );

		if ( ! wp_trash_comment( $comment_id ) ) {
			return new \WP_Error( 'dws_ic_not_trashed', __( 'The internal comment could not be moved to the trash. Please try again.', 'internal-comments' ), array( 'status' => 500 ) );
		}

		return $this->respond( null === $stored ? 0 : (int) $stored->comment_post_ID );
	}

	// endregion

	// region HELPERS

	/**
	 * Returns whether the current user may work with a post's internal comments: one of a selected type that they can edit, an order in any storage included.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $post_id The post's id.
	 *
	 * @return  bool
	 */
	protected function can_use_post( int $post_id ): bool {
		return dws_ic_is_selected_post_type( $post_id ) && current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Returns a response holding a post's comments, rendered for the current user.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $post_id The post's id.
	 * @param   int $status  Optional. The response's status. Default 200.
	 *
	 * @return  \WP_REST_Response
	 */
	protected function respond( int $post_id, int $status = 200 ): \WP_REST_Response {
		return new \WP_REST_Response( array( 'html' => dws_ic_get_comments_list_html( $post_id ) ), $status );
	}

	/**
	 * Returns the error that refuses an empty comment.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  \WP_Error
	 */
	protected function empty_content(): \WP_Error {
		return new \WP_Error( 'dws_ic_empty_content', __( 'You cannot post an empty internal comment.', 'internal-comments' ), array( 'status' => 400 ) );
	}

	// endregion
}
