<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments;

\defined( 'ABSPATH' ) || exit;

/**
 * Names the plugin's stored settings, each in a row of its own, their defaults and the setting each of v1's settings fields moved to.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class Options {
	// region FIELDS AND CONSTANTS

	/**
	 * The option that holds the names of the post types that support internal comments.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string POST_TYPES = 'dws_ic_post_types';

	/**
	 * The option that holds 'yes' where internal comments can be marked private.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string PRIVATE_COMMENTS = 'dws_ic_private_comments_enabled';

	/**
	 * The option that holds 'yes' where the site consents to uninstall removing the plugin's data.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string DELETE_DATA = 'dws_ic_delete_data';

	/**
	 * The default of each option, the value v1 stored as its default, in the form the option stores it.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<string, mixed>
	 */
	public const array DEFAULTS = array(
		self::POST_TYPES       => array(),
		self::PRIVATE_COMMENTS => 'no',
		self::DELETE_DATA      => 'no',
	);

	/**
	 * The option of each of v1's settings fields, by v1's group and field id, in the order the settings page shows them.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<string, array<string, string>>
	 */
	public const array V1_SETTINGS = array(
		'general' => array(
			'post-types'              => self::POST_TYPES,
			'enable-private-comments' => self::PRIVATE_COMMENTS,
		),
		'plugin'  => array(
			'remove-data-uninstall' => self::DELETE_DATA,
		),
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
	 * Returns a setting's group, field id and option, or null for a group v1 did not have.
	 *
	 * As in v1, a read without a group names the group in the field id, such as 'general/post-types'; a field an extension adds to a group lives in the option dws_ic_{group}_{id}.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string      $field_id The field's v1 id, prefixed with its group and a slash when no group is given.
	 * @param   string|null $group    The field's v1 group, with or without its '-settings' suffix.
	 *
	 * @return  array{0: string, 1: string, 2: string}|null
	 */
	public static function find_v1_setting( string $field_id, ?string $group ): ?array {
		if ( \is_null( $group ) ) {
			if ( false === \strpos( $field_id, '/' ) ) {
				return null;
			}

			list( $group, $field_id ) = \explode( '/', $field_id, 2 );
		}

		if ( '-settings' === \substr( $group, -9 ) ) {
			$group = \substr( $group, 0, -9 );
		}

		return isset( self::V1_SETTINGS[ $group ] ) ? array( $group, $field_id, self::V1_SETTINGS[ $group ][ $field_id ] ?? "dws_ic_{$group}_$field_id" ) : null;
	}

	// endregion
}
