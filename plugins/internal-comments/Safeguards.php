<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments;

\defined( 'ABSPATH' ) || exit;

/**
 * Keeps WordPress's own actions on internal comments refused unless the booted plugin relaxes them, and WooCommerce's note routes off them, so a failed boot never leaves them to whoever can edit their post, moderate comments or edit orders.
 *
 * The entry requires it by path before the floor gate, so it parses on PHP 7.4 and calls only what the oldest WordPress the bootstrap package runs on provides.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class Safeguards {
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
	 * Hooks the safeguards, as static closures, so that only their own filters relax them.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public static function register(): void {
		// Runs last, so no capability mapping that runs after it can reopen what it refuses.
		add_filter(
			'map_meta_cap',
			static function ( array $caps, string $cap, int $user_id, array $args ): array {
				// An empty id names the loop's comment, as in core's own mapping.
				$comment = 'edit_comment' === $cap && isset( $args[0] ) ? $args[0] : null;
				$comment = \is_numeric( $comment ) ? get_comment( (int) $comment ) : $comment;
				return ! self::is_internal( $comment ) || ! self::is_restricted( (int) $comment->comment_ID ) ? $caps : array( 'do_not_allow' );
			},
			\PHP_INT_MAX,
			4
		);

		// Core's REST API lets a user who moderates comments update or delete one before it asks about edit_comment, and WooCommerce's note routes read and delete any comment on the order.
		add_filter(
			'rest_request_before_callbacks',
			static function ( $response, $handler, $request ) {
				$callback   = \is_array( $handler ) && isset( $handler['callback'] ) && \is_array( $handler['callback'] ) ? $handler['callback'] : array();
				$controller = $callback[0] ?? null;
				$method     = $callback[1] ?? null;
				$is_note    = $controller instanceof \WC_REST_Order_Notes_V1_Controller && \in_array( $method, array( 'get_item', 'delete_item' ), true );
				$is_core    = $controller instanceof \WP_REST_Comments_Controller && \in_array( $method, array( 'get_item', 'update_item', 'delete_item' ), true );
				$comment_id = ( $is_note || $is_core ) && $request instanceof \WP_REST_Request && \is_numeric( $request['id'] ) ? (int) $request['id'] : 0;
				$comment    = 0 < $comment_id ? get_comment( $comment_id ) : null;
				if ( is_wp_error( $response ) || ! self::is_internal( $comment ) ) {
					return $response;
				}

				if ( $is_note ) {
					return new \WP_Error( 'woocommerce_rest_invalid_id', __( 'Invalid resource ID.', 'internal-comments' ), array( 'status' => 404 ) );
				}

				return self::is_restricted( $comment_id ) ? new \WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to do that to this internal comment.', 'internal-comments' ), array( 'status' => rest_authorization_required_code() ) ) : $response;
			},
			10,
			3
		);

		// WooCommerce's note deletion casts whatever note id it is sent and deletes the comment it names for good, whatever its type, so the refusal reads the id as WooCommerce does.
		add_action(
			'wp_ajax_woocommerce_delete_order_note',
			static function (): void {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the nonce once this refusal has passed.
				$note_id = isset( $_POST['note_id'] ) && ( \is_scalar( $_POST['note_id'] ) || \is_array( $_POST['note_id'] ) ) ? absint( $_POST['note_id'] ) : 0;
				if ( 0 < $note_id && self::is_internal( get_comment( $note_id ) ) ) {
					wp_die( -1, '', array( 'response' => 403 ) );
				}
			},
			-10
		);
	}

	// endregion

	// region HELPERS

	/**
	 * Returns whether a value is an internal comment.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $comment The value, such as what get_comment() returned.
	 *
	 * @return  bool
	 *
	 * @phpstan-assert-if-true  \WP_Comment $comment
	 */
	protected static function is_internal( $comment ): bool {
		return $comment instanceof \WP_Comment && 'dws_internal' === $comment->comment_type;
	}

	/**
	 * Returns whether WordPress's own actions on an internal comment stay refused.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $comment_id The comment's id.
	 *
	 * @return  bool
	 */
	protected static function is_restricted( int $comment_id ): bool {
		/**
		 * Filters whether WordPress's own actions on an internal comment stay refused; an explicit false leaves them to the plugin's capabilities once it has booted.
		 *
		 * @since   2.0.0
		 * @version 2.0.0
		 *
		 * @param   bool $restricted Whether WordPress's actions on the comment stay refused.
		 * @param   int  $comment_id The comment's id.
		 */
		return ! self::is_relaxed( apply_filters( 'internal_comments/core_comment_actions/should_restrict', true, $comment_id ) ); // @phpstan-ignore WPCompat.parameterNotAvailable.applyfilters.args (WordPress 6.0 only formalized the arguments that older versions already passed on.)
	}

	/**
	 * Returns whether a safeguard's filtered value relaxes it, which takes an explicit false once the plugin has finished booting.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $filtered The value the safeguard's filter returned for true.
	 *
	 * @return  bool
	 */
	protected static function is_relaxed( $filtered ): bool {
		return 0 < did_action( 'internal_comments/boot/succeeded' ) && false === $filtered;
	}

	// endregion
}
