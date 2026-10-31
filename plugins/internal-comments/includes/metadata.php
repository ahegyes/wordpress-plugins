<?php declare( strict_types=1 );
/**
 * Defines the getters through which a peer plugin reads the plugin's slug, name and version, so the plugin's classes never become its contract.
 *
 * A peer calls them from plugins_loaded on, since it may load before the plugin, and behind function_exists(), since a site below the floors or with a partial build may leave them undefined. Each reads only the plugin's header, so a getter that is defined still answers when a later include fails.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\InternalComments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the plugin's slug, which is the text domain its header declares.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  string
 */
function dws_ic_get_plugin_slug(): string {
	return get_file_data( DWS_IC_FILE, array( 'TextDomain' => 'Text Domain' ) )['TextDomain'];
}

/**
 * Returns the plugin's name from its header, translated only once init has fired, because WordPress flags translations loaded earlier.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  string
 */
function dws_ic_get_plugin_name(): string {
	$name = get_file_data( DWS_IC_FILE, array( 'Name' => 'Plugin Name' ) )['Name'];

	return 0 < did_action( 'init' ) ? __( $name, 'internal-comments' ) : $name; // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- The header's name reaches the POT through make-pot's header extraction.
}

/**
 * Returns the plugin's version from its header.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  string
 */
function dws_ic_get_plugin_version(): string {
	return get_file_data( DWS_IC_FILE, array( 'Version' => 'Version' ) )['Version'];
}
