<?php declare( strict_types=1 );

// The plugin's files exit when WordPress has not defined ABSPATH.
define( 'ABSPATH', __DIR__ . '/' );

// WordPress's include loader is absent here, so the suite requires the declarations it reads.
require_once dirname( __DIR__, 2 ) . '/includes/options.php';
require_once dirname( __DIR__, 2 ) . '/includes/footprint.php';
