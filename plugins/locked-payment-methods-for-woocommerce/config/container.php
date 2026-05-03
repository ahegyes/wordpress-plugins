<?php declare( strict_types=1 );

/**
 * PHP-DI container definitions for non-autowired dependencies.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @package DeepWebSolutions\LockedPaymentMethods
 */

use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\Kernel\PluginKernel;
use Psr\Container\ContainerInterface;

return array(
	PluginKernel::class => static function ( ContainerInterface $container ): PluginKernel {
		return new PluginKernel( $container );
	},
);
