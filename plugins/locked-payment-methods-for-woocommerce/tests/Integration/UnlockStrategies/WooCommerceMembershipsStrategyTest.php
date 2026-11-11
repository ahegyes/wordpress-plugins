<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Integration\UnlockStrategies;

use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use DeepWebSolutions\LockedPaymentMethods\Scoped\Psr\Log\NullLogger as PsrNullLogger;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\MembershipsDouble;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\RestoresOptionsTrait;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies\WooCommerceMembershipsStrategy;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

require_once \dirname( __DIR__, 2 ) . '/Support/memberships-functions.php';

// Memberships is a paid plugin absent from WP Packages, so these rows run against a double of its one function and its three hooks, unverified against a licensed copy.
#[CoversNothing]
#[Group( 'wc-memberships' )]
final class WooCommerceMembershipsStrategyTest extends TestCase {
	use RestoresOptionsTrait;

	protected const string FORM = 'dws_lpmwc_membership_plan';

	protected const array OPTIONS = array( DWS_LPMWC_OPTION_LOCKED_METHODS, DWS_LPMWC_OPTION_UNLOCK_VIA_WC_MEMBERSHIPS, 'wc_memberships_version' );

	protected static WooCommerceMembershipsStrategy $strategy;

	protected static array $hooks = array();

	protected static array $users = array();

	protected int $plan;

