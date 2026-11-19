<?php declare( strict_types=1 );
/**
 * Stands in for WooCommerce Memberships' main file, which declares its loader at include time and defines its functions only on plugins_loaded at priority 15, after the plugin's boot.
 *
 * @package DeepWebSolutions\LockedPaymentMethods\Tests
 */

if ( ! class_exists( 'WC_Memberships_Loader' ) ) {
	final class WC_Memberships_Loader {}
}
