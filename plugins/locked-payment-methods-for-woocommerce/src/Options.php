<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods;

\defined( 'ABSPATH' ) || exit;

/**
 * Names the plugin's stored settings, their defaults and the setting each of v1's settings fields moved to, and reads each setting as its type, guarding the stored value.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class Options {
	// region FIELDS AND CONSTANTS

	/**
	 * The option that holds 'yes' where the site consents to uninstall removing the plugin's data.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string DELETE_DATA = 'dws_lpmwc_delete_data';

	/**
	 * The option that holds the ids of the payment methods locked for every customer whom no unlock strategy unlocks.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string LOCKED_METHODS = 'dws_lpmwc_locked_payment_methods';

	/**
	 * The option that holds 'yes' where users with the full-access roles may use every locked payment method.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string UNLOCK_VIA_USER_ROLES = 'dws_lpmwc_unlock_via_user_roles';

	/**
	 * The option that holds the roles whose users may use every locked payment method while that strategy is on.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string FULL_ACCESS_ROLES = 'dws_lpmwc_full_access_user_roles';

	/**
	 * The option that holds 'yes' where a user's profile can unlock locked payment methods for that user.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string UNLOCK_VIA_USER_PROFILES = 'dws_lpmwc_unlock_via_user_profiles';

	/**
	 * The option that holds 'yes' where an order can unlock locked payment methods for paying that order.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string UNLOCK_VIA_ORDERS = 'dws_lpmwc_unlock_via_orders';

	/**
	 * The option that holds 'yes' where a Groups group can unlock locked payment methods for its members.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string UNLOCK_VIA_GROUPS = 'dws_lpmwc_unlock_via_groups';

	/**
	 * The option that holds 'yes' where a WooCommerce Memberships plan can unlock locked payment methods for its active members.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string UNLOCK_VIA_WC_MEMBERSHIPS = 'dws_lpmwc_unlock_via_wc_memberships';

	/**
	 * The default of each option, the value v1 stored as its default, in the form the option stores it.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<string, string|list<string>>
	 */
	public const array DEFAULTS = array(
		self::LOCKED_METHODS            => array(),
		self::UNLOCK_VIA_USER_ROLES     => 'yes',
		self::FULL_ACCESS_ROLES         => array( 'administrator', 'shop_manager' ),
		self::UNLOCK_VIA_USER_PROFILES  => 'yes',
		self::UNLOCK_VIA_ORDERS         => 'no',
		self::UNLOCK_VIA_GROUPS         => 'no',
		self::UNLOCK_VIA_WC_MEMBERSHIPS => 'no',
		self::DELETE_DATA               => 'no',
	);

	/**
	 * The option each of v1's settings moved to, by v1's settings group and field id, from which v1 stored it as dws-wc-lpm_{group}_{id}.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<string, array<string, string>>
	 */
	public const array V1_SETTINGS = array(
		'general'      => array(
			'locked-payment-methods' => self::LOCKED_METHODS,
			'unlock-via-user-role'   => self::UNLOCK_VIA_USER_ROLES,
			'full-access-user-roles' => self::FULL_ACCESS_ROLES,
			'unlock-via-user-meta'   => self::UNLOCK_VIA_USER_PROFILES,
			'unlock-via-order-meta'  => self::UNLOCK_VIA_ORDERS,
		),
		'integrations' => array(
			'unlock-via-groups'         => self::UNLOCK_VIA_GROUPS,
			'unlock-via-wc-memberships' => self::UNLOCK_VIA_WC_MEMBERSHIPS,
		),
		'plugin'       => array(
			'remove-data-uninstall' => self::DELETE_DATA,
		),
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
	 * Returns an option as a boolean, which reads checkbox values such as 'yes' and 'no'.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $key The option's key.
	 *
	 * @return  bool
	 */
	public static function get_bool( string $key ): bool {
		$default = self::get_default( $key );
		return \filter_var( get_option( $key, $default ), \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE ) ?? \filter_var( $default, \FILTER_VALIDATE_BOOL );
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
	public static function get_list( string $key ): array {
		$default = self::get_default( $key );
		$stored  = get_option( $key, $default );
		$stored  = \is_array( $stored ) ? $stored : $default;
		return \is_array( $stored ) ? \array_values( \array_filter( $stored, 'is_string' ) ) : array();
	}

	/**
	 * Returns v1's group and field ids and the option for one of v1's settings, or null for a group v1 did not have.
	 *
	 * As in v1, a read without a group names the group in the field id, such as 'general/locked-payment-methods', and a field an extension adds to a group lives in the option the section stores it in, dws-wc-lpm_{group}_{id}.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string      $field_id v1's id of the setting, prefixed with its group and a slash when the group is null.
	 * @param   string|null $group    v1's id of the setting's group, with or without its '-settings' suffix.
	 *
	 * @return  array{group: string, field: string, key: string}|null
	 */
	public static function find_v1_setting( string $field_id, ?string $group ): ?array {
		if ( \is_null( $group ) ) {
			$parts = \explode( '/', $field_id, 2 );
			if ( 2 !== \count( $parts ) ) {
				return null;
			}
			list( $group, $field_id ) = $parts;
		}

		$group = \str_ends_with( $group, '-settings' ) ? \substr( $group, 0, -9 ) : $group;
		return isset( self::V1_SETTINGS[ $group ] ) ? array(
			'group' => $group,
			'field' => $field_id,
			'key'   => self::V1_SETTINGS[ $group ][ $field_id ] ?? "dws-wc-lpm_{$group}_$field_id",
		) : null;
	}

	// endregion

	// region HELPERS

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
	protected static function get_default( string $key ): mixed {
		return self::DEFAULTS[ $key ] ?? null;
	}

	/**
	 * Returns a setting's value as v1's settings read it: validated through its group's filter, within v1's page filter when the group is null, or null for a group v1 did not have.
	 *
	 * @since       2.0.0
	 * @version     2.0.0
	 * @deprecated  2.0.0
	 *
	 * @param       string      $field_id v1's id of the setting, prefixed with its group and a slash when the group is null.
	 * @param       string|null $group    v1's id of the setting's group.
	 *
	 * @return      mixed
	 */
	public static function read_setting( string $field_id, ?string $group ): mixed {
		$setting     = self::find_v1_setting( $field_id, $group );
		$key         = $setting['key'] ?? null;
		$replacement = match ( $key ) {
			null                 => 'get_option',
			self::LOCKED_METHODS => 'locked_payment_methods_for_woocommerce/payment_method/locked_ids',
			default              => "option_$key",
		};
		$read = static fn (): mixed => \is_null( $setting ) ? null : Safeguards::apply_v1_setting_filter( self::validate_setting( get_option( $setting['key'], null ), $setting['key'] ), $setting['group'], $setting['field'], $replacement );
		$page = 'locked_payment_methods_for_woocommerce/settings/get_validated_option_value';
		if ( ! \is_null( $group ) || ! has_filter( $page ) ) {
			return $read();
		}

		// v1's groups hooked their resolver at priority 10 as the plugin loaded, before the callbacks at 10 that code loaded later adds, and a callback may read another setting inside the filter, so the resolver runs at 9 and answers only its own field.
		$resolve = static fn ( mixed $value, string $id ): mixed => \is_null( $value ) && $field_id === $id ? $read() : $value;
		add_filter( $page, $resolve, 9, 2 );
		try {
			/**
			 * Filters a setting's value, by its group-prefixed v1 id, as v1's settings page read it.
			 *
			 * @since       1.0.0
			 * @version     2.0.0
			 * @deprecated  2.0.0 Use the hook the deprecation notice names.
			 *
			 * @param       mixed  $value    Null, or the value a callback answers, which the setting's group resolves at priority 9.
			 * @param       string $field_id v1's id of the setting, prefixed with its group and a slash.
			 */
			return apply_filters_deprecated( 'locked_payment_methods_for_woocommerce/settings/get_validated_option_value', array( null, $field_id ), '2.0.0', $replacement );
		} finally {
			remove_filter( $page, $resolve, 9 );
		}
	}

	/**
	 * Returns a value as a setting's kind, a boolean for a switch and a list of strings for a list, or the setting's default, and an extension's field's value as given.
	 *
	 * @since       2.0.0
	 * @version     2.0.0
	 * @deprecated  2.0.0
	 *
	 * @param       mixed  $value The value.
	 * @param       string $key   The setting's option.
	 *
	 * @return      mixed
	 */
	public static function validate_setting( mixed $value, string $key ): mixed {
		if ( ! \array_key_exists( $key, self::DEFAULTS ) ) {
			return $value;
		}

		$default = self::DEFAULTS[ $key ];
		if ( ! \is_array( $default ) ) {
			return \filter_var( $value ?? $default, \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE ) ?? \filter_var( $default, \FILTER_VALIDATE_BOOL );
		}

		return \is_array( $value ) ? \array_values( \array_filter( $value, 'is_string' ) ) : $default;
	}

	/**
	 * Returns what replaces a v1 read of a setting's option, for the read's deprecation notice: the locked list's global route and hooks, or get_option() on any other option.
	 *
	 * @since       2.0.0
	 * @version     2.0.0
	 * @deprecated  2.0.0
	 *
	 * @param       string|null $key The setting's option, or null for an id v1 had no setting for.
	 *
	 * @return      string
	 */
	public static function get_v1_read_replacement( ?string $key ): string {
		return match ( $key ) {
			null                 => 'get_option()',
			self::LOCKED_METHODS => 'dws_lpmwc_get_locked_payment_method_ids(), the locked_payment_methods_for_woocommerce/payment_method/locked_ids filter or dws_lpmwc_is_payment_method_locked()',
			default              => "get_option() on {$key}",
		};
	}

	// endregion
}
