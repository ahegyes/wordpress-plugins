<?php declare( strict_types=1 );
/**
 * Keeps v1's settings API, which reads each setting by its v1 field id within its group of the plugin's section.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LinkedOrders
 */

defined( 'ABSPATH' ) || exit;

/**
 * The ids of v1's settings groups, in the order the plugin's section shows them; a field of a group lives in the option `dws-lowc_{group}_{field}`.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     list<string>
 */
const DWS_LOWC_SETTINGS_GROUPS = array( 'general', 'plugin' );

/**
 * Returns the stored value of a setting, or null for a setting that is not stored or a group v1 did not have.
 *
 * @since   1.0.0
 * @version 2.0.0
 *
 * @param   string      $field_id The field's v1 id, prefixed with its group and a slash when no group is given.
 * @param   string|null $group    The field's v1 group, with or without its '-settings' suffix.
 *
 * @return  mixed
 */
function dws_lowc_get_raw_setting( string $field_id, ?string $group = null ) {
	$field = dws_lowc_find_setting( $field_id, $group );

	return null === $field ? null : get_option( $field[2], null );
}

/**
 * Returns the validated value of a setting, filtered, or null for a group v1 did not have.
 *
 * @since   1.0.0
 * @version 2.0.0
 *
 * @param   string      $field_id The field's v1 id, prefixed with its group and a slash when no group is given.
 * @param   string|null $group    The field's v1 group, with or without its '-settings' suffix.
 *
 * @return  mixed
 */
function dws_lowc_get_validated_setting( string $field_id, ?string $group = null ) {
	$field = dws_lowc_find_setting( $field_id, $group );
	if ( null === $field ) {
		return null;
	}

	list( $group_id, $id, $key ) = $field;
	$value                       = dws_lowc_validate_setting( get_option( $key, null ), $id, $group_id );

	/**
	 * Filters a setting's validated value.
	 *
	 * @since       1.0.0
	 * @version     2.0.0
	 * @deprecated  2.0.0 Use dws_lowc/settings/validated_value.
	 *
	 * @param       mixed  $value    The validated value.
	 * @param       string $field_id The field's v1 id.
	 */
	$value = apply_filters_deprecated( "linked_orders_for_woocommerce/{$group_id}_settings/get_validated_option_value", array( $value, $id ), '2.0.0', 'dws_lowc/settings/validated_value' );
	if ( null === $group ) {
		/**
		 * Filters the validated value of a setting read through the section, after its group's filter.
		 *
		 * @since       1.0.0
		 * @version     2.0.0
		 * @deprecated  2.0.0 Use dws_lowc/settings/validated_value.
		 *
		 * @param       mixed  $value    The validated value.
		 * @param       string $field_id The field's v1 id, prefixed with its group and a slash.
		 */
		$value = apply_filters_deprecated( 'linked_orders_for_woocommerce/settings/get_validated_option_value', array( $value, $field_id ), '2.0.0', 'dws_lowc/settings/validated_value' );
	}

	/**
	 * Filters a setting's validated value.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed  $value    The validated value.
	 * @param   string $field_id The field's v1 id.
	 * @param   string $group    The field's v1 group.
	 */
	return apply_filters( 'dws_lowc/settings/validated_value', $value, $id, $group_id );
}

/**
 * Returns a value validated as the given built-in setting stores it, or the value itself for any other field.
 *
 * @since   1.1.0
 * @version 2.0.0
 *
 * @param   mixed       $value    The value to validate.
 * @param   string      $field_id The field's v1 id, prefixed with its group and a slash when no group is given.
 * @param   string|null $group    The field's v1 group, with or without its '-settings' suffix.
 *
 * @return  mixed
 */
function dws_lowc_validate_setting( $value, string $field_id, ?string $group = null ) {
	$field = dws_lowc_find_setting( $field_id, $group );
	switch ( null === $field ? null : $field[2] ) {
		case DWS_LOWC_OPTION_MAX_DEPTH:
			return max( filter_var( $value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE ) ?? 1, 1 );
		case DWS_LOWC_OPTION_AUTOCOMPLETE:
		case DWS_LOWC_OPTION_DELETE_DATA:
			return filter_var( $value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE ) ?? false;
		default:
			return $value;
	}
}

/**
 * Returns the deepest level a linked order can be created at: the validated setting, at least 1 as stored, or what v1's and v2's filters make it.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  int
 */
function dws_lowc_get_max_depth(): int {
	return filter_var( dws_lowc_get_validated_setting( 'max-depth', 'general' ), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE ) ?? 1;
}

/**
 * Returns a setting's group, field id and option, or null for a group v1 did not have.
 *
 * As in v1, a read without a group names the group in the field id, such as 'general/max-depth', and a field an extension adds to a group lives in the option WooCommerce saves it under, `dws-lowc_{group}_{field}`.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string      $field_id The field's v1 id, prefixed with its group and a slash when no group is given.
 * @param   string|null $group    The field's v1 group, with or without its '-settings' suffix.
 *
 * @return  array{0: string, 1: string, 2: string}|null
 */
function dws_lowc_find_setting( string $field_id, ?string $group ): ?array {
	if ( null === $group ) {
		if ( false === strpos( $field_id, '/' ) ) {
			return null;
		}

		list( $group, $field_id ) = explode( '/', $field_id, 2 );
	}

	if ( '-settings' === substr( $group, -9 ) ) {
		$group = substr( $group, 0, -9 );
	}

	return in_array( $group, DWS_LOWC_SETTINGS_GROUPS, true ) ? array( $group, $field_id, "dws-lowc_{$group}_{$field_id}" ) : null;
}
