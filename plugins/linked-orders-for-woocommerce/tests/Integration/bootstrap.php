<?php declare( strict_types=1 );

// WordPress ends the process with exit code 0 when it cannot load, which would otherwise pass the suite.
$dws_lowc_loaded = false;
register_shutdown_function(
	static function () use ( &$dws_lowc_loaded ): void {
		if ( ! $dws_lowc_loaded ) {
			fwrite( STDERR, "WordPress exited while the Integration bootstrap was loading it.\n" );
			exit( 1 );
		}
	}
);

// wp-env maps the repository to wp-content/plugins/linked-orders-for-woocommerce, three levels below the WordPress root.
require_once dirname( __DIR__, 5 ) . '/wp-load.php';
// The uninstall row runs WordPress's own uninstall_plugin().
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$dws_lowc_loaded = true;
