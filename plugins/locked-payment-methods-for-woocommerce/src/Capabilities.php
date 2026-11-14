<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods;

\defined( 'ABSPATH' ) || exit;

/**
 * Names the plugin's capabilities, under the names v1 granted them, and the roles that receive them.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class Capabilities {
	// region FIELDS AND CONSTANTS

	/**
	 * The capability a user needs to unlock payment methods on users' profiles.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string UNLOCK_VIA_USER_PROFILES = 'unlock_dws_lpm_via_user_profiles';

	/**
	 * The capability a user needs to unlock payment methods on orders.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string UNLOCK_VIA_ORDERS = 'unlock_dws_lpm_via_orders';

	/**
	 * The capability a user needs to unlock payment methods on Groups groups.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string UNLOCK_VIA_GROUPS = 'unlock_dws_lpm_via_groups';

	/**
	 * The capability a user needs to unlock payment methods on WooCommerce Memberships plans.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string UNLOCK_VIA_WC_MEMBERSHIPS = 'unlock_dws_lpm_via_wc_memberships';

	/**
	 * The capabilities each role receives, as v1 granted them.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<string, list<string>>
	 */
	public const array GRANTS = array(
		'administrator' => array( self::UNLOCK_VIA_USER_PROFILES, self::UNLOCK_VIA_ORDERS, self::UNLOCK_VIA_GROUPS, self::UNLOCK_VIA_WC_MEMBERSHIPS ),
		'shop_manager'  => array( self::UNLOCK_VIA_USER_PROFILES, self::UNLOCK_VIA_ORDERS ),
	);

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	protected function __construct() {}

	// endregion
}
