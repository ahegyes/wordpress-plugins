<?php declare( strict_types=1 );
/**
 * Loads the files in includes/, defines the plugin's accessor, boots the plugin on plugins_loaded, declares its WooCommerce feature compatibility, unschedules its jobs on deactivation and fires v1's boot hooks at the earliest priority of the hooks that replace them.
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
add_action(
	'plugins_loaded',
	static function (): void {
		dws_lpmwc_plugin()->boot();
	},
	15
);
add_action(
	'before_woocommerce_init',
	static function (): void {
		FeaturesUtil::declare_compatibility( 'custom_order_tables', DWS_LPMWC_FILE, true );
	}
);
add_action(
	'locked_payment_methods_for_woocommerce/boot/succeeded',
	static function (): void {
		/**
		 * Fires once the plugin has booted.
		 *
		 * @since       1.0.0
		 * @version     2.0.0
		 * @deprecated  2.0.0 Use locked_payment_methods_for_woocommerce/boot/succeeded.
		 */
		do_action_deprecated( 'dws_lpmwc_initialized', array(), '2.0.0', 'locked_payment_methods_for_woocommerce/boot/succeeded' );
	},
	PHP_INT_MIN
);
add_action(
	'locked_payment_methods_for_woocommerce/boot/failed',
	static function ( Throwable $error ): void {
		/**
		 * Fires when the plugin's boot failed.
		 *
		 * @since       1.0.0
		 * @version     2.0.0
		 * @deprecated  2.0.0 Use locked_payment_methods_for_woocommerce/boot/failed.
		 *
		 * @param       Throwable $error The failure, where v1 passed its own exception.
		 */
		do_action_deprecated( 'dws_lpmwc_initialization_failure', array( $error ), '2.0.0', 'locked_payment_methods_for_woocommerce/boot/failed' );
	},
	PHP_INT_MIN
);
register_deactivation_hook(
	DWS_LPMWC_FILE,
	static function (): void {
		dws_lpmwc_plugin()->deactivate();
	}
);

/**
 * Returns the plugin's composition root, built on first use.
 *
 * Only the function is contract: the root's members stay internal unless tagged @api.
 *
 * @api
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
