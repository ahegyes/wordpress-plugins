<?php declare( strict_types=1 );
/**
 * Defines the getters through which a peer plugin reads the plugin's slug, name and version.
 *
 * Each reads only the plugin's header, so a getter that is defined still answers when a later include fails.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LockedPaymentMethods
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
function dws_lpmwc_get_plugin_slug(): string {
	return get_file_data( DWS_LPMWC_FILE, array( 'TextDomain' => 'Text Domain' ) )['TextDomain'];
}

/**
 * Returns the plugin's name from its header, translated once init has fired.
 *
 * WordPress flags translations loaded earlier.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  string
 */
function dws_lpmwc_get_plugin_name(): string {
	$name = get_file_data( DWS_LPMWC_FILE, array( 'Name' => 'Plugin Name' ) )['Name'];

	return 0 < did_action( 'init' ) ? __( $name, 'locked-payment-methods-for-woocommerce' ) : $name; // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- The header's name reaches the POT through make-pot's header extraction.
}

/**
 * Returns the plugin's version from its header.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  string
 */
function dws_lpmwc_get_plugin_version(): string {
	return get_file_data( DWS_LPMWC_FILE, array( 'Version' => 'Version' ) )['Version'];
}
