<?php declare( strict_types=1 );
/**
 * Holds the plugin's option keys, which are v1's, and their defaults, and reads each option as its type, guarding the stored value.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LinkedOrders
 */

defined( 'ABSPATH' ) || exit;

/**
 * The option that holds the deepest level a linked order can be created at, where root orders are level 0.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_LOWC_OPTION_MAX_DEPTH = 'dws-lowc_general_max-depth';

/**
 * The option that holds 'yes' where completing an order completes its descendants.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_LOWC_OPTION_AUTOCOMPLETE = 'dws-lowc_general_autocomplete-descendants';

/**
 * The option that holds 'yes' where the site consents to uninstall removing the plugin's data.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_LOWC_OPTION_DELETE_DATA = 'dws-lowc_plugin_remove-data-uninstall';

/**
 * The value each option reads as while nothing valid is stored.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     array<string, bool|int>
 */
const DWS_LOWC_OPTION_DEFAULTS = array(
	DWS_LOWC_OPTION_MAX_DEPTH    => 1,
	DWS_LOWC_OPTION_AUTOCOMPLETE => false,
	DWS_LOWC_OPTION_DELETE_DATA  => false,
);

/**
 * Returns an option as a boolean, which reads checkbox values such as 'yes' and 'no'.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $key The option's key.
 *
 * @return  bool
 */
function dws_lowc_get_bool_option( string $key ): bool {
	$default = dws_lowc_get_option_default( $key );

	return filter_var( get_option( $key, $default ), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE ) ?? ( true === $default );
}

/**
 * Returns an option as an integer.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $key The option's key.
 *
 * @return  int
 */
function dws_lowc_get_int_option( string $key ): int {
	$default = dws_lowc_get_option_default( $key );

	return filter_var( get_option( $key, $default ), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE ) ?? ( is_int( $default ) ? $default : 0 );
}

/**
 * Returns the deepest level a linked order can be created at, at least 1, as v1 read it.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  int
 */
function dws_lowc_get_max_depth(): int {
	return max( dws_lowc_get_int_option( DWS_LOWC_OPTION_MAX_DEPTH ), 1 );
}

/**
 * Returns the maximum depth in the form WooCommerce stores, or an error when the submission is not a whole number of at least 1.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   mixed $submitted The submitted value.
 *
 * @return  string|WP_Error
 */
function dws_lowc_validate_max_depth( $submitted ) {
	$depth = is_string( $submitted ) ? filter_var( $submitted, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ) : false;

	return false === $depth ? new WP_Error( 'invalid_number', __( 'Enter a whole number of 1 or more, such as 2.', 'linked-orders-for-woocommerce' ) ) : (string) $depth;
}

/**
 * Returns the option's default, or null for an option without one.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $key The option's key.
 *
 * @return  mixed
 */
function dws_lowc_get_option_default( string $key ) {
	return DWS_LOWC_OPTION_DEFAULTS[ $key ] ?? null;
}
