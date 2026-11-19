<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods;

use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;
use DeepWebSolutions\LockedPaymentMethods\Scoped\Psr\Log\LoggerInterface as PsrLoggerInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Holds the strategies registered for the request and unlocks a payment method one of them unlocks.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class UnlockStrategies implements ComponentInterface {
	// region FIELDS AND CONSTANTS

	/**
	 * The strategies registered for the request, by id, in the order they registered.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<string, \DWS_LPMWC_Unlock_Strategy>
	 */
	protected static array $strategies = array();

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   PsrLoggerInterface $logger The plugin's logger, which records a strategy that throws.
	 */
	public function __construct(
		protected PsrLoggerInterface $logger
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
	public function register_hooks(): void {
		add_filter( 'locked_payment_methods_for_woocommerce/payment_method/should_lock', array( $this, 'apply_strategies' ), 10, 4 );
	}

	// endregion

	// region METHODS

	/**
	 * Registers a strategy, unless one with its id is registered already.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \DWS_LPMWC_Unlock_Strategy $strategy The strategy.
	 *
	 * @return  bool
	 */
	public static function register( \DWS_LPMWC_Unlock_Strategy $strategy ): bool {
		$id = $strategy->get_id();
		if ( isset( self::$strategies[ $id ] ) ) {
			_doing_it_wrong( __METHOD__, esc_html( "The unlock strategy '$id' is registered already." ), '2.0.0' );
			return false;
		}

		self::$strategies[ $id ] = $strategy;
		return true;
	}

	/**
	 * Returns the registered strategy with an id, or null for none or before the plugin has booted, since a boot that fails partway leaves the strategies it registered.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $id The strategy's id.
	 *
	 * @return  \DWS_LPMWC_Unlock_Strategy|null
	 */
	public static function get( string $id ): ?\DWS_LPMWC_Unlock_Strategy {
		return 0 < did_action( 'locked_payment_methods_for_woocommerce/boot/succeeded' ) ? self::$strategies[ $id ] ?? null : null;
	}

	/**
	 * Returns the registered strategies, by id, in the order they registered, or none before the plugin has booted.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  array<string, \DWS_LPMWC_Unlock_Strategy>
	 */
	public static function get_all(): array {
		return 0 < did_action( 'locked_payment_methods_for_woocommerce/boot/succeeded' ) ? self::$strategies : array();
	}

	/**
	 * Returns the first registered strategy that unlocks a payment method for a user and an order, or null for none.
	 *
	 * A strategy that throws is logged and passed over, so another plugin's failure leaves the method locked.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $gateway_id The payment method's id.
	 * @param   int    $user_id    The paying user, or 0 for a guest.
	 * @param   int    $order_id   The order being paid, or 0 off its payment page.
	 *
	 * @return  \DWS_LPMWC_Unlock_Strategy|null
	 */
	public function find( string $gateway_id, int $user_id, int $order_id ): ?\DWS_LPMWC_Unlock_Strategy {
		foreach ( self::$strategies as $id => $strategy ) {
			try {
				if ( $strategy->unlocks( $gateway_id, $user_id, $order_id ) ) {
					return $strategy;
				}
			} catch ( \Throwable $error ) {
				$this->logger->error(
					'The unlock strategy {strategy} failed, so it unlocks nothing: {message}',
					array(
						'strategy'  => $id,
						'message'   => $error->getMessage(),
						'exception' => $error,
					)
				);
			}
		}

		return null;
	}

	// endregion

	// region HOOKS

	/**
	 * Returns false for a payment method a registered strategy unlocks, and the lock as given otherwise.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed  $lock       Whether the payment method stays locked, as earlier callbacks left it, passed on unchanged since only an explicit false unlocks.
	 * @param   string $gateway_id The payment method's id.
	 * @param   int    $user_id    The paying user, or 0 for a guest.
	 * @param   int    $order_id   The order being paid, or 0 off its payment page.
	 *
	 * @return  mixed
	 */
	public function apply_strategies( mixed $lock, string $gateway_id, int $user_id, int $order_id ): mixed {
		return false === $lock || \is_null( $this->find( $gateway_id, $user_id, $order_id ) ) ? $lock : false;
	}

	// endregion
}
