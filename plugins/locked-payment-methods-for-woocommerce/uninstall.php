<?php declare( strict_types=1 );

/**
 * Uninstall handler. Runs in stripped context (no autoloader, no DI) — use raw `get_option` / `$wpdb`.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LockedPaymentMethods
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Plugin-specific cleanup goes here, e.g.:
// delete_option( 'dws_lpmwc_settings' );
