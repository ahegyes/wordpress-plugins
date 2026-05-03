<?php declare( strict_types=1 );

/**
 * Plugin Name:       Locked Payment Methods for WooCommerce
 * Plugin URI:        https://github.com/ahegyes/wc-locked-payment-methods
 * Description:       Restricts WooCommerce payment methods based on cart contents — products, categories, shipping methods.
 * Version:           1.0.0
 * Requires PHP:      8.5
 * Requires at least: 7.0
 * Author:            Contributors
 * Author URI:        https://github.com/ahegyes
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       locked-payment-methods-for-woocommerce
 * Domain Path:       /languages
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @package DeepWebSolutions\LockedPaymentMethods
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_file( __DIR__ . '/vendor/autoload.php' ) ) {
	return;
}

if ( ! defined( 'DWS_LPMWC_FILE' ) ) {
	define( 'DWS_LPMWC_FILE', __FILE__ );
}
if ( ! defined( 'DWS_LPMWC_VERSION' ) ) {
	define( 'DWS_LPMWC_VERSION', '1.0.0' );
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/functions.php';

$dws_lpmwc_requirements = \DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Bootstrap\check_requirements(
	plugin_basename( __FILE__ )
);
if ( true !== $dws_lpmwc_requirements ) {
	\DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Bootstrap\output_requirements_error(
		plugin_basename( __FILE__ ),
		$dws_lpmwc_requirements
	);
	return;
}
unset( $dws_lpmwc_requirements );

add_action( 'plugins_loaded', 'dws_lpmwc_boot', 15 );
register_activation_hook( DWS_LPMWC_FILE, 'dws_lpmwc_activate' );
register_deactivation_hook( DWS_LPMWC_FILE, 'dws_lpmwc_deactivate' );
