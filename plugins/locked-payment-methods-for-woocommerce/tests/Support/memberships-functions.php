<?php declare( strict_types=1 );
/**
 * Stands in for WooCommerce Memberships once it has loaded, its loader and the one function the plugin calls, since Memberships is a paid plugin absent from WP Packages; PHPStan reads its declarations too.
 *
 * @package DeepWebSolutions\LockedPaymentMethods\Tests
 */

use DeepWebSolutions\LockedPaymentMethods\Tests\Support\MembershipsDouble;

require_once __DIR__ . '/memberships-loader.php';

if ( ! function_exists( 'wc_memberships_get_user_active_memberships' ) ) {
	/**
	 * Returns a user's active memberships.
	 *
	 * @param   int|null             $user_id The user's id, or null for the current user.
	 * @param   array<string, mixed> $args    The query's arguments.
	 *
	 * @return  array<int, mixed>
	 */
	function wc_memberships_get_user_active_memberships( $user_id = null, $args = array() ) {
		return MembershipsDouble::$memberships[ (int) $user_id ] ?? array();
	}
}
