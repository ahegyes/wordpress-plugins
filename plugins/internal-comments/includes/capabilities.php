<?php declare( strict_types=1 );
/**
 * Holds the plugin's capabilities, under the names v1 granted them, and the roles that receive them.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\InternalComments
 */

defined( 'ABSPATH' ) || exit;

/**
 * The capability a user needs to post internal comments.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_IC_CAP_INSERT = 'insert_dws_internal_comments';

/**
 * The capability a user needs to edit their own internal comments.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_IC_CAP_EDIT = 'edit_dws_internal_comments';

/**
 * The capability a user needs to edit other users' internal comments.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_IC_CAP_EDIT_OTHERS = 'edit_others_dws_internal_comments';

/**
 * The capability a user needs to trash their own internal comments.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_IC_CAP_TRASH = 'trash_dws_internal_comments';

/**
 * The capability a user needs to trash other users' internal comments.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_IC_CAP_TRASH_OTHERS = 'trash_others_dws_internal_comments';

/**
 * The capability a user needs to read other users' internal comments.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_IC_CAP_SEE = 'see_dws_internal_comments';

/**
 * The capability a user needs to see the list of every internal comment under Comments.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_IC_CAP_SEE_TABLE = 'see_dws_internal_comments_table';

/**
 * The capability a user needs to see the internal comments meta box on an edit screen.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_IC_CAP_SEE_METABOX = 'see_dws_internal_comments_metabox';

/**
 * The capability a user needs to see the internal comments column of a posts list.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_IC_CAP_SEE_COLUMN = 'see_dws_internal_comments_column';

/**
 * The capability a user needs to change the plugin's settings.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_IC_CAP_MANAGE_OPTIONS = 'manage_dws_internal_comments_options';

/**
 * The capability a user needs to mark their internal comments private.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_IC_CAP_INSERT_PRIVATE = 'insert_private_dws_internal_comments';

/**
 * The capability a user needs to read other users' private internal comments.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_IC_CAP_SEE_PRIVATE = 'see_private_dws_internal_comments';

/**
 * The capabilities each role receives, as v1 granted them.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     array<string, list<string>>
 */
const DWS_IC_CAPABILITY_GRANTS = array(
	'administrator' => array( DWS_IC_CAP_INSERT, DWS_IC_CAP_EDIT, DWS_IC_CAP_EDIT_OTHERS, DWS_IC_CAP_TRASH, DWS_IC_CAP_TRASH_OTHERS, DWS_IC_CAP_SEE, DWS_IC_CAP_SEE_TABLE, DWS_IC_CAP_SEE_METABOX, DWS_IC_CAP_SEE_COLUMN, DWS_IC_CAP_MANAGE_OPTIONS, DWS_IC_CAP_INSERT_PRIVATE, DWS_IC_CAP_SEE_PRIVATE ),
	'editor'        => array( DWS_IC_CAP_INSERT, DWS_IC_CAP_SEE, DWS_IC_CAP_SEE_METABOX, DWS_IC_CAP_SEE_COLUMN, DWS_IC_CAP_INSERT_PRIVATE ),
	'author'        => array( DWS_IC_CAP_INSERT, DWS_IC_CAP_SEE, DWS_IC_CAP_SEE_METABOX, DWS_IC_CAP_SEE_COLUMN, DWS_IC_CAP_INSERT_PRIVATE ),
	'contributor'   => array( DWS_IC_CAP_INSERT, DWS_IC_CAP_SEE, DWS_IC_CAP_SEE_METABOX, DWS_IC_CAP_SEE_COLUMN, DWS_IC_CAP_INSERT_PRIVATE ),
	'shop_manager'  => array( DWS_IC_CAP_INSERT, DWS_IC_CAP_SEE, DWS_IC_CAP_SEE_METABOX, DWS_IC_CAP_SEE_COLUMN, DWS_IC_CAP_INSERT_PRIVATE ),
);
