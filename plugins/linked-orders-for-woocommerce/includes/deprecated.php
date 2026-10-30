<?php declare( strict_types=1 );
/**
 * Keeps v1's root getters, hook-tag builders, constants and boot hooks, deprecated, for code written against v1.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LinkedOrders
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
$dws_lowc_header = get_file_data(
	DWS_LOWC_FILE,
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
 * @deprecated  2.0.0 Use dws_lowc_get_plugin_name().
 */
define( 'DWS_LOWC_NAME', $dws_lowc_header['Name'] );

/**
 * The plugin's version.
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use dws_lowc_get_plugin_version().
 */
define( 'DWS_LOWC_VERSION', $dws_lowc_header['Version'] );

/**
 * The path of the plugin's main file.
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use DWS_LOWC_FILE.
 */
define( 'DWS_LOWC_PATH', DWS_LOWC_FILE );

/**
 * The oldest PHP version the plugin runs on.
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0
 */
define( 'DWS_LOWC_MIN_PHP', $dws_lowc_header['RequiresPHP'] );

/**
 * The oldest WordPress version the plugin runs on.
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0
 */
define( 'DWS_LOWC_MIN_WP', $dws_lowc_header['RequiresWP'] );

unset( $dws_lowc_header );

/**
 * The ids of v1's components, whose hook tags took the id with dashes as underscores.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     list<string>
 */
const DWS_LOWC_V1_COMPONENTS = array( 'actions', 'autocompletion', 'output', 'metabox-output', 'list-table-output', 'permissions', 'output-permissions', 'settings', 'general-settings', 'plugin-settings', 'integrations', 'wc-sequential-order-numbers-pro-integration' );

/**
 * Returns the plugin's name, translated once init has fired.
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use dws_lowc_get_plugin_name().
 *
 * @return      string
 */
function dws_lowc_name(): string {
	_deprecated_function( __FUNCTION__, '2.0.0', 'dws_lowc_get_plugin_name()' );

	return dws_lowc_get_plugin_name();
}

/**
 * Returns the plugin's version.
 *
 * @since       1.0.0
 * @version     2.0.0
 * @deprecated  2.0.0 Use dws_lowc_get_plugin_version().
 *
 * @return      string
 */
function dws_lowc_version(): string {
	_deprecated_function( __FUNCTION__, '2.0.0', 'dws_lowc_get_plugin_version()' );

	return dws_lowc_get_plugin_version();
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
function dws_lowc_path(): string {
	_deprecated_function( __FUNCTION__, '2.0.0' );

	return DWS_LOWC_PATH;
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
function dws_lowc_min_php(): string {
	_deprecated_function( __FUNCTION__, '2.0.0' );

	return DWS_LOWC_MIN_PHP;
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
function dws_lowc_min_wp(): string {
	_deprecated_function( __FUNCTION__, '2.0.0' );

	return DWS_LOWC_MIN_WP;
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
function dws_lowc_get_hook_tag( string $name, $extra = array() ): string {
	_deprecated_function( __FUNCTION__, '2.0.0' );

	return dws_lowc_join_v1_hook_tag( 'linked_orders_for_woocommerce', $name, $extra );
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
function dws_lowc_get_component_hook_tag( string $component_id, string $name, $extra = array() ): ?string {
	_deprecated_function( __FUNCTION__, '2.0.0' );

	return in_array( $component_id, DWS_LOWC_V1_COMPONENTS, true ) ? dws_lowc_join_v1_hook_tag( 'linked_orders_for_woocommerce/' . str_replace( '-', '_', $component_id ), $name, $extra ) : null;
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
function dws_lowc_join_v1_hook_tag( string $root, string $name, $extra ): string {
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
function dws_lowc_fire_v1_initialized(): void {
	/**
	 * Fires once the plugin has booted.
	 *
	 * @since       1.0.0
	 * @version     2.0.0
	 * @deprecated  2.0.0 Use dws_lowc/boot/succeeded.
	 */
	do_action_deprecated( 'dws_lowc_initialized', array(), '2.0.0', 'dws_lowc/boot/succeeded' );
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
function dws_lowc_fire_v1_initialization_failure( Throwable $error ): void {
	/**
	 * Fires when the plugin's boot failed.
	 *
	 * @since       1.0.0
	 * @version     2.0.0
	 * @deprecated  2.0.0 Use dws_lowc/boot/failed.
	 *
	 * @param       Throwable $error The failure, where v1 passed its own exception.
	 */
	do_action_deprecated( 'dws_lowc_initialization_failure', array( $error ), '2.0.0', 'dws_lowc/boot/failed' );
}
