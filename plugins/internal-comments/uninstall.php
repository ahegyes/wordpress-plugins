<?php declare( strict_types=1 );
/**
 * Removes the plugin's data where the site consents, through the framework's uninstall engine, once the site meets the plugin's floors.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\InternalComments
 */

use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Bootstrap\Requirements;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\Uninstall\Uninstaller;

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$dws_ic_file = __DIR__ . '/internal-comments.php';
try {
	$dws_ic_bootstrap = __DIR__ . '/vendor-prefixed/ahegyes/wp-framework-bootstrap/load.php';
	if ( ! is_readable( $dws_ic_bootstrap ) ) {
		throw new RuntimeException( 'its bootstrap package is missing.' );
	}

	require_once $dws_ic_bootstrap;
	if ( ! Requirements::check_floors( $dws_ic_file ) ) {
		throw new RuntimeException( 'the site is below its PHP or WordPress floor.' );
	}

	require_once __DIR__ . '/vendor/autoload.php';
	require_once __DIR__ . '/includes/capabilities.php';
	require_once __DIR__ . '/includes/footprint.php';
	require_once __DIR__ . '/includes/options.php';
	$dws_ic_footprint = dws_ic_get_footprint();
	$dws_ic_consent   = array(
		'option' => DWS_IC_OPTION_PLUGIN,
		'key'    => DWS_IC_SETTING_DELETE_DATA,
	);
	if ( ! class_exists( Uninstaller::class ) ) {
		throw new RuntimeException( 'its uninstall engine is missing.' );
	}
} catch ( Throwable $error ) {
	// WP-CLI then fails and keeps the files for a rerun on a complete build, while a web request deletes them and leaves the data.
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		throw $error;
	}

	error_log( 'Internal Comments kept its data on uninstall, because ' . $error->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- The uninstall request has no other channel.
	return;
}

Uninstaller::run(
	$dws_ic_file,
	$dws_ic_footprint,
	array( 'consent' => $dws_ic_consent )
);
