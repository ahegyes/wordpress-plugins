<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders;

\defined( 'ABSPATH' ) || exit;

/**
 * Names the plugin's stored settings, which keep v1's keys, and their defaults, and reads them.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class Options {
	// region FIELDS AND CONSTANTS

	/**
	 * The option that holds the deepest level a linked order can be created at, where root orders are level 0.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string MAX_DEPTH = 'dws-lowc_general_max-depth';

	/**
	 * The option that holds 'yes' where completing an order completes its descendants.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string AUTOCOMPLETE = 'dws-lowc_general_autocomplete-descendants';

	/**
	 * The option that holds 'yes' where the site consents to uninstall removing the plugin's data.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string DELETE_DATA = 'dws-lowc_plugin_remove-data-uninstall';

	/**
	 * The default of each option, the value v1 stored as its default, in the form the option stores it.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<string, string>
	 */
	public const array DEFAULTS = array(
		self::MAX_DEPTH    => '1',
		self::AUTOCOMPLETE => 'no',
		self::DELETE_DATA  => 'no',
	);

	/**
	 * The option of each of v1's settings fields, by v1's group and field id, in the order the settings section shows them.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<string, array<string, string>>
	 */
	public const array V1_SETTINGS = array(
		'general' => array(
			'max-depth'                => self::MAX_DEPTH,
			'autocomplete-descendants' => self::AUTOCOMPLETE,
		),
		'plugin'  => array(
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
	 * Returns an option as a boolean, which reads checkbox values such as 'yes' and 'no', through v1's validation and value filter for a setting v1 had.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $key The option's key.
	 *
	 * @return  bool
	 */
	public static function get_bool( string $key ): bool {
		return \filter_var( self::read( $key ), \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE ) ?? \filter_var( self::DEFAULTS[ $key ] ?? null, \FILTER_VALIDATE_BOOL );
	}

	/**
	 * Returns an option as an integer, through v1's validation and value filter for a setting v1 had.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $key The option's key.
	 *
	 * @return  int
	 */
	public static function get_int( string $key ): int {
		return \filter_var( self::read( $key ), \FILTER_VALIDATE_INT, \FILTER_NULL_ON_FAILURE ) ?? \filter_var( self::DEFAULTS[ $key ] ?? null, \FILTER_VALIDATE_INT, \FILTER_NULL_ON_FAILURE ) ?? 0;
	}

	/**
	 * Returns a setting's group without its '-settings' suffix, its field id and its option, which is null for a group v1 did not have.
	 *
	 * As in v1, a read without a group names the group in the field id, such as 'general/max-depth', and a field an extension adds to a group without an id of its own lives in the option WooCommerce saves it under, dws-lowc_{group}_{field}. An id read without a group that names none has an empty group.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string      $field_id The field's v1 id, prefixed with its group and a slash when no group is given.
	 * @param   string|null $group    The field's v1 group, with or without its '-settings' suffix.
	 *
	 * @return  array{0: string, 1: string, 2: string|null}
	 */
	public static function find_v1_setting( string $field_id, ?string $group ): array {
		if ( \is_null( $group ) ) {
			list( $group, $field_id ) = \array_pad( \explode( '/', $field_id, 2 ), -2, '' );
		}

		if ( '-settings' === \substr( $group, -9 ) ) {
			$group = \substr( $group, 0, -9 );
		}

		return array( $group, $field_id, \in_array( $group, array( 'general', 'plugin' ), true ) ? "dws-lowc_{$group}_$field_id" : null );
	}

	/**
	 * Returns a setting's value as v1's settings read it: validated, through its group's filter, then through v1's section filter when the group is null, or null for a group v1 did not have.
	 *
	 * As in v1, a read without a group is filtered even for an id no group maps, so an extension's callback can answer for it. The plugin's own reads of v1's settings pass through here too, so v1's callbacks keep changing what it does until the next major removes v1's filters.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string      $field_id The field's v1 id, prefixed with its group and a slash when no group is given.
	 * @param   string|null $group    The field's v1 group, with or without its '-settings' suffix.
	 *
	 * @return  mixed
	 */
	public static function read_setting( string $field_id, ?string $group ): mixed {
		list( $group_id, $id, $key ) = self::find_v1_setting( $field_id, $group );
		if ( \is_null( $key ) && ! \is_null( $group ) ) {
			return null;
		}

		$replacement = match ( $key ) {
			null            => 'get_option',
			self::MAX_DEPTH => 'linked_orders_for_woocommerce/node/can_create_child',
			default         => "option_{$key}",
		};

		$value = null;
		if ( ! \is_null( $key ) ) {
			/**
			 * Filters a setting's validated value.
			 *
			 * @since       1.0.0
			 * @version     2.0.0
			 * @deprecated  2.0.0 Use the hook the deprecation notice names.
			 *
			 * @param       mixed  $value    The validated value.
			 * @param       string $field_id The field's v1 id.
			 */
			$value = apply_filters_deprecated( "linked_orders_for_woocommerce/{$group_id}_settings/get_validated_option_value", array( self::validate_setting( get_option( $key, null ), $key ), $id ), '2.0.0', $replacement );
		}

		if ( ! \is_null( $group ) ) {
			return $value;
		}

		/**
		 * Filters the validated value of a setting read through the section, after its group's filter, or null for an id no group maps.
		 *
		 * @since       1.0.0
		 * @version     2.0.0
		 * @deprecated  2.0.0 Use the hook the deprecation notice names.
		 *
		 * @param       mixed  $value    The validated value.
		 * @param       string $field_id The field's v1 id, prefixed with its group and a slash.
		 */
		return apply_filters_deprecated( 'linked_orders_for_woocommerce/settings/get_validated_option_value', array( $value, $field_id ), '2.0.0', $replacement );
	}

	/**
	 * Returns a value validated as v1 validated the given setting, or the value itself for any other option.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed       $value The value to validate.
	 * @param   string|null $key   The setting's option.
	 *
	 * @return  mixed
	 */
	public static function validate_setting( mixed $value, ?string $key ): mixed {
		return match ( $key ) {
			self::MAX_DEPTH                       => \max( \filter_var( $value, \FILTER_VALIDATE_INT, \FILTER_NULL_ON_FAILURE ) ?? (int) self::DEFAULTS[ $key ], 1 ),
			self::AUTOCOMPLETE, self::DELETE_DATA => \filter_var( $value, \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE ) ?? \filter_var( self::DEFAULTS[ $key ], \FILTER_VALIDATE_BOOL ),
			default                               => $value,
		};
	}

	/**
	 * Returns what replaces a v1 read of a setting's option, for the read's deprecation notice.
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
		return \is_null( $key ) ? 'get_option()' : "get_option() on {$key}";
	}

	// endregion

	// region HELPERS

	/**
	 * Returns an option's value, which for a setting v1 had passes through v1's validation and its group's filter, as v1's reads did.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $key The option's key.
	 *
	 * @return  mixed
	 */
	protected static function read( string $key ): mixed {
		foreach ( self::V1_SETTINGS as $group => $keys ) {
			$field_id = \array_search( $key, $keys, true );
			if ( \is_string( $field_id ) ) {
				return self::read_setting( $field_id, $group );
			}
		}

		return get_option( $key, self::DEFAULTS[ $key ] ?? null );
	}

	// endregion
}
