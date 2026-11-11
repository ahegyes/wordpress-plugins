<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Integration\UnlockStrategies;

use DeepWebSolutions\LockedPaymentMethods\Plugin;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\RecordsDeprecationsTrait;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\RestoresOptionsTrait;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies\UserRoleStrategy;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class UserRoleStrategyTest extends TestCase {
	use RecordsDeprecationsTrait;
	use RestoresOptionsTrait;

	protected const string V1_HOOK = 'locked_payment_methods_for_woocommerce/general_settings/get_validated_option_value';

	protected const array OPTIONS = array( DWS_LPMWC_OPTION_LOCKED_METHODS, DWS_LPMWC_OPTION_UNLOCK_VIA_USER_ROLES, DWS_LPMWC_OPTION_FULL_ACCESS_ROLES );

	protected static array $users = array();

	#[\Override]
	public static function setUpBeforeClass(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array( 'shop_manager', 'customer' ) as $role ) {
			self::$users[ $role ] = (int) wp_insert_user(
				array(
					'user_login' => "lpm-roles-$role",
					'user_pass'  => 'password',
					'user_email' => "lpm-roles-$role@example.org",
					'role'       => $role,
				)
			);
		}
	}

	#[\Override]
	public static function tearDownAfterClass(): void {
		foreach ( self::$users as $user ) {
			wp_delete_user( $user );
		}
	}

	#[Before]
	protected function grant_shop_managers_every_method(): void {
		update_option( DWS_LPMWC_OPTION_FULL_ACCESS_ROLES, array( 'shop_manager' ) );
	}

	#[After]
	protected function log_out(): void {
		wp_set_current_user( 0 );
	}

	public function test_the_strategy_loads_while_its_switch_is_on_as_v1s_did_by_default(): void {
		delete_option( DWS_LPMWC_OPTION_UNLOCK_VIA_USER_ROLES );
		self::assertTrue( UserRoleStrategy::should_load() );

		update_option( DWS_LPMWC_OPTION_UNLOCK_VIA_USER_ROLES, 'no' );
		self::assertFalse( UserRoleStrategy::should_load() );

		update_option( DWS_LPMWC_OPTION_UNLOCK_VIA_USER_ROLES, 'yes' );
		self::assertTrue( UserRoleStrategy::should_load() );
	}

	public function test_the_booted_plugin_runs_the_strategy(): void {
		self::assertContains( UserRoleStrategy::class, Plugin::COMPONENTS );
		self::assertNotContains( UserRoleStrategy::class, dws_lpmwc_plugin()->kernel?->skipped ?? array( UserRoleStrategy::class ) );
	}

	public function test_only_a_user_with_a_full_access_role_is_unlocked(): void {
		$strategy = new UserRoleStrategy();

		self::assertFalse( $strategy->unlock_for_full_access_roles( true, 'cod', self::$users['shop_manager'] ) );
		self::assertTrue( $strategy->unlock_for_full_access_roles( true, 'cod', self::$users['customer'] ) );
		self::assertTrue( $strategy->unlock_for_full_access_roles( true, 'cod', 0 ) );
		self::assertFalse( $strategy->unlock_for_full_access_roles( false, 'cod', self::$users['customer'] ) );
	}

	public function test_an_empty_roles_list_unlocks_no_one(): void {
		update_option( DWS_LPMWC_OPTION_FULL_ACCESS_ROLES, array() );

		self::assertTrue( ( new UserRoleStrategy() )->unlock_for_full_access_roles( true, 'cod', self::$users['shop_manager'] ) );
	}

	public function test_at_checkout_the_lock_opens_for_a_full_access_user_only(): void {
		update_option( DWS_LPMWC_OPTION_LOCKED_METHODS, array( 'cod' ) );

		wp_set_current_user( self::$users['shop_manager'] );
		self::assertArrayHasKey( 'cod', apply_filters( 'woocommerce_available_payment_gateways', WC()->payment_gateways()->payment_gateways() ) );

		wp_set_current_user( self::$users['customer'] );
		self::assertArrayNotHasKey( 'cod', apply_filters( 'woocommerce_available_payment_gateways', WC()->payment_gateways()->payment_gateways() ) );
	}

	public function test_v1s_value_filter_on_the_roles_fires_as_deprecated_and_counts(): void {
		$seen    = array();
		$replace = static function ( mixed $value, string $field_id ) use ( &$seen ): array {
			$seen[] = array( $value, $field_id );

			return array( 'customer' );
		};
		add_filter( self::V1_HOOK, $replace, 10, 2 );

		try {
			$lock = ( new UserRoleStrategy() )->unlock_for_full_access_roles( true, 'cod', self::$users['customer'] );
		} finally {
			remove_filter( self::V1_HOOK, $replace );
		}

		self::assertFalse( $lock );
		self::assertSame( array( array( array( 'shop_manager' ), 'full-access-user-roles' ) ), $seen );
		self::assertSame( array( array( self::V1_HOOK, 'option_' . DWS_LPMWC_OPTION_FULL_ACCESS_ROLES ) ), $this->deprecated );
	}
}
