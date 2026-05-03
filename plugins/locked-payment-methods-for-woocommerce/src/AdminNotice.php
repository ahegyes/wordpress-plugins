<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods;

use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\Contracts\HookableInterface;

/**
 * Demo component — renders an info notice in the WordPress admin to confirm
 * the plugin booted and the framework's HookableInterface dispatch works.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class AdminNotice implements HookableInterface {
	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function register_hooks(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	// endregion

	// region HOOKS

	/**
	 * Renders the admin notice. Hooked on `admin_notices`.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public function render(): void {
		printf(
			'<div class="notice notice-info"><p>%s</p></div>',
			esc_html__( 'Locked Payment Methods for WooCommerce is active.', 'locked-payment-methods-for-woocommerce' )
		);
	}

	// endregion
}
