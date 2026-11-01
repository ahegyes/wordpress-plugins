<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Integration\UnlockStrategies;

use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use DeepWebSolutions\LockedPaymentMethods\Scoped\Psr\Log\NullLogger as PsrNullLogger;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies\GroupsStrategy;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Group( 'groups' )]
final class GroupsStrategyTest extends TestCase {
	protected const array KEYS = array( DWS_LPMWC_OPTION_LOCKED_METHODS, DWS_LPMWC_OPTION_UNLOCK_VIA_GROUPS, DWS_LPMWC_OPTION_GROUPS_UNLOCKED_METHODS );

	protected static GroupsStrategy $strategy;

	protected static array $hooks = array();

	protected static array $users = array();

	protected array $stored = array();

	protected array $groups = array();

	// Groups is off by default, so the booted plugin runs no Groups strategy; the class hooks one of its own, as the plugin does with the switch on.
	#[\Override]
	public static function setUpBeforeClass(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		self::$strategy = new GroupsStrategy( new AdminNotices( 'dws_lpmwc', new PsrNullLogger() ) );
		self::$hooks    = \array_map( static fn ( \WP_Hook $hook ): \WP_Hook => clone $hook, $GLOBALS['wp_filter'] );
		self::$strategy->register_hooks();
		foreach ( array( 'administrator', 'shop_manager', 'customer', 'subscriber' ) as $role ) {
			self::$users[ $role ] = (int) wp_insert_user(
				array(
					'user_login' => "lpm-groups-$role",
					'user_pass'  => 'password',
					'user_email' => "lpm-groups-$role@example.org",
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
		$GLOBALS['wp_filter'] = self::$hooks; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Unhooks the class's strategy and its form.
	}

	#[Before]
	protected function store_rows(): void {
		foreach ( self::KEYS as $key ) {
			$this->stored[ $key ] = get_option( $key );
		}
		update_option( DWS_LPMWC_OPTION_LOCKED_METHODS, array( 'cod', 'bacs' ) );
		delete_option( DWS_LPMWC_OPTION_GROUPS_UNLOCKED_METHODS );
	}

	#[After]
	protected function restore_rows(): void {
		foreach ( $this->groups as $group ) {
			\Groups_Group::delete( $group );
		}
		foreach ( $this->stored as $key => $value ) {
			false === $value ? delete_option( $key ) : update_option( $key, $value );
		}
		unset( $_POST['dws_lpmwc_group'] );
		wp_set_current_user( 0 );
	}

	public function test_the_strategy_loads_only_while_groups_is_active_and_its_switch_is_on(): void {
		self::assertTrue( GroupsStrategy::is_available() );

		delete_option( DWS_LPMWC_OPTION_UNLOCK_VIA_GROUPS );
		self::assertFalse( GroupsStrategy::should_load() );

		update_option( DWS_LPMWC_OPTION_UNLOCK_VIA_GROUPS, 'yes' );
		self::assertTrue( GroupsStrategy::should_load() );
	}

	public function test_the_settings_show_the_groups_switch_while_groups_is_active(): void {
		$fields = ( new \DeepWebSolutions\LockedPaymentMethods\Settings() )->get_section_fields( 'advanced', 'dws-locked-payment-methods' );
		$switch = \array_values( \array_filter( $fields, static fn ( array $field ): bool => DWS_LPMWC_OPTION_UNLOCK_VIA_GROUPS === ( $field['id'] ?? null ) ) );

		self::assertSame( 'checkbox', $switch[0]['type'] ?? null );
		self::assertSame( 'no', $switch[0]['default'] ?? null );
		self::assertContains( 'dws-wc-lpm_integrations', \array_column( $fields, 'id' ) );
	}

	public function test_a_member_of_a_group_or_of_its_subgroup_is_unlocked_for_the_groups_methods(): void {
		$parent = $this->create_group( 'LPM parent' );
		$child  = $this->create_group( 'LPM child', $parent );
		\Groups_User_Group::create(
			array(
				'user_id'  => self::$users['customer'],
				'group_id' => $child,
			)
		);
		update_option( DWS_LPMWC_OPTION_GROUPS_UNLOCKED_METHODS, array( $parent => array( 'cod' ) ) );

		self::assertSame( array( $parent => array( 'cod' ) ), dws_lpmwc_get_groups_unlocked_payment_method_ids() );
		self::assertFalse( self::should_lock( 'cod', self::$users['customer'] ) );
		self::assertTrue( self::should_lock( 'bacs', self::$users['customer'] ) );
		self::assertTrue( self::should_lock( 'cod', self::$users['subscriber'] ) );
		self::assertTrue( self::should_lock( 'cod', 0 ) );
	}

	public function test_the_group_forms_offer_the_locked_methods_to_a_user_with_both_capabilities(): void {
		$group = $this->create_group( 'LPM shown' );
		update_option( DWS_LPMWC_OPTION_GROUPS_UNLOCKED_METHODS, array( $group => array( 'cod' ) ) );
		wp_set_current_user( self::$users['administrator'] );

		$edit = self::parse( apply_filters( 'groups_admin_groups_edit_form_after_fields', '', $group ) );
		self::assertSame(
			array(
				'cod'  => true,
				'bacs' => false,
			),
			self::choices( $edit )
		);
		self::assertSame(
			array(
				'cod'  => false,
				'bacs' => false,
			),
			self::choices( self::parse( apply_filters( 'groups_admin_groups_add_form_after_fields', '' ) ) )
		);

		wp_set_current_user( self::$users['shop_manager'] );
		self::assertSame( '', apply_filters( 'groups_admin_groups_edit_form_after_fields', '', $group ) );
	}

	public function test_a_group_form_save_stores_the_checked_methods_under_the_group(): void {
		$group = $this->create_group( 'LPM saved' );
		wp_set_current_user( self::$users['administrator'] );

		$_POST['dws_lpmwc_group'] = array( 'dws_lpmwc_unlocked_payment_methods' => array( 'bacs' ) );
		do_action( 'groups_admin_groups_add_submit_success', $group );
		self::assertSame( array( $group => array( 'bacs' ) ), dws_lpmwc_get_groups_unlocked_payment_method_ids() );

		$_POST['dws_lpmwc_group'] = array( 'dws_lpmwc_unlocked_payment_methods' => array( 'paypal' ) );
		do_action( 'groups_admin_groups_edit_submit_success', $group );
		self::assertSame( array( $group => array( 'bacs' ) ), dws_lpmwc_get_groups_unlocked_payment_method_ids() );

		$_POST['dws_lpmwc_group'] = array( 'dws_lpmwc_unlocked_payment_methods' => '' );
		do_action( 'groups_admin_groups_edit_submit_success', $group );
		self::assertSame( array(), dws_lpmwc_get_groups_unlocked_payment_method_ids() );
	}

	public function test_a_save_without_the_capability_or_without_the_field_stores_nothing(): void {
		$group = $this->create_group( 'LPM refused' );

		wp_set_current_user( self::$users['shop_manager'] );
		$_POST['dws_lpmwc_group'] = array( 'dws_lpmwc_unlocked_payment_methods' => array( 'bacs' ) );
		do_action( 'groups_admin_groups_edit_submit_success', $group );
		self::assertSame( array(), dws_lpmwc_get_groups_unlocked_payment_method_ids() );

		wp_set_current_user( self::$users['administrator'] );
		unset( $_POST['dws_lpmwc_group'] );
		do_action( 'groups_admin_groups_edit_submit_success', $group );
		self::assertSame( array(), dws_lpmwc_get_groups_unlocked_payment_method_ids() );
	}

	public function test_deleting_a_group_forgets_its_methods(): void {
		$kept    = $this->create_group( 'LPM kept' );
		$deleted = $this->create_group( 'LPM deleted' );
		update_option(
			DWS_LPMWC_OPTION_GROUPS_UNLOCKED_METHODS,
			array(
				$kept    => array( 'cod' ),
				$deleted => array( 'bacs' ),
			)
		);

		\Groups_Group::delete( $deleted );

		self::assertSame( array( $kept => array( 'cod' ) ), dws_lpmwc_get_groups_unlocked_payment_method_ids() );
	}

	public function test_the_grants_step_moves_v1s_group_options_into_the_groups_map(): void {
		$moved  = $this->create_group( 'LPM moved' );
		$merged = $this->create_group( 'LPM merged' );
		update_option( "dws-wc-lpm_groups-settings_$moved", array( 'cod' ) );
		update_option( "dws-wc-lpm_groups-settings_$merged", array( 'cod', 7 ) );
		update_option( DWS_LPMWC_OPTION_GROUPS_UNLOCKED_METHODS, array( $merged => array( 'cheque' ) ) );

		dws_lpmwc_move_group_grants();
		dws_lpmwc_move_group_grants();

		self::assertSame(
			array(
				$merged => array( 'cheque', 'cod' ),
				$moved  => array( 'cod' ),
			),
			dws_lpmwc_get_groups_unlocked_payment_method_ids()
		);
		self::assertFalse( get_option( "dws-wc-lpm_groups-settings_$moved" ) );
		self::assertFalse( get_option( "dws-wc-lpm_groups-settings_$merged" ) );
	}

	protected function create_group( string $name, int $parent_id = 0 ): int {
		$group          = (int) \Groups_Group::create(
			array(
				'name'      => \uniqid( "$name " ),
				'parent_id' => 0 === $parent_id ? null : $parent_id,
			)
		);
		$this->groups[] = $group;

		return $group;
	}

	protected static function should_lock( string $gateway_id, int $user_id ): mixed {
		return apply_filters( 'dws_lpmwc/payment_method/should_lock', true, $gateway_id, $user_id, 0 );
	}

	protected static function parse( string $markup ): \Dom\HTMLDocument {
		return \Dom\HTMLDocument::createFromString( '<!DOCTYPE html><html><body>' . $markup . '</body></html>', \LIBXML_NOERROR );
	}

	protected static function choices( \Dom\HTMLDocument $form ): array {
		$choices = array();
		foreach ( $form->querySelectorAll( 'input[type="checkbox"][name="dws_lpmwc_group[dws_lpmwc_unlocked_payment_methods][]"]' ) as $input ) {
			$choices[ (string) $input->getAttribute( 'value' ) ] = $input->hasAttribute( 'checked' );
		}

		return $choices;
	}
}
