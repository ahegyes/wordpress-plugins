<?php declare( strict_types=1 );
/**
 * Holds the plugin's settings rows and fields, which are v1's, and the values a new site starts with.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\InternalComments
 */

defined( 'ABSPATH' ) || exit;

/**
 * The option that holds the general settings, keyed by field id.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_IC_OPTION_GENERAL = 'dws-internal-comments_general';

/**
 * The option that holds the plugin settings, keyed by field id.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_IC_OPTION_PLUGIN = 'dws-internal-comments_plugin';

/**
 * The general settings field that holds the names of the post types that support internal comments.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_IC_SETTING_POST_TYPES = 'post-types';

/**
 * The general settings field that holds 'yes' where internal comments can be marked private.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_IC_SETTING_PRIVATE_COMMENTS = 'enable-private-comments';

/**
 * The plugin settings field that holds 'yes' where the site consents to uninstall removing the plugin's data.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_IC_SETTING_DELETE_DATA = 'remove-data-uninstall';

/**
 * The rows a new site starts with, holding the values v1 stored as its defaults.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     array<string, array<string, mixed>>
 */
const DWS_IC_SETTING_DEFAULTS = array(
	DWS_IC_OPTION_GENERAL => array(
		DWS_IC_SETTING_POST_TYPES       => array(),
		DWS_IC_SETTING_PRIVATE_COMMENTS => 'no',
	),
	DWS_IC_OPTION_PLUGIN  => array(
		DWS_IC_SETTING_DELETE_DATA => 'no',
	),
);
