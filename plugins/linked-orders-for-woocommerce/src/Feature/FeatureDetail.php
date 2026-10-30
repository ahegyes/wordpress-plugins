<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Feature;

use DeepWebSolutions\LinkedOrders\Plugin;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Greets customers on their account dashboard once they have placed the set number of orders.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class FeatureDetail implements ComponentInterface {
	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		add_action( 'woocommerce_account_dashboard', array( $this, 'print_greeting' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_style' ) );
	}

	// endregion

	// region HOOKS

	/**
	 * Prints the greeting as a WooCommerce notice to a customer whose orders reach the set number.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public function print_greeting(): void {
		$greeting = dws_lowc_get_string_option( DWS_LOWC_OPTION_GREETING );
		if ( '' === $greeting || wc_get_customer_order_count( get_current_user_id() ) < dws_lowc_get_int_option( DWS_LOWC_OPTION_GREETING_ORDERS ) ) {
			return;
		}

		wc_print_notice( esc_html( $greeting ), 'notice', array( Plugin::SLUG => 'greeting' ) );
	}

	/**
	 * Enqueues the greeting's stylesheet on the account dashboard.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public function enqueue_style(): void {
		if ( ! is_account_page() || is_wc_endpoint_url() ) {
			return;
		}

		dws_lowc_register_style( Plugin::SLUG . '-greeting', 'assets/build/greeting.css' );
		wp_enqueue_style( Plugin::SLUG . '-greeting' );
	}

	// endregion
}
