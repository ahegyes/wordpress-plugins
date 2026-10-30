<?php declare( strict_types=1 );
/**
 * Declares the data the plugin stores, which uninstall removes where the site consents.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LinkedOrders
 */

use DeepWebSolutions\LinkedOrders\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Returns the plugin's stored data by kind, which covers every option, transient, user meta key and scheduled job under the plugin's prefix, and what v1 stored under its own names: its settings, version rows and notices, the order links' meta and the capabilities.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  array{option_prefixes: list<non-empty-string>, user_meta_site_prefixes: list<non-empty-string>, user_meta_global_prefixes: list<non-empty-string>, network_options: list<non-empty-string>, cron_hooks: list<non-empty-string>, as_groups: list<non-empty-string>}
 */
function dws_lowc_get_footprint(): array {
	return array(
		'option_prefixes'           => array( Plugin::PREFIX . '_', '_transient_' . Plugin::PREFIX . '_', '_transient_timeout_' . Plugin::PREFIX . '_', 'dws-lowc_' ),
		'options'                   => array( 'linked_orders_for_woocommerce_version', 'linked_orders_for_woocommerce_original_version', '_linked_orders_for_woocommerce_admin_notices' ),
		'meta_prefixes'             => array( 'order' => array( '_dws_lo_' ) ),
		'user_meta_site_prefixes'   => array( Plugin::PREFIX . '_' ),
		'user_meta_global_prefixes' => array( Plugin::PREFIX . '_' ),
		'user_meta_global'          => array( '_linked_orders_for_woocommerce_admin_notices' ),
		'network_options'           => array( Plugin::PREFIX . '_network_notices' ),
		'capabilities'              => array( DWS_LOWC_CAP_CREATE_LINKED_CHILDREN, DWS_LOWC_CAP_SEE_ORDER_LINKS, DWS_LOWC_CAP_SEE_METABOX, DWS_LOWC_CAP_SEE_TABLE_COLUMN ),
		'cron_hooks'                => array( Plugin::PREFIX . '_revision/migration/run' ),
		'as_groups'                 => array( Plugin::PREFIX ),
	);
}
