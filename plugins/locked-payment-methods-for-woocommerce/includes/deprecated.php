<?php declare( strict_types=1 );
/**
 * Keeps v1's root getters, hook-tag builders, constants, boot hooks, access checks and setting readers, deprecated, for code written against v1.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LockedPaymentMethods
 */

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
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use dws_lpmwc_get_plugin_name().
 */
define( 'DWS_LPMWC_NAME', $dws_lpmwc_header['Name'] );

/**
 * The plugin's version.
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use dws_lpmwc_get_plugin_version().
 */
define( 'DWS_LPMWC_VERSION', $dws_lpmwc_header['Version'] );

/**
 * The path of the plugin's main file.
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use DWS_LPMWC_FILE.
 */
define( 'DWS_LPMWC_PATH', DWS_LPMWC_FILE );

/**
 * The oldest PHP version the plugin runs on.
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0
 */
define( 'DWS_LPMWC_MIN_PHP', $dws_lpmwc_header['RequiresPHP'] );

/**
 * The oldest WordPress version the plugin runs on.
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0
 */
define( 'DWS_LPMWC_MIN_WP', $dws_lpmwc_header['RequiresWP'] );

unset( $dws_lpmwc_header );

/**
 * The root of each v1 component's hook tags, keyed by the component's id, which v1 built from the component's name.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     array<string, string>
 */
const DWS_LPMWC_V1_COMPONENTS = array(
	'settings'                 => 'settings',
	'general-settings'         => 'general_settings',
	'plugin-settings'          => 'plugin_settings',
	'permissions'              => 'permissions',
	'lock-manager'             => 'lock_manager',
	'user-meta-strategy'       => 'user_meta_unlock_strategy',
	'user-role-strategy'       => 'user_role_unlock_strategy',
	'integrations-settings'    => 'integrations_settings',
	'integrations-permissions' => 'integrations_permissions',
	'order-meta-strategy'      => 'order_meta_unlock_strategy',
	'groups-strategy'          => 'groups_unlock_strategy',
	'wc-memberships-strategy'  => 'wc_memberships_unlock_strategy',
);

/**
 * Returns the plugin's name, translated once init has fired.
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
 * Returns a hook name as v1 built it for the plugin.
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

	return dws_lpmwc_join_v1_hook_tag( 'locked_payment_methods_for_woocommerce', $name, $extra );
}

/**
 * Returns a hook name as v1 built it for one of its components, or null for an id v1 had no component for.
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0
 *
 * @param       string               $component_id The v1 component's id.
 * @param       string               $name         The hook's name.
 * @param       string|array<string> $extra        The name's further parts.
 *
 * @return      string|null
 */
function dws_lpmwc_get_component_hook_tag( string $component_id, string $name, $extra = array() ): ?string {
	_deprecated_function( __FUNCTION__, '2.0.0' );

	return isset( DWS_LPMWC_V1_COMPONENTS[ $component_id ] ) ? dws_lpmwc_join_v1_hook_tag( 'locked_payment_methods_for_woocommerce/' . DWS_LPMWC_V1_COMPONENTS[ $component_id ], $name, $extra ) : null;
}

/**
 * Returns a hook name joined and sanitized as v1 did: the non-empty parts joined with slashes, spaces and backslashes as underscores, lowercased.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string               $root  The name's root.
 * @param   string               $name  The hook's name.
 * @param   string|array<string> $extra The name's further parts.
 *
 * @return  string
 */
function dws_lpmwc_join_v1_hook_tag( string $root, string $name, $extra ): string {
	return strtolower( str_replace( array( ' ', '\\' ), '_', implode( '/', array_filter( array_merge( array( $root, $name ), is_array( $extra ) ? $extra : array( $extra ) ), 'boolval' ) ) ) );
}

/**
 * Fires v1's hook for a plugin that booted.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  void
 */
function dws_lpmwc_fire_v1_initialized(): void {
	/**
	 * Fires once the plugin has booted.
	 *
	 * @since       1.0.0
	 * @version     2.0.0
	 * @deprecated  2.0.0 Use dws_lpmwc/boot/succeeded.
	 */
	do_action_deprecated( 'dws_lpmwc_initialized', array(), '2.0.0', 'dws_lpmwc/boot/succeeded' );
}

