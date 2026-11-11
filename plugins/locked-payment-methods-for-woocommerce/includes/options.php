<?php declare( strict_types=1 );
/**
 * Holds the plugin's option keys and defaults, and reads each option as its type, guarding the stored value.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LockedPaymentMethods
 */

defined( 'ABSPATH' ) || exit;

/**
 * The option that holds 'yes' where the site consents to uninstall removing the plugin's data.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_LPMWC_OPTION_DELETE_DATA = 'dws_lpmwc_delete_data';

/**
 * The option that holds the ids of the payment methods locked for every customer whom no unlock strategy unlocks.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_LPMWC_OPTION_LOCKED_METHODS = 'dws_lpmwc_locked_payment_methods';

/**
 * The option that holds 'yes' where users with the full-access roles may use every locked payment method.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_LPMWC_OPTION_UNLOCK_VIA_USER_ROLES = 'dws_lpmwc_unlock_via_user_roles';

/**
 * The option that holds the roles whose users may use every locked payment method while that strategy is on.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_LPMWC_OPTION_FULL_ACCESS_ROLES = 'dws_lpmwc_full_access_user_roles';

/**
 * The option that holds 'yes' where a user's profile can unlock locked payment methods for that user.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_LPMWC_OPTION_UNLOCK_VIA_USER_PROFILES = 'dws_lpmwc_unlock_via_user_profiles';

/**
 * The option that holds 'yes' where an order can unlock locked payment methods for paying that order.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_LPMWC_OPTION_UNLOCK_VIA_ORDERS = 'dws_lpmwc_unlock_via_orders';

/**
 * The option that holds 'yes' where a Groups group can unlock locked payment methods for its members.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_LPMWC_OPTION_UNLOCK_VIA_GROUPS = 'dws_lpmwc_unlock_via_groups';

/**
 * The option that holds 'yes' where a WooCommerce Memberships plan can unlock locked payment methods for its active members.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_LPMWC_OPTION_UNLOCK_VIA_WC_MEMBERSHIPS = 'dws_lpmwc_unlock_via_wc_memberships';

/**
 * The value each option reads as while nothing valid is stored.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     array<string, bool|int|string|list<string>>
 */
const DWS_LPMWC_OPTION_DEFAULTS = array(
	DWS_LPMWC_OPTION_LOCKED_METHODS            => array(),
	DWS_LPMWC_OPTION_UNLOCK_VIA_USER_ROLES     => true,
	DWS_LPMWC_OPTION_FULL_ACCESS_ROLES         => array( 'administrator', 'shop_manager' ),
	DWS_LPMWC_OPTION_UNLOCK_VIA_USER_PROFILES  => true,
	DWS_LPMWC_OPTION_UNLOCK_VIA_ORDERS         => false,
	DWS_LPMWC_OPTION_UNLOCK_VIA_GROUPS         => false,
	DWS_LPMWC_OPTION_UNLOCK_VIA_WC_MEMBERSHIPS => false,
	DWS_LPMWC_OPTION_DELETE_DATA               => false,
);

/**
 * The option each of v1's settings moved to, by v1's settings group and field id, from which v1 stored it as dws-wc-lpm_{group}_{id}.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     array<string, array<string, string>>
 */
const DWS_LPMWC_V1_SETTINGS = array(
	'general'      => array(
		'locked-payment-methods' => DWS_LPMWC_OPTION_LOCKED_METHODS,
		'unlock-via-user-role'   => DWS_LPMWC_OPTION_UNLOCK_VIA_USER_ROLES,
		'full-access-user-roles' => DWS_LPMWC_OPTION_FULL_ACCESS_ROLES,
		'unlock-via-user-meta'   => DWS_LPMWC_OPTION_UNLOCK_VIA_USER_PROFILES,
		'unlock-via-order-meta'  => DWS_LPMWC_OPTION_UNLOCK_VIA_ORDERS,
	),
	'integrations' => array(
		'unlock-via-groups'         => DWS_LPMWC_OPTION_UNLOCK_VIA_GROUPS,
		'unlock-via-wc-memberships' => DWS_LPMWC_OPTION_UNLOCK_VIA_WC_MEMBERSHIPS,
	),
	'plugin'       => array(
		'remove-data-uninstall' => DWS_LPMWC_OPTION_DELETE_DATA,
	),
);

/**
 * Returns an option as a boolean, which reads checkbox values such as 'yes' and 'no'.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $key The option's key.
 *
 * @return  bool
 */
function dws_lpmwc_get_bool_option( string $key ): bool {
	$default = dws_lpmwc_get_option_default( $key );

	return filter_var( get_option( $key, $default ), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE ) ?? ( true === $default );
}

/**
 * Returns an option as an integer.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $key The option's key.
 *
 * @return  int
 */
function dws_lpmwc_get_int_option( string $key ): int {
	$default = dws_lpmwc_get_option_default( $key );

	return filter_var( get_option( $key, $default ), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE ) ?? ( is_int( $default ) ? $default : 0 );
}

/**
 * Returns an option as a string, reading a missing or empty value of an option with a translated default as that default.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $key The option's key.
 *
 * @return  string
 */
function dws_lpmwc_get_string_option( string $key ): string {
	$text    = dws_lpmwc_get_option_text( $key );
	$default = dws_lpmwc_get_option_default( $key ) ?? $text;
	$stored  = get_option( $key, $default );
	if ( '' === $stored ) {
		return $text; // The field shows a translated default as its placeholder, so an untouched field stores an empty value.
	}

	return is_string( $stored ) ? $stored : ( is_string( $default ) ? $default : '' );
}

/**
 * Returns an option as a list of strings, keeping the stored strings in order.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $key The option's key.
 *
 * @return  list<string>
 */
function dws_lpmwc_get_list_option( string $key ): array {
	$default = dws_lpmwc_get_option_default( $key );
	$stored  = get_option( $key, $default );
	$stored  = is_array( $stored ) ? $stored : $default;

	return is_array( $stored ) ? array_values( array_filter( $stored, 'is_string' ) ) : array();
}

/**
 * Returns an option's translated default, or an empty string for an option without one.
 *
 * Callers read it from init on, because WordPress flags translations loaded earlier.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $key The option's key.
 *
 * @return  string
 */
function dws_lpmwc_get_option_text( string $key ): string {
	switch ( $key ) {
		default:
			return '';
	}
}

/**
 * Returns the option's default, or null for an option without one.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $key The option's key.
 *
 * @return  mixed
 */
function dws_lpmwc_get_option_default( string $key ) {
	return DWS_LPMWC_OPTION_DEFAULTS[ $key ] ?? null;
}
