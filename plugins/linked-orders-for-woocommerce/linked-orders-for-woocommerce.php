<?php declare( strict_types=1 );

/**
 * Plugin Name:       Linked Orders for WooCommerce
 * Plugin URI:        https://github.com/ahegyes/wc-linked-orders
 * Description:       Adds parent/child relationships between WooCommerce orders for splits, refunds, returns, and renewals.
 * Version:           2.0.0
 * Requires PHP:      8.5
 * Requires at least: 7.0
 * Author:            Contributors
 * Author URI:        https://github.com/ahegyes
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       linked-orders-for-woocommerce
 * Domain Path:       /languages
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LinkedOrders
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_file( __DIR__ . '/vendor/autoload.php' ) ) {
	return;
}

if ( ! defined( 'DWS_LOWC_FILE' ) ) {
	define( 'DWS_LOWC_FILE', __FILE__ );
}
if ( ! defined( 'DWS_LOWC_VERSION' ) ) {
	define( 'DWS_LOWC_VERSION', '2.0.0' );
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/functions.php';

$dws_lowc_requirements = \DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Bootstrap\check_requirements(
	plugin_basename( __FILE__ )
);
if ( true !== $dws_lowc_requirements ) {
	\DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Bootstrap\output_requirements_error(
		plugin_basename( __FILE__ ),
		$dws_lowc_requirements
	);
	return;
}
unset( $dws_lowc_requirements );

add_action( 'plugins_loaded', 'dws_lowc_boot', 15 );
register_activation_hook( DWS_LOWC_FILE, 'dws_lowc_activate' );
register_deactivation_hook( DWS_LOWC_FILE, 'dws_lowc_deactivate' );
