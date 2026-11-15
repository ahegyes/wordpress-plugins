<?php declare( strict_types=1 );
/**
 * Loads the plugin's safeguards, then the rest of the plugin once the site meets its floors, showing a notice where its build is incomplete.
 *
 * Plugin Name:          Locked Payment Methods for WooCommerce
 * Plugin URI:           https://github.com/ahegyes/wc-locked-payment-methods
 * Description:          Hides the payment methods you lock from customers at checkout until you unlock them for a user, a role, an order, a group or a membership plan.
 * Version:              2.0.0
 * Requires at least:    7.1
 * Requires PHP:         8.5
 * Requires Plugins:     woocommerce
 * WC requires at least: 11.3
 * WC tested up to:      11.3
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

use DeepWebSolutions\LockedPaymentMethods\Safeguards;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Bootstrap\Requirements;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Bootstrap\Updater;

defined( 'ABSPATH' ) || exit;

define( 'DWS_LPMWC_FILE', __FILE__ );

require_once __DIR__ . '/Safeguards.php'; // Before the floor gate, so the restrictions hold on any runtime that parses the plugin.
Safeguards::register();

try {
	$dws_lpmwc_bootstrap = __DIR__ . '/vendor-prefixed/ahegyes/wp-framework-bootstrap/load.php';
	if ( ! is_readable( $dws_lpmwc_bootstrap ) ) { // The floor gate lives in the bootstrap package, so nothing built may load without it.
		throw new RuntimeException( 'The bootstrap package is missing.' );
	}

	require_once $dws_lpmwc_bootstrap;
	Updater::register( __FILE__ ); // Before the floor gate, so a release that lowers the floors still reaches a site below them.
	if ( ! Requirements::check_floors( __FILE__ ) ) {
		return;
	}

	require_once __DIR__ . '/vendor/autoload.php';
	require_once __DIR__ . '/functions.php';
} catch ( Throwable $error ) { // PHP throws an Error for a missing required file, so an incomplete build lands here.
	if ( function_exists( 'wp_get_environment_type' ) && in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) {
		throw $error;
	}

	add_action(
		'all_admin_notices',
		static function (): void {
			if ( current_user_can( 'activate_plugins' ) ) {
				printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html__( 'Locked Payment Methods for WooCommerce is missing some of its files, so its features stay off until the plugin is reinstalled.', 'locked-payment-methods-for-woocommerce' ) );
			}
		}
	);
}
