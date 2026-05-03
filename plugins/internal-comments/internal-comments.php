<?php declare( strict_types=1 );

/**
 * Plugin Name:       Internal Comments
 * Plugin URI:        https://github.com/ahegyes/wp-internal-comments
 * Description:       Internal, staff-only comment threads on WordPress and WooCommerce content. Independent of public comments.
 * Version:           2.0.0
 * Requires PHP:      8.5
 * Requires at least: 7.0
 * Author:            Contributors
 * Author URI:        https://github.com/ahegyes
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       internal-comments
 * Domain Path:       /languages
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\InternalComments
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_file( __DIR__ . '/vendor/autoload.php' ) ) {
	return;
}

if ( ! defined( 'DWS_IC_FILE' ) ) {
	define( 'DWS_IC_FILE', __FILE__ );
}
if ( ! defined( 'DWS_IC_VERSION' ) ) {
	define( 'DWS_IC_VERSION', '2.0.0' );
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/functions.php';

$dws_ic_requirements = \DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Bootstrap\check_requirements(
	plugin_basename( __FILE__ )
);
if ( true !== $dws_ic_requirements ) {
	\DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Bootstrap\output_requirements_error(
		plugin_basename( __FILE__ ),
		$dws_ic_requirements
	);
	return;
}
unset( $dws_ic_requirements );

add_action( 'plugins_loaded', 'dws_ic_boot', 15 );
register_activation_hook( DWS_IC_FILE, 'dws_ic_activate' );
register_deactivation_hook( DWS_IC_FILE, 'dws_ic_deactivate' );
