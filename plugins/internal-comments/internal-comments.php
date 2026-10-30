<?php declare( strict_types=1 );
/**
 * Plugin Name:          Internal Comments
 * Plugin URI:           https://github.com/ahegyes/wp-internal-comments
 * Description:          WordPress plugins on the wp-framework packages start here, from one tested anatomy, so all are built alike and a fix found in one applies to all.
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
 * Update URI:           https://github.com/ahegyes/wp-internal-comments
 * Text Domain:          internal-comments
 * Domain Path:          /languages
 *
 * @package DeepWebSolutions\InternalComments
 */

use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Bootstrap\Requirements;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Bootstrap\Updater;

defined( 'ABSPATH' ) || exit;

define( 'DWS_IC_FILE', __FILE__ );
load_plugin_textdomain( 'internal-comments', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- WordPress registers languages/ itself only for plugins it does not network-activate.

$dws_ic_bootstrap = __DIR__ . '/vendor-prefixed/ahegyes/wp-framework-bootstrap/load.php';
if ( is_readable( $dws_ic_bootstrap ) ) {
	require_once $dws_ic_bootstrap;
	Updater::register( __FILE__ ); // Before the floor gate, so a release that lowers the floors still reaches a site below them.
	if ( ! Requirements::check_floors( __FILE__ ) ) {
		return;
	}
}

require_once __DIR__ . '/includes/safeguards.php';

if ( ! is_readable( $dws_ic_bootstrap ) || ! is_readable( __DIR__ . '/blocks/build/blocks-manifest.php' ) ) { // The floor gate never ran, or the scripts were never built, so nothing built may load.
	add_action( 'all_admin_notices', 'dws_ic_render_build_notice' );
	return;
}

try {
	require_once __DIR__ . '/vendor/autoload.php';
	require_once __DIR__ . '/functions.php';
} catch ( Throwable $error ) { // PHP throws an Error for a missing required file, so a partial deploy lands here.
	dws_ic_handle_build_error( $error );
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
function dws_ic_handle_build_error( Throwable $error ): void {
	if ( in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) {
		throw $error;
	}

	error_log( 'Internal Comments did not finish loading: ' . $error->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- The plugin's logger is part of the build that failed.
	add_action( 'all_admin_notices', 'dws_ic_render_build_notice' );
}

/**
 * Prints the notice that the plugin's build is incomplete to users who can activate plugins.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  void
 */
function dws_ic_render_build_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html__( 'Internal Comments is missing some of its files, so its features are off. Reinstall the plugin to restore them.', 'internal-comments' ) );
}
