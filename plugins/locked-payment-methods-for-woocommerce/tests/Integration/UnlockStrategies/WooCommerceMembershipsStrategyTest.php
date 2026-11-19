<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Integration\UnlockStrategies;

use DeepWebSolutions\LockedPaymentMethods\Migrations;
use DeepWebSolutions\LockedPaymentMethods\Options;
use DeepWebSolutions\LockedPaymentMethods\Plugin;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\PluginIdentity;
use DeepWebSolutions\LockedPaymentMethods\Scoped\Psr\Log\NullLogger as PsrNullLogger;
use DeepWebSolutions\LockedPaymentMethods\Settings;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\InsertsUsersTrait;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\IsolatesStrategiesTrait;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\MembershipsDouble;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\RecordsDeprecationsTrait;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\RecordsGrantUpdatesTrait;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\RestoresOptionsTrait;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies\WooCommerceMembershipsStrategy;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

// Memberships is a paid plugin absent from WP Packages, so these rows run against a double of its loader, its one function and its three hooks.
#[CoversClass( WooCommerceMembershipsStrategy::class )]
#[Group( 'wc-memberships' )]
final class WooCommerceMembershipsStrategyTest extends TestCase {
	use InsertsUsersTrait;
	use IsolatesStrategiesTrait;
	use RecordsDeprecationsTrait;
	use RecordsGrantUpdatesTrait;
	use RestoresOptionsTrait;

	protected const string FORM = 'dws_lpmwc_membership_plan';

	protected const array OPTIONS = array( Options::LOCKED_METHODS, Options::UNLOCK_VIA_WC_MEMBERSHIPS, 'wc_memberships_version' );

	protected static WooCommerceMembershipsStrategy $strategy;

	protected static array $users = array();

	protected int $plan;

