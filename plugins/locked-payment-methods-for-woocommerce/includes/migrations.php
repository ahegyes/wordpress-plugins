<?php declare( strict_types=1 );
/**
 * Holds the plugin's migration steps, which bring its stored data to the revision its code expects.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LockedPaymentMethods
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
function dws_lpmwc_get_migration_steps(): array {
	return array(
		'dws_lpmwc_add_settings',
	);
}

/**
 * Stores each setting the site has not stored yet, autoloaded, from v1's row where it holds a value of the setting's kind and otherwise from the setting's default, and leaves v1's rows in place, so a site that goes back to v1 still finds its settings.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @throws  RuntimeException Thrown when a setting could not be stored.
 *
 * @return  void
 */
function dws_lpmwc_add_settings(): void {
	foreach ( DWS_LPMWC_V1_SETTINGS as $group => $fields ) {
		foreach ( $fields as $id => $key ) {
			if ( false !== get_option( $key ) ) {
				continue;
			}

			$default = DWS_LPMWC_OPTION_DEFAULTS[ $key ];
			$v1      = get_option( "dws-wc-lpm_{$group}_{$id}", null );
			if ( is_array( $default ) ) {
				$value = is_array( $v1 ) ? array_values( array_filter( $v1, 'is_string' ) ) : $default;
			} else {
				$value = wc_bool_to_string( null === $v1 ? $default : ( filter_var( $v1, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE ) ?? $default ) ); // filter_var() reads null as false, so an absent row takes the default before it.
			}

			if ( ! add_option( $key, $value, '', true ) ) {
				throw new RuntimeException( "The option $key could not be stored." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
			}
		}
	}
}
