<?php declare( strict_types=1 );
/**
 * Keeps v1's deprecated settings API, which reads each setting by its v1 field id within its group of the plugin's settings page, and the plugin's readers of its settings.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\InternalComments
 */

use DeepWebSolutions\InternalComments\Options;

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
function dws_ic_get_raw_setting( string $field_id, ?string $group = null ) {
	$field = Options::find_v1_setting( $field_id, $group );
	_deprecated_function( __FUNCTION__, '2.0.0', esc_html( Options::get_v1_read_replacement( $field[2] ?? null ) ) );
	return is_null( $field ) ? null : get_option( $field[2], null );
}

/**
 * Returns the validated value of a setting, through v1's filters, or null for a group v1 did not have.
 *
 * @api
 *
 * @since       1.1.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use get_option() on the option the notice names.
 *
 * @param       string      $field_id The field's v1 id, prefixed with its group and a slash when no group is given.
 * @param       string|null $group    The field's v1 group, with or without its '-settings' suffix.
 *
 * @return      mixed
 */
function dws_ic_get_validated_setting( string $field_id, ?string $group = null ) {
	$field = Options::find_v1_setting( $field_id, $group );
	_deprecated_function( __FUNCTION__, '2.0.0', esc_html( Options::get_v1_read_replacement( $field[2] ?? null ) ) );
	if ( is_null( $field ) ) {
		return null;
	}

	list( $group_id, $id, $option ) = $field;
	$value                          = get_option( $option, null );
	switch ( $option ) {
		case Options::POST_TYPES:
			$value = is_array( $value ) ? array_values( array_intersect( array_filter( $value, 'is_string' ), get_post_types( array( 'show_ui' => true ) ) ) ) : array();
			break;
		case Options::PRIVATE_COMMENTS:
		case Options::DELETE_DATA:
			$value = filter_var( $value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE ) ?? false;
			break;
	}

	$value = Options::apply_v1_setting_filter( $value, $group_id, $id, $option );
	if ( ! is_null( $group ) ) {
		return $value;
	}

	/**
	 * Filters the validated value of a setting read through the settings page, after its group's filter.
	 *
	 * @since       1.0.0
	 * @version     2.0.0
	 * @deprecated  2.0.0 Use the hook the deprecation notice names.
	 *
	 * @param       mixed  $value    The validated value.
	 * @param       string $field_id The field's v1 id, prefixed with its group and a slash.
	 */
	return apply_filters_deprecated( 'internal_comments/settings/get_validated_option_value', array( $value, $field_id ), '2.0.0', "option_{$option}" );
}

/**
 * Returns the names of the post types selected for internal comments, as stored, so a post type that registers late stays selected.
 *
 * @api
 *
 * @since   1.0.0
 * @version 2.0.0
 *
 * @return  list<string>
 */
function dws_ic_get_selected_post_types(): array {
	return Options::get_list( Options::POST_TYPES );
}

/**
 * Returns whether internal comments can be marked private.
 *
 * @api
 *
 * @since   1.0.0
 * @version 2.0.0
 *
 * @return  bool
 */
function dws_ic_are_enabled_private_comments(): bool {
	return Options::get_bool( Options::PRIVATE_COMMENTS );
}
