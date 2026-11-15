<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods;

\defined( 'ABSPATH' ) || exit;

/**
 * Holds what v1's deprecated API needs and no other code calls.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class Deprecated {
	// region FIELDS AND CONSTANTS

	/**
	 * The root of each v1 component's hook tags, keyed by the component's id, which v1 built from the component's name.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<string, string>
	 */
	public const array COMPONENTS = array(
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

	// region METHODS

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
	public static function join_hook_tag( string $root, string $name, $extra ): string {
		return \strtolower( \str_replace( array( ' ', '\\' ), '_', \implode( '/', \array_filter( \array_merge( array( $root, $name ), \is_array( $extra ) ? $extra : array( $extra ) ), 'boolval' ) ) ) );
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
	public static function read_setting( string $field_id, ?string $group ) {
		$setting = Options::find_v1_setting( $field_id, $group );
		if ( \is_null( $group ) ) {
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
			$value = apply_filters_deprecated( 'locked_payment_methods_for_woocommerce/settings/get_validated_option_value', array( null, $field_id ), '2.0.0', \is_null( $setting ) ? 'get_option' : 'option_' . $setting['key'] );
			if ( ! \is_null( $value ) ) {
				return $value;
			}
		}

		return \is_null( $setting ) ? null : Safeguards::apply_v1_setting_filter( self::validate_setting( get_option( $setting['key'], null ), $setting['key'] ), $setting['group'], $setting['field'], 'option_' . $setting['key'] );
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
	public static function validate_setting( $value, string $key ) {
		$default = Options::DEFAULTS[ $key ] ?? null;
		if ( ! \is_array( $default ) ) {
			return \filter_var( $value, \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE ) ?? \filter_var( $default, \FILTER_VALIDATE_BOOL );
		}

		return \is_array( $value ) ? \array_values( \array_filter( $value, 'is_string' ) ) : $default;
	}

	// endregion
}
