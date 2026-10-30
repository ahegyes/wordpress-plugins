<?php declare( strict_types=1 );
/**
 * Holds the plugin's option keys and defaults, and reads each option as its type, guarding the stored value.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\InternalComments
 */

use DeepWebSolutions\InternalComments\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * The option that holds 'yes' where the site consents to uninstall removing the plugin's data.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_IC_OPTION_DELETE_DATA = Plugin::PREFIX . '_delete_data';

/**
 * The option that holds 'yes' where the site greets returning customers on their account dashboard.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_IC_OPTION_FEATURE = Plugin::PREFIX . '_feature';

/**
 * The option that holds the greeting.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_IC_OPTION_GREETING = Plugin::PREFIX . '_greeting';

/**
 * The option that holds the number of orders a customer places before the greeting shows.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_IC_OPTION_GREETING_ORDERS = Plugin::PREFIX . '_greeting_orders';

/**
 * The value each option reads as while nothing valid is stored.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     array<string, bool|int|string|list<string>>
 */
const DWS_IC_OPTION_DEFAULTS = array(
	DWS_IC_OPTION_DELETE_DATA     => false,
	DWS_IC_OPTION_FEATURE         => false,
	DWS_IC_OPTION_GREETING_ORDERS => 1,
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
function dws_ic_get_bool_option( string $key ): bool {
	$default = dws_ic_get_option_default( $key );

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
function dws_ic_get_int_option( string $key ): int {
	$default = dws_ic_get_option_default( $key );

	return filter_var( get_option( $key, $default ), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE ) ?? ( is_int( $default ) ? $default : 0 );
}

/**
 * Returns an option as a string, reading a missing or empty value of an option with a translated default as that default, only after init.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $key The option's key.
 *
 * @return  string
 */
function dws_ic_get_string_option( string $key ): string {
	$text    = dws_ic_get_option_text( $key );
	$default = dws_ic_get_option_default( $key ) ?? $text;
	$stored  = get_option( $key, $default );
	if ( '' === $stored ) {
		return $text; // The field shows a translated default as its placeholder, so an untouched field stores an empty value.
	}

	return is_string( $stored ) ? $stored : ( is_string( $default ) ? $default : '' );
}

/**
 * Returns an option as a list of strings, keeping the stored strings in order.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $key The option's key.
 *
 * @return  list<string>
 */
function dws_ic_get_list_option( string $key ): array {
	$default = dws_ic_get_option_default( $key );
	$stored  = get_option( $key, $default );
	$stored  = is_array( $stored ) ? $stored : $default;

	return is_array( $stored ) ? array_values( array_filter( $stored, 'is_string' ) ) : array();
}

/**
 * Returns an option's translated default, which exists only after init, or an empty string for an option without one.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $key The option's key.
 *
 * @return  string
 */
function dws_ic_get_option_text( string $key ): string {
	switch ( $key ) {
		case DWS_IC_OPTION_GREETING:
			return __( 'Welcome back, and thank you for shopping with us again.', 'internal-comments' );
		default:
			return '';
	}
}

/**
 * Returns the number of orders before the greeting shows in the form WooCommerce stores, or an error when the submission is not a whole number of at least 1.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   mixed $submitted The submitted value.
 *
 * @return  string|WP_Error
 */
function dws_ic_validate_greeting_orders( $submitted ) {
	$orders = is_string( $submitted ) ? filter_var( $submitted, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ) : false;

	return false === $orders ? new WP_Error( 'invalid_number', __( 'Enter a whole number of 1 or more, such as 3.', 'internal-comments' ) ) : (string) $orders;
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
function dws_ic_get_option_default( string $key ) {
	return DWS_IC_OPTION_DEFAULTS[ $key ] ?? null;
}
