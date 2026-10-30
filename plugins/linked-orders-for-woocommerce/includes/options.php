<?php declare( strict_types=1 );
/**
 * Holds the plugin's option keys, which are v1's, their defaults, and the maximum depth's validator.
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
