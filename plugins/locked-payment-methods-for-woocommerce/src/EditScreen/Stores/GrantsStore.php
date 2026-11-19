<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\EditScreen\Stores;

use DeepWebSolutions\LockedPaymentMethods\Grants;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Settings\EditScreen\ObjectStoreInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Stores a grants form's grants field through the grants writer, so a form's save changes and announces the grants as the plugin's functions do, and every field another plugin adds through the host's own store.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class GrantsStore implements ObjectStoreInterface {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string               $strategy_id The id of the strategy whose grants the form edits.
	 * @param   ObjectStoreInterface $store       The host's store, which keeps every other field.
	 */
	public function __construct(
		protected string $strategy_id,
		protected ObjectStoreInterface $store
	) {}

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_values( int $object_id, array $keys ): array {
		$others = \array_values( \array_diff( $keys, array( Grants::FIELD ) ) );
		$values = array() === $others ? array() : $this->store->get_values( $object_id, $others );

		return \in_array( Grants::FIELD, $keys, true ) ? array( Grants::FIELD => Grants::get( $this->strategy_id, $object_id ) ) + $values : $values;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function update_values( int $object_id, array $values ): void {
		$others = \array_diff_key( $values, array( Grants::FIELD => true ) );

		// The host's store throws on a value it cannot store before it writes any, so it runs first and a refused save leaves the grants as they were.
		if ( array() !== $others ) {
			$this->store->update_values( $object_id, $others );
		}

		if ( \array_key_exists( Grants::FIELD, $values ) ) {
			// The form reports no failed write, as the framework's own stores report none.
			Grants::update( $this->strategy_id, $object_id, \is_array( $values[ Grants::FIELD ] ) ? $values[ Grants::FIELD ] : array() );
		}
	}

	// endregion
}
