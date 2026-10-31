<?php declare( strict_types=1 );
/**
 * Defines the plugin's accessor, boots it on plugins_loaded, unschedules its jobs on deactivation, declares its WooCommerce feature compatibility and loads the files in includes/.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\InternalComments
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use DeepWebSolutions\InternalComments\Plugin;

defined( 'ABSPATH' ) || exit;

$dws_ic_includes = glob( __DIR__ . '/includes/*.php' );
if ( false === $dws_ic_includes ) {
	throw new RuntimeException( 'The includes directory could not be read.' );
}

sort( $dws_ic_includes );
foreach ( $dws_ic_includes as $dws_ic_include ) {
	if ( ! str_starts_with( basename( $dws_ic_include ), '_' ) ) { // An underscore prefix keeps a file from loading.
		require_once $dws_ic_include;
	}
}

// The hooks come last, so an include that throws leaves nothing of the plugin hooked.
add_action( 'plugins_loaded', 'dws_ic_boot', 15 );
add_action( 'before_woocommerce_init', 'dws_ic_declare_compatibility' );
add_action( 'dws_ic/boot/succeeded', 'dws_ic_fire_v1_initialized' );
add_action( 'dws_ic/boot/failed', 'dws_ic_fire_v1_initialization_failure' );
register_deactivation_hook( DWS_IC_FILE, 'dws_ic_deactivate' );

/**
 * Returns the plugin's composition root, built on first use.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  Plugin
 */
function dws_ic_plugin(): Plugin {
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
function dws_ic_boot(): void {
	try {
		dws_ic_plugin()->boot();
	} catch ( Throwable $error ) {
		dws_ic_handle_build_error( $error );
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
function dws_ic_deactivate(): void {
	try {
		dws_ic_plugin()->deactivate();
	} catch ( Throwable $error ) {
		dws_ic_handle_build_error( $error );
	}
}

/**
 * Declares that the plugin works with WooCommerce's order tables, where its comments on orders live as on orders stored as posts.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  void
 */
function dws_ic_declare_compatibility(): void {
	FeaturesUtil::declare_compatibility( 'custom_order_tables', DWS_IC_FILE, true );
}
