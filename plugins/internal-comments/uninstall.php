<?php declare( strict_types=1 );
/**
 * Removes the plugin's data where the site consents, through the framework's uninstall engine, once the site meets the plugin's floors.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\InternalComments
 */

use DeepWebSolutions\InternalComments\Options;
use DeepWebSolutions\InternalComments\Plugin;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Bootstrap\Requirements;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\PluginIdentity;
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
	$dws_ic_footprint = Plugin::FOOTPRINT;
	$dws_ic_consent   = Options::DELETE_DATA;
	if ( ! class_exists( Uninstaller::class ) ) {
		throw new RuntimeException( 'its uninstall engine is missing.' );
	}

	$dws_ic_identity = new PluginIdentity( $dws_ic_file, Plugin::SLUG, Plugin::PREFIX );
} catch ( Throwable $error ) {
	// WP-CLI then fails and keeps the files for a rerun on a complete build, while a web request deletes them and leaves the data.
	if ( defined( 'WP_CLI' ) && true === WP_CLI ) {
		throw $error;
	}

	error_log( 'Internal Comments kept its data on uninstall, because ' . $error->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- The uninstall request has no other channel.
	return;
}

// A site that never ran the update holds its consent only in v1's row, which v1's uninstall honored; the engine deletes that row with the rest, so where a pass fails after it, the retry keeps what remains.
add_filter(
	'default_option_' . $dws_ic_consent,
	static function ( $value ) {
		$v1 = get_option( 'dws-internal-comments_plugin' );

		return is_array( $v1 ) && 'yes' === ( $v1['remove-data-uninstall'] ?? null ) ? 'yes' : $value;
	}
);

try {
	Uninstaller::run(
		$dws_ic_identity,
		$dws_ic_footprint,
		array(
			'consent' => array( 'option' => $dws_ic_consent ),
		)
	);
} catch ( RuntimeException $exception ) {
	if ( defined( 'WP_CLI' ) && true === WP_CLI ) {
		throw $exception;
	}

	// wp_die() ends the request before WordPress deletes the files, so the next uninstall retries, and shows the reason where a throw shows only the critical-error page.
	wp_die( esc_html( $exception->getMessage() ), '', array( 'response' => 500 ) );
}
