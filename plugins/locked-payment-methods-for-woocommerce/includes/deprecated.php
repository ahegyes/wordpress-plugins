<?php declare( strict_types=1 );
/**
 * Keeps v1's root getters, hook-tag builders, constants, access checks and setting readers, deprecated, for code written against v1.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LockedPaymentMethods
 */

use DeepWebSolutions\LockedPaymentMethods\Options;
use DeepWebSolutions\LockedPaymentMethods\Safeguards;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies\GroupsStrategy;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies\OrderStrategy;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies\UserProfileStrategy;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies\UserRoleStrategy;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies\WooCommerceMembershipsStrategy;

defined( 'ABSPATH' ) || exit;

/**
 * The plugin's header values that v1's constants and getters returned, read once per request.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     array<string, string>
 */
$dws_lpmwc_header = get_file_data(
	DWS_LPMWC_FILE,
	array(
		'Name'        => 'Plugin Name',
		'Version'     => 'Version',
		'RequiresWP'  => 'Requires at least',
		'RequiresPHP' => 'Requires PHP',
	)
);

/**
 * The plugin's untranslated name.
 *
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use dws_lpmwc_get_plugin_name().
 */
define( 'DWS_LPMWC_NAME', $dws_lpmwc_header['Name'] );

/**
 * The plugin's version.
 *
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use dws_lpmwc_get_plugin_version().
 */
define( 'DWS_LPMWC_VERSION', $dws_lpmwc_header['Version'] );

/**
 * The path of the plugin's main file.
 *
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use DWS_LPMWC_FILE.
 */
define( 'DWS_LPMWC_PATH', DWS_LPMWC_FILE );

/**
 * The oldest PHP version the plugin runs on.
 *
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0
 */
define( 'DWS_LPMWC_MIN_PHP', $dws_lpmwc_header['RequiresPHP'] );

/**
 * The oldest WordPress version the plugin runs on.
 *
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0
 */
define( 'DWS_LPMWC_MIN_WP', $dws_lpmwc_header['RequiresWP'] );

unset( $dws_lpmwc_header );

/**
 * Returns the plugin's name, translated once init has fired.
 *
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use dws_lpmwc_get_plugin_name().
 *
 * @return      string
 */
function dws_lpmwc_name(): string {
	_deprecated_function( __FUNCTION__, '2.0.0', 'dws_lpmwc_get_plugin_name()' );
	return dws_lpmwc_get_plugin_name();
}

/**
 * Returns the plugin's version.
 *
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use dws_lpmwc_get_plugin_version().
 *
 * @return      string
 */
function dws_lpmwc_version(): string {
	_deprecated_function( __FUNCTION__, '2.0.0', 'dws_lpmwc_get_plugin_version()' );
	return dws_lpmwc_get_plugin_version();
}

/**
 * Returns the path of the plugin's main file.
 *
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0
 *
 * @return      string
 */
function dws_lpmwc_path(): string {
	_deprecated_function( __FUNCTION__, '2.0.0' );
	return DWS_LPMWC_PATH;
}

/**
 * Returns the oldest PHP version the plugin runs on.
 *
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0
 *
 * @return      string
 */
function dws_lpmwc_min_php(): string {
	_deprecated_function( __FUNCTION__, '2.0.0' );
	return DWS_LPMWC_MIN_PHP;
}

/**
 * Returns the oldest WordPress version the plugin runs on.
 *
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0
 *
 * @return      string
 */
function dws_lpmwc_min_wp(): string {
	_deprecated_function( __FUNCTION__, '2.0.0' );
	return DWS_LPMWC_MIN_WP;
}

/**
 * Returns a hook name under the plugin's v1 root, its non-empty parts joined with slashes, with spaces and backslashes as underscores, control and non-ASCII bytes stripped and lowercased, as v1 built it.
 *
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0
 *
 * @param       string               $name  The hook's name.
 * @param       string|array<string> $extra The name's further parts.
 *
 * @return      string
 */
function dws_lpmwc_get_hook_tag( string $name, $extra = array() ): string {
	_deprecated_function( __FUNCTION__, '2.0.0' );
	return strtolower( (string) filter_var( str_replace( array( ' ', '\\' ), '_', implode( '/', array_filter( array_merge( array( 'locked_payment_methods_for_woocommerce', $name ), (array) $extra ), 'boolval' ) ) ), FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ) );
}

/**
 * Returns a hook name under one of v1's components, its non-empty parts joined with slashes, with spaces and backslashes as underscores, control and non-ASCII bytes stripped and lowercased, as v1 built it: the root v1 named the component's hooks under, which is its id with dashes as underscores but for the unlock strategies, which v1 renamed.
 *
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0
 *
 * @param       string               $component_id The v1 component's id.
 * @param       string               $name         The hook's name.
 * @param       string|array<string> $extra        The name's further parts.
 *
 * @return      string
 */
