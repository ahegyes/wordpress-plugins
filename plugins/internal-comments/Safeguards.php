<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments;

\defined( 'ABSPATH' ) || exit;

/**
 * Keeps WordPress's own actions on internal comments refused unless the booted plugin relaxes them, so a failed boot never leaves them to whoever can edit their post.
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
				$comment = 'edit_comment' === $cap && isset( $args[0] ) ? $args[0] : null;
				$comment = \is_numeric( $comment ) ? get_comment( (int) $comment ) : $comment;
				if ( ! $comment instanceof \WP_Comment || 'dws_internal' !== $comment->comment_type ) {
					return $caps;
				}

				/**
				 * Filters whether WordPress's own actions on an internal comment stay refused; an explicit false leaves them to the plugin's capabilities once it has booted.
				 *
				 * @since   2.0.0
				 * @version 2.0.0
				 *
				 * @param   bool $restricted Whether WordPress's actions on the comment stay refused.
				 * @param   int  $comment_id The comment's id.
				 */
				return self::is_relaxed( apply_filters( 'internal_comments/core_comment_actions/should_restrict', true, (int) $comment->comment_ID ) ) ? $caps : array( 'do_not_allow' ); // @phpstan-ignore WPCompat.parameterNotAvailable.applyfilters.args (WordPress 6.0 only formalized the arguments that older versions already passed on.)
			},
			\PHP_INT_MAX,
			4
		);
	}

	// endregion

	// region HELPERS

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
