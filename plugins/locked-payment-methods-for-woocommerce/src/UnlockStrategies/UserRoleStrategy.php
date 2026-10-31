<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\UnlockStrategies;

use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\ConditionalComponentInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Unlocks every locked payment method for users holding one of the full-access roles.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class UserRoleStrategy implements ComponentInterface, ConditionalComponentInterface {
	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public static function should_load(): bool {
		return dws_lpmwc_get_bool_option( DWS_LPMWC_OPTION_UNLOCK_VIA_USER_ROLES );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		add_filter( 'dws_lpmwc/payment_method/should_lock', array( $this, 'unlock_for_full_access_roles' ), 10, 3 );
	}

	// endregion

	// region HOOKS

	/**
	 * Returns false for a user holding one of the full-access roles, and the lock as given otherwise.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   bool   $lock       Whether the payment method stays locked.
	 * @param   string $gateway_id The payment method's id.
	 * @param   int    $user_id    The paying user, or 0 for a guest.
	 *
	 * @return  bool
	 */
	public function unlock_for_full_access_roles( bool $lock, string $gateway_id, int $user_id ): bool {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return $lock;
		}

		$roles = dws_lpmwc_apply_v1_setting_filter( dws_lpmwc_get_list_option( DWS_LPMWC_OPTION_FULL_ACCESS_ROLES ), 'general', 'full-access-user-roles', 'option_' . DWS_LPMWC_OPTION_FULL_ACCESS_ROLES );

		return \is_array( $roles ) && array() !== \array_intersect( $user->roles, \array_filter( $roles, 'is_string' ) ) ? false : $lock;
	}

	// endregion
}
