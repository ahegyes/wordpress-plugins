<?php declare( strict_types=1 );
/**
 * Holds the plugin's migration steps, which bring its stored data to the revision its code expects.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\InternalComments
 */

use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\Migration\BatchedMigrationStepInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Returns the migration steps, append-only, where index 0 brings the data to revision 1.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  list<(callable(): void)|BatchedMigrationStepInterface>
 */
function dws_ic_get_migration_steps(): array {
	return array(
		'dws_ic_add_settings',
		new class() implements BatchedMigrationStepInterface {
			// region INHERITED METHODS

			/**
			 * {@inheritDoc}
			 *
			 * @since   2.0.0
			 * @version 2.0.0
			 */
			#[\Override]
			public function process_batch(): bool {
				return dws_ic_convert_v1_comments();
			}

			// endregion
		},
	);
}

/**
 * Stores each settings row a site lacks with v1's defaults, autoloaded, so a site that never saved the settings page reads them without a query, and keeps the rows a v1 site stored.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @throws  RuntimeException Thrown when a row could not be stored.
 *
 * @return  void
 */
function dws_ic_add_settings(): void {
	foreach ( DWS_IC_SETTING_DEFAULTS as $row => $defaults ) {
		if ( false === get_option( $row ) && ! add_option( $row, $defaults, '', true ) ) {
			throw new RuntimeException( "The option $row could not be stored." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
		}
	}
}

/**
 * Moves the next window of comment ids from v1's storage, which kept internal comments approved, to the internal status, recounts the posts it moved comments of, and returns whether ids remain.
 *
 * The window walks the comments table by id, so each batch reads a bounded range however few internal comments it holds, and the option that holds its position is deleted once the walk ends.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @global  wpdb $wpdb The WordPress database connection.
 *
 * @throws  RuntimeException Thrown when a query fails or the walk's position could not be stored.
 *
 * @return  bool
 */
function dws_ic_convert_v1_comments(): bool {
	global $wpdb;

	$cursor = filter_var( get_option( 'dws_ic_v1_comments_cursor', 0 ), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE ) ?? 0;
	$last   = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(comment_ID) FROM %i', $wpdb->comments ) ?? '' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- A one-time walk of the comments table.
	$end    = min( $cursor + 10000, $last );
	$ids    = '' === $wpdb->last_error && $cursor < $last ? wp_parse_id_list( $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM %i WHERE comment_ID > %d AND comment_ID <= %d AND comment_type = %s AND comment_approved IN ('0', '1', 'spam')", $wpdb->comments, $cursor, $end, DWS_IC_COMMENT_TYPE ) ?? '' ) ) : array(); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- A one-time walk of the comments table.
	$posts  = '' === $wpdb->last_error && array() !== $ids ? wp_parse_id_list( $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT comment_post_ID FROM %i WHERE comment_ID > %d AND comment_ID <= %d AND comment_type = %s AND comment_approved IN ('0', '1', 'spam')", $wpdb->comments, $cursor, $end, DWS_IC_COMMENT_TYPE ) ?? '' ) ) : array(); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- A one-time walk of the comments table.
	if ( '' !== $wpdb->last_error ) {
		throw new RuntimeException( "The comments after id $cursor could not be read." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
	}

	if ( array() !== $ids ) {
		$moved = $wpdb->query( $wpdb->prepare( "UPDATE %i SET comment_approved = %s WHERE comment_ID > %d AND comment_ID <= %d AND comment_type = %s AND comment_approved IN ('0', '1', 'spam')", $wpdb->comments, DWS_IC_COMMENT_STATUS, $cursor, $end, DWS_IC_COMMENT_TYPE ) ?? '' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- A one-time walk of the comments table, whose cache is cleaned below.
		if ( false === $moved ) {
			throw new RuntimeException( "The internal comments after id $cursor could not be moved." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
		}

		clean_comment_cache( $ids );
		foreach ( $posts as $post_id ) {
			wp_update_comment_count_now( $post_id );
		}
	}

	if ( $end >= $last ) {
		delete_option( 'dws_ic_v1_comments_cursor' );

		return false;
	}

	if ( ! update_option( 'dws_ic_v1_comments_cursor', $end, false ) ) {
		throw new RuntimeException( "The walk of the comments could not store its position after id $end." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
	}

	return true;
}
