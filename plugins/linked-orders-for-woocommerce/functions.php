<?php declare( strict_types=1 );
/**
 * Defines the plugin's accessor, boots it on plugins_loaded, unschedules its jobs on deactivation, declares its WooCommerce feature compatibility and loads the files in includes/.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LinkedOrders
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use DeepWebSolutions\LinkedOrders\Plugin;

defined( 'ABSPATH' ) || exit;

$dws_lowc_includes = glob( __DIR__ . '/includes/*.php' );
if ( false === $dws_lowc_includes ) {
	throw new RuntimeException( 'The includes directory could not be read.' );
}

sort( $dws_lowc_includes );
foreach ( $dws_lowc_includes as $dws_lowc_include ) {
	if ( ! str_starts_with( basename( $dws_lowc_include ), '_' ) ) { // An underscore prefix keeps a file from loading.
		require_once $dws_lowc_include;
	}
}

// The hooks come last, so an include that throws leaves nothing of the plugin hooked.
add_action( 'plugins_loaded', 'dws_lowc_boot', 15 );
add_action( 'before_woocommerce_init', 'dws_lowc_declare_compatibility' );
add_action( 'dws_lowc/boot/succeeded', 'dws_lowc_fire_v1_initialized' );
add_action( 'dws_lowc/boot/failed', 'dws_lowc_fire_v1_initialization_failure' );
register_deactivation_hook( DWS_LOWC_FILE, 'dws_lowc_deactivate' );

/**
 * Returns the plugin's composition root, built on first use.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  Plugin
 */
function dws_lowc_plugin(): Plugin {
	static $plugin = null;
	if ( ! $plugin instanceof Plugin ) {
		$plugin = new Plugin();
	}

	return $plugin;
}

/**
 * Boots the plugin, reporting a composition root that cannot be built as an incomplete build.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  void
 */
function dws_lowc_boot(): void {
	try {
		dws_lowc_plugin()->boot();
	} catch ( Throwable $error ) {
		dws_lowc_handle_build_error( $error );
	}
}

/**
 * Unschedules the plugin's jobs on the site it is deactivated on, reporting a composition root that cannot be built as an incomplete build.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  void
 */
function dws_lowc_deactivate(): void {
	try {
		dws_lowc_plugin()->deactivate();
	} catch ( Throwable $error ) {
		dws_lowc_handle_build_error( $error );
	}
}

/**
 * Declares that the plugin works with WooCommerce's order tables, which its tests cover on both order storages.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  void
 */
function dws_lowc_declare_compatibility(): void {
	FeaturesUtil::declare_compatibility( 'custom_order_tables', DWS_LOWC_FILE, true );
}
