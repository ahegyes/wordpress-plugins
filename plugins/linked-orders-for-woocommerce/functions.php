<?php declare( strict_types=1 );

/**
 * Plugin facade for theme / snippet developers — thin delegators only, no business logic.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LinkedOrders
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the plugin's singleton instance.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  \DeepWebSolutions\LinkedOrders\Plugin
 */
function dws_lowc_instance(): \DeepWebSolutions\LinkedOrders\Plugin {
	return \DeepWebSolutions\LinkedOrders\Plugin::get_instance();
}

/**
 * Boots the plugin — invoked on `plugins_loaded` priority 15.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  void
 */
function dws_lowc_boot(): void {
	dws_lowc_instance()->boot();
}

/**
 * Plugin activation hook callback.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  void
 */
function dws_lowc_activate(): void {
	dws_lowc_instance()->activate();
}

/**
 * Plugin deactivation hook callback.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  void
 */
function dws_lowc_deactivate(): void {
	dws_lowc_instance()->deactivate();
}
