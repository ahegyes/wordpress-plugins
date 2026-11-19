<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\UnlockStrategies;

use DeepWebSolutions\LockedPaymentMethods\Capabilities;
use DeepWebSolutions\LockedPaymentMethods\Options;
use DeepWebSolutions\LockedPaymentMethods\Plugin;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\ConditionalComponentInterface;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\PluginIdentity;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Settings\EditScreen\ObjectForm;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Settings\EditScreen\ObjectFormDefinitionInterface;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\WooCommerce\EditScreen\Hosts\OrderMetaBox;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies;

\defined( 'ABSPATH' ) || exit;

/**
 * Unlocks payment methods for paying an order from its edit screen, on either order storage.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class OrderStrategy implements ComponentInterface, ConditionalComponentInterface, ObjectFormDefinitionInterface, \DWS_LPMWC_Unlock_Strategy {
	// region FIELDS AND CONSTANTS

	/**
	 * The strategy's id.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string ID = 'order';

	/**
	 * The order box's form id.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	protected const string FORM = Plugin::PREFIX . '_order';

	/**
	 * The order meta that holds the ids of the payment methods unlocked for paying the order.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	protected const string META_KEY = '_dws_lpmwc_unlocked_payment_methods';

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   PluginIdentity $identity The plugin's identity, which the form takes.
	 * @param   AdminNotices   $notices  The plugin's admin notices, which report the form's rejections.
	 */
	public function __construct(
		protected PluginIdentity $identity,
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
		return Options::get_bool( Options::UNLOCK_VIA_ORDERS );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		UnlockStrategies::register( $this );
		new ObjectForm( $this->identity, self::FORM, $this, new OrderMetaBox( array( 'shop_order' ), 'side' ), $this->notices )->register_hooks();
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
			'id'      => self::META_KEY,
			'title'   => __( 'Unlocked payment methods', 'locked-payment-methods-for-woocommerce' ),
			'desc'    => __( 'The customer may pay for this order with these methods on its payment page.', 'locked-payment-methods-for-woocommerce' ),
			'options' => $order instanceof \WC_Order ? GrantChoices::get( self::get_unlocked_ids( $object_id ), $order->get_customer_id() ) : array(),
		);
		if ( $order instanceof \WC_Order && $order->is_paid() ) {
			$field['desc']              = __( 'This order is paid, so its unlocked payment methods no longer change.', 'locked-payment-methods-for-woocommerce' );
			$field['custom_attributes'] = array( 'disabled' => 'disabled' );
		}

		return array(
			'title'      => __( 'Locked Payment Methods', 'locked-payment-methods-for-woocommerce' ),
			'capability' => Capabilities::UNLOCK_VIA_ORDERS,
			'fields'     => array() === $field['options'] ? array() : array( $field ),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_id(): string {
		return self::ID;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_label(): string {
		return _x( 'Orders', 'unlock-strategies', 'locked-payment-methods-for-woocommerce' );
	}

	/**
	 * Returns whether the grants list of the order being paid holds the payment method.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $gateway_id The payment method's id.
	 * @param   int    $user_id    The paying user, or 0 for a guest.
	 * @param   int    $order_id   The order being paid, or 0 off its payment page.
	 *
	 * @return  bool
	 */
	#[\Override]
	public function unlocks( string $gateway_id, int $user_id, int $order_id ): bool {
		return \in_array( $gateway_id, self::get_unlocked_ids( $order_id ), true );
	}

	// endregion

	// region METHODS

	/**
	 * Returns an order, or null for none or until the plugin has booted.
	 *
	 * The plugin's v1 order functions stay defined where WooCommerce is inactive, so they read orders only through here, once the boot has checked WooCommerce.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $order_id The order's id, or 0 for none.
	 *
	 * @return  \WC_Order|null
	 */
	public static function get_order( int $order_id ): ?\WC_Order {
		if ( 0 === $order_id || 0 === did_action( 'locked_payment_methods_for_woocommerce/boot/succeeded' ) ) {
			return null;
		}

		$order = wc_get_order( $order_id );
		return $order instanceof \WC_Order ? $order : null;
	}

	/**
	 * Returns the ids of the payment methods unlocked for paying an order, none until the plugin has booted.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $order_id The order's id.
	 *
	 * @return  string[]
	 */
	public static function get_unlocked_ids( int $order_id ): array {
		$ids = self::get_order( $order_id )?->get_meta( self::META_KEY );
		return \is_array( $ids ) ? \array_values( \array_filter( $ids, 'is_string' ) ) : array();
	}

	// endregion
}
