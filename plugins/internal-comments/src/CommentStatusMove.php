<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments;

use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\Migration\BatchedMigrationStepInterface;

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
		$ids    = '' === $wpdb->last_error && $cursor < $last ? wp_parse_id_list( $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM %i WHERE comment_ID > %d AND comment_ID <= %d AND comment_type = %s AND comment_approved IN ('0', '1', 'spam')", $wpdb->comments, $cursor, $end, 'dws_internal' ) ?? '' ) ) : array(); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- A one-time walk of the comments table.
		$posts  = '' === $wpdb->last_error && array() !== $ids ? wp_parse_id_list( $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT comment_post_ID FROM %i WHERE comment_ID > %d AND comment_ID <= %d AND comment_type = %s AND comment_approved IN ('0', '1', 'spam')", $wpdb->comments, $cursor, $end, 'dws_internal' ) ?? '' ) ) : array(); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- A one-time walk of the comments table.
		if ( '' !== $wpdb->last_error ) {
			throw new \RuntimeException( "The comments after id $cursor could not be read." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
		}

		if ( array() !== $ids ) {
			$moved = $wpdb->query( $wpdb->prepare( "UPDATE %i SET comment_approved = %s WHERE comment_ID > %d AND comment_ID <= %d AND comment_type = %s AND comment_approved IN ('0', '1', 'spam')", $wpdb->comments, 'dws_internal', $cursor, $end, 'dws_internal' ) ?? '' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- A one-time walk of the comments table, whose cache is cleaned below.
			if ( false === $moved ) {
				throw new \RuntimeException( "The internal comments after id $cursor could not be moved." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
			}

			clean_comment_cache( $ids );
			foreach ( $posts as $post_id ) {
				wp_update_comment_count_now( $post_id );
			}
		}

		// WooCommerce counts the moved comments under the status they left, since it adjusts its counts only on announced status changes.
		if ( ( array() !== $ids || $end >= $last ) && \class_exists( 'WC_Comments' ) ) {
			\WC_Comments::delete_comments_count_cache();
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
}
