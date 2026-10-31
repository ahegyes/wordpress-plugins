<?php declare( strict_types=1 );
/**
 * Defines the plugin's accessor, boots it on plugins_loaded, unschedules its jobs on deactivation, declares its WooCommerce feature compatibility and loads the files in includes/.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LockedPaymentMethods
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use DeepWebSolutions\LockedPaymentMethods\Plugin;

defined( 'ABSPATH' ) || exit;

$dws_lpmwc_includes = glob( __DIR__ . '/includes/*.php' );
if ( false === $dws_lpmwc_includes ) {
	throw new RuntimeException( 'The includes directory could not be read.' );
}

sort( $dws_lpmwc_includes );
foreach ( $dws_lpmwc_includes as $dws_lpmwc_include ) {
	if ( ! str_starts_with( basename( $dws_lpmwc_include ), '_' ) ) { // An underscore prefix keeps a file from loading.
		require_once $dws_lpmwc_include;
	}
}

// The hooks come last, so an include that throws leaves nothing of the plugin hooked.
add_action( 'plugins_loaded', 'dws_lpmwc_boot', 15 );
add_action( 'before_woocommerce_init', 'dws_lpmwc_declare_compatibility' );
add_action( 'dws_lpmwc/boot/succeeded', 'dws_lpmwc_fire_v1_initialized' );
add_action( 'dws_lpmwc/boot/failed', 'dws_lpmwc_fire_v1_initialization_failure' );
register_deactivation_hook( DWS_LPMWC_FILE, 'dws_lpmwc_deactivate' );

/**
 * Returns the plugin's composition root, built on first use.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  Plugin
 */
function dws_lpmwc_plugin(): Plugin {
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
function dws_lpmwc_boot(): void {
	try {
		dws_lpmwc_plugin()->boot();
	} catch ( Throwable $error ) {
		dws_lpmwc_handle_build_error( $error );
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
function dws_lpmwc_deactivate(): void {
	try {
		dws_lpmwc_plugin()->deactivate();
	} catch ( Throwable $error ) {
		dws_lpmwc_handle_build_error( $error );
	}
}

/**
 * Declares whether the plugin works with WooCommerce's order tables, which each plugin built from the template sets from its own evidence.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  void
 */
function dws_lpmwc_declare_compatibility(): void {
	FeaturesUtil::declare_compatibility( 'custom_order_tables', DWS_LPMWC_FILE, false );
}