/**
 * Fires v1's hook for a plugin whose boot failed, with the failure.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   Throwable $error The failure.
 *
 * @return  void
 */
function dws_lpmwc_fire_v1_initialization_failure( Throwable $error ): void {
	/**
	 * Fires when the plugin's boot failed.
	 *
	 * @since       1.0.0
	 * @version     2.0.0
	 * @deprecated  2.0.0 Use dws_lpmwc/boot/failed.
	 *
	 * @param       Throwable $error The failure, where v1 passed its own exception.
	 */
	do_action_deprecated( 'dws_lpmwc_initialization_failure', array( $error ), '2.0.0', 'dws_lpmwc/boot/failed' );
}

/**
 * Returns whether a payment method stays locked for a user, as the plugin's strategies judge it, firing v1's filter first.
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
	 * @deprecated  2.0.0 Use dws_lpmwc/payment_method/should_lock.
	 *
	 * @param       bool   $is_locked        Whether the payment method stays locked.
	 * @param       string $locked_method_id The payment method's id.
	 * @param       int    $user_id          The user's id.
	 */
	$lock = apply_filters_deprecated( 'locked_payment_methods_for_woocommerce/check_payment_method_access_for_user', array( true, $locked_method_id, $user_id ), '2.0.0', 'dws_lpmwc/payment_method/should_lock' );

	return dws_lpmwc_should_lock( false !== $lock, $locked_method_id, $user_id, 0 );
}

/**
 * Returns whether a payment method stays locked for paying an order, as the plugin's strategies judge it for the order and its customer, firing v1's filter first.
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
	$order = wc_get_order( $order_id );

	/**
	 * Filters whether a payment method stays locked for paying an order.
	 *
	 * @since       1.0.0
	 * @version     2.0.0
	 * @deprecated  2.0.0 Use dws_lpmwc/payment_method/should_lock.
	 *
	 * @param       bool   $is_locked        Whether the payment method stays locked.
	 * @param       string $locked_method_id The payment method's id.
	 * @param       int    $order_id         The order's id.
	 */
	$lock = apply_filters_deprecated( 'locked_payment_methods_for_woocommerce/check_payment_method_access_for_wc_order', array( true, $locked_method_id, $order_id ), '2.0.0', 'dws_lpmwc/payment_method/should_lock' );

	return dws_lpmwc_should_lock( false !== $lock, $locked_method_id, $order instanceof WC_Order ? $order->get_customer_id() : 0, $order_id );
}

/**
 * Returns whether the user-profile strategy leaves a payment method locked for a user, true while the strategy is off.
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

	return ! ( UserProfileStrategy::should_load() && UserProfileStrategy::unlocks( $locked_method_id, $user_id ) );
}

/**
 * Returns whether the role strategy leaves a payment method locked for a user, true while the strategy is off.
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

	return ! ( UserRoleStrategy::should_load() && UserRoleStrategy::unlocks( $locked_method_id, $user_id ) );
}

/**
 * Returns whether the Groups strategy leaves a payment method locked for a user, true while the strategy is off.
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

	return ! ( GroupsStrategy::should_load() && GroupsStrategy::unlocks( $locked_method_id, $user_id ) );
}

/**
 * Returns whether the WooCommerce Memberships strategy leaves a payment method locked for a user, true while the strategy is off.
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

	return ! ( WooCommerceMembershipsStrategy::should_load() && WooCommerceMembershipsStrategy::unlocks( $locked_method_id, $user_id ) );
}

/**
 * Returns whether the order strategy leaves a payment method locked for paying an order, true while the strategy is off.
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

	return ! ( OrderStrategy::should_load() && OrderStrategy::unlocks( $locked_method_id, $order_id ) );
}

/**
 * Returns a setting's stored value, by v1's field id and group, or null for an id v1 had no setting for.
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use get_option().
 *
 * @param       string      $field_id v1's id of the setting, prefixed with its group and a slash when the group is null.
 * @param       string|null $group    Optional. v1's id of the setting's group. Default null.
 *
 * @return      mixed
 */
function dws_lpmwc_get_raw_setting( string $field_id, ?string $group = null ) {
	_deprecated_function( __FUNCTION__, '2.0.0', 'get_option()' );
	$setting = dws_lpmwc_find_v1_setting( $field_id, $group );

	return null === $setting ? null : get_option( $setting['key'], null );
}

