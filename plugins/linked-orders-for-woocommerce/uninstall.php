<?php declare( strict_types=1 );
/**
 * Removes the plugin's data where the site consents, through the framework's uninstall engine, once the site meets the plugin's floors.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LinkedOrders
 */

use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Bootstrap\Requirements;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Uninstall\Uninstaller;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\WooCommerce\Uninstall\MetaTypes;

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$dws_lowc_file = __DIR__ . '/linked-orders-for-woocommerce.php';
try {
	$dws_lowc_bootstrap = __DIR__ . '/vendor-prefixed/ahegyes/wp-framework-bootstrap/load.php';
	if ( ! is_readable( $dws_lowc_bootstrap ) ) {
		throw new RuntimeException( 'its bootstrap package is missing.' );
	}

	require_once $dws_lowc_bootstrap;
	if ( ! Requirements::check_floors( $dws_lowc_file ) ) {
		throw new RuntimeException( 'the site is below its PHP or WordPress floor.' );
	}

	require_once __DIR__ . '/vendor/autoload.php';
	require_once __DIR__ . '/includes/footprint.php';
	require_once __DIR__ . '/includes/options.php';
	$dws_lowc_footprint  = dws_lowc_get_footprint();
	$dws_lowc_consent    = DWS_LOWC_OPTION_DELETE_DATA;
	$dws_lowc_meta_types = MetaTypes::DEFINITIONS;
	if ( ! class_exists( Uninstaller::class ) ) {
		throw new RuntimeException( 'its uninstall engine is missing.' );
	}
} catch ( Throwable $error ) {
	// WP-CLI then fails and keeps the files for a rerun on a complete build, while a web request deletes them and leaves the data.
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		throw $error;
	}

	error_log( 'Linked Orders for WooCommerce kept its data on uninstall, because ' . $error->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- The uninstall request has no other channel.
	return;
}

Uninstaller::run(
	$dws_lowc_file,
	$dws_lowc_footprint,
	array(
		'consent'    => array( 'option' => $dws_lowc_consent ),
		'meta_types' => $dws_lowc_meta_types,
	)
);
