<?php declare( strict_types=1 );
/**
 * Plugin Name:          Linked Orders for WooCommerce
 * Plugin URI:           https://github.com/ahegyes/wc-linked-orders
 * Description:          A WooCommerce extension for creating orders that are logically connected to existing ones.
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
 * Text Domain:          linked-orders-for-woocommerce
 * Domain Path:          /languages
 *
 * @package DeepWebSolutions\LinkedOrders
 */

use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Bootstrap\Requirements;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Bootstrap\Updater;

defined( 'ABSPATH' ) || exit;

define( 'DWS_LOWC_FILE', __FILE__ );

$dws_lowc_bootstrap = __DIR__ . '/vendor-prefixed/ahegyes/wp-framework-bootstrap/load.php';
if ( is_readable( $dws_lowc_bootstrap ) ) {
	require_once $dws_lowc_bootstrap;
	Updater::register( __FILE__ ); // Before the floor gate, so a release that lowers the floors still reaches a site below them.
	if ( ! Requirements::check_floors( __FILE__ ) ) {
		return;
	}
}

require_once __DIR__ . '/includes/safeguards.php';

if ( ! is_readable( $dws_lowc_bootstrap ) || ! is_readable( __DIR__ . '/assets/build/orders-list-table.asset.php' ) ) { // The floor gate never ran, or the scripts were never built, so nothing built may load.
	add_action( 'all_admin_notices', 'dws_lowc_render_build_notice' );
	return;
}

try {
	require_once __DIR__ . '/vendor/autoload.php';
	require_once __DIR__ . '/functions.php';
} catch ( Throwable $error ) { // PHP throws an Error for a missing required file, so a partial deploy lands here.
	dws_lowc_handle_build_error( $error );
	return;
}

/**
 * Reports a build that could not load, rethrowing in local and development environments and otherwise logging the error and showing the build notice.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   Throwable $error The error that stopped the build.
 *
 * @throws  Throwable Thrown in local and development environments.
 *
 * @return  void
 */
function dws_lowc_handle_build_error( Throwable $error ): void {
	if ( in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) {
		throw $error;
	}

	error_log( 'Linked Orders for WooCommerce did not finish loading: ' . $error->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- The plugin's logger is part of the build that failed.
	add_action( 'all_admin_notices', 'dws_lowc_render_build_notice' );
}

/**
 * Prints the notice that the plugin's build is incomplete to users who can activate plugins.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  void
 */
function dws_lowc_render_build_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html__( 'Linked Orders for WooCommerce is missing some of its files, so its features are off. Reinstall the plugin to restore them.', 'linked-orders-for-woocommerce' ) );
}
