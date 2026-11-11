<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Integration\UnlockStrategies;

use DeepWebSolutions\LockedPaymentMethods\Plugin;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\RestoresOptionsTrait;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies\UserProfileStrategy;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class UserProfileStrategyTest extends TestCase {
	use RestoresOptionsTrait;

	protected const string FORM = 'dws_lpmwc_user_profile';

	protected const array OPTIONS = array( DWS_LPMWC_OPTION_LOCKED_METHODS, DWS_LPMWC_OPTION_UNLOCK_VIA_USER_PROFILES, DWS_LPMWC_OPTION_FULL_ACCESS_ROLES );

	protected static array $users = array();

	#[\Override]
	public static function setUpBeforeClass(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		foreach ( array( 'administrator', 'shop_manager', 'editor' ) as $role ) {
			self::$users[ $role ] = self::create_user( $role );
		}
		get_userdata( self::$users['editor'] )?->add_cap( 'edit_users' );
	}

	#[\Override]
	public static function tearDownAfterClass(): void {
		foreach ( self::$users as $user ) {
			wp_delete_user( $user );
		}
	}

	// The form evaluates its definition and saves an object once per request, so every row edits a customer of its own.
	#[Before]
	protected function create_the_customer(): void {
		self::$users['customer'] = self::create_user( 'customer' );
		update_option( DWS_LPMWC_OPTION_LOCKED_METHODS, array( 'cod', 'bacs' ) );
		update_option( DWS_LPMWC_OPTION_FULL_ACCESS_ROLES, array( 'shop_manager' ) );
	}

	#[After]
	protected function clean_up(): void {
		wp_delete_user( self::$users['customer'] );
		foreach ( self::$users as $user ) {
			delete_user_meta( $user, self::key() );
			delete_user_meta( $user, DWS_LPMWC_USER_OPTION_UNLOCKED_METHODS );
		}
		delete_option( 'dws_lpmwc_notices' );
		unset( $_POST[ self::FORM ], $_POST[ self::FORM . '_nonce' ], $_GET['dws_lpmwc_unlocked_for_top'], $_GET['dws_lpmwc_unlocked_for_bottom'], $_GET['bottom'] );
		$GLOBALS['pagenow'] = 'index.php';
		unset( $GLOBALS['current_screen'] );
		wp_set_current_user( 0 );
	}

	public function test_the_strategy_loads_while_its_switch_is_on_as_v1s_did_by_default(): void {
		delete_option( DWS_LPMWC_OPTION_UNLOCK_VIA_USER_PROFILES );
		self::assertTrue( UserProfileStrategy::should_load() );

		update_option( DWS_LPMWC_OPTION_UNLOCK_VIA_USER_PROFILES, 'no' );
		self::assertFalse( UserProfileStrategy::should_load() );
	}

	public function test_the_booted_plugin_runs_the_strategy(): void {
		self::assertContains( UserProfileStrategy::class, Plugin::COMPONENTS );
		self::assertNotContains( UserProfileStrategy::class, dws_lpmwc_plugin()->kernel?->skipped ?? array( UserProfileStrategy::class ) );
	}

	public function test_a_user_is_unlocked_only_for_the_methods_their_site_grants_list_holds(): void {
		update_user_meta( self::$users['customer'], self::key(), array( 'cod' ) );
		update_user_meta( self::$users['editor'], DWS_LPMWC_USER_OPTION_UNLOCKED_METHODS, array( 'cod' ) );

		self::assertSame( array( 'cod' ), dws_lpmwc_get_user_unlocked_payment_method_ids( self::$users['customer'] ) );
		self::assertFalse( self::should_lock( 'cod', self::$users['customer'] ) );
		self::assertTrue( self::should_lock( 'bacs', self::$users['customer'] ) );
		self::assertTrue( self::should_lock( 'cod', self::$users['editor'] ) );
		self::assertTrue( self::should_lock( 'cod', 0 ) );
	}

	public function test_a_stored_grants_value_that_is_not_a_list_unlocks_nothing(): void {
		update_user_meta( self::$users['customer'], self::key(), 'cod' );

		self::assertSame( array(), dws_lpmwc_get_user_unlocked_payment_method_ids( self::$users['customer'] ) );
	}

	public function test_at_checkout_a_customer_may_use_the_methods_granted_to_them(): void {
		update_user_meta( self::$users['customer'], self::key(), array( 'cod' ) );
		wp_set_current_user( self::$users['customer'] );

		$available = apply_filters( 'woocommerce_available_payment_gateways', WC()->payment_gateways()->payment_gateways() );

		self::assertArrayHasKey( 'cod', $available );
		self::assertArrayNotHasKey( 'bacs', $available );
	}

	public function test_the_profile_offers_the_locked_methods_and_the_users_other_grants_and_marks_each_one(): void {
		update_user_meta( self::$users['customer'], self::key(), array( 'cod', 'cheque' ) );
		wp_set_current_user( self::$users['administrator'] );

		$form = self::render( self::$users['customer'] );

		self::assertSame(
			array(
				'cod'    => array( 'Cash on delivery', true ),
				'bacs'   => array( 'Direct bank transfer', false ),
				'cheque' => array( 'Check payments (not locked)', true ),
			),
			self::choices( $form )
		);
		self::assertSame( 'Locked Payment Methods', \trim( (string) $form->querySelector( 'h2' )?->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
	}

	public function test_the_profile_offers_the_methods_the_locked_ids_filter_adds_as_locked(): void {
		$add = static fn ( array $ids ): array => array( ...$ids, 'cheque' );
		add_filter( 'dws_lpmwc/payment_method/locked_ids', $add );
		wp_set_current_user( self::$users['administrator'] );

		try {
			$form = self::render( self::$users['customer'] );
		} finally {
			remove_filter( 'dws_lpmwc/payment_method/locked_ids', $add );
		}

		self::assertSame(
			array(
				'cod'    => array( 'Cash on delivery', false ),
				'bacs'   => array( 'Direct bank transfer', false ),
				'cheque' => array( 'Check payments', false ),
			),
			self::choices( $form )
		);
	}

	public function test_the_profile_marks_the_methods_another_rule_already_unlocks_for_the_user(): void {
		wp_set_current_user( self::$users['administrator'] );

		self::assertSame(
			array(
				'cod'  => array( 'Cash on delivery (unlocked by another rule)', false ),
				'bacs' => array( 'Direct bank transfer (unlocked by another rule)', false ),
			),
			self::choices( self::render( self::$users['shop_manager'] ) )
		);
	}

	public function test_the_profile_shows_nothing_to_a_user_without_the_capability(): void {
		wp_set_current_user( self::$users['editor'] );

		self::assertNull( self::render( self::$users['customer'] )->querySelector( 'input' ) );
	}

	public function test_the_profile_shows_nothing_while_there_is_nothing_to_offer(): void {
		wp_set_current_user( self::$users['administrator'] );
		update_option( DWS_LPMWC_OPTION_LOCKED_METHODS, array() );

		self::assertNull( self::render( self::$users['customer'] )->querySelector( 'input' ) );
	}

	public function test_saving_the_profile_stores_the_checked_methods_in_the_sites_grants_list(): void {
		wp_set_current_user( self::$users['administrator'] );

		self::save( self::$users['customer'], array( 'bacs' ) );

		self::assertSame( array( 'bacs' ), get_user_meta( self::$users['customer'], self::key(), true ) );
		self::assertSame( '', get_user_meta( self::$users['customer'], DWS_LPMWC_USER_OPTION_UNLOCKED_METHODS, true ) );
	}

	public function test_saving_the_profile_with_nothing_checked_stores_an_empty_list(): void {
		update_user_meta( self::$users['customer'], self::key(), array( 'cod' ) );
		wp_set_current_user( self::$users['administrator'] );

		self::save( self::$users['customer'], '' );

		self::assertSame( array(), get_user_meta( self::$users['customer'], self::key(), true ) );
	}

	public function test_a_save_naming_a_method_the_profile_does_not_offer_keeps_the_stored_grants(): void {
		update_user_meta( self::$users['customer'], self::key(), array( 'cod' ) );
		wp_set_current_user( self::$users['administrator'] );

		self::save( self::$users['customer'], array( 'bacs', 'paypal' ) );

		self::assertSame( array( 'cod' ), get_user_meta( self::$users['customer'], self::key(), true ) );
	}

	public function test_a_save_without_the_capability_stores_nothing(): void {
		wp_set_current_user( self::$users['editor'] );

		self::save( self::$users['customer'], array( 'bacs' ) );

		self::assertSame( '', get_user_meta( self::$users['customer'], self::key(), true ) );
	}

	public function test_the_users_screen_lists_each_users_locked_methods_with_their_state_in_text(): void {
		update_user_meta( self::$users['customer'], self::key(), array( 'cod' ) );

		$columns = apply_filters(
			'manage_users_columns',
			array(
				'username' => 'Username',
				'role'     => 'Role',
				'posts'    => 'Posts',
			)
		);
		self::assertSame( array( 'username', 'role', 'dws_lpmwc_unlocked_for', 'posts' ), \array_keys( $columns ) );
		self::assertSame( 'Unlocked for', $columns['dws_lpmwc_unlocked_for'] );

		self::assertSame( 'Cash on delivery: unlocked on the profile<br />Direct bank transfer: locked', apply_filters( 'manage_users_custom_column', '', 'dws_lpmwc_unlocked_for', self::$users['customer'] ) );
		self::assertSame( 'Cash on delivery: unlocked by another rule<br />Direct bank transfer: unlocked by another rule', apply_filters( 'manage_users_custom_column', '', 'dws_lpmwc_unlocked_for', self::$users['shop_manager'] ) );
		self::assertSame( 'other', apply_filters( 'manage_users_custom_column', 'other', 'posts', self::$users['customer'] ) );
	}

	public function test_the_users_screen_filters_by_a_method_granted_on_the_profile_from_either_dropdown(): void {
		update_user_meta( self::$users['customer'], self::key(), array( 'cod' ) );
		update_user_meta( self::$users['editor'], self::key(), array( 'bacs' ) );
		$GLOBALS['pagenow'] = 'users.php';
		set_current_screen( 'users' );

		\ob_start();
		do_action( 'restrict_manage_users', 'top' );
		$select = \Dom\HTMLDocument::createFromString( '<!DOCTYPE html><html><body>' . \ob_get_clean() . '</body></html>', \LIBXML_NOERROR );
		self::assertSame( array( '', 'cod', 'bacs' ), \array_map( static fn ( \Dom\Element $option ): string => (string) $option->getAttribute( 'value' ), \iterator_to_array( $select->querySelectorAll( 'select[name="dws_lpmwc_unlocked_for_top"] option' ) ) ) );

		$_GET['dws_lpmwc_unlocked_for_top'] = 'cod';
		self::assertSame( array( self::$users['customer'] ), self::listed_users() );

		$_GET['dws_lpmwc_unlocked_for_bottom'] = 'bacs';
		$_GET['bottom']                        = 'Filter';
		self::assertSame( array( self::$users['editor'] ), self::listed_users() );
	}

	public function test_the_users_screen_filter_leaves_other_user_queries_alone(): void {
		update_user_meta( self::$users['customer'], self::key(), array( 'cod' ) );
		$GLOBALS['pagenow']                 = 'users.php';
		$_GET['dws_lpmwc_unlocked_for_top'] = 'cod';
		set_current_screen( 'users' );

		self::assertSame( \array_values( self::$users ), self::queried_users() );
	}

	protected static function create_user( string $role ): int {
		$login = \uniqid( "lpm-profiles-$role-" );

		return (int) wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => 'password',
				'user_email' => "$login@example.org",
				'role'       => $role,
			)
		);
	}

	protected static function key(): string {
		global $wpdb;

		return $wpdb->get_blog_prefix() . DWS_LPMWC_USER_OPTION_UNLOCKED_METHODS;
	}

	protected static function should_lock( string $gateway_id, int $user_id ): mixed {
		return apply_filters( 'dws_lpmwc/payment_method/should_lock', true, $gateway_id, $user_id, 0 );
	}

	protected static function render( int $user_id ): \Dom\HTMLDocument {
		\ob_start();
		do_action( 'edit_user_profile', get_userdata( $user_id ) );

		return \Dom\HTMLDocument::createFromString( '<!DOCTYPE html><html><body>' . \ob_get_clean() . '</body></html>', \LIBXML_NOERROR );
	}

	protected static function choices( \Dom\HTMLDocument $form ): array {
		$choices = array();
		foreach ( $form->querySelectorAll( 'input[type="checkbox"]' ) as $input ) {
			$choices[ (string) $input->getAttribute( 'value' ) ] = array( \trim( (string) $input->parentElement?->textContent ), $input->hasAttribute( 'checked' ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM properties.
		}

		return $choices;
	}

	protected static function save( int $user_id, array|string $methods ): void {
		$_POST[ self::FORM ]            = array( self::key() => $methods );
		$_POST[ self::FORM . '_nonce' ] = wp_create_nonce( self::FORM . "_nonce_$user_id" );
		do_action( 'edit_user_profile_update', $user_id );
		wp_cache_flush();
	}

	protected static function listed_users(): array {
		return self::queried_users( apply_filters( 'users_list_table_query_args', self::query_args() ) );
	}

	protected static function queried_users( ?array $args = null ): array {
		return \array_map( 'intval', ( new \WP_User_Query( $args ?? self::query_args() ) )->get_results() );
	}

	protected static function query_args(): array {
		return array(
			'include' => \array_values( self::$users ),
			'fields'  => 'ID',
			'orderby' => 'ID',
		);
	}
}
