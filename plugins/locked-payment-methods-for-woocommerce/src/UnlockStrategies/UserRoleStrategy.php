<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\UnlockStrategies;

use DeepWebSolutions\LockedPaymentMethods\Options;
use DeepWebSolutions\LockedPaymentMethods\Safeguards;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\ConditionalComponentInterface;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies;

\defined( 'ABSPATH' ) || exit;

/**
 * Unlocks every locked payment method for users holding one of the full-access roles.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class UserRoleStrategy implements ComponentInterface, ConditionalComponentInterface, \DWS_LPMWC_Unlock_Strategy {
	// region FIELDS AND CONSTANTS

	/**
	 * The strategy's id.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string ID = 'user_role';

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public static function should_load(): bool {
		return Options::get_bool( Options::UNLOCK_VIA_USER_ROLES );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		UnlockStrategies::register( $this );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_id(): string {
		return self::ID;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_label(): string {
		return _x( 'User roles', 'unlock-strategies', 'locked-payment-methods-for-woocommerce' );
	}

	/**
	 * Returns whether the user holds one of the full-access roles, which unlocks every locked payment method.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $gateway_id The payment method's id.
	 * @param   int    $user_id    The paying user, or 0 for a guest.
	 * @param   int    $order_id   The order being paid, or 0 off its payment page.
	 *
	 * @return  bool
	 */
	#[\Override]
	public function unlocks( string $gateway_id, int $user_id, int $order_id ): bool {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return false;
		}

		$roles = Safeguards::apply_v1_setting_filter( Options::get_list( Options::FULL_ACCESS_ROLES ), 'general', 'full-access-user-roles', 'option_' . Options::FULL_ACCESS_ROLES );
		return \is_array( $roles ) && array() !== \array_intersect( $user->roles, \array_filter( $roles, 'is_string' ) );
	}

	// endregion
}
