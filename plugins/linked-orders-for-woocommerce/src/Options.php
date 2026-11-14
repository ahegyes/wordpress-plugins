<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders;

\defined( 'ABSPATH' ) || exit;

/**
 * Names the plugin's stored settings, which keep v1's keys, and their defaults.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class Options {
	// region FIELDS AND CONSTANTS

	/**
	 * The option that holds the deepest level a linked order can be created at, where root orders are level 0.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string MAX_DEPTH = 'dws-lowc_general_max-depth';

	/**
	 * The option that holds 'yes' where completing an order completes its descendants.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string AUTOCOMPLETE = 'dws-lowc_general_autocomplete-descendants';

	/**
	 * The option that holds 'yes' where the site consents to uninstall removing the plugin's data.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string DELETE_DATA = 'dws-lowc_plugin_remove-data-uninstall';

	/**
	 * The default of each option, the value v1 stored as its default, in the form the option stores it.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<string, string>
	 */
	public const array DEFAULTS = array(
		self::MAX_DEPTH    => '1',
		self::AUTOCOMPLETE => 'no',
		self::DELETE_DATA  => 'no',
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
	 * Returns a setting's group without its '-settings' suffix, its field id and its option, which is null for a group v1 did not have.
	 *
	 * As in v1, a read without a group names the group in the field id, such as 'general/max-depth', and a field an extension adds to a group without an id of its own lives in the option WooCommerce saves it under, dws-lowc_{group}_{field}. An id read without a group that names none has an empty group.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string      $field_id The field's v1 id, prefixed with its group and a slash when no group is given.
	 * @param   string|null $group    The field's v1 group, with or without its '-settings' suffix.
	 *
	 * @return  array{0: string, 1: string, 2: string|null}
	 */
	public static function find_v1_setting( string $field_id, ?string $group ): array {
		if ( \is_null( $group ) ) {
			list( $group, $field_id ) = \array_pad( \explode( '/', $field_id, 2 ), -2, '' );
		}

		if ( '-settings' === \substr( $group, -9 ) ) {
			$group = \substr( $group, 0, -9 );
		}

		return array( $group, $field_id, \in_array( $group, array( 'general', 'plugin' ), true ) ? "dws-lowc_{$group}_$field_id" : null );
	}

	// endregion
}
