<?php declare( strict_types=1 );

/**
 * Plugin facade for theme / snippet developers — thin delegators only, no business logic.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\InternalComments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the plugin's singleton instance.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  \DeepWebSolutions\InternalComments\Plugin
 */
function dws_ic_instance(): \DeepWebSolutions\InternalComments\Plugin {
	return \DeepWebSolutions\InternalComments\Plugin::get_instance();
}

/**
 * Boots the plugin — invoked on `plugins_loaded` priority 15.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  void
 */
function dws_ic_boot(): void {
	dws_ic_instance()->boot();
}

/**
 * Plugin activation hook callback.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  void
 */
function dws_ic_activate(): void {
	dws_ic_instance()->activate();
}

/**
 * Plugin deactivation hook callback.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  void
 */
function dws_ic_deactivate(): void {
	dws_ic_instance()->deactivate();
}
