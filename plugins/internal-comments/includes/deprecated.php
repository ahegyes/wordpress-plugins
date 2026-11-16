<?php declare( strict_types=1 );
/**
 * Keeps v1's root getters, hook-tag builders and constants, deprecated, for code written against v1.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\InternalComments
 */

defined( 'ABSPATH' ) || exit;

/**
 * The plugin's header values that v1's constants and getters returned, read once per request.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     array<string, string>
 */
$dws_ic_header = get_file_data(
	DWS_IC_FILE,
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
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use dws_ic_get_plugin_name().
 */
define( 'DWS_IC_NAME', $dws_ic_header['Name'] );

/**
 * The plugin's version.
 *
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use dws_ic_get_plugin_version().
 */
define( 'DWS_IC_VERSION', $dws_ic_header['Version'] );

/**
 * The path of the plugin's main file.
 *
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use DWS_IC_FILE.
 */
define( 'DWS_IC_PATH', DWS_IC_FILE );

/**
 * The oldest PHP version the plugin runs on.
 *
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0
 */
define( 'DWS_IC_MIN_PHP', $dws_ic_header['RequiresPHP'] );

/**
 * The oldest WordPress version the plugin runs on.
 *
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0
 */
define( 'DWS_IC_MIN_WP', $dws_ic_header['RequiresWP'] );

unset( $dws_ic_header );

/**
 * Returns the plugin's name, translated once init has fired.
 *
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use dws_ic_get_plugin_name().
 *
 * @return      string
 */
function dws_ic_name(): string {
	_deprecated_function( __FUNCTION__, '2.0.0', 'dws_ic_get_plugin_name()' );
	return dws_ic_get_plugin_name();
}

/**
 * Returns the plugin's version.
 *
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use dws_ic_get_plugin_version().
 *
 * @return      string
 */
function dws_ic_version(): string {
	_deprecated_function( __FUNCTION__, '2.0.0', 'dws_ic_get_plugin_version()' );
	return dws_ic_get_plugin_version();
}

/**
 * Returns the path of the plugin's main file.
 *
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0
 *
 * @return      string
 */
function dws_ic_path(): string {
	_deprecated_function( __FUNCTION__, '2.0.0' );
	return DWS_IC_PATH;
}

/**
 * Returns the oldest PHP version the plugin runs on.
 *
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0
 *
 * @return      string
 */
function dws_ic_min_php(): string {
	_deprecated_function( __FUNCTION__, '2.0.0' );
	return DWS_IC_MIN_PHP;
}

/**
 * Returns the oldest WordPress version the plugin runs on.
 *
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0
 *
 * @return      string
 */
function dws_ic_min_wp(): string {
	_deprecated_function( __FUNCTION__, '2.0.0' );
	return DWS_IC_MIN_WP;
}

/**
 * Returns a hook name under the plugin's v1 root, its non-empty parts joined with slashes, with spaces and backslashes as underscores, control and non-ASCII bytes stripped and lowercased, as v1 built it.
 *
 * @api
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
function dws_ic_get_hook_tag( string $name, $extra = array() ): string {
	_deprecated_function( __FUNCTION__, '2.0.0' );
	return strtolower( (string) filter_var( str_replace( array( ' ', '\\' ), '_', implode( '/', array_filter( array_merge( array( 'internal_comments', $name ), (array) $extra ), 'boolval' ) ) ), FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ) );
}

/**
 * Returns a hook name under one of v1's components, its non-empty parts joined with slashes, with spaces and backslashes as underscores, control and non-ASCII bytes stripped and lowercased, as v1 built it: the root v1 named the component's hooks under, which is its id with dashes as underscores but for two components v1 renamed.
 *
 * @api
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0
 *
 * @param       string               $component_id The v1 component's id.
 * @param       string               $name         The hook's name.
 * @param       string|array<string> $extra        The name's further parts.
 *
 * @return      string
 */
function dws_ic_get_component_hook_tag( string $component_id, string $name, $extra = array() ): string {
	_deprecated_function( __FUNCTION__, '2.0.0' );
	$root = match ( $component_id ) {
		'comments-list-table' => 'internal_comments_list_table',
		'comment-permissions' => 'comments_permissions',
		default               => str_replace( '-', '_', $component_id ),
	};

	return strtolower( (string) filter_var( str_replace( array( ' ', '\\' ), '_', implode( '/', array_filter( array_merge( array( 'internal_comments', $root, $name ), (array) $extra ), 'boolval' ) ) ), FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ) );
}
