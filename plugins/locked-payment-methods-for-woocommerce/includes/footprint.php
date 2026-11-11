<?php declare( strict_types=1 );
/**
 * Declares the data the plugin stores, which uninstall removes where the site consents.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LockedPaymentMethods
 */

use DeepWebSolutions\LockedPaymentMethods\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Returns the plugin's stored data by kind.
 *
 * It also covers the grants on Groups groups and WooCommerce Memberships plans, and what v1 stored under its own names.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  array{option_prefixes: list<non-empty-string>, options: list<non-empty-string>, meta: array<string, list<non-empty-string>>, meta_prefixes: array<string, list<non-empty-string>>, user_meta_site_prefixes: list<non-empty-string>, user_meta_global_prefixes: list<non-empty-string>, user_meta_global: list<non-empty-string>, network_options: list<non-empty-string>, capabilities: list<non-empty-string>, cron_hooks: list<non-empty-string>, as_groups: list<non-empty-string>}
 */
function dws_lpmwc_get_footprint(): array {
	return array(
		'option_prefixes'           => array( Plugin::PREFIX . '_', '_transient_' . Plugin::PREFIX . '_', '_transient_timeout_' . Plugin::PREFIX . '_', 'dws-wc-lpm_' ),
		'options'                   => array( 'locked_payment_methods_for_woocommerce_version', 'locked_payment_methods_for_woocommerce_original_version', '_locked_payment_methods_for_woocommerce_admin_notices' ),
		'meta'                      => array( 'post' => array( DWS_LPMWC_META_UNLOCKED_METHODS, '_dws_wc_lpm_unlocked_payment_methods' ) ),
		'meta_prefixes'             => array( 'order' => array( '_' . Plugin::PREFIX . '_', '_dws_wc_lpm_' ) ),
		'user_meta_site_prefixes'   => array( Plugin::PREFIX . '_' ),
		'user_meta_global_prefixes' => array( Plugin::PREFIX . '_', 'dws_wc_lpm_grant_access_' ),
		'user_meta_global'          => array( '_locked_payment_methods_for_woocommerce_admin_notices' ),
		'network_options'           => array( Plugin::PREFIX . '_network_notices' ),
		'capabilities'              => array( DWS_LPMWC_CAP_UNLOCK_VIA_USER_PROFILES, DWS_LPMWC_CAP_UNLOCK_VIA_ORDERS, DWS_LPMWC_CAP_UNLOCK_VIA_GROUPS, DWS_LPMWC_CAP_UNLOCK_VIA_WC_MEMBERSHIPS ),
		'cron_hooks'                => array( Plugin::PREFIX . '_revision/migration/run' ),
		'as_groups'                 => array( Plugin::PREFIX ),
	);
}
