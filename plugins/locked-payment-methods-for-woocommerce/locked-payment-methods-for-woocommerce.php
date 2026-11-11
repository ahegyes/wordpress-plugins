<?php declare( strict_types=1 );
/**
 * Loads the plugin's safeguards, then the rest of the plugin once the site meets its floors and its build is complete.
 *
 * Plugin Name:          Locked Payment Methods for WooCommerce
 * Plugin URI:           https://github.com/ahegyes/wc-locked-payment-methods
 * Description:          Hides the payment methods you lock from customers at checkout until you unlock them for a user, a role, an order, a group or a membership plan.
 * Version:              2.0.0
 * Requires at least:    7.1
 * Requires PHP:         8.5
 * Requires Plugins:     woocommerce
 * WC requires at least: 11.2
 * WC tested up to:      11.2
 * Author:               Contributors
 * Author URI:           https://github.com/ahegyes
 * License:              GPL-3.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:          locked-payment-methods-for-woocommerce
 * Domain Path:          /languages
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LockedPaymentMethods
 */

use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Bootstrap\Requirements;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Bootstrap\Updater;

defined( 'ABSPATH' ) || exit;

define( 'DWS_LPMWC_FILE', __FILE__ );

require_once __DIR__ . '/includes/safeguards.php'; // Before the floor gate, so the restrictions hold on any runtime that parses the plugin.

$dws_lpmwc_bootstrap = __DIR__ . '/vendor-prefixed/ahegyes/wp-framework-bootstrap/load.php';
if ( ! is_readable( $dws_lpmwc_bootstrap ) ) { // The floor gate lives in the bootstrap package, so nothing built may load without it.
	add_action( 'all_admin_notices', 'dws_lpmwc_render_build_notice' );
	return;
}

require_once $dws_lpmwc_bootstrap;
Updater::register( __FILE__ ); // Before the floor gate, so a release that lowers the floors still reaches a site below them.
if ( ! Requirements::check_floors( __FILE__ ) ) {
	return;
}

try {
	require_once __DIR__ . '/vendor/autoload.php';
	require_once __DIR__ . '/functions.php';
} catch ( Throwable $error ) { // PHP throws an Error for a missing required file, so a partial deploy lands here.
	dws_lpmwc_handle_build_error( $error );
	return;
}

/**
 * Reports a build that could not load.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   Throwable $error The error that stopped the build.
 *
 * @throws  Throwable Thrown when the site runs in a local or development environment.
 *
 * @return  void
 */
function dws_lpmwc_handle_build_error( Throwable $error ): void {
	if ( in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) {
		throw $error;
	}

	// A partial deploy fails the same way on every request, so each distinct message is logged about once an hour, as best effort.
	$key = 'dws_lpmwc_build_error_' . md5( $error->getMessage() );
	if ( false === get_transient( $key ) ) {
		set_transient( $key, 1, HOUR_IN_SECONDS );
		error_log( 'Locked Payment Methods for WooCommerce did not finish loading: ' . $error->getMessage() . ' in ' . $error->getFile() . ':' . $error->getLine() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- The plugin's logger is part of the build that failed.
	}

	add_action( 'all_admin_notices', 'dws_lpmwc_render_build_notice' );
}

/**
 * Prints the notice that the plugin's build is incomplete to users who can activate plugins.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  void
 */
function dws_lpmwc_render_build_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html__( 'Locked Payment Methods for WooCommerce is missing some of its files, so its features are off. Reinstall the plugin to restore them.', 'locked-payment-methods-for-woocommerce' ) );
}
