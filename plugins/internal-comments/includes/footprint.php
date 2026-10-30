<?php declare( strict_types=1 );
/**
 * Declares the data the plugin stores, which uninstall removes where the site consents.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\InternalComments
 */

use DeepWebSolutions\InternalComments\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Returns the plugin's stored data by kind, which covers every option, transient, user meta key and scheduled job under the plugin's prefix.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  array{option_prefixes: list<non-empty-string>, user_meta_site_prefixes: list<non-empty-string>, user_meta_global_prefixes: list<non-empty-string>, network_options: list<non-empty-string>, cron_hooks: list<non-empty-string>, as_groups: list<non-empty-string>}
 */
function dws_ic_get_footprint(): array {
	return array(
		'option_prefixes'           => array( Plugin::PREFIX . '_', '_transient_' . Plugin::PREFIX . '_', '_transient_timeout_' . Plugin::PREFIX . '_' ),
		'user_meta_site_prefixes'   => array( Plugin::PREFIX . '_' ),
		'user_meta_global_prefixes' => array( Plugin::PREFIX . '_' ),
		'network_options'           => array( Plugin::PREFIX . '_network_notices' ),
		'cron_hooks'                => array( Plugin::PREFIX . '_revision/migration/run' ),
		'as_groups'                 => array( Plugin::PREFIX ),
	);
}
