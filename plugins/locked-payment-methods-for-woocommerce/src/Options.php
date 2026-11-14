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
	public static function find_v1_setting( string $field_id, ?string $group ): ?array {
		if ( \is_null( $group ) ) {
			$parts = \explode( '/', $field_id, 2 );
			if ( 2 !== \count( $parts ) ) {
				return null;
			}
			list( $group, $field_id ) = $parts;
		}

		$key = self::V1_SETTINGS[ $group ][ $field_id ] ?? null;
		return \is_null( $key ) ? null : array(
			'group' => $group,
			'field' => $field_id,
			'key'   => $key,
		);
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

	// endregion
}
