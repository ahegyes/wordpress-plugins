<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods;

\defined( 'ABSPATH' ) || exit;

/**
 * Holds the plugin's safeguards, the restrictions that hold even when the rest of the plugin fails to load: the locked payment methods stay off the checkout unless the booted plugin relaxes the lock.
 *
 * The entry requires it by path before the floor gate, so it parses on PHP 7.4 and calls only what the oldest WordPress the bootstrap package runs on provides, and WooCommerce's APIs present since 3.6.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class Safeguards {
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
	 * Hooks the safeguards, as static closures, so that only their own filters relax them.
	 *
	 * WordPress loads the plugin while WooCommerce is inactive, so the safeguards that call WooCommerce wait for it to load.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public static function register(): void {
		$add_woocommerce_safeguards = static function (): void {
			add_filter(
				'woocommerce_available_payment_gateways',
				static function ( $gateways ): array {
					$gateways = (array) $gateways; // WooCommerce casts the filtered value to a list, so the lock judges what it would offer.

					$ids = self::get_locked_payment_method_ids();

					/**
					 * Filters the locked payment methods' ids.
					 *
					 * @since       1.0.0
					 * @version     2.0.0
					 * @deprecated  2.0.0 Use locked_payment_methods_for_woocommerce/payment_method/locked_ids.
					 *
					 * @param       string[]     $ids      The locked payment methods' ids.
					 * @param       array<mixed> $gateways The payment gateways offered, by id.
					 */
					$ids = self::add_locked_ids( $ids, apply_filters_deprecated( 'locked_payment_methods_for_woocommerce/lock_manager/locked_payment_methods', array( $ids, $gateways ), '2.0.0', 'locked_payment_methods_for_woocommerce/payment_method/locked_ids' ) );

					// The order-pay page pays the order its key names, which WooCommerce requires before it takes the payment, so a request naming another order's id gains none of its grants; AJAX and REST checkouts pay a new order from the cart whatever order the URL names.
					$order_id = 0;
					if ( ! wp_doing_ajax() && ! ( \defined( 'REST_REQUEST' ) && true === REST_REQUEST ) && \function_exists( 'is_checkout_pay_page' ) && is_checkout_pay_page() ) {
						$order = wc_get_order( $GLOBALS['wp'] instanceof \WP ? ( $GLOBALS['wp']->query_vars['order-pay'] ?? 0 ) : 0 );
						$key   = isset( $_GET['key'] ) && \is_string( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The order key authorizes paying the order, as on WooCommerce's order-pay page.
						if ( $order instanceof \WC_Order && '' !== $key && \hash_equals( $order->get_order_key(), $key ) ) {
							$order_id = $order->get_id();
						}
					}

					$user_id = get_current_user_id();
					foreach ( $ids as $id ) {
						if ( ! isset( $gateways[ $id ] ) ) {
							continue;
						}

						/**
						 * Filters whether a payment method stays locked.
						 *
						 * @since       1.0.0
						 * @version     2.0.0
						 * @deprecated  2.0.0 Use locked_payment_methods_for_woocommerce/payment_method/should_lock.
						 *
						 * @param       bool   $is_locked Whether the payment method stays locked.
						 * @param       string $id        The payment method's id.
						 */
						$lock = apply_filters_deprecated( 'locked_payment_methods_for_woocommerce/lock_manager/is_locked_payment_method', array( true, $id ), '2.0.0', 'locked_payment_methods_for_woocommerce/payment_method/should_lock' );

						/**
						 * Filters whether a payment method stays locked, under its id with spaces and backslashes as underscores, stripped of control and non-ASCII bytes and lowercased, as v1 named it.
						 *
						 * @since       1.0.0
						 * @version     2.0.0
						 * @deprecated  2.0.0 Use locked_payment_methods_for_woocommerce/payment_method/should_lock.
						 *
						 * @param       bool $is_locked Whether the payment method stays locked.
						 */
						$lock = apply_filters_deprecated( 'locked_payment_methods_for_woocommerce/lock_manager/is_locked_payment_method/' . \strtolower( (string) \filter_var( \str_replace( array( ' ', '\\' ), '_', $id ), \FILTER_UNSAFE_RAW, \FILTER_FLAG_STRIP_LOW | \FILTER_FLAG_STRIP_HIGH ) ), array( $lock ), '2.0.0', 'locked_payment_methods_for_woocommerce/payment_method/should_lock' );

						if ( self::should_lock( false !== $lock, $id, $user_id, $order_id ) ) {
							unset( $gateways[ $id ] );
						}
					}

					return $gateways;
				},
				999
			);
		};

		if ( 0 < did_action( 'woocommerce_loaded' ) ) {
			$add_woocommerce_safeguards();
		} else {
			add_action( 'woocommerce_loaded', $add_woocommerce_safeguards );
		}
	}

	/**
	 * Returns the ids of the locked payment methods: the plugin's list, or v1's while the plugin has stored none, because the migration that copies v1's list runs only once the plugin boots, with the ids the plugin's list filters add.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  string[]
	 */
	public static function get_locked_payment_method_ids(): array {
		$stored = get_option( 'dws_lpmwc_locked_payment_methods' );
		$ids    = self::add_locked_ids( array(), false === $stored ? get_option( 'dws-wc-lpm_general_locked-payment-methods' ) : $stored );
		$ids    = self::add_locked_ids( $ids, self::apply_v1_setting_filter( $ids, 'general', 'locked-payment-methods', 'locked_payment_methods_for_woocommerce/payment_method/locked_ids' ) );

		/**
		 * Filters the locked payment methods' ids wherever the plugin reads them, at checkout, in the lock check and on the grants forms; a callback adds the ids it locks, and an id it leaves out stays locked.
		 *
		 * @since   2.0.0
		 * @version 2.0.0
		 *
		 * @param   string[] $ids The locked payment methods' ids.
		 */
		return self::add_locked_ids( $ids, apply_filters( 'locked_payment_methods_for_woocommerce/payment_method/locked_ids', $ids ) );
	}

	/**
	 * Returns a setting's value after v1's value filter for its settings group, which v1 applied on every read of a setting through that group.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed  $value       The setting's value.
	 * @param   string $group       v1's id of the setting's group.
	 * @param   string $field_id    v1's id of the setting.
	 * @param   string $replacement The hook that replaces v1's filter.
	 *
	 * @return  mixed
	 */
	public static function apply_v1_setting_filter( $value, string $group, string $field_id, string $replacement ) {
		/**
		 * Filters a setting's value as v1's settings group read it.
		 *
		 * @since       1.0.0
		 * @version     2.0.0
		 * @deprecated  2.0.0 Use the hook the deprecation notice names.
		 *
		 * @param       mixed  $value    The setting's value.
		 * @param       string $field_id v1's id of the setting.
		 */
		return apply_filters_deprecated( "locked_payment_methods_for_woocommerce/{$group}_settings/get_validated_option_value", array( $value, $field_id ), '2.0.0', $replacement );
	}

	/**
	 * Returns whether a payment method stays locked for a user and an order, which only an explicit false from the plugin's filter relaxes, once the plugin has booted.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   bool   $lock       Whether the payment method is locked before the plugin's filter runs.
	 * @param   string $gateway_id The payment method's id.
	 * @param   int    $user_id    The paying user, or 0 for a guest.
	 * @param   int    $order_id   The order being paid, or 0 off the order-pay page.
	 *
	 * @return  bool
	 */
	public static function should_lock( bool $lock, string $gateway_id, int $user_id, int $order_id ): bool {
		/**
		 * Filters whether a payment method stays locked for a user and an order; an explicit false unlocks it once the plugin has booted.
		 *
		 * @since   2.0.0
		 * @version 2.0.0
		 *
		 * @param   bool   $lock       Whether the payment method stays locked.
		 * @param   string $gateway_id The payment method's id.
		 * @param   int    $user_id    The paying user, or 0 for a guest.
		 * @param   int    $order_id   The order being paid, or 0 off the order-pay page.
		 */
		return ! self::is_relaxed( apply_filters( 'locked_payment_methods_for_woocommerce/payment_method/should_lock', $lock, $gateway_id, $user_id, $order_id ) ); // @phpstan-ignore WPCompat.parameterNotAvailable.applyfilters.args (WordPress 6.0 only formalized the arguments that older versions already passed on.)
	}

	// endregion

	// region HELPERS

	/**
	 * Returns whether a safeguard's filtered value relaxes it, which takes an explicit false once the plugin has finished booting.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $filtered The value the safeguard's filter returned for true.
	 *
	 * @return  bool
	 */
	protected static function is_relaxed( $filtered ): bool {
		return 0 < did_action( 'locked_payment_methods_for_woocommerce/boot/succeeded' ) && false === $filtered;
	}

	/**
	 * Returns the ids with the strings of a filter's value added, so a filter locks more payment methods but never unlocks one.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string[] $ids      The ids before the filter.
	 * @param   mixed    $filtered The value the filter returned.
	 *
	 * @return  string[]
	 */
	protected static function add_locked_ids( array $ids, $filtered ): array {
		return \is_array( $filtered ) ? \array_values( \array_unique( \array_merge( $ids, \array_filter( $filtered, 'is_string' ) ) ) ) : $ids;
	}

	// endregion
}
