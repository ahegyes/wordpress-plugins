<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\UnlockStrategies;

use DeepWebSolutions\LockedPaymentMethods\Options;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Settings\Forms;

\defined( 'ABSPATH' ) || exit;

/**
 * Declares the unlock strategies' settings fields, which the plugin's settings section shows within v1's groups.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @phpstan-import-type Field from Forms
 */
final class UnlockStrategySettings {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	protected function __construct() {}

	// endregion

	// region METHODS

	/**
	 * Returns the strategies' settings fields, each under the option it stores, an integration's switch only while its plugin is active.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  list<Field>
	 */
	public static function get_fields(): array {
		$fields = array(
			array(
				'id'      => Options::UNLOCK_VIA_USER_ROLES,
				'title'   => __( 'Unlock via user roles', 'locked-payment-methods-for-woocommerce' ),
				'type'    => 'checkbox',
				'desc'    => __( 'Give users with the roles below every locked payment method', 'locked-payment-methods-for-woocommerce' ),
				'default' => Options::DEFAULTS[ Options::UNLOCK_VIA_USER_ROLES ],
			),
			array(
				'id'      => Options::FULL_ACCESS_ROLES,
				'title'   => __( 'User roles with full access to every locked payment method', 'locked-payment-methods-for-woocommerce' ),
				'type'    => 'multiselect',
				'class'   => 'wc-enhanced-select',
				'options' => \array_map( 'translate_user_role', wp_roles()->get_names() ),
				'default' => Options::DEFAULTS[ Options::FULL_ACCESS_ROLES ],
			),
			array(
				'id'      => Options::UNLOCK_VIA_USER_PROFILES,
				'title'   => __( 'Unlock via user profiles', 'locked-payment-methods-for-woocommerce' ),
				'type'    => 'checkbox',
				'desc'    => __( 'Unlock payment methods for a user from their profile, for all their future orders and their unpaid orders', 'locked-payment-methods-for-woocommerce' ),
				'default' => Options::DEFAULTS[ Options::UNLOCK_VIA_USER_PROFILES ],
			),
			array(
				'id'      => Options::UNLOCK_VIA_ORDERS,
				'title'   => __( 'Unlock via orders', 'locked-payment-methods-for-woocommerce' ),
				'type'    => 'checkbox',
				'desc'    => __( 'Unlock payment methods for an unpaid order from its edit screen, for that order only', 'locked-payment-methods-for-woocommerce' ),
				'default' => Options::DEFAULTS[ Options::UNLOCK_VIA_ORDERS ],
			),
		);

		if ( GroupsStrategy::is_available() ) {
			$fields[] = array(
				'id'      => Options::UNLOCK_VIA_GROUPS,
				'title'   => __( 'Unlock via Groups', 'locked-payment-methods-for-woocommerce' ),
				'type'    => 'checkbox',
				'desc'    => __( 'Unlock payment methods for the members of a Groups group from the group\'s settings', 'locked-payment-methods-for-woocommerce' ),
				'default' => Options::DEFAULTS[ Options::UNLOCK_VIA_GROUPS ],
			);
		}

		if ( WooCommerceMembershipsStrategy::is_available() ) {
			$fields[] = array(
				'id'      => Options::UNLOCK_VIA_WC_MEMBERSHIPS,
				'title'   => __( 'Unlock via WooCommerce Memberships', 'locked-payment-methods-for-woocommerce' ),
				'type'    => 'checkbox',
				'desc'    => __( 'Unlock payment methods for the active members of a membership plan from the plan\'s data', 'locked-payment-methods-for-woocommerce' ),
				'default' => Options::DEFAULTS[ Options::UNLOCK_VIA_WC_MEMBERSHIPS ],
			);
		}

		return $fields;
	}

	// endregion
}
