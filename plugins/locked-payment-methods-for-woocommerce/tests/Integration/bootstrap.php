<?php declare( strict_types=1 );

// A row that breaks the plugin on disk repairs it in a finally block, which an aborted run never reaches, so the suite refuses to test what such a run left behind.
$dws_lpmwc_leftovers = array();
foreach ( array( 'includes/zz-*.php', 'includes/*_zz-*.php', 'blocks/build/*.hidden', 'assets/build/*.hidden', 'vendor-prefixed/ahegyes/*/*.hidden', 'vendor-prefixed/ahegyes/*/src/*.hidden' ) as $dws_lpmwc_pattern ) {
	$dws_lpmwc_found = glob( dirname( __DIR__, 2 ) . '/' . $dws_lpmwc_pattern );
	if ( false === $dws_lpmwc_found ) {
		throw new RuntimeException( "The plugin's $dws_lpmwc_pattern could not be listed." );
	}

	$dws_lpmwc_leftovers = array_merge( $dws_lpmwc_leftovers, $dws_lpmwc_found );
}

if ( array() !== $dws_lpmwc_leftovers ) {
	fwrite( STDERR, "An aborted run left the plugin changed on disk; restore or delete these, then run the suite again:\n" . implode( "\n", $dws_lpmwc_leftovers ) . "\n" );
	exit( 1 );
}

// WordPress ends the process with exit code 0 when it cannot load, which would otherwise pass the suite.
$dws_lpmwc_loaded = false;
register_shutdown_function(
	static function () use ( &$dws_lpmwc_loaded ): void {
		if ( ! $dws_lpmwc_loaded ) {
			fwrite( STDERR, "WordPress exited while the Integration bootstrap was loading it.\n" );
			exit( 1 );
		}
	}
);

// wp-env maps the repository to wp-content/plugins/locked-payment-methods-for-woocommerce, three levels below the WordPress root.
require_once dirname( __DIR__, 5 ) . '/wp-load.php';
// The uninstall row runs WordPress's own uninstall_plugin().
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$dws_lpmwc_loaded = true;

// Every row calls the plugin's functions, which an aborted run of UninstallTest's floor row keeps from loading by leaving the entry's Requires PHP header raised; PluginBootTest reports a boot that fails once they have loaded.
if ( ! function_exists( 'dws_lpmwc_plugin' ) ) {
	fwrite( STDERR, "The plugin's functions did not load; activate it, restore the entry file's Requires headers and complete the build, then run the suite again.\n" );
	exit( 1 );
}
