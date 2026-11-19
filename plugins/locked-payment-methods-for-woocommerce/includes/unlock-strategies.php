<?php declare( strict_types=1 );
/**
 * Registers and looks up the strategies that unlock locked payment methods.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LockedPaymentMethods
 */

use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies;

defined( 'ABSPATH' ) || exit;

/**
 * Registers a strategy that unlocks locked payment methods, unless one with its id is registered already.
 *
 * The plugin's own strategies register as it boots, so another plugin registers its own on locked_payment_methods_for_woocommerce/boot/succeeded.
 *
 * @api
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   DWS_LPMWC_Unlock_Strategy $strategy The strategy.
 *
 * @return  bool
 */
function dws_lpmwc_register_unlock_strategy( DWS_LPMWC_Unlock_Strategy $strategy ): bool {
	return UnlockStrategies::register( $strategy );
}

/**
 * Returns the registered strategy with an id, or null for none, as for one of the plugin's own strategies whose switch is off or before the plugin has booted.
 *
 * @api
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $id The strategy's id, such as user_role, user_profile, order, groups or wc_memberships.
 *
 * @return  DWS_LPMWC_Unlock_Strategy|null
 */
function dws_lpmwc_get_unlock_strategy( string $id ): ?DWS_LPMWC_Unlock_Strategy {
	return UnlockStrategies::get( $id );
}

/**
 * Returns the registered strategies, by id, in the order they registered, or none before the plugin has booted.
 *
 * @api
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  array<string, DWS_LPMWC_Unlock_Strategy>
 */
function dws_lpmwc_get_unlock_strategies(): array {
	return UnlockStrategies::get_all();
}
