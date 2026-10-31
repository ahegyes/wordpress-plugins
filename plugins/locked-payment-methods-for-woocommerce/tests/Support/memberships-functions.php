<?php declare( strict_types=1 );
/**
 * Stands in for the one WooCommerce Memberships function the plugin calls, since Memberships is a paid plugin absent from WP Packages.
 *
 * @package DeepWebSolutions\LockedPaymentMethods\Tests
 */

use DeepWebSolutions\LockedPaymentMethods\Tests\Support\MembershipsDouble;

if ( ! function_exists( 'wc_memberships_get_user_active_memberships' ) ) {
	function wc_memberships_get_user_active_memberships( $user_id = null, $args = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Memberships' signature.
		return MembershipsDouble::$memberships[ (int) $user_id ] ?? array();
	}
}
