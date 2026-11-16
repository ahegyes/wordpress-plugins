<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments;

\defined( 'ABSPATH' ) || exit;

/**
 * Names the plugin's stored settings, each in a row of its own, their defaults and the setting each of v1's settings fields moved to, and reads them.
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
	protected const array V1_SETTINGS = array(
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
	 * Returns an option as a boolean, which reads checkbox values such as 'yes' and 'no', through v1's value filter for a setting v1 had.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $key The option's key.
	 *
	 * @return  bool
	 */
	public static function get_bool( string $key ): bool {
		$default = \filter_var( self::DEFAULTS[ $key ] ?? null, \FILTER_VALIDATE_BOOL );
		$value   = \filter_var( get_option( $key, $default ), \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE ) ?? $default;
		foreach ( self::V1_SETTINGS as $group => $keys ) {
			$field_id = \array_search( $key, $keys, true );
			if ( \is_string( $field_id ) ) {
				$value = self::apply_v1_setting_filter( $value, $group, $field_id, $key );
			}
		}

		return \filter_var( $value, \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE ) ?? $default;
	}

	/**
	 * Returns an option as a list of strings, keeping the stored strings in order.
	 *
	 * As in v1, the selected post types, the one list among the settings, are read without v1's value filter, so a post type that registers late stays selected and the per-type hooks the boot adds agree with every later read.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $key The option's key.
	 *
	 * @return  list<string>
	 */
	public static function get_list( string $key ): array {
		$default = self::DEFAULTS[ $key ] ?? array();
		$stored  = get_option( $key, $default );
		return \is_array( $stored ) ? \array_values( \array_filter( $stored, 'is_string' ) ) : array();
	}

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

	/**
	 * Returns a setting's value after v1's value filter for its settings group, which v1 applied when a setting was read through that group.
	 *
	 * The plugin's reads of the private-comments setting pass through here too, so v1's callbacks keep changing what it does until the next major removes v1's filters; the selected post types, which v1 read raw, and the uninstall consent, which the uninstall engine reads, do not.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed  $value    The setting's value.
	 * @param   string $group    The field's v1 group, without its '-settings' suffix.
	 * @param   string $field_id The field's v1 id.
	 * @param   string $key      The setting's option.
	 *
	 * @return  mixed
	 */
	public static function apply_v1_setting_filter( mixed $value, string $group, string $field_id, string $key ): mixed {
		/**
		 * Filters a setting's validated value.
		 *
		 * @since       1.0.0
		 * @version     2.0.0
		 * @deprecated  2.0.0 Use the hook the deprecation notice names.
		 *
		 * @param       mixed  $value    The validated value.
		 * @param       string $field_id The field's v1 id.
		 */
		return apply_filters_deprecated( "internal_comments/{$group}_settings/get_validated_option_value", array( $value, $field_id ), '2.0.0', "option_{$key}" );
	}

	/**
	 * Returns what replaces a v1 read of a setting's option, for the read's deprecation notice.
	 *
	 * @since       2.0.0
	 * @version     2.0.0
	 * @deprecated  2.0.0
	 *
	 * @param       string|null $key The setting's option, or null for an id v1 had no setting for.
	 *
	 * @return      string
	 */
	public static function get_v1_read_replacement( ?string $key ): string {
		return \is_null( $key ) ? 'get_option()' : "get_option() on {$key}";
	}

	// endregion
}
