<?php declare( strict_types=1 );
/**
 * Holds the plugin's migration steps, which bring its stored data to the revision its code expects.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\InternalComments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the migration steps, append-only, where index 0 brings the data to revision 1.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  list<callable(): void>
 */
function dws_ic_get_migration_steps(): array {
	return array(
		'dws_ic_add_settings',
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
