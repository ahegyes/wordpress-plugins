<?php declare( strict_types=1 );
/**
 * Holds the plugin's migration steps, which bring its stored data to the revision its code expects.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LinkedOrders
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
function dws_lowc_get_migration_steps(): array {
	return array(
		'dws_lowc_add_settings',
	);
}

/**
 * Stores the settings' defaults, autoloaded, so a site that never saved them reads them without a query, and keeps every value a v1 site stored.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @throws  RuntimeException Thrown when a default could not be stored.
 *
 * @return  void
 */
function dws_lowc_add_settings(): void {
	$defaults = array(
		DWS_LOWC_OPTION_MAX_DEPTH    => (string) DWS_LOWC_OPTION_DEFAULTS[ DWS_LOWC_OPTION_MAX_DEPTH ],
		DWS_LOWC_OPTION_AUTOCOMPLETE => wc_bool_to_string( DWS_LOWC_OPTION_DEFAULTS[ DWS_LOWC_OPTION_AUTOCOMPLETE ] ),
		DWS_LOWC_OPTION_DELETE_DATA  => wc_bool_to_string( DWS_LOWC_OPTION_DEFAULTS[ DWS_LOWC_OPTION_DELETE_DATA ] ),
	);

	foreach ( $defaults as $key => $value ) {
		if ( false === get_option( $key ) && ! add_option( $key, $value, '', true ) ) {
			throw new RuntimeException( "The option $key could not be stored." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
		}
	}
}