	// Memberships is off by default, so the booted plugin runs no Memberships strategy; the class hooks one of its own, as the plugin does with the switch on.
	#[\Override]
	public static function setUpBeforeClass(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		require_once \dirname( __DIR__, 2 ) . '/Support/memberships-functions.php';
		register_post_type( 'wc_membership_plan' );
		$identity       = new PluginIdentity( DWS_LPMWC_FILE, Plugin::SLUG, Plugin::PREFIX );
		self::$strategy = new WooCommerceMembershipsStrategy( $identity, new AdminNotices( $identity, new PsrNullLogger() ) );
		self::$strategy->register_hooks();
		foreach ( array( 'administrator', 'editor', 'customer', 'subscriber' ) as $role ) {
			self::$users[ $role ] = self::insert_user(
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
	}

	// The form evaluates its definition and saves an object once per request, so every row edits a plan of its own.
	#[Before]
	protected function create_the_plan(): void {
		update_option( Options::LOCKED_METHODS, array( 'cod', 'bacs' ) );
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
		update_option( Options::UNLOCK_VIA_WC_MEMBERSHIPS, 'yes' );
		self::assertTrue( WooCommerceMembershipsStrategy::is_available() );
		self::assertTrue( WooCommerceMembershipsStrategy::should_load() );

		update_option( 'wc_memberships_version', '1.17.9' );
		self::assertFalse( WooCommerceMembershipsStrategy::is_available() );

		update_option( 'wc_memberships_version', '1.27.0' );
		delete_option( Options::UNLOCK_VIA_WC_MEMBERSHIPS );
		self::assertFalse( WooCommerceMembershipsStrategy::should_load() );
	}

	// Memberships declares its loader as WordPress includes it but defines its functions only on plugins_loaded at priority 15, after the plugin's boot.
	public function test_the_settings_show_the_memberships_switch_while_memberships_is_active(): void {
		$fields = new Settings()->get_section_fields( 'advanced', 'dws-locked-payment-methods' );
		$switch = \array_values( \array_filter( $fields, static fn ( array $field ): bool => Options::UNLOCK_VIA_WC_MEMBERSHIPS === ( $field['id'] ?? null ) ) );

		self::assertSame( 'checkbox', $switch[0]['type'] ?? null );
		self::assertSame( 'no', $switch[0]['default'] ?? null );
		self::assertContains( 'dws-wc-lpm_integrations', \array_column( $fields, 'id' ) );
	}

	public function test_an_active_member_of_a_plan_is_unlocked_for_the_plans_methods(): void {
		update_post_meta( $this->plan, '_dws_lpmwc_unlocked_payment_methods', array( 'cod' ) );
		MembershipsDouble::add( self::$users['customer'], $this->plan );
		MembershipsDouble::add( self::$users['subscriber'], $this->plan, false );

		self::assertSame( array( 'cod' ), self::$strategy->get_granted_ids( $this->plan ) );
		self::assertFalse( self::should_lock( 'cod', self::$users['customer'] ) );
		self::assertTrue( self::should_lock( 'bacs', self::$users['customer'] ) );
		self::assertTrue( self::should_lock( 'cod', self::$users['subscriber'] ) );
		self::assertTrue( self::should_lock( 'cod', 0 ) );

		self::assertFalse( dws_lpmwc_check_payment_method_access_via_wc_memberships( 'cod', self::$users['customer'] ) );
		self::assertTrue( dws_lpmwc_check_payment_method_access_via_wc_memberships( 'cod', self::$users['subscriber'] ) );
		$holder = new \ReflectionProperty( UnlockStrategies::class, 'strategies' );
		$on     = $holder->getValue();
		$holder->setValue( null, self::$registered );
		try {
			self::assertTrue( dws_lpmwc_check_payment_method_access_via_wc_memberships( 'cod', self::$users['customer'] ) );
		} finally {
			$holder->setValue( null, $on );
		}
		self::assertContains( array( 'dws_lpmwc_check_payment_method_access_via_wc_memberships', 'dws_lpmwc_is_payment_method_locked()' ), $this->deprecated );
	}

	public function test_the_plan_data_offers_a_tab_and_its_panel_to_a_user_with_the_capability(): void {
		$GLOBALS['post'] = get_post( $this->plan );
		update_post_meta( $this->plan, '_dws_lpmwc_unlocked_payment_methods', array( 'cod' ) );

		wp_set_current_user( self::$users['customer'] );
		self::assertSame( array(), apply_filters( 'wc_membership_plan_data_tabs', array() ) );

		wp_set_current_user( self::$users['administrator'] );
		self::assertSame( 'dws-lpmwc-membership-plan-data', apply_filters( 'wc_membership_plan_data_tabs', array() )[ self::FORM ]['target'] ?? null );

		\ob_start();
		do_action( 'wc_membership_plan_data_panels' );
		$panel = self::parse( (string) \ob_get_clean() );

		// Memberships' panels print WooCommerce's options-panel rows, whose CSS lays out a group's choices beside its legend only inside a wc-radios list.
		self::assertSame( 'Unlocked payment methods', \trim( (string) $panel->querySelector( '#dws-lpmwc-membership-plan-data > div.options_group > fieldset.form-field > legend' )?->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertCount( 2, $panel->querySelectorAll( '#dws-lpmwc-membership-plan-data fieldset.form-field > ul.wc-radios[role="presentation"] > li input[type="checkbox"]' ) );

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
		$_POST[ self::FORM ] = array( 'dws_lpmwc_unlocked_payment_methods' => array( 'bacs' ) );

		$_POST[ self::FORM . '_nonce' ] = wp_create_nonce( self::FORM . '_nonce_' . ( $this->plan + 1 ) );
		do_action( 'wc_memberships_save_meta_box', array(), 'wc-memberships-membership-plan-data', $this->plan, get_post( $this->plan ) );
		self::assertSame( array(), self::$strategy->get_granted_ids( $this->plan ) );

		$_POST[ self::FORM . '_nonce' ] = wp_create_nonce( self::FORM . "_nonce_$this->plan" );
		do_action( 'wc_memberships_save_meta_box', array(), 'wc-memberships-membership-plan-data', $this->plan, get_post( $this->plan ) );
		self::assertSame( array( 'bacs' ), self::$strategy->get_granted_ids( $this->plan ) );
		self::assertSame( array( array( 'wc_memberships', $this->plan, array( 'bacs' ), array() ) ), $this->updates );
	}

	public function test_a_plan_editor_without_the_capability_gets_no_tab_and_saves_nothing_under_a_valid_nonce(): void {
		$GLOBALS['post'] = get_post( $this->plan );
		wp_set_current_user( self::$users['editor'] );
		self::assertTrue( current_user_can( 'edit_post', $this->plan ) );

		self::assertSame( array(), apply_filters( 'wc_membership_plan_data_tabs', array() ) );
		$_POST[ self::FORM ]            = array( 'dws_lpmwc_unlocked_payment_methods' => array( 'bacs' ) );
		$_POST[ self::FORM . '_nonce' ] = wp_create_nonce( self::FORM . "_nonce_$this->plan" );
		do_action( 'wc_memberships_save_meta_box', array(), 'wc-memberships-membership-plan-data', $this->plan, get_post( $this->plan ) );
		self::assertSame( array(), self::$strategy->get_granted_ids( $this->plan ) );
	}

	public function test_the_grants_step_moves_v1s_plan_meta_to_the_plugins_key(): void {
		update_post_meta( $this->plan, '_dws_wc_lpm_unlocked_payment_methods', array( 'cod', 7 ) );
		update_post_meta( $this->plan, '_dws_lpmwc_unlocked_payment_methods', array( 'cheque' ) );

		Migrations::get_steps()[5]();
		Migrations::get_steps()[5]();

		self::assertSame( array( 'cheque', 'cod' ), self::$strategy->get_granted_ids( $this->plan ) );
		self::assertSame( '', get_post_meta( $this->plan, '_dws_wc_lpm_unlocked_payment_methods', true ) );
	}

	public function test_the_grants_step_keeps_v1s_plan_meta_when_the_plugins_key_cannot_be_stored(): void {
		update_post_meta( $this->plan, '_dws_wc_lpm_unlocked_payment_methods', array( 'cod' ) );
		$refuse = static fn ( mixed $check, int $object_id, string $key ): mixed => '_dws_lpmwc_unlocked_payment_methods' === $key ? false : $check;
		add_filter( 'add_post_metadata', $refuse, 10, 3 );

		try {
			Migrations::get_steps()[5]();
			self::fail( 'The step stored nothing and still went on.' );
		} catch ( \RuntimeException ) {
			self::assertSame( '', get_post_meta( $this->plan, '_dws_lpmwc_unlocked_payment_methods', true ) );
		} finally {
			remove_filter( 'add_post_metadata', $refuse );
		}

		self::assertSame( array( 'cod' ), get_post_meta( $this->plan, '_dws_wc_lpm_unlocked_payment_methods', true ) );
	}
}
