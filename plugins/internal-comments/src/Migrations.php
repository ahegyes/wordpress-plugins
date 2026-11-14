<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments;

use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\Migration\BatchedMigrationStepInterface;

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
			self::move_v1_settings( ... ),
			new CommentStatusMove(),
		);
	}

	// endregion

	// region HELPERS

	/**
	 * Stores each setting's default in an autoloaded row of its own and leaves a stored setting as it is.
	 *
	 * Each release that adds settings lists this step again, so that existing sites store the new defaults.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @throws  \RuntimeException Thrown when a default could not be stored.
	 *
	 * @return  void
	 */
	protected static function add_settings(): void {
		wp_prime_option_caches( \array_keys( Options::DEFAULTS ) );
		foreach ( Options::DEFAULTS as $key => $value ) {
			if ( false === get_option( $key ) && ! add_option( $key, $value, '', true ) ) {
				throw new \RuntimeException( "The option $key could not be stored." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
			}
		}
	}

	/**
	 * Copies each field of v1's two settings rows into the row of its own that the field now has, over what the row holds.
	 *
	 * The rows v1 stored stay, so a rollback to 1.x still reads them. A field an extension added to a group moves to the row dws_ic_{group}_{id}, where v1's getters read it, unless its id cannot name an option.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @throws  \RuntimeException Thrown when a field could not be stored.
	 *
	 * @return  void
	 */
	protected static function move_v1_settings(): void {
		$fields = array(
			'general' => array(
				'post-types'              => 'dws_ic_post_types',
				'enable-private-comments' => 'dws_ic_private_comments_enabled',
			),
			'plugin'  => array(
				'remove-data-uninstall' => 'dws_ic_delete_data',
			),
		);
		foreach ( $fields as $group => $keys ) {
			$row = get_option( "dws-internal-comments_$group" );
			foreach ( \is_array( $row ) ? $row : array() as $id => $value ) {
				$key = $keys[ $id ] ?? ( 1 === \preg_match( '/^[a-z0-9_-]+$/', (string) $id ) ? "dws_ic_{$group}_$id" : null );
				if ( \is_null( $key ) ) {
					continue;
				}

				update_option( $key, $value, true );
				if ( get_option( $key ) !== $value ) {
					throw new \RuntimeException( "The option $key could not be stored." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
				}
			}
		}
	}

	// endregion
}
