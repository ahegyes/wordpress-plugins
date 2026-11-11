<?php declare( strict_types=1 );
/**
 * Hooks the plugin into WordPress and loads the files in includes/.
 *
 * It also fires v1's boot hooks, dws_ic_initialized and dws_ic_initialization_failure, at the earliest priority of the v2 hooks that replace them.
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
add_action( 'dws_ic/boot/succeeded', 'dws_ic_fire_v1_initialized', PHP_INT_MIN );
add_action( 'dws_ic/boot/failed', 'dws_ic_fire_v1_initialization_failure', PHP_INT_MIN );
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
		$plugin = dws_ic_plugin();
	} catch ( Throwable $error ) {
		dws_ic_handle_build_error( $error );
		return;
	}

	$plugin->boot(); // PluginBoot reports a failed composition itself, and a hook listener's throw is not a build error.
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
		$plugin = dws_ic_plugin();
	} catch ( Throwable $error ) {
		dws_ic_handle_build_error( $error );
		return;
	}

	$plugin->deactivate();
}

/**
 * Declares that the plugin works with WooCommerce's order tables.
 *
 * Its comments on orders live as on orders stored as posts, which its tests cover on both order storages.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  void
 */
function dws_ic_declare_compatibility(): void {
	FeaturesUtil::declare_compatibility( 'custom_order_tables', DWS_IC_FILE, true );
}
