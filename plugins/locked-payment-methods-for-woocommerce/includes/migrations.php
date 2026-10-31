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
		'dws_lpmwc_move_user_grants',
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

/**
 * Moves v1's user grants, one global 'yes' row per payment method, into every site's list beside the grants it holds, so they keep v1's reach across a network, then deletes every v1 grant row; a run that stops part way converges on the next, since each list keeps what an earlier run merged.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @global  wpdb $wpdb WordPress's database access.
 *
 * @throws  RuntimeException Thrown when v1's grants could not be read, a user's grants could not be stored or a v1 grant could not be deleted.
 *
 * @return  void
 */
function dws_lpmwc_move_user_grants(): void {
	global $wpdb;

	$prefix = 'dws_wc_lpm_grant_access_';
	$wpdb->flush();
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT DISTINCT user_id, meta_key, meta_value FROM %i WHERE meta_key LIKE %s', $wpdb->usermeta, $wpdb->esc_like( $prefix ) . '%' ) ?? '', ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- v1's keys carry the payment method's id, so no metadata API lists them.
	if ( '' !== $wpdb->last_error ) {
		throw new RuntimeException( 'The v1 grants could not be read.' );
	}

	$grants = array();
	$keys   = array();
	foreach ( (array) $rows as $row ) {
		// The column's collation also matches keys that differ from v1's in letter case.
		if ( ! is_array( $row ) || ! is_numeric( $row['user_id'] ?? null ) || ! is_string( $row['meta_key'] ?? null ) || ! str_starts_with( $row['meta_key'], $prefix ) ) {
			continue;
		}

		$user_id                              = (int) $row['user_id'];
		$keys[ $user_id ][ $row['meta_key'] ] = true;
		if ( 'yes' === ( $row['meta_value'] ?? null ) ) {
			$grants[ $user_id ][] = substr( $row['meta_key'], strlen( $prefix ) );
		}
	}

	$sites = is_multisite() ? get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) : array( get_current_blog_id() );
	foreach ( $grants as $user_id => $ids ) {
		foreach ( $sites as $site_id ) {
			$key     = $wpdb->get_blog_prefix( $site_id ) . DWS_LPMWC_USER_OPTION_UNLOCKED_METHODS;
			$current = get_user_meta( $user_id, $key, true );
			$merged  = array_values( array_unique( array_merge( is_array( $current ) ? array_filter( $current, 'is_string' ) : array(), $ids ) ) );
			if ( $merged !== $current && false === update_user_meta( $user_id, $key, wp_slash( $merged ) ) ) {
				throw new RuntimeException( "The payment methods unlocked for user $user_id could not be stored." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
			}
		}
	}

	foreach ( $keys as $user_id => $user_keys ) {
		foreach ( array_keys( $user_keys ) as $meta_key ) {
			if ( ! delete_user_meta( $user_id, $meta_key ) ) {
				throw new RuntimeException( "The v1 grant $meta_key of user $user_id could not be deleted." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
			}
		}
	}
}
