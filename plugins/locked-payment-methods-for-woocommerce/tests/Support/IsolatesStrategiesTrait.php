<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Support;

use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies;
use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\Attributes\BeforeClass;

// A strategy's class hooks and registers a strategy of its own, as the plugin does with its switch on, so the hooks and the registered strategies go back as they were after the class.
trait IsolatesStrategiesTrait {
	protected static array $hooks = array();

	protected static array $registered = array();

	#[BeforeClass]
	public static function keep_the_hooks_and_strategies(): void {
		self::$hooks      = \array_map( static fn ( \WP_Hook $hook ): \WP_Hook => clone $hook, $GLOBALS['wp_filter'] );
		self::$registered = new \ReflectionProperty( UnlockStrategies::class, 'strategies' )->getValue();
	}

	#[AfterClass]
	public static function restore_the_hooks_and_strategies(): void {
		$GLOBALS['wp_filter'] = self::$hooks;
		new \ReflectionProperty( UnlockStrategies::class, 'strategies' )->setValue( null, self::$registered );
	}

	protected static function should_lock( string $gateway_id, int $user_id, int $order_id = 0 ): mixed {
		return apply_filters( 'locked_payment_methods_for_woocommerce/payment_method/should_lock', true, $gateway_id, $user_id, $order_id );
	}

	protected static function parse( string $markup ): \Dom\HTMLDocument {
		return \Dom\HTMLDocument::createFromString( '<!DOCTYPE html><html><body>' . $markup . '</body></html>', \LIBXML_NOERROR );
	}
}
