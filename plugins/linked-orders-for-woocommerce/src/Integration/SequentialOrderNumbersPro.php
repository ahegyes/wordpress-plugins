<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Integration;

use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\ConditionalComponentInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Gives a linked child its sequential number from WooCommerce Sequential Order Numbers Pro.
 *
 * That plugin numbers only orders created through checkout or the editor.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class SequentialOrderNumbersPro implements ComponentInterface, ConditionalComponentInterface {
	// region FIELDS AND CONSTANTS

	/**
	 * The v1 action a linked child fires on before its v2 action, where v1 numbered it at the default priority.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	protected const string V1_CREATED = 'linked_orders_for_woocommerce/created_linked_order';

	/**
	 * The children numbered among v1's listeners, keyed by object id, which the v2 action then leaves alone.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<int, true>
	 */
	protected array $numbered = array();

	// endregion

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
		add_action( 'deprecated_hook_run', array( $this, 'join_v1_listeners' ) );
		add_action( 'dws_lowc/linked_order/created', array( $this, 'set_order_number' ) );
	}

	// endregion

	// region HOOKS

	/**
	 * Numbers the next linked child at the default priority of v1's action when that action is about to run its listeners.
	 *
	 * WordPress fires a deprecated action only while it has listeners, so numbering joins it only then, and listening to it always would report the action as used.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $hook The deprecated hook about to run.
	 *
	 * @return  void
	 */
	public function join_v1_listeners( string $hook ): void {
		if ( self::V1_CREATED === $hook ) {
			add_action( self::V1_CREATED, array( $this, 'set_v1_order_number' ) );
		}
	}

	/**
	 * Gives a linked child its sequential number among v1's listeners.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \WC_Order $child The linked child.
	 *
	 * @return  void
	 */
	public function set_v1_order_number( \WC_Order $child ): void {
		$this->numbered[ \spl_object_id( $child ) ] = true;
		wc_seq_order_number_pro()->set_sequential_order_number( $child );
	}

	/**
	 * Gives a linked child its sequential number before it is saved, unless v1's action already did, and then leaves v1's action.
	 *
	 * WordPress skips the next priority of an action whose running callback removes its own, so numbering leaves v1's action only once it has ended.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \WC_Order $child The linked child.
	 *
	 * @return  void
	 */
	public function set_order_number( \WC_Order $child ): void {
		$id = \spl_object_id( $child );
		if ( isset( $this->numbered[ $id ] ) ) {
			unset( $this->numbered[ $id ] );
			remove_action( self::V1_CREATED, array( $this, 'set_v1_order_number' ) );
			return;
		}

		wc_seq_order_number_pro()->set_sequential_order_number( $child );
	}

	// endregion
}
