<?php declare( strict_types=1 );
/**
 * Holds the plugin's settings, each in a row of its own, the values a new site starts with, and where each of v1's settings fields now lives.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\InternalComments
 */

defined( 'ABSPATH' ) || exit;

/**
 * The option that holds the names of the post types that support internal comments.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_IC_OPTION_POST_TYPES = 'dws_ic_post_types';

/**
 * The option that holds 'yes' where internal comments can be marked private.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_IC_OPTION_PRIVATE_COMMENTS = 'dws_ic_private_comments_enabled';

/**
 * The option that holds 'yes' where the site consents to uninstall removing the plugin's data.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_IC_OPTION_DELETE_DATA = 'dws_ic_delete_data';

/**
 * The default of each option, the value v1 stored as its default, in the form the option stores it.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     array<string, mixed>
 */
const DWS_IC_OPTION_DEFAULTS = array(
	DWS_IC_OPTION_POST_TYPES       => array(),
	DWS_IC_OPTION_PRIVATE_COMMENTS => 'no',
	DWS_IC_OPTION_DELETE_DATA      => 'no',
);

/**
 * The option of each of v1's settings fields, by v1's group and field id, in the order the settings page shows them.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     array<string, array<string, string>>
 */
const DWS_IC_V1_SETTINGS = array(
	'general' => array(
		'post-types'              => DWS_IC_OPTION_POST_TYPES,
		'enable-private-comments' => DWS_IC_OPTION_PRIVATE_COMMENTS,
	),
	'plugin'  => array(
		'remove-data-uninstall' => DWS_IC_OPTION_DELETE_DATA,
	),
);
