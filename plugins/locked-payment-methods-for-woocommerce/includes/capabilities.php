<?php declare( strict_types=1 );
/**
 * Holds the plugin's capabilities, under the names v1 granted them, and the roles that receive them.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LockedPaymentMethods
 */

defined( 'ABSPATH' ) || exit;

/**
 * The capability a user needs to unlock payment methods on users' profiles.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_LPMWC_CAP_UNLOCK_VIA_USER_PROFILES = 'unlock_dws_lpm_via_user_profiles';

/**
 * The capability a user needs to unlock payment methods on orders.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_LPMWC_CAP_UNLOCK_VIA_ORDERS = 'unlock_dws_lpm_via_orders';

/**
 * The capability a user needs to unlock payment methods on Groups groups.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_LPMWC_CAP_UNLOCK_VIA_GROUPS = 'unlock_dws_lpm_via_groups';

/**
 * The capability a user needs to unlock payment methods on WooCommerce Memberships plans.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_LPMWC_CAP_UNLOCK_VIA_WC_MEMBERSHIPS = 'unlock_dws_lpm_via_wc_memberships';

/**
 * The capabilities each role receives, as v1 granted them.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     array<string, list<string>>
 */
const DWS_LPMWC_CAPABILITY_GRANTS = array(
	'administrator' => array( DWS_LPMWC_CAP_UNLOCK_VIA_USER_PROFILES, DWS_LPMWC_CAP_UNLOCK_VIA_ORDERS, DWS_LPMWC_CAP_UNLOCK_VIA_GROUPS, DWS_LPMWC_CAP_UNLOCK_VIA_WC_MEMBERSHIPS ),
	'shop_manager'  => array( DWS_LPMWC_CAP_UNLOCK_VIA_USER_PROFILES, DWS_LPMWC_CAP_UNLOCK_VIA_ORDERS ),
);