function dws_lpmwc_get_component_hook_tag( string $component_id, string $name, $extra = array() ): string {
	_deprecated_function( __FUNCTION__, '2.0.0' );
	$root = match ( $component_id ) {
		'user-meta-strategy'      => 'user_meta_unlock_strategy',
		'user-role-strategy'      => 'user_role_unlock_strategy',
		'order-meta-strategy'     => 'order_meta_unlock_strategy',
		'groups-strategy'         => 'groups_unlock_strategy',
		'wc-memberships-strategy' => 'wc_memberships_unlock_strategy',
		default                   => str_replace( '-', '_', $component_id ),
	};

	return strtolower( (string) filter_var( str_replace( array( ' ', '\\' ), '_', implode( '/', array_filter( array_merge( array( 'locked_payment_methods_for_woocommerce', $root, $name ), (array) $extra ), 'boolval' ) ) ), FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ) );
}

/**
 * Returns whether a payment method stays locked for a user, as the plugin's strategies judge it, firing v1's filter first.
 *
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use dws_lpmwc_is_payment_method_locked().
 *
 * @param       string $locked_method_id The payment method's id.
 * @param       int    $user_id          The user's id.
 *
 * @return      bool
 */
function dws_lpmwc_check_payment_method_access_for_user( string $locked_method_id, int $user_id ): bool {
	_deprecated_function( __FUNCTION__, '2.0.0', 'dws_lpmwc_is_payment_method_locked()' );

	/**
	 * Filters whether a payment method stays locked for a user.
	 *
	 * @since       1.0.0
	 * @version     2.0.0
	 * @deprecated  2.0.0 Use locked_payment_methods_for_woocommerce/payment_method/should_lock.
	 *
	 * @param       bool   $is_locked        Whether the payment method stays locked.
	 * @param       string $locked_method_id The payment method's id.
	 * @param       int    $user_id          The user's id.
	 */
	$lock = apply_filters_deprecated( 'locked_payment_methods_for_woocommerce/check_payment_method_access_for_user', array( true, $locked_method_id, $user_id ), '2.0.0', 'locked_payment_methods_for_woocommerce/payment_method/should_lock' );
	return Safeguards::should_lock( false !== $lock, $locked_method_id, $user_id, 0 );
}

/**
 * Returns whether a payment method stays locked for paying an order, as the plugin's strategies judge it for the order and its customer, firing v1's filter first, and true until the plugin has booted.
 *
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use dws_lpmwc_is_payment_method_locked().
 *
 * @param       string $locked_method_id The payment method's id.
 * @param       int    $order_id         The order's id.
 *
 * @return      bool
 */
function dws_lpmwc_check_payment_method_access_for_wc_order( string $locked_method_id, int $order_id ): bool {
	_deprecated_function( __FUNCTION__, '2.0.0', 'dws_lpmwc_is_payment_method_locked()' );
	$order = OrderStrategy::get_order( $order_id );

	/**
	 * Filters whether a payment method stays locked for paying an order.
	 *
	 * @since       1.0.0
	 * @version     2.0.0
	 * @deprecated  2.0.0 Use locked_payment_methods_for_woocommerce/payment_method/should_lock.
	 *
	 * @param       bool   $is_locked        Whether the payment method stays locked.
	 * @param       string $locked_method_id The payment method's id.
	 * @param       int    $order_id         The order's id.
	 */
	$lock = apply_filters_deprecated( 'locked_payment_methods_for_woocommerce/check_payment_method_access_for_wc_order', array( true, $locked_method_id, $order_id ), '2.0.0', 'locked_payment_methods_for_woocommerce/payment_method/should_lock' );
	return Safeguards::should_lock( false !== $lock, $locked_method_id, $order instanceof WC_Order ? $order->get_customer_id() : 0, $order_id );
}

/**
 * Returns whether the user-profile strategy leaves a payment method locked for a user, true while the strategy is off or the plugin has not booted.
 *
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use dws_lpmwc_is_payment_method_locked().
 *
 * @param       string $locked_method_id The payment method's id.
 * @param       int    $user_id          The user's id.
 *
 * @return      bool
 */
function dws_lpmwc_check_payment_method_access_via_user_meta( string $locked_method_id, int $user_id ): bool {
	_deprecated_function( __FUNCTION__, '2.0.0', 'dws_lpmwc_is_payment_method_locked()' );
	return ! ( dws_lpmwc_get_unlock_strategy( UserProfileStrategy::ID )?->unlocks( $locked_method_id, $user_id, 0 ) ?? false );
}

/**
 * Returns whether the role strategy leaves a payment method locked for a user, true while the strategy is off or the plugin has not booted.
 *
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use dws_lpmwc_is_payment_method_locked().
 *
 * @param       string $locked_method_id The payment method's id.
 * @param       int    $user_id          The user's id.
 *
 * @return      bool
 */
function dws_lpmwc_check_payment_method_access_via_user_roles( string $locked_method_id, int $user_id ): bool {
	_deprecated_function( __FUNCTION__, '2.0.0', 'dws_lpmwc_is_payment_method_locked()' );
	return ! ( dws_lpmwc_get_unlock_strategy( UserRoleStrategy::ID )?->unlocks( $locked_method_id, $user_id, 0 ) ?? false );
}

