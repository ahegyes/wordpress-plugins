<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments;

use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Maps v1's meta capabilities, and core's edit_comment on an internal comment, to the capabilities the plugin grants.
 *
 * Each also needs access to edit the comment's post, and for a private comment, to be one of the users who made it private or to hold the private capability.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class Permissions implements ComponentInterface {
	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		add_filter( 'map_meta_cap', array( $this, 'map_meta_cap' ), 10, 4 );
		add_filter( 'rest_request_before_callbacks', array( $this, 'guard_core_rest_comment' ), 10, 3 );
		add_filter( 'rest_comment_query', array( $this, 'exclude_from_core_rest_collections' ) );
		add_action( 'pre_get_comments', array( $this, 'exclude_from_xml_rpc' ) );
		// Before WooCommerce's handler for product reviews at -1 and core's at 1.
		add_action( 'wp_ajax_replyto-comment', array( $this, 'guard_core_replies' ), -10 );
	}

	// endregion

	// region HOOKS

	/**
	 * Returns the primitive capabilities a user needs to see, edit or trash an internal comment.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   list<string> $caps    The primitive capabilities the user needs.
	 * @param   string       $cap     The capability checked.
	 * @param   int          $user_id The user's id.
	 * @param   array<mixed> $args    The check's arguments, the comment's id first.
	 *
	 * @return  list<string>
	 */
	public function map_meta_cap( array $caps, string $cap, int $user_id, array $args ): array {
		// Core trashes, deletes, restores and moderates a comment on edit_comment alone, so it asks for both capabilities.
		if ( 'edit_comment' === $cap ) {
			return true === dws_ic_is_internal_comment( $args[0] ?? null ) ? \array_values( \array_unique( \array_merge( map_meta_cap( 'edit_dws_internal_comment', $user_id, ...$args ), map_meta_cap( 'trash_dws_internal_comment', $user_id, ...$args ) ) ) ) : $caps;
		}

		if ( ! \in_array( $cap, array( 'see_dws_internal_comment', 'edit_dws_internal_comment', 'trash_dws_internal_comment' ), true ) ) {
			return $caps;
		}

		$comment = dws_ic_get_comment( $args[0] ?? null );
		$owners  = dws_ic_get_private_comment_owners( $comment );
		if ( null === $comment || null === $owners ) {
			return array( 'do_not_allow' );
		}

		$is_author = 0 !== $user_id && (int) $comment->user_id === $user_id;
		$caps      = match ( $cap ) {
			'see_dws_internal_comment'  => $is_author ? array() : array( DWS_IC_CAP_SEE ),
			'edit_dws_internal_comment' => array( $is_author ? DWS_IC_CAP_EDIT : DWS_IC_CAP_EDIT_OTHERS ),
			default                     => array( $is_author ? DWS_IC_CAP_TRASH : DWS_IC_CAP_TRASH_OTHERS ),
		};
		if ( array() !== $owners && ! $is_author && ! \in_array( $user_id, $owners, true ) ) {
			$caps[] = DWS_IC_CAP_SEE_PRIVATE;
		}

		return \array_values( \array_unique( \array_merge( $caps, map_meta_cap( 'edit_post', $user_id, (int) $comment->comment_post_ID ) ) ) );
	}

	/**
	 * Refuses core's REST API a read, update or deletion of an internal comment the current user may not see, edit or trash.
	 *
	 * Core lets a comment's author read it, and any user who moderates comments update or delete one, before it asks about the comment itself. The check names the comment core acts on, the request's id, which a query or body parameter can set apart from the route's.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed                                  $response The response so far, or an error.
	 * @param   mixed                                  $handler  The route's handler.
	 * @param   \WP_REST_Request<array<string, mixed>> $request  The request.
	 *
	 * @return  mixed
	 */
	public function guard_core_rest_comment( mixed $response, mixed $handler, \WP_REST_Request $request ): mixed {
		$callback   = \is_array( $handler ) ? ( $handler['callback'] ?? null ) : null;
		$capability = \is_array( $callback ) && ( $callback[0] ?? null ) instanceof \WP_REST_Comments_Controller ? match ( $callback[1] ?? null ) {
			'get_item'    => 'see_dws_internal_comment',
			'update_item' => 'edit_dws_internal_comment',
			'delete_item' => 'trash_dws_internal_comment',
			default       => null,
		} : null;
		$comment_id = \is_numeric( $request['id'] ) ? (int) $request['id'] : 0;
		if ( \is_wp_error( $response ) || null === $capability || true !== dws_ic_is_internal_comment( $comment_id ) || current_user_can( $capability, $comment_id ) ) {
			return $response;
		}

		return new \WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to do that to this internal comment.', 'internal-comments' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Leaves internal comments out of core's REST comment collections, which they reach only through the plugin's own routes.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, mixed> $args The collection's comment query arguments.
	 *
	 * @return  array<string, mixed>
	 */
	public function exclude_from_core_rest_collections( array $args ): array {
		$args['type__not_in'] = dws_ic_exclude_internal_type( $args['type__not_in'] ?? array() );

		return $args;
	}

	/**
	 * Refuses core's reply action an internal comment from a user without the capability to post one, as the action takes the comment's type from the request and checks only access to the post.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public function guard_core_replies(): void {
		$type = isset( $_POST['comment_type'] ) && \is_string( $_POST['comment_type'] ) ? sanitize_key( wp_unslash( $_POST['comment_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Core verifies the nonce once this refusal has passed.
		if ( DWS_IC_COMMENT_TYPE === $type && ! current_user_can( DWS_IC_CAP_INSERT ) ) {
			wp_die( -1, '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Leaves internal comments out of the comment queries of an XML-RPC request that do not name their type, as XML-RPC's wp.getComments lists the comments of any status a user who moderates comments names.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \WP_Comment_Query $query The comment query.
	 *
	 * @return  void
	 */
	public function exclude_from_xml_rpc( \WP_Comment_Query $query ): void {
		// XML-RPC's wp.getComments takes no type from its caller, so a query naming the type is one of the plugin's own.
		if ( \defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST && ! dws_ic_query_names_internal_type( $query->query_vars ) ) {
			$query->query_vars['type__not_in'] = dws_ic_exclude_internal_type( $query->query_vars['type__not_in'] ?? array() );
		}
	}

	// endregion
}
