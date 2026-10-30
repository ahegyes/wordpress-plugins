<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Feature;

use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\CompositeComponentInterface;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\ConditionalComponentInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Groups the greeting's components, which load only on sites that switch the greeting on.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class Feature implements CompositeComponentInterface, ConditionalComponentInterface {
	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public static function should_load(): bool {
		return dws_lowc_get_bool_option( DWS_LOWC_OPTION_FEATURE );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public static function get_child_component_classes(): array {
		return array( FeatureDetail::class );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function register_hooks(): void {}

	// endregion
}
