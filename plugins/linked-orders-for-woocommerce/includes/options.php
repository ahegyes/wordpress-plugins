<?php declare( strict_types=1 );
/**
 * Holds the maximum depth's validator.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LinkedOrders
 */

defined( 'ABSPATH' ) || exit;

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
