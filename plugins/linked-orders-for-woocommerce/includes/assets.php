<?php declare( strict_types=1 );
/**
 * Registers the plugin's built scripts and styles with the dependencies and version wp-scripts generates beside each.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LinkedOrders
 */

use DeepWebSolutions\LinkedOrders\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Returns the payload wp-scripts generates beside a built file, which lists its dependencies, script module imports included, and its version.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $path The built file's path, relative to the plugin's directory.
 *
 * @return  array{dependencies: list<non-empty-string|array{id: non-empty-string, import?: 'static'|'dynamic'}>, version: string, type?: string, handle?: string}
 */
function dws_lowc_get_asset( string $path ): array {
	/**
	 * The payload wp-scripts generates.
	 *
	 * @var array{dependencies: list<non-empty-string|array{id: non-empty-string, import?: 'static'|'dynamic'}>, version: string, type?: string, handle?: string} $asset
	 */
	$asset = require plugin_dir_path( DWS_LOWC_FILE ) . dirname( $path ) . '/' . basename( $path, '.' . pathinfo( $path, PATHINFO_EXTENSION ) ) . '.asset.php';

	return $asset;
}

/**
 * Registers a built script with its generated dependencies and version, and loads its translations from languages/.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   non-empty-string                                                                         $handle The script's handle.
 * @param   non-empty-string                                                                         $path   The built script's path, relative to the plugin's directory.
 * @param   array{strategy?: 'async'|'defer', in_footer?: bool, fetchpriority?: 'auto'|'low'|'high'} $args   Optional. How the script loads. Default none, which loads it in the head.
 *
 * @return  bool
 */
function dws_lowc_register_script( string $handle, string $path, array $args = array() ): bool {
	$asset      = dws_lowc_get_asset( $path );
	$registered = wp_register_script( $handle, plugin_dir_url( DWS_LOWC_FILE ) . $path, array_values( array_filter( $asset['dependencies'], 'is_string' ) ), $asset['version'], $args );
	if ( $registered ) {
		wp_set_script_translations( $handle, Plugin::SLUG, plugin_dir_path( DWS_LOWC_FILE ) . 'languages' );
	}

	return $registered;
}

/**
 * Registers a built stylesheet with its generated version, and its right-to-left variant for right-to-left languages.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   non-empty-string $handle The stylesheet's handle.
 * @param   non-empty-string $path   The built stylesheet's path, relative to the plugin's directory.
 * @param   string           $media  Optional. The media the stylesheet applies to. Default 'all'.
 *
 * @return  bool
 */
function dws_lowc_register_style( string $handle, string $path, string $media = 'all' ): bool {
	$registered = wp_register_style( $handle, plugin_dir_url( DWS_LOWC_FILE ) . $path, array(), dws_lowc_get_asset( $path )['version'], $media );
	if ( $registered ) {
		wp_style_add_data( $handle, 'rtl', 'replace' );
	}

	return $registered;
}
