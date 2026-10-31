<?php declare( strict_types=1 );
/**
 * Holds the keys under which payment methods are unlocked for a subject, and reads each as a list of payment method ids.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LockedPaymentMethods
 */

defined( 'ABSPATH' ) || exit;

/**
 * The user option, under each site's table prefix, that holds the ids of the payment methods unlocked for the user on that site.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_LPMWC_USER_OPTION_UNLOCKED_METHODS = 'dws_lpmwc_unlocked_payment_methods';

/**
 * Returns the current site's user meta key for the payment methods unlocked for a user.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @global  wpdb $wpdb WordPress's database access.
 *
 * @return  string
 */
function dws_lpmwc_get_user_grants_key(): string {
	global $wpdb;

	return $wpdb->get_blog_prefix() . DWS_LPMWC_USER_OPTION_UNLOCKED_METHODS;
}

/**
 * Returns the ids of the payment methods unlocked for a user on the current site, read from the site's own key, since get_user_option() falls back to a key every site shares.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   int $user_id The user's id.
 *
 * @return  string[]
 */
function dws_lpmwc_get_user_unlocked_payment_method_ids( int $user_id ): array {
	$ids = get_user_meta( $user_id, dws_lpmwc_get_user_grants_key(), true );

	return is_array( $ids ) ? array_values( array_filter( $ids, 'is_string' ) ) : array();
}
