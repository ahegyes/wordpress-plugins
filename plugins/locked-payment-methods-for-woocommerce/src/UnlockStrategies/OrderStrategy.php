<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\UnlockStrategies;

use DeepWebSolutions\LockedPaymentMethods\Plugin;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\ConditionalComponentInterface;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Settings\EditScreen\ObjectForm;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Settings\EditScreen\ObjectFormDefinitionInterface;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\WooCommerce\EditScreen\Hosts\OrderMetaBox;

\defined( 'ABSPATH' ) || exit;

/**
 * Unlocks payment methods for paying an order from its edit screen, on either order storage.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class OrderStrategy implements ComponentInterface, ConditionalComponentInterface, ObjectFormDefinitionInterface {
	// region FIELDS AND CONSTANTS

	/**
	 * The order box's form id.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	protected const string FORM = Plugin::PREFIX . '_order';

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   AdminNotices $notices The plugin's admin notices, which report the form's rejections.
	 */
	public function __construct(
		protected AdminNotices $notices
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
	public static function should_load(): bool {
		return dws_lpmwc_get_bool_option( DWS_LPMWC_OPTION_UNLOCK_VIA_ORDERS );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		add_filter( 'dws_lpmwc/payment_method/should_lock', array( $this, 'unlock_granted_methods' ), 10, 4 );
		new ObjectForm( self::FORM, $this, new OrderMetaBox( array( 'shop_order' ), 'side' ), $this->notices )->register_hooks();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_object_form( string $form_id, int $object_id ): array {
		$order = wc_get_order( $object_id );
		$field = array(
			'type'    => 'checkboxes',
			'id'      => DWS_LPMWC_META_UNLOCKED_METHODS,
			'title'   => __( 'Unlocked payment methods', 'locked-payment-methods-for-woocommerce' ),
			'desc'    => __( 'The customer may pay for this order with these methods on its payment page.', 'locked-payment-methods-for-woocommerce' ),
			'options' => $order instanceof \WC_Order ? dws_lpmwc_get_grant_choices( dws_lpmwc_get_order_unlocked_payment_method_ids( $object_id ), $order->get_customer_id() ) : array(),
		);
		if ( $order instanceof \WC_Order && $order->is_paid() ) {
			$field['desc']              = __( 'This order is paid, so its unlocked payment methods no longer change.', 'locked-payment-methods-for-woocommerce' );
			$field['custom_attributes'] = array( 'disabled' => 'disabled' );
		}

		return array(
			'title'      => __( 'Locked Payment Methods', 'locked-payment-methods-for-woocommerce' ),
			'capability' => DWS_LPMWC_CAP_UNLOCK_VIA_ORDERS,
			'fields'     => array() === $field['options'] ? array() : array( $field ),
		);
	}

	// endregion

	// region METHODS

	/**
	 * Returns whether the order's grants list holds the payment method.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $gateway_id The payment method's id.
	 * @param   int    $order_id   The order's id, or 0 for none.
	 *
	 * @return  bool
	 */
	public static function unlocks( string $gateway_id, int $order_id ): bool {
		return \in_array( $gateway_id, dws_lpmwc_get_order_unlocked_payment_method_ids( $order_id ), true );
	}

	// endregion

	// region HOOKS

	/**
	 * Returns false for a payment method the list of the order being paid holds, and the lock as given otherwise.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed  $lock       Whether the payment method stays locked, as earlier callbacks left it, passed on unchanged since only an explicit false unlocks.
	 * @param   string $gateway_id The payment method's id.
	 * @param   int    $user_id    The paying user, or 0 for a guest.
	 * @param   int    $order_id   The order being paid, or 0 off the order-pay page.
	 *
	 * @return  mixed
	 */
	public function unlock_granted_methods( mixed $lock, string $gateway_id, int $user_id, int $order_id ): mixed {
		return self::unlocks( $gateway_id, $order_id ) ? false : $lock;
	}

	// endregion
}
