<?php declare( strict_types=1 );
/**
 * Removes the plugin's data where the site consents, through the framework's uninstall engine, once the site meets the plugin's floors.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LockedPaymentMethods
 */

use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Bootstrap\Requirements;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\Uninstall\Uninstaller;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\WooCommerce\Uninstall\MetaTypes;

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$dws_lpmwc_file = __DIR__ . '/locked-payment-methods-for-woocommerce.php';
try {
	$dws_lpmwc_bootstrap = __DIR__ . '/vendor-prefixed/ahegyes/wp-framework-bootstrap/load.php';
	if ( ! is_readable( $dws_lpmwc_bootstrap ) ) {
		throw new RuntimeException( 'its bootstrap package is missing.' );
	}

	require_once $dws_lpmwc_bootstrap;
	if ( ! Requirements::check_floors( $dws_lpmwc_file ) ) {
		throw new RuntimeException( 'the site is below its PHP or WordPress floor.' );
	}

	require_once __DIR__ . '/vendor/autoload.php';
	require_once __DIR__ . '/includes/capabilities.php';
	require_once __DIR__ . '/includes/footprint.php';
	require_once __DIR__ . '/includes/grants.php';
	require_once __DIR__ . '/includes/options.php';
	$dws_lpmwc_footprint  = dws_lpmwc_get_footprint();
	$dws_lpmwc_consent    = DWS_LPMWC_OPTION_DELETE_DATA;
	$dws_lpmwc_meta_types = MetaTypes::DEFINITIONS;
	if ( ! class_exists( Uninstaller::class ) ) {
		throw new RuntimeException( 'its uninstall engine is missing.' );
	}
} catch ( Throwable $error ) {
	// WP-CLI then fails and keeps the files for a rerun on a complete build, while a web request deletes them and leaves the data.
	if ( defined( 'WP_CLI' ) && true === WP_CLI ) {
		throw $error;
	}

	error_log( 'Locked Payment Methods for WooCommerce kept its data on uninstall, because ' . $error->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- The uninstall request has no other channel.
	return;
}

Uninstaller::run(
	$dws_lpmwc_file,
	$dws_lpmwc_footprint,
	array(
		'consent'    => array( 'option' => $dws_lpmwc_consent ),
		'meta_types' => $dws_lpmwc_meta_types,
	)
);