/**
 * Returns a setting's value, of the setting's kind, by v1's field id and group, through v1's value filters, or null for an id v1 had no setting for.
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use get_option().
 *
 * @param       string      $field_id v1's id of the setting, prefixed with its group and a slash when the group is null.
 * @param       string|null $group    Optional. v1's id of the setting's group. Default null.
 *
 * @return      mixed
 */
function dws_lpmwc_get_validated_setting( string $field_id, ?string $group = null ) {
	_deprecated_function( __FUNCTION__, '2.0.0', 'get_option()' );

	return dws_lpmwc_read_v1_setting( $field_id, $group );
}

/**
 * Returns a value as the setting's kind, by v1's field id and group: a boolean for a switch and a list of strings for a list, falling back to the setting's default.
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
	$setting = dws_lpmwc_find_v1_setting( $field_id, $group );

	return null === $setting ? null : dws_lpmwc_validate_v1_setting( $value, $setting['key'] );
}

/**
 * Returns a General setting's value, of the setting's kind, by v1's field id, through v1's value filter.
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use get_option().
 *
 * @param       string $field_id v1's id of the setting.
 *
 * @return      mixed
 */
function dws_lpmwc_get_validated_general_setting( string $field_id ) {
	_deprecated_function( __FUNCTION__, '2.0.0', 'get_option()' );

	return dws_lpmwc_read_v1_setting( $field_id, 'general' );
}

/**
 * Returns v1's group and field ids and the plugin's option for one of v1's settings, or null for an id v1 had no setting for.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string      $field_id v1's id of the setting, prefixed with its group and a slash when the group is null.
 * @param   string|null $group    v1's id of the setting's group.
 *
 * @return  array{group: string, field: string, key: string}|null
 */
function dws_lpmwc_find_v1_setting( string $field_id, ?string $group ): ?array {
	if ( null === $group ) {
		$parts = explode( '/', $field_id, 2 );
		if ( 2 !== count( $parts ) ) {
			return null;
		}
		list( $group, $field_id ) = $parts;
	}

	$key = DWS_LPMWC_V1_SETTINGS[ $group ][ $field_id ] ?? null;

	return null === $key ? null : array(
		'group' => $group,
		'field' => $field_id,
		'key'   => $key,
	);
}

/**
 * Returns a setting's value as v1's settings read it: through v1's page filter when the group is null, then through its group's filter.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string      $field_id v1's id of the setting, prefixed with its group and a slash when the group is null.
 * @param   string|null $group    v1's id of the setting's group.
 *
 * @return  mixed
 */
function dws_lpmwc_read_v1_setting( string $field_id, ?string $group ) {
	$setting = dws_lpmwc_find_v1_setting( $field_id, $group );
	if ( null === $group ) {
		/**
		 * Filters a setting's value, by its group-prefixed v1 id, before its group reads it.
		 *
		 * @since       1.0.0
		 * @version     2.0.0
		 * @deprecated  2.0.0 Use the hook the deprecation notice names.
		 *
		 * @param       mixed  $value    Null, or the value a callback answers.
		 * @param       string $field_id v1's id of the setting, prefixed with its group and a slash.
		 */
		$value = apply_filters_deprecated( 'locked_payment_methods_for_woocommerce/settings/get_validated_option_value', array( null, $field_id ), '2.0.0', null === $setting ? 'get_option' : 'option_' . $setting['key'] );
		if ( null !== $value ) {
			return $value;
		}
	}

	return null === $setting ? null : dws_lpmwc_apply_v1_setting_filter( dws_lpmwc_validate_v1_setting( get_option( $setting['key'], null ), $setting['key'] ), $setting['group'], $setting['field'], 'option_' . $setting['key'] );
}

/**
 * Returns a value as a setting's kind, a boolean for a switch and a list of strings for a list, or the setting's default.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   mixed  $value The value.
 * @param   string $key   The setting's option.
 *
 * @return  mixed
 */
function dws_lpmwc_validate_v1_setting( $value, string $key ) {
	$default = DWS_LPMWC_OPTION_DEFAULTS[ $key ] ?? null;
	if ( is_bool( $default ) ) {
		return filter_var( $value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE ) ?? $default;
	}

	return is_array( $value ) ? array_values( array_filter( $value, 'is_string' ) ) : $default;
}
