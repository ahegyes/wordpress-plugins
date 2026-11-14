<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders;

use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Migration\BatchedMigrationStepInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Holds the plugin's migration steps, which bring its stored data to the revision its code expects.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class Migrations {
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
	 * Returns the migration steps, append-only, where index 0 brings the data to revision 1.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  list<(callable(): void)|BatchedMigrationStepInterface>
	 */
	public static function get_steps(): array {
		return array(
			self::add_settings( ... ),
			new LinkRepair(),
		);
	}

	// endregion

	// region HELPERS

	/**
	 * Stores the settings' defaults.
	 *
	 * Every value a v1 site stored stays as it is.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @throws  \RuntimeException Thrown when a default could not be stored.
	 *
	 * @return  void
	 */
	protected static function add_settings(): void {
		$defaults = array(
			Options::MAX_DEPTH    => (string) Options::DEFAULTS[ Options::MAX_DEPTH ],
			Options::AUTOCOMPLETE => wc_bool_to_string( Options::DEFAULTS[ Options::AUTOCOMPLETE ] ),
			Options::DELETE_DATA  => wc_bool_to_string( Options::DEFAULTS[ Options::DELETE_DATA ] ),
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) && ! add_option( $key, $value, '', true ) ) {
				throw new \RuntimeException( "The option $key could not be stored." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
			}
		}
	}

	// endregion
}