/**
 * Returns whether the Groups strategy leaves a payment method locked for a user, true while the strategy is off or the plugin has not booted.
 *
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use dws_lpmwc_is_payment_method_locked().
 *
 * @param       string $locked_method_id The payment method's id.
 * @param       int    $user_id          The user's id.
 *
 * @return      bool
 */
function dws_lpmwc_check_payment_method_access_via_groups( string $locked_method_id, int $user_id ): bool {
	_deprecated_function( __FUNCTION__, '2.0.0', 'dws_lpmwc_is_payment_method_locked()' );
	return ! ( dws_lpmwc_get_unlock_strategy( GroupsStrategy::ID )?->unlocks( $locked_method_id, $user_id, 0 ) ?? false );
}

/**
 * Returns whether the WooCommerce Memberships strategy leaves a payment method locked for a user, true while the strategy is off or the plugin has not booted.
 *
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use dws_lpmwc_is_payment_method_locked().
 *
 * @param       string $locked_method_id The payment method's id.
 * @param       int    $user_id          The user's id.
 *
 * @return      bool
 */
function dws_lpmwc_check_payment_method_access_via_wc_memberships( string $locked_method_id, int $user_id ): bool {
	_deprecated_function( __FUNCTION__, '2.0.0', 'dws_lpmwc_is_payment_method_locked()' );
	return ! ( dws_lpmwc_get_unlock_strategy( WooCommerceMembershipsStrategy::ID )?->unlocks( $locked_method_id, $user_id, 0 ) ?? false );
}

/**
 * Returns whether the order strategy leaves a payment method locked for paying an order, true while the strategy is off or the plugin has not booted.
 *
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use dws_lpmwc_is_payment_method_locked().
 *
 * @param       string $locked_method_id The payment method's id.
 * @param       int    $order_id         The order's id.
 *
 * @return      bool
 */
function dws_lpmwc_check_payment_method_access_via_wc_order_meta( string $locked_method_id, int $order_id ): bool {
	_deprecated_function( __FUNCTION__, '2.0.0', 'dws_lpmwc_is_payment_method_locked()' );
	return ! ( dws_lpmwc_get_unlock_strategy( OrderStrategy::ID )?->unlocks( $locked_method_id, 0, $order_id ) ?? false );
}

/**
 * Returns a setting's stored value, by v1's field id and group, or null for an id v1 had no setting for.
 *
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use what the deprecation notice names.
 *
 * @param       string      $field_id v1's id of the setting, prefixed with its group and a slash when the group is null.
 * @param       string|null $group    Optional. v1's id of the setting's group. Default null.
 *
 * @return      mixed
 */
function dws_lpmwc_get_raw_setting( string $field_id, ?string $group = null ) {
	$setting = Options::find_v1_setting( $field_id, $group );
	_deprecated_function( __FUNCTION__, '2.0.0', esc_html( Options::get_v1_read_replacement( $setting['key'] ?? null ) ) );
	return is_null( $setting ) ? null : get_option( $setting['key'], null );
}

/**
 * Returns a setting's value, of the setting's kind, by v1's field id and group, through v1's value filters, or null for an id v1 had no setting for.
 *
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use what the deprecation notice names.
 *
 * @param       string      $field_id v1's id of the setting, prefixed with its group and a slash when the group is null.
 * @param       string|null $group    Optional. v1's id of the setting's group. Default null.
 *
 * @return      mixed
 */
function dws_lpmwc_get_validated_setting( string $field_id, ?string $group = null ) {
	_deprecated_function( __FUNCTION__, '2.0.0', esc_html( Options::get_v1_read_replacement( Options::find_v1_setting( $field_id, $group )['key'] ?? null ) ) );
	return Options::read_setting( $field_id, $group );
}

/**
 * Returns a value as the setting's kind, by v1's field id and group: a boolean for a switch and a list of strings for a list, falling back to the setting's default.
 *
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0
 *
 * @param       mixed       $value    The value.
 * @param       string      $field_id v1's id of the setting, prefixed with its group and a slash when the group is null.
 * @param       string|null $group    Optional. v1's id of the setting's group. Default null.
 *
 * @return      mixed
 */
function dws_lpmwc_validate_setting( $value, string $field_id, ?string $group = null ) {
	_deprecated_function( __FUNCTION__, '2.0.0' );
	$setting = Options::find_v1_setting( $field_id, $group );
	return is_null( $setting ) ? null : Options::validate_setting( $value, $setting['key'] );
}

/**
 * Returns a General setting's value, of the setting's kind, by v1's field id, through v1's value filter.
 *
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use what the deprecation notice names.
 *
 * @param       string $field_id v1's id of the setting.
 *
 * @return      mixed
 */
function dws_lpmwc_get_validated_general_setting( string $field_id ) {
	_deprecated_function( __FUNCTION__, '2.0.0', esc_html( Options::get_v1_read_replacement( Options::find_v1_setting( $field_id, 'general' )['key'] ?? null ) ) );
	return Options::read_setting( $field_id, 'general' );
}