	// Memberships is off by default, so the booted plugin runs no Memberships strategy; the class hooks one of its own, as the plugin does with the switch on.
	#[\Override]
	public static function setUpBeforeClass(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		register_post_type( 'wc_membership_plan' );
		self::$strategy = new WooCommerceMembershipsStrategy( new AdminNotices( 'dws_lpmwc', new PsrNullLogger() ) );
		self::$hooks    = \array_map( static fn ( \WP_Hook $hook ): \WP_Hook => clone $hook, $GLOBALS['wp_filter'] );
		self::$strategy->register_hooks();
		foreach ( array( 'administrator', 'customer', 'subscriber' ) as $role ) {
			self::$users[ $role ] = (int) wp_insert_user(
				array(
					'user_login' => "lpm-memberships-$role",
					'user_pass'  => 'password',
					'user_email' => "lpm-memberships-$role@example.org",
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
		unregister_post_type( 'wc_membership_plan' );
		$GLOBALS['wp_filter'] = self::$hooks; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Unhooks the class's strategy and its form.
	}

	// The form evaluates its definition and saves an object once per request, so every row edits a plan of its own.
	#[Before]
	protected function create_the_plan(): void {
		update_option( DWS_LPMWC_OPTION_LOCKED_METHODS, array( 'cod', 'bacs' ) );
		update_option( 'wc_memberships_version', '1.27.0' );
		$this->plan = (int) wp_insert_post(
			array(
				'post_type'   => 'wc_membership_plan',
				'post_title'  => 'LPM plan',
				'post_status' => 'publish',
			)
		);
	}

	#[After]
	protected function clean_up(): void {
		wp_delete_post( $this->plan, true );
		MembershipsDouble::$memberships = array();
		unset( $_POST[ self::FORM ], $_POST[ self::FORM . '_nonce' ], $GLOBALS['post'] );
		wp_set_current_user( 0 );
	}

	public function test_the_strategy_loads_only_while_memberships_is_active_at_v1s_oldest_version_and_its_switch_is_on(): void {
		update_option( DWS_LPMWC_OPTION_UNLOCK_VIA_WC_MEMBERSHIPS, 'yes' );
		self::assertTrue( WooCommerceMembershipsStrategy::is_available() );
		self::assertTrue( WooCommerceMembershipsStrategy::should_load() );

		update_option( 'wc_memberships_version', '1.17.9' );
		self::assertFalse( WooCommerceMembershipsStrategy::is_available() );

		update_option( 'wc_memberships_version', '1.27.0' );
		delete_option( DWS_LPMWC_OPTION_UNLOCK_VIA_WC_MEMBERSHIPS );
		self::assertFalse( WooCommerceMembershipsStrategy::should_load() );
	}

	public function test_an_active_member_of_a_plan_is_unlocked_for_the_plans_methods(): void {
		update_post_meta( $this->plan, DWS_LPMWC_META_UNLOCKED_METHODS, array( 'cod' ) );
		MembershipsDouble::add( self::$users['customer'], $this->plan );
		MembershipsDouble::add( self::$users['subscriber'], $this->plan, false );

		self::assertSame( array( 'cod' ), dws_lpmwc_get_plan_unlocked_payment_method_ids( $this->plan ) );
		self::assertFalse( self::should_lock( 'cod', self::$users['customer'] ) );
		self::assertTrue( self::should_lock( 'bacs', self::$users['customer'] ) );
		self::assertTrue( self::should_lock( 'cod', self::$users['subscriber'] ) );
		self::assertTrue( self::should_lock( 'cod', 0 ) );
	}

	public function test_the_plan_data_offers_a_tab_and_its_panel_to_a_user_with_the_capability(): void {
		$GLOBALS['post'] = get_post( $this->plan );
		update_post_meta( $this->plan, DWS_LPMWC_META_UNLOCKED_METHODS, array( 'cod' ) );

		wp_set_current_user( self::$users['customer'] );
		self::assertSame( array(), apply_filters( 'wc_membership_plan_data_tabs', array() ) );

		wp_set_current_user( self::$users['administrator'] );
		self::assertSame( 'dws-lpmwc-membership-plan-data', apply_filters( 'wc_membership_plan_data_tabs', array() )[ self::FORM ]['target'] ?? null );

		\ob_start();
		do_action( 'wc_membership_plan_data_panels' );
		$panel = \Dom\HTMLDocument::createFromString( '<!DOCTYPE html><html><body>' . \ob_get_clean() . '</body></html>', \LIBXML_NOERROR );

		$choices = array();
		foreach ( $panel->querySelectorAll( '#dws-lpmwc-membership-plan-data input[type="checkbox"]' ) as $input ) {
			$choices[ (string) $input->getAttribute( 'value' ) ] = $input->hasAttribute( 'checked' );
		}
		self::assertSame(
			array(
				'cod'  => true,
				'bacs' => false,
			),
			$choices
		);
	}

	public function test_saving_the_plan_stores_the_checked_methods_under_its_own_nonce(): void {
		wp_set_current_user( self::$users['administrator'] );
		$_POST[ self::FORM ] = array( DWS_LPMWC_META_UNLOCKED_METHODS => array( 'bacs' ) );

		$_POST[ self::FORM . '_nonce' ] = wp_create_nonce( self::FORM . '_nonce_' . ( $this->plan + 1 ) );
		do_action( 'wc_memberships_save_meta_box', array(), 'wc-memberships-membership-plan-data', $this->plan, get_post( $this->plan ) );
		self::assertSame( array(), dws_lpmwc_get_plan_unlocked_payment_method_ids( $this->plan ) );

		$_POST[ self::FORM . '_nonce' ] = wp_create_nonce( self::FORM . "_nonce_$this->plan" );
		do_action( 'wc_memberships_save_meta_box', array(), 'wc-memberships-membership-plan-data', $this->plan, get_post( $this->plan ) );
		self::assertSame( array( 'bacs' ), dws_lpmwc_get_plan_unlocked_payment_method_ids( $this->plan ) );
	}

	public function test_the_grants_step_moves_v1s_plan_meta_to_the_plugins_key(): void {
		update_post_meta( $this->plan, '_dws_wc_lpm_unlocked_payment_methods', array( 'cod', 7 ) );
		update_post_meta( $this->plan, DWS_LPMWC_META_UNLOCKED_METHODS, array( 'cheque' ) );

		dws_lpmwc_move_plan_grants();
		dws_lpmwc_move_plan_grants();

		self::assertSame( array( 'cheque', 'cod' ), dws_lpmwc_get_plan_unlocked_payment_method_ids( $this->plan ) );
		self::assertSame( '', get_post_meta( $this->plan, '_dws_wc_lpm_unlocked_payment_methods', true ) );
	}

	protected static function should_lock( string $gateway_id, int $user_id ): mixed {
		return apply_filters( 'dws_lpmwc/payment_method/should_lock', true, $gateway_id, $user_id, 0 );
	}
}
