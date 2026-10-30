<?php declare( strict_types=1 );
/**
 * Keeps v1's root getters, hook-tag builders, constants and boot hooks, deprecated, for code written against v1.
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
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use dws_ic_get_plugin_name().
 */
define( 'DWS_IC_NAME', $dws_ic_header['Name'] );

/**
 * The plugin's version.
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use dws_ic_get_plugin_version().
 */
define( 'DWS_IC_VERSION', $dws_ic_header['Version'] );

/**
 * The path of the plugin's main file.
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use DWS_IC_FILE.
 */
define( 'DWS_IC_PATH', DWS_IC_FILE );

/**
 * The oldest PHP version the plugin runs on.
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0
 */
define( 'DWS_IC_MIN_PHP', $dws_ic_header['RequiresPHP'] );

/**
 * The oldest WordPress version the plugin runs on.
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0
 */
define( 'DWS_IC_MIN_WP', $dws_ic_header['RequiresWP'] );

unset( $dws_ic_header );

/**
 * The root of each v1 component's hook tags, keyed by the component's id, which v1 built from the component's name.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     array<string, string>
 */
const DWS_IC_V1_COMPONENTS = array(
	'ajax'                 => 'ajax',
	'comments'             => 'comments',
	'comments-list-table'  => 'internal_comments_list_table',
	'reply-comments'       => 'reply_comments',
	'query'                => 'query',
	'query-scoped-hooks'   => 'query_scoped_hooks',
	'settings'             => 'settings',
	'general-settings'     => 'general_settings',
	'plugin-settings'      => 'plugin_settings',
	'permissions'          => 'permissions',
	'comment-permissions'  => 'comments_permissions',
	'output-permissions'   => 'output_permissions',
	'settings-permissions' => 'settings_permissions',
	'output'               => 'output',
	'metabox-output'       => 'metabox_output',
	'wp-table-output'      => 'wp_table_output',
);

/**
 * Returns the plugin's name, translated once init has fired.
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
 * Returns a hook name as v1 built it for the plugin.
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

	return dws_ic_join_v1_hook_tag( 'internal_comments', $name, $extra );
}

/**
 * Returns a hook name as v1 built it for one of its components, or null for an id v1 had no component for.
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0
 *
 * @param       string               $component_id The v1 component's id.
 * @param       string               $name         The hook's name.
 * @param       string|array<string> $extra        The name's further parts.
 *
 * @return      string|null
 */
function dws_ic_get_component_hook_tag( string $component_id, string $name, $extra = array() ): ?string {
	_deprecated_function( __FUNCTION__, '2.0.0' );

	return isset( DWS_IC_V1_COMPONENTS[ $component_id ] ) ? dws_ic_join_v1_hook_tag( 'internal_comments/' . DWS_IC_V1_COMPONENTS[ $component_id ], $name, $extra ) : null;
}

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
function dws_ic_join_v1_hook_tag( string $root, string $name, $extra ): string {
	return strtolower( str_replace( array( ' ', '\\' ), '_', implode( '/', array_filter( array_merge( array( $root, $name ), is_array( $extra ) ? $extra : array( $extra ) ), 'boolval' ) ) ) );
}

/**
 * Fires v1's hook for a plugin that booted.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  void
 */
function dws_ic_fire_v1_initialized(): void {
	/**
	 * Fires once the plugin has booted.
	 *
	 * @since       1.0.0
	 * @version     2.0.0
	 * @deprecated  2.0.0 Use dws_ic/boot/succeeded.
	 */
	do_action_deprecated( 'dws_ic_initialized', array(), '2.0.0', 'dws_ic/boot/succeeded' );
}

/**
 * Fires v1's hook for a plugin whose boot failed, with the failure.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   Throwable $error The failure.
 *
 * @return  void
 */
function dws_ic_fire_v1_initialization_failure( Throwable $error ): void {
	/**
	 * Fires when the plugin's boot failed.
	 *
	 * @since       1.0.0
	 * @version     2.0.0
	 * @deprecated  2.0.0 Use dws_ic/boot/failed.
	 *
	 * @param       Throwable $error The failure, where v1 passed its own exception.
	 */
	do_action_deprecated( 'dws_ic_initialization_failure', array( $error ), '2.0.0', 'dws_ic/boot/failed' );
}
