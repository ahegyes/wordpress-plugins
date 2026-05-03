<?php declare( strict_types=1 );

/**
 * PHPUnit bootstrap. Inside wp-env's `cli` container, also loads WP and the plugin entry.
 *
 * @since   2.0.0
 * @version 2.0.0
 */

require_once __DIR__ . '/../vendor/autoload.php';

$wp_load = '/var/www/html/wp-load.php';
if ( file_exists( $wp_load ) ) {
	require_once $wp_load;
	require_once __DIR__ . '/../linked-orders-for-woocommerce.php';
}
