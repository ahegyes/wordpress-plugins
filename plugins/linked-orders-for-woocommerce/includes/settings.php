<?php declare( strict_types=1 );
/**
 * Keeps v1's deprecated settings API, which reads each setting by its v1 field id within its group of the plugin's section.
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
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use get_option() on the option the notice names.
 *
 * @param       string      $field_id The field's v1 id, prefixed with its group and a slash when no group is given.
 * @param       string|null $group    The field's v1 group, with or without its '-settings' suffix.
 *
 * @return      mixed
 */
function dws_lowc_get_raw_setting( string $field_id, ?string $group = null ) {
	$key = Options::find_v1_setting( $field_id, $group )[2];
	_deprecated_function( __FUNCTION__, '2.0.0', esc_html( Options::get_v1_read_replacement( $key ) ) );
	return is_null( $key ) ? null : get_option( $key, null );
}

/**
 * Returns the validated value of a setting, through v1's filters, or null for a group v1 did not have.
 *
 * As in v1, a read without a group is filtered even for an id no group maps, so an extension's callback can answer for it.
 *
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use get_option() on the option the notice names.
 *
 * @param       string      $field_id The field's v1 id, prefixed with its group and a slash when no group is given.
 * @param       string|null $group    The field's v1 group, with or without its '-settings' suffix.
 *
 * @return      mixed
 */
function dws_lowc_get_validated_setting( string $field_id, ?string $group = null ) {
	_deprecated_function( __FUNCTION__, '2.0.0', esc_html( Options::get_v1_read_replacement( Options::find_v1_setting( $field_id, $group )[2] ) ) );
	return Options::read_setting( $field_id, $group );
}

/**
 * Returns a value validated as the given built-in setting stores it, or the value itself for any other field.
 *
 * @api
 *
 * @since       1.1.0
 * @version     2.0.0
 * @deprecated  2.0.0
 *
 * @param       mixed       $value    The value to validate.
 * @param       string      $field_id The field's v1 id, prefixed with its group and a slash when no group is given.
 * @param       string|null $group    The field's v1 group, with or without its '-settings' suffix.
 *
 * @return      mixed
 */
function dws_lowc_validate_setting( $value, string $field_id, ?string $group = null ) {
	_deprecated_function( __FUNCTION__, '2.0.0' );
	return Options::validate_setting( $value, Options::find_v1_setting( $field_id, $group )[2] );
}
