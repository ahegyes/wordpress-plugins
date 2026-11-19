<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Integration;

use DeepWebSolutions\LockedPaymentMethods\Options;
use DeepWebSolutions\LockedPaymentMethods\Safeguards;
use DeepWebSolutions\LockedPaymentMethods\Scoped\Psr\Log\AbstractLogger as PsrAbstractLogger;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\RecordsDeprecationsTrait;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\RestoresOptionsTrait;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\RunsRequestsTrait;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( UnlockStrategies::class )]
final class UnlockStrategiesTest extends TestCase {
	use RecordsDeprecationsTrait;
	use RestoresOptionsTrait;
	use RunsRequestsTrait;

	protected const array OPTIONS = array( Options::LOCKED_METHODS, Options::UNLOCK_VIA_USER_ROLES, Options::UNLOCK_VIA_USER_PROFILES, Options::UNLOCK_VIA_ORDERS, Options::UNLOCK_VIA_GROUPS, Options::UNLOCK_VIA_WC_MEMBERSHIPS, Options::FULL_ACCESS_ROLES, 'wc_memberships_version' );

	protected static array $users = array();

	protected array $registered = array();

	protected array $wrong = array();

	#[\Override]
	public static function setUpBeforeClass(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array( 'shop_manager', 'customer' ) as $role ) {
			self::$users[ $role ] = (int) wp_insert_user(
				array(
					'user_login' => "lpm-strategies-$role",
					'user_pass'  => 'password',
					'user_email' => "lpm-strategies-$role@example.org",
					'role'       => $role,
				)
			);
			self::assertGreaterThan( 0, self::$users[ $role ] );
		}
	}

	#[\Override]
	public static function tearDownAfterClass(): void {
		foreach ( self::$users as $user ) {
			wp_delete_user( $user );
		}
	}

	#[Before]
	protected function keep_the_registered_strategies(): void {
		$this->registered = self::holder()->getValue();
		update_option( Options::LOCKED_METHODS, array( 'cod', 'bacs' ) );
		update_option( Options::FULL_ACCESS_ROLES, array( 'shop_manager' ) );
		add_action( 'doing_it_wrong_run', array( $this, 'record_doing_it_wrong' ) );
		add_filter( 'doing_it_wrong_trigger_error', '__return_false' );
	}

	#[After]
	protected function restore_the_registered_strategies(): void {
		remove_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		remove_action( 'doing_it_wrong_run', array( $this, 'record_doing_it_wrong' ) );
		self::holder()->setValue( null, $this->registered );
		wp_set_current_user( 0 );
	}

	public function record_doing_it_wrong( string $function_name ): void {
		$this->wrong[] = $function_name;
	}

	public function test_each_built_in_strategy_whose_switch_is_on_registers_under_its_id_once_the_plugin_boots(): void {
		foreach ( array( Options::UNLOCK_VIA_USER_ROLES, Options::UNLOCK_VIA_USER_PROFILES, Options::UNLOCK_VIA_ORDERS, Options::UNLOCK_VIA_GROUPS, Options::UNLOCK_VIA_WC_MEMBERSHIPS ) as $switch ) {
			update_option( $switch, 'yes' );
		}
		update_option( 'wc_memberships_version', '1.27.0' );
		$list = 'echo wp_json_encode( array_map( static fn ( DWS_LPMWC_Unlock_Strategy $strategy ): string => $strategy->get_id(), dws_lpmwc_get_unlock_strategies() ) );';

		$request = self::request( $list, '--require=' . \dirname( __DIR__ ) . '/Support/memberships-functions.php' );
		self::assertSame(
			array(
				'user_role'      => 'user_role',
				'user_profile'   => 'user_profile',
				'order'          => 'order',
				'groups'         => 'groups',
				'wc_memberships' => 'wc_memberships',
			),
			\json_decode( $request['last'], true )
		);

		foreach ( array( Options::UNLOCK_VIA_USER_ROLES, Options::UNLOCK_VIA_USER_PROFILES, Options::UNLOCK_VIA_ORDERS, Options::UNLOCK_VIA_GROUPS, Options::UNLOCK_VIA_WC_MEMBERSHIPS ) as $switch ) {
			update_option( $switch, 'no' );
		}
		self::assertSame( '[]', self::request( $list )['last'] );
	}

	public function test_without_the_boot_no_strategy_registers_and_every_locked_method_stays_locked(): void {
		$request = self::request( 'echo wp_json_encode( array( dws_lpmwc_get_unlock_strategies(), dws_lpmwc_get_unlock_strategy( "user_role" ), dws_lpmwc_is_payment_method_locked( "cod", ' . self::$users['shop_manager'] . ' ) ) );', '--skip-plugins=woocommerce' );

		self::assertSame( array( array(), null, true ), \json_decode( $request['last'], true ) );
	}

	public function test_a_strategy_registered_once_the_plugin_has_booted_unlocks_at_checkout_and_in_the_lock_check(): void {
		self::assertTrue( dws_lpmwc_register_unlock_strategy( self::strategy( 'acme_credit_check', 'cod', self::$users['customer'] ) ) );

		wp_set_current_user( self::$users['customer'] );
		$offered = apply_filters( 'woocommerce_available_payment_gateways', WC()->payment_gateways()->payment_gateways() );
		self::assertArrayHasKey( 'cod', $offered );
		self::assertArrayNotHasKey( 'bacs', $offered );
		self::assertFalse( dws_lpmwc_is_payment_method_locked( 'cod', self::$users['customer'] ) );
		self::assertTrue( dws_lpmwc_is_payment_method_locked( 'bacs', self::$users['customer'] ) );
		self::assertTrue( dws_lpmwc_is_payment_method_locked( 'cod', 0 ) );
		self::assertSame( 'acme_credit_check', dws_lpmwc_get_unlock_strategy( 'acme_credit_check' )?->get_id() );
		self::assertArrayHasKey( 'acme_credit_check', dws_lpmwc_get_unlock_strategies() );
	}

	public function test_a_second_strategy_under_a_registered_id_is_refused_and_the_first_one_kept(): void {
		$first = self::strategy( 'acme_credit_check', 'cod', self::$users['customer'] );

		self::assertTrue( dws_lpmwc_register_unlock_strategy( $first ) );
		self::assertFalse( dws_lpmwc_register_unlock_strategy( self::strategy( 'acme_credit_check', 'bacs', self::$users['customer'] ) ) );
		self::assertSame( $first, dws_lpmwc_get_unlock_strategy( 'acme_credit_check' ) );
		self::assertCount( 1, $this->wrong );
	}

	public function test_a_strategy_that_throws_is_logged_and_unlocks_nothing(): void {
		$logger = new class() extends PsrAbstractLogger {
			public array $records = array();

			public function log( $level, string|\Stringable $message, array $context = array() ): void {
				$this->records[] = array( $level, $context['exception'] ?? null );
			}
		};
		$throwing   = self::strategy( 'acme_broken', 'cod', self::$users['customer'], new \RuntimeException( 'The credit check is down.' ) );
		$strategies = new UnlockStrategies( $logger );
		self::assertTrue( dws_lpmwc_register_unlock_strategy( $throwing ) );

		self::assertTrue( $strategies->apply_strategies( true, 'cod', self::$users['customer'], 0 ) );
		self::assertCount( 1, $logger->records );
		self::assertInstanceOf( \RuntimeException::class, $logger->records[0][1] );
		self::assertFalse( $strategies->apply_strategies( true, 'cod', self::$users['shop_manager'], 0 ) );
	}

	public function test_v1s_lock_filters_reach_the_lock_check_as_they_reach_the_checkout(): void {
		$unlock_cod = static fn ( mixed $locked, string $id ): mixed => 'cod' === $id ? false : $locked;
		add_filter( 'locked_payment_methods_for_woocommerce/lock_manager/is_locked_payment_method', $unlock_cod, 10, 2 );
		add_filter( 'locked_payment_methods_for_woocommerce/lock_manager/is_locked_payment_method/bacs', '__return_false' );

		try {
			self::assertFalse( dws_lpmwc_is_payment_method_locked( 'cod', self::$users['customer'] ) );
			self::assertFalse( Safeguards::should_lock( true, 'bacs', self::$users['customer'], 0 ) );
		} finally {
			remove_filter( 'locked_payment_methods_for_woocommerce/lock_manager/is_locked_payment_method/bacs', '__return_false' );
			remove_filter( 'locked_payment_methods_for_woocommerce/lock_manager/is_locked_payment_method', $unlock_cod );
		}

		self::assertContains( array( 'locked_payment_methods_for_woocommerce/lock_manager/is_locked_payment_method', 'locked_payment_methods_for_woocommerce/payment_method/should_lock' ), $this->deprecated );
		self::assertContains( array( 'locked_payment_methods_for_woocommerce/lock_manager/is_locked_payment_method/bacs', 'locked_payment_methods_for_woocommerce/payment_method/should_lock' ), $this->deprecated );
		self::assertTrue( dws_lpmwc_is_payment_method_locked( 'cod', self::$users['customer'] ) );
	}

	protected static function strategy( string $id, string $gateway_id, int $user_id, ?\Throwable $error = null ): \DWS_LPMWC_Unlock_Strategy {
		return new class( $id, $gateway_id, $user_id, $error ) implements \DWS_LPMWC_Unlock_Strategy {
			public function __construct( protected string $id, protected string $gateway_id, protected int $user_id, protected ?\Throwable $error ) {}

			public function get_id(): string {
				return $this->id;
			}

			public function get_label(): string {
				return 'Acme credit check';
			}

			public function unlocks( string $gateway_id, int $user_id, int $order_id ): bool {
				if ( $this->error instanceof \Throwable ) {
					throw $this->error;
				}

				return $this->gateway_id === $gateway_id && $this->user_id === $user_id;
			}
		};
	}

	protected static function holder(): \ReflectionProperty {
		return new \ReflectionProperty( UnlockStrategies::class, 'strategies' );
	}
}
