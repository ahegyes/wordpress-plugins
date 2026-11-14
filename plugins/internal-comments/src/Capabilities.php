<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments;

\defined( 'ABSPATH' ) || exit;

/**
 * Names the plugin's capabilities, under the names v1 granted them, and the roles that receive them.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class Capabilities {
	// region FIELDS AND CONSTANTS

	/**
	 * The capability a user needs to post internal comments.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string INSERT = 'insert_dws_internal_comments';

	/**
	 * The capability a user needs to edit their own internal comments.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string EDIT = 'edit_dws_internal_comments';

	/**
	 * The capability a user needs to edit other users' internal comments.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string EDIT_OTHERS = 'edit_others_dws_internal_comments';

	/**
	 * The capability a user needs to trash their own internal comments.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string TRASH = 'trash_dws_internal_comments';

	/**
	 * The capability a user needs to trash other users' internal comments.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string TRASH_OTHERS = 'trash_others_dws_internal_comments';

	/**
	 * The capability a user needs to read other users' internal comments.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string SEE = 'see_dws_internal_comments';

	/**
	 * The capability a user needs to see the list of every internal comment under Comments.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string SEE_TABLE = 'see_dws_internal_comments_table';

	/**
	 * The capability a user needs to see the internal comments meta box on an edit screen.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string SEE_METABOX = 'see_dws_internal_comments_metabox';

	/**
	 * The capability a user needs to see the internal comments column of a posts list.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string SEE_COLUMN = 'see_dws_internal_comments_column';

	/**
	 * The capability a user needs to change the plugin's settings.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string MANAGE_OPTIONS = 'manage_dws_internal_comments_options';

	/**
	 * The capability a user needs to mark their internal comments private.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string INSERT_PRIVATE = 'insert_private_dws_internal_comments';

	/**
	 * The capability a user needs to read other users' private internal comments.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string SEE_PRIVATE = 'see_private_dws_internal_comments';

	/**
	 * The capabilities each role receives, as v1 granted them.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<string, list<string>>
	 */
	public const array GRANTS = array(
		'administrator' => array( self::INSERT, self::EDIT, self::EDIT_OTHERS, self::TRASH, self::TRASH_OTHERS, self::SEE, self::SEE_TABLE, self::SEE_METABOX, self::SEE_COLUMN, self::MANAGE_OPTIONS, self::INSERT_PRIVATE, self::SEE_PRIVATE ),
		'editor'        => array( self::INSERT, self::SEE, self::SEE_METABOX, self::SEE_COLUMN, self::INSERT_PRIVATE ),
		'author'        => array( self::INSERT, self::SEE, self::SEE_METABOX, self::SEE_COLUMN, self::INSERT_PRIVATE ),
		'contributor'   => array( self::INSERT, self::SEE, self::SEE_METABOX, self::SEE_COLUMN, self::INSERT_PRIVATE ),
		'shop_manager'  => array( self::INSERT, self::SEE, self::SEE_METABOX, self::SEE_COLUMN, self::INSERT_PRIVATE ),
	);

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	protected function __construct() {}

	// endregion
}
