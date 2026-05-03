<?php declare( strict_types=1 );

/**
 * PHP-scoper config. Prefix passed via composer script's --prefix flag.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @package DeepWebSolutions\LinkedOrders
 */

$config = __DIR__ . '/vendor/ahegyes/wordpress-configs/php/php-scoper';
$vendor = __DIR__ . '/vendor';

$wp_framework = ( require "$config/contrib/wp-framework.inc.php" )( $vendor );
$php_di       = ( require "$config/contrib/php-di.inc.php" )( $vendor );

return ( require "$config/scoper-base.inc.php" )(
	array(
		'project_dir'   => __DIR__,
		'finders'       => array_merge( $wp_framework['finders'], $php_di['finders'] ),
		'exclude_files' => $php_di['exclude_files'],
	)
);
