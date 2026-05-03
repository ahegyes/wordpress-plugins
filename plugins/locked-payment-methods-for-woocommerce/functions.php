<?php declare( strict_types=1 );

/**
 * Plugin facade for theme / snippet developers — thin delegators only, no business logic.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @package DeepWebSolutions\LockedPaymentMethods
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the plugin's singleton instance.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  \DeepWebSolutions\LockedPaymentMethods\Plugin
 */
function dws_lpmwc_instance(): \DeepWebSolutions\LockedPaymentMethods\Plugin {
	return \DeepWebSolutions\LockedPaymentMethods\Plugin::get_instance();
}

/**
 * Boots the plugin — invoked on `plugins_loaded` priority 15.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  void
 */
function dws_lpmwc_boot(): void {
	dws_lpmwc_instance()->boot();
}

/**
 * Plugin activation hook callback.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  void
 */
function dws_lpmwc_activate(): void {
	dws_lpmwc_instance()->activate();
}

/**
 * Plugin deactivation hook callback.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  void
 */
function dws_lpmwc_deactivate(): void {
	dws_lpmwc_instance()->deactivate();
}
