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
	 * The value each option reads as while nothing valid is stored.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<string, bool|int>
	 */
	public const array DEFAULTS = array(
		self::MAX_DEPTH    => 1,
		self::AUTOCOMPLETE => false,
		self::DELETE_DATA  => false,
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
