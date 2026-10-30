<?php declare( strict_types=1 );
/**
 * Keeps v1's settings API, which reads each setting by its v1 field id within its group of the plugin's settings page.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\InternalComments
 */

defined( 'ABSPATH' ) || exit;

/**
 * The option row of each of v1's settings groups, in the order the settings page shows them.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     array<string, string>
 */
const DWS_IC_SETTINGS_GROUPS = array(
	'general' => DWS_IC_OPTION_GENERAL,
	'plugin'  => DWS_IC_OPTION_PLUGIN,
);

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
function dws_ic_get_raw_setting( string $field_id, ?string $group = null ) {
	$field = dws_ic_find_setting( $field_id, $group );
	if ( null === $field ) {
		return null;
	}

	$row = get_option( DWS_IC_SETTINGS_GROUPS[ $field[0] ] );

	return is_array( $row ) && array_key_exists( $field[1], $row ) ? $row[ $field[1] ] : null;
}

/**
 * Returns the validated value of a setting, filtered, or null for a group v1 did not have.
 *
 * @since   1.1.0
 * @version 2.0.0
 *
 * @param   string      $field_id The field's v1 id, prefixed with its group and a slash when no group is given.
 * @param   string|null $group    The field's v1 group, with or without its '-settings' suffix.
 *
 * @return  mixed
 */
function dws_ic_get_validated_setting( string $field_id, ?string $group = null ) {
	$field = dws_ic_find_setting( $field_id, $group );
	if ( null === $field ) {
		return null;
	}

	list( $group_id, $id ) = $field;
	$value                 = dws_ic_get_raw_setting( $id, $group_id );
	switch ( $id ) {
		case DWS_IC_SETTING_POST_TYPES:
			$value = is_array( $value ) ? array_values( array_intersect( array_filter( $value, 'is_string' ), get_post_types( array( 'show_ui' => true ) ) ) ) : array();
			break;
		case DWS_IC_SETTING_PRIVATE_COMMENTS:
		case DWS_IC_SETTING_DELETE_DATA:
			$value = filter_var( $value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE ) ?? false;
			break;
	}

	/**
	 * Filters a setting's validated value.
	 *
	 * @since       1.0.0
	 * @version     2.0.0
	 * @deprecated  2.0.0 Use dws_ic/settings/validated_value.
	 *
	 * @param       mixed  $value    The validated value.
	 * @param       string $field_id The field's v1 id.
	 */
	$value = apply_filters_deprecated( "internal_comments/{$group_id}_settings/get_validated_option_value", array( $value, $id ), '2.0.0', 'dws_ic/settings/validated_value' );
	if ( null === $group ) {
		/**
		 * Filters the validated value of a setting read through the settings page, after its group's filter.
		 *
		 * @since       1.0.0
		 * @version     2.0.0
		 * @deprecated  2.0.0 Use dws_ic/settings/validated_value.
		 *
		 * @param       mixed  $value    The validated value.
		 * @param       string $field_id The field's v1 id, prefixed with its group and a slash.
		 */
		$value = apply_filters_deprecated( 'internal_comments/settings/get_validated_option_value', array( $value, $field_id ), '2.0.0', 'dws_ic/settings/validated_value' );
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
	return apply_filters( 'dws_ic/settings/validated_value', $value, $id, $group_id );
}

/**
 * Returns the names of the post types selected for internal comments, as stored, so a post type that registers late stays selected.
 *
 * @since   1.0.0
 * @version 2.0.0
 *
 * @return  list<string>
 */
function dws_ic_get_selected_post_types(): array {
	$selected = dws_ic_get_raw_setting( DWS_IC_SETTING_POST_TYPES, 'general' );

	return is_array( $selected ) ? array_values( array_filter( $selected, 'is_string' ) ) : array();
}

/**
 * Returns whether internal comments can be marked private.
 *
 * @since   1.0.0
 * @version 2.0.0
 *
 * @return  bool
 */
function dws_ic_are_enabled_private_comments(): bool {
	return true === dws_ic_get_validated_setting( DWS_IC_SETTING_PRIVATE_COMMENTS, 'general' );
}

/**
 * Returns a setting's group and field id, or null for a group v1 did not have.
 *
 * As in v1, a read without a group names the group in the field id, such as 'general/post-types', and a field an extension adds to a group lives in the group's row.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string      $field_id The field's v1 id, prefixed with its group and a slash when no group is given.
 * @param   string|null $group    The field's v1 group, with or without its '-settings' suffix.
 *
 * @return  array{0: string, 1: string}|null
 */
function dws_ic_find_setting( string $field_id, ?string $group ): ?array {
	if ( null === $group ) {
		if ( false === strpos( $field_id, '/' ) ) {
			return null;
		}

		list( $group, $field_id ) = explode( '/', $field_id, 2 );
	}

	if ( '-settings' === substr( $group, -9 ) ) {
		$group = substr( $group, 0, -9 );
	}

	return isset( DWS_IC_SETTINGS_GROUPS[ $group ] ) ? array( $group, $field_id ) : null;
}
