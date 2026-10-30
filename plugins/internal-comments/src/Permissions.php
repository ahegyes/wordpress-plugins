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
		if ( 'edit_comment' === $cap ) {
			return true === dws_ic_is_internal_comment( $args[0] ?? null ) ? \array_values( map_meta_cap( 'edit_dws_internal_comment', $user_id, ...$args ) ) : $caps;
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

	// endregion
}
