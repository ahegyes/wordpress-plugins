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
 * Returns the plugin's stored data by kind, which covers every option, transient, user meta key and scheduled job under the plugin's prefix, and what v1 stored under its own names: its settings, version rows and notices, the internal comments and the capabilities.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  array{option_prefixes: list<non-empty-string>, options: list<non-empty-string>, comment_types: list<non-empty-string>, user_meta_site_prefixes: list<non-empty-string>, user_meta_global_prefixes: list<non-empty-string>, user_meta_global: list<non-empty-string>, network_options: list<non-empty-string>, capabilities: list<non-empty-string>, cron_hooks: list<non-empty-string>, as_groups: list<non-empty-string>}
 */
function dws_ic_get_footprint(): array {
	return array(
		'option_prefixes'           => array( Plugin::PREFIX . '_', '_transient_' . Plugin::PREFIX . '_', '_transient_timeout_' . Plugin::PREFIX . '_', 'dws-internal-comments_' ),
		'options'                   => array( 'internal_comments_version', 'internal_comments_original_version', '_internal_comments_admin_notices' ),
		'comment_types'             => array( 'dws_internal' ),
		'user_meta_site_prefixes'   => array( Plugin::PREFIX . '_' ),
		'user_meta_global_prefixes' => array( Plugin::PREFIX . '_' ),
		'user_meta_global'          => array( '_internal_comments_admin_notices' ),
		'network_options'           => array( Plugin::PREFIX . '_network_notices' ),
		'capabilities'              => array( DWS_IC_CAP_INSERT, DWS_IC_CAP_EDIT, DWS_IC_CAP_EDIT_OTHERS, DWS_IC_CAP_TRASH, DWS_IC_CAP_TRASH_OTHERS, DWS_IC_CAP_SEE, DWS_IC_CAP_SEE_TABLE, DWS_IC_CAP_SEE_METABOX, DWS_IC_CAP_SEE_COLUMN, DWS_IC_CAP_MANAGE_OPTIONS, DWS_IC_CAP_INSERT_PRIVATE, DWS_IC_CAP_SEE_PRIVATE ),
		'cron_hooks'                => array( Plugin::PREFIX . '_revision/migration/run' ),
		'as_groups'                 => array( Plugin::PREFIX ),
	);
}
