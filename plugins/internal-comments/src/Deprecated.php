<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments;

\defined( 'ABSPATH' ) || exit;

/**
 * Holds what v1's deprecated API needs and no other code calls.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class Deprecated {
	// region FIELDS AND CONSTANTS

	/**
	 * The root of each v1 component's hook tags, keyed by the component's id, which v1 built from the component's name.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<string, string>
	 */
	public const array COMPONENTS = array(
		'ajax'                 => 'ajax',
		'comments'             => 'comments',
		'comments-list-table'  => 'internal_comments_list_table',
		'reply-comments'       => 'reply_comments',
		'query'                => 'query',
		'query-scoped-hooks'   => 'query_scoped_hooks',
		'settings'             => 'settings',
		'general-settings'     => 'general_settings',
		'plugin-settings'      => 'plugin_settings',
		'permissions'          => 'permissions',
		'comment-permissions'  => 'comments_permissions',
		'output-permissions'   => 'output_permissions',
		'settings-permissions' => 'settings_permissions',
		'output'               => 'output',
		'metabox-output'       => 'metabox_output',
		'wp-table-output'      => 'wp_table_output',
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

	// region METHODS

	/**
	 * Returns a hook name joined and sanitized as v1 did: the non-empty parts joined with slashes, spaces and backslashes as underscores, lowercased.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string               $root  The name's root.
	 * @param   string               $name  The hook's name.
	 * @param   string|array<string> $extra The name's further parts.
	 *
	 * @return  string
	 */
	public static function join_hook_tag( string $root, string $name, $extra ): string {
		return \strtolower( \str_replace( array( ' ', '\\' ), '_', \implode( '/', \array_filter( \array_merge( array( $root, $name ), \is_array( $extra ) ? $extra : array( $extra ) ), 'boolval' ) ) ) );
	}

	// endregion
}
