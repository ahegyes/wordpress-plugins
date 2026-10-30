<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Integration;

use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\ConditionalComponentInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Gives a linked child its sequential number from WooCommerce Sequential Order Numbers Pro, which numbers only orders created through checkout or the editor.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class SequentialOrderNumbersPro implements ComponentInterface, ConditionalComponentInterface {
	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public static function should_load(): bool {
		return \function_exists( 'wc_seq_order_number_pro' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		add_action( 'dws_lowc/linked_order/created', array( $this, 'set_order_number' ) );
	}

	// endregion

	// region HOOKS

	/**
	 * Gives a linked child its sequential number before it is saved.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \WC_Order $child The linked child.
	 *
	 * @return  void
	 */
	public function set_order_number( \WC_Order $child ): void {
		wc_seq_order_number_pro()->set_sequential_order_number( $child );
	}

	// endregion
}
