<?php declare( strict_types=1 );
/**
 * Keeps v1's settings API, which reads each setting by its v1 field id within its group of the plugin's section.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LinkedOrders
 */

use DeepWebSolutions\LinkedOrders\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Returns the stored value of a setting, or null for a setting that is not stored or a group v1 did not have.
 *
 * @api
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
	$key = Options::find_v1_setting( $field_id, $group )[2];
	return is_null( $key ) ? null : get_option( $key, null );
}

/**
 * Returns the validated value of a setting, filtered, or null for a group v1 did not have.
 *
 * As in v1, a read without a group is filtered even for an id no group maps, so an extension's callback can answer for it.
 *
 * @api
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
	list( $group_id, $id, $key ) = Options::find_v1_setting( $field_id, $group );
	if ( ! is_null( $key ) ) {
		$value = dws_lowc_validate_setting( get_option( $key, null ), $id, $group_id );

		/**
		 * Filters a setting's validated value.
		 *
		 * @since       1.0.0
		 * @version     2.0.0
		 * @deprecated  2.0.0 Use linked_orders_for_woocommerce/settings/validated_value.
		 *
		 * @param       mixed  $value    The validated value.
		 * @param       string $field_id The field's v1 id.
		 */
		$value = apply_filters_deprecated( "linked_orders_for_woocommerce/{$group_id}_settings/get_validated_option_value", array( $value, $id ), '2.0.0', 'linked_orders_for_woocommerce/settings/validated_value' );
	} elseif ( is_null( $group ) ) {
		$value = null;
	} else {
		return null;
	}

	if ( is_null( $group ) ) {
		/**
		 * Filters the validated value of a setting read through the section, after its group's filter, or null for an id no group maps.
		 *
		 * @since       1.0.0
		 * @version     2.0.0
		 * @deprecated  2.0.0 Use linked_orders_for_woocommerce/settings/validated_value.
		 *
		 * @param       mixed  $value    The validated value.
		 * @param       string $field_id The field's v1 id, prefixed with its group and a slash.
		 */
		$value = apply_filters_deprecated( 'linked_orders_for_woocommerce/settings/get_validated_option_value', array( $value, $field_id ), '2.0.0', 'linked_orders_for_woocommerce/settings/validated_value' );
	}

	/**
	 * Filters a setting's validated value.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed  $value    The validated value.
	 * @param   string $field_id The field's v1 id.
	 * @param   string $group    The field's v1 group without its '-settings' suffix, empty when the id names none.
	 */
	return apply_filters( 'linked_orders_for_woocommerce/settings/validated_value', $value, $id, $group_id );
}

/**
 * Returns a value validated as the given built-in setting stores it, or the value itself for any other field.
 *
 * @api
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
	$key = Options::find_v1_setting( $field_id, $group )[2];
	if ( Options::MAX_DEPTH === $key ) {
		return max( filter_var( $value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE ) ?? (int) Options::DEFAULTS[ $key ], 1 );
	}

	if ( Options::AUTOCOMPLETE === $key || Options::DELETE_DATA === $key ) {
		return filter_var( $value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE ) ?? filter_var( Options::DEFAULTS[ $key ], FILTER_VALIDATE_BOOL );
	}

	return $value;
}
