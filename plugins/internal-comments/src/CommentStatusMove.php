<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments;

use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\Migration\BatchedMigrationStepInterface;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\Migration\MigrationRunner;

\defined( 'ABSPATH' ) || exit;

/**
 * Moves the comments v1 stored approved under the internal status, a window of comment ids per batch.
 *
 * The window walks the comments table by id, so each batch reads a bounded range however few internal comments it holds, and the option that holds its position is deleted once the walk ends.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class CommentStatusMove implements BatchedMigrationStepInterface {
	// region FIELDS AND CONSTANTS

	/**
	 * The data revision the step brings the site to, from which the comments v1 stored approved are under the internal status.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     int
	 */
	protected const int REVISION = 3;

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @global  \wpdb $wpdb The WordPress database connection.
	 *
	 * @throws  \RuntimeException Thrown when a query fails or the walk's position could not be stored.
	 */
	#[\Override]
	public function process_batch(): bool {
		global $wpdb;

		$cursor = \filter_var( get_option( 'dws_ic_v1_comments_cursor', 0 ), \FILTER_VALIDATE_INT, \FILTER_NULL_ON_FAILURE ) ?? 0;
		$last   = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(comment_ID) FROM %i', $wpdb->comments ) ?? '' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- A one-time walk of the comments table.
		$end    = \min( $cursor + 10000, $last );
		$ids    = '' === $wpdb->last_error && $cursor < $last ? wp_parse_id_list( $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM %i WHERE comment_ID > %d AND comment_ID <= %d AND comment_type = %s AND comment_approved IN ('0', '1', 'spam')", $wpdb->comments, $cursor, $end, Comments::TYPE ) ?? '' ) ) : array(); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- A one-time walk of the comments table.
		$posts  = '' === $wpdb->last_error && array() !== $ids ? wp_parse_id_list( $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT comment_post_ID FROM %i WHERE comment_ID > %d AND comment_ID <= %d AND comment_type = %s AND comment_approved IN ('0', '1', 'spam')", $wpdb->comments, $cursor, $end, Comments::TYPE ) ?? '' ) ) : array(); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- A one-time walk of the comments table.
		if ( '' !== $wpdb->last_error ) {
			throw new \RuntimeException( "The comments after id $cursor could not be read." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
		}

		if ( array() !== $ids ) {
			$moved = $wpdb->query( $wpdb->prepare( "UPDATE %i SET comment_approved = %s WHERE comment_ID > %d AND comment_ID <= %d AND comment_type = %s AND comment_approved IN ('0', '1', 'spam')", $wpdb->comments, Comments::STATUS, $cursor, $end, Comments::TYPE ) ?? '' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- A one-time walk of the comments table, whose cache is cleaned below.
			if ( false === $moved ) {
				throw new \RuntimeException( "The internal comments after id $cursor could not be moved." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
			}

			clean_comment_cache( $ids );
			foreach ( $posts as $post_id ) {
				wp_update_comment_count_now( $post_id );
			}
		}

		// Counts cached while the move is pending leave internal comments out, and WooCommerce adjusts its own only on announced status changes.
		if ( array() !== $ids || $end >= $last ) {
			wp_cache_delete( 'comments-0', 'counts' );
			if ( \class_exists( 'WC_Comments' ) ) {
				\WC_Comments::delete_comments_count_cache();
			}
		}

		if ( $end >= $last ) {
			delete_option( 'dws_ic_v1_comments_cursor' );
			return false;
		}

		if ( ! update_option( 'dws_ic_v1_comments_cursor', $end, false ) ) {
			throw new \RuntimeException( "The walk of the comments could not store its position after id $end." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
		}

		return true;
	}

	// endregion

	// region METHODS

	/**
	 * Returns whether the migration has yet to move the comments v1 stored approved under the internal status.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  bool
	 */
	public static function is_pending(): bool {
		return MigrationRunner::get_revision( 'dws_ic_revision' ) < self::REVISION;
	}

	// endregion
}
