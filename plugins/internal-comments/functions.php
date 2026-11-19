<?php declare( strict_types=1 );
/**
 * Loads the files in includes/, defines the plugin's accessor, boots the plugin on plugins_loaded, declares its WooCommerce feature compatibility, unschedules its jobs on deactivation and fires v1's boot hooks at the earliest priority of the hooks that replace them.
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
add_action(
	'plugins_loaded',
	static function (): void {
		dws_ic_plugin()->boot();
	},
	15
);
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( FeaturesUtil::class ) ) { // WooCommerce fires the hook in releases older than the class, below the floor the boot checks later.
			FeaturesUtil::declare_compatibility( 'custom_order_tables', DWS_IC_FILE, true );
		}
	}
);
add_action(
	'internal_comments/boot/succeeded',
	static function (): void {
		/**
		 * Fires once the plugin has booted.
		 *
		 * @since       1.0.0
		 * @version     2.0.0
		 * @deprecated  2.0.0 Use internal_comments/boot/succeeded.
		 */
		do_action_deprecated( 'dws_ic_initialized', array(), '2.0.0', 'internal_comments/boot/succeeded' );
	},
	PHP_INT_MIN
);
add_action(
	'internal_comments/boot/failed',
	static function ( Throwable $error ): void {
		/**
		 * Fires when the plugin's boot failed.
		 *
		 * @since       1.0.0
		 * @version     2.0.0
		 * @deprecated  2.0.0 Use internal_comments/boot/failed.
		 *
		 * @param       Throwable $error The failure, where v1 passed its own exception.
		 */
		do_action_deprecated( 'dws_ic_initialization_failure', array( $error ), '2.0.0', 'internal_comments/boot/failed' );
	},
	PHP_INT_MIN
);
register_deactivation_hook(
	DWS_IC_FILE,
	static function (): void {
		dws_ic_plugin()->deactivate();
	}
);

/**
 * Returns the plugin's composition root, built on first use.
 *
 * Only the function is contract, not the composition root it returns.
 *
 * @api
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
