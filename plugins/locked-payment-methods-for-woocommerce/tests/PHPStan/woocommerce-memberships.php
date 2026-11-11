<?php declare( strict_types=1 );
/**
 * Declares, for PHPStan alone, the one WooCommerce Memberships function the plugin calls, since Memberships is a paid plugin absent from WP Packages.
 *
 * @package DeepWebSolutions\LockedPaymentMethods\Tests
 */

/**
 * Returns a user's active memberships.
 *
 * @param   int|null             $user_id The user's id, or null for the current user.
 * @param   array<string, mixed> $args    The query's arguments.
 *
 * @return  array<int, mixed>
 */
function wc_memberships_get_user_active_memberships( $user_id = null, $args = array() ) {}
