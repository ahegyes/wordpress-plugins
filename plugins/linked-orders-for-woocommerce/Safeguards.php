<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders;

\defined( 'ABSPATH' ) || exit;

/**
 * Holds the plugin's safeguards, the restrictions that hold even when the rest of the plugin fails to load.
 *
 * The entry requires it by path before the floor gate, so it parses on PHP 7.4 and calls only what the oldest WordPress the bootstrap package runs on provides.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class Safeguards {
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
	 * Hooks the safeguards, as static closures, so that only their own filters relax them.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public static function register(): void {}

	// endregion
}
