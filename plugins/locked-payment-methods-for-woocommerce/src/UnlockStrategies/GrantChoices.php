<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\UnlockStrategies;

use DeepWebSolutions\LockedPaymentMethods\Safeguards;

\defined( 'ABSPATH' ) || exit;

/**
 * Builds the choices of the strategies' grants fields: the locked payment methods and a subject's other grants, by id.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class GrantChoices {
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
	 * Returns a payment method's title, or its id for a payment method no longer registered.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $gateway_id The payment method's id.
	 *
	 * @return  string
	 */
	public static function get_payment_method_title( string $gateway_id ): string {
		$gateway = WC()->payment_gateways()->payment_gateways()[ $gateway_id ] ?? null;
		return $gateway instanceof \WC_Payment_Gateway ? $gateway->get_title() : $gateway_id;
	}

	/**
	 * Returns the choices of a grants field by payment method id: the locked methods and the subject's other grants, since a save keeps only the choices a field offers, each marked when it is not locked or when another rule already unlocks it for the user.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string[] $granted The ids of the payment methods the subject unlocks.
	 * @param   int      $user_id The user other rules are judged for, or 0 for none.
	 *
	 * @return  array<string, string>
	 */
	public static function get( array $granted, int $user_id ): array {
		$locked  = Safeguards::get_locked_payment_method_ids();
		$choices = array();
		foreach ( \array_unique( \array_merge( $locked, $granted ) ) as $id ) {
			$title = self::get_payment_method_title( $id );
			if ( ! \in_array( $id, $locked, true ) ) {
				/* translators: %s: payment method title. */
				$title = \sprintf( __( '%s (not locked)', 'locked-payment-methods-for-woocommerce' ), $title );
			} elseif ( 0 !== $user_id && ! \in_array( $id, $granted, true ) && ! Safeguards::should_lock( true, $id, $user_id, 0 ) ) {
				/* translators: %s: payment method title. */
				$title = \sprintf( __( '%s (unlocked by another rule)', 'locked-payment-methods-for-woocommerce' ), $title );
			}
			$choices[ $id ] = $title;
		}

		return $choices;
	}

	// endregion
}
