<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Integration\UnlockStrategies;

use DeepWebSolutions\LockedPaymentMethods\Migrations;
use DeepWebSolutions\LockedPaymentMethods\Options;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\InsertsUsersTrait;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\IsolatesStrategiesTrait;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\RecordsGrantUpdatesTrait;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\RestoresOptionsTrait;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies\UserProfileStrategy;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( UserProfileStrategy::class )]
final class UserProfileStrategyTest extends TestCase {
	use InsertsUsersTrait;
	use IsolatesStrategiesTrait;
	use RecordsGrantUpdatesTrait;
	use RestoresOptionsTrait;

	protected const string FORM = 'dws_lpmwc_user_profile';

	protected const array OPTIONS = array( Options::LOCKED_METHODS, Options::UNLOCK_VIA_USER_PROFILES, Options::FULL_ACCESS_ROLES );

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
		update_option( Options::LOCKED_METHODS, array( 'cod', 'bacs' ) );
		update_option( Options::FULL_ACCESS_ROLES, array( 'shop_manager' ) );
	}

	#[After]
	protected function clean_up(): void {
		wp_delete_user( self::$users['customer'] );
		foreach ( self::$users as $user ) {
			delete_user_meta( $user, self::key() );
			delete_user_meta( $user, 'dws_lpmwc_unlocked_payment_methods' );
		}
		delete_option( 'dws_lpmwc_notices' );
		unset( $_POST[ self::FORM ], $_POST[ self::FORM . '_nonce' ], $_GET['dws_lpmwc_unlocked_for_top'], $_GET['dws_lpmwc_unlocked_for_bottom'], $_GET['bottom'] );
		$GLOBALS['pagenow'] = 'index.php';
		unset( $GLOBALS['current_screen'] );
		wp_set_current_user( 0 );
	}

	public function test_the_strategy_loads_while_its_switch_is_on_as_v1s_did_by_default(): void {
		delete_option( Options::UNLOCK_VIA_USER_PROFILES );
		self::assertTrue( UserProfileStrategy::should_load() );

		update_option( Options::UNLOCK_VIA_USER_PROFILES, 'no' );
		self::assertFalse( UserProfileStrategy::should_load() );
	}

	public function test_a_user_is_unlocked_only_for_the_methods_their_site_grants_list_holds(): void {
		update_user_meta( self::$users['customer'], self::key(), array( 'cod' ) );
		update_user_meta( self::$users['editor'], 'dws_lpmwc_unlocked_payment_methods', array( 'cod' ) );

		self::assertSame( array( 'cod' ), self::strategy()->get_granted_ids( self::$users['customer'] ) );
		self::assertFalse( self::should_lock( 'cod', self::$users['customer'] ) );
		self::assertTrue( self::should_lock( 'bacs', self::$users['customer'] ) );
		self::assertTrue( self::should_lock( 'cod', self::$users['editor'] ) );
		self::assertTrue( self::should_lock( 'cod', 0 ) );
	}

	public function test_a_stored_grants_value_that_is_not_a_list_unlocks_nothing(): void {
		update_user_meta( self::$users['customer'], self::key(), 'cod' );

		self::assertSame( array(), self::strategy()->get_granted_ids( self::$users['customer'] ) );
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
		add_filter( 'locked_payment_methods_for_woocommerce/payment_method/locked_ids', $add );
		wp_set_current_user( self::$users['administrator'] );

		try {
			$form = self::render( self::$users['customer'] );
		} finally {
			remove_filter( 'locked_payment_methods_for_woocommerce/payment_method/locked_ids', $add );
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
		update_option( Options::LOCKED_METHODS, array() );

		self::assertNull( self::render( self::$users['customer'] )->querySelector( 'input' ) );
	}

	public function test_saving_the_profile_stores_the_checked_methods_in_the_sites_grants_list(): void {
		wp_set_current_user( self::$users['administrator'] );

		self::save( self::$users['customer'], array( 'bacs' ) );

		self::assertSame( array( 'bacs' ), get_user_meta( self::$users['customer'], self::key(), true ) );
		self::assertSame( '', get_user_meta( self::$users['customer'], 'dws_lpmwc_unlocked_payment_methods', true ) );
		self::assertSame( array( array( 'user_profile', self::$users['customer'], array( 'bacs' ), array() ) ), $this->updates );
	}

	public function test_a_field_another_plugin_adds_to_the_form_is_stored_beside_the_grants(): void {
		$add = static fn ( array $fields, int $object_id, string $form ): array => self::FORM === $form ? array(
			...$fields,
			array(
				'id'    => 'acme_note',
				'type'  => 'text',
				'title' => 'Note',
			),
		) : $fields;
		add_filter( 'locked_payment_methods_for_woocommerce/object_form/fields', $add, 10, 3 );
		update_user_meta( self::$users['customer'], 'acme_note', 'Pending' );
		wp_set_current_user( self::$users['administrator'] );

		try {
			self::assertSame( 'Pending', self::render( self::$users['customer'] )->querySelector( 'input[name="' . self::FORM . '[acme_note]"]' )?->getAttribute( 'value' ) );
			$_POST[ self::FORM ]            = array(
				'dws_lpmwc_unlocked_payment_methods' => array( 'cod' ),
				'acme_note'                          => 'Approved',
			);
			$_POST[ self::FORM . '_nonce' ] = wp_create_nonce( self::FORM . '_nonce_' . self::$users['customer'] );
			do_action( 'edit_user_profile_update', self::$users['customer'] );
			wp_cache_flush();

			self::assertSame( array( 'cod' ), get_user_meta( self::$users['customer'], self::key(), true ) );
			self::assertSame( 'Approved', get_user_meta( self::$users['customer'], 'acme_note', true ) );
		} finally {
			remove_filter( 'locked_payment_methods_for_woocommerce/object_form/fields', $add );
			delete_user_meta( self::$users['customer'], 'acme_note' );
		}
	}

	public function test_a_save_whose_added_field_the_host_store_refuses_leaves_the_grants_unchanged(): void {
		$add = static fn ( array $fields, int $object_id, string $form ): array => self::FORM === $form ? array(
			...$fields,
			array(
				'id'       => 'acme_note',
				'type'     => 'text',
				'title'    => 'Note',
				'validate' => static fn (): object => new \stdClass(),
			),
		) : $fields;
		add_filter( 'locked_payment_methods_for_woocommerce/object_form/fields', $add, 10, 3 );
		update_user_meta( self::$users['customer'], self::key(), array( 'bacs' ) );
		wp_set_current_user( self::$users['administrator'] );
		// The exception leaves WordPress marking the action as running, so the row puts the hook and the current-filter stack back.
		$current = $GLOBALS['wp_current_filter'];
		$hook    = clone $GLOBALS['wp_filter']['edit_user_profile_update'];

		try {
			$_POST[ self::FORM ]            = array(
				'dws_lpmwc_unlocked_payment_methods' => array( 'cod' ),
				'acme_note'                          => 'Approved',
			);
			$_POST[ self::FORM . '_nonce' ] = wp_create_nonce( self::FORM . '_nonce_' . self::$users['customer'] );
			$this->expectException( \InvalidArgumentException::class );
			do_action( 'edit_user_profile_update', self::$users['customer'] );
		} finally {
			$GLOBALS['wp_current_filter']                     = $current;
			$GLOBALS['wp_filter']['edit_user_profile_update'] = $hook;
			remove_filter( 'locked_payment_methods_for_woocommerce/object_form/fields', $add );
			wp_cache_flush();
			self::assertSame( array( 'bacs' ), get_user_meta( self::$users['customer'], self::key(), true ) );
			self::assertSame( array(), $this->updates );
		}
	}

	public function test_saving_the_profile_with_its_grants_in_another_order_changes_nothing(): void {
		update_user_meta( self::$users['customer'], self::key(), array( 'bacs', 'cod' ) );
		wp_set_current_user( self::$users['administrator'] );

		self::save( self::$users['customer'], array( 'cod', 'bacs' ) );

		self::assertSame( array( 'bacs', 'cod' ), get_user_meta( self::$users['customer'], self::key(), true ) );
		self::assertSame( array(), $this->updates );
	}

	public function test_a_stored_duplicate_reads_once_and_saving_the_profile_over_it_changes_nothing(): void {
		update_user_meta( self::$users['customer'], self::key(), array( 'cod', 'cod' ) );
		wp_set_current_user( self::$users['administrator'] );

		self::assertSame( array( 'cod' ), self::strategy()->get_granted_ids( self::$users['customer'] ) );
		self::save( self::$users['customer'], array( 'cod' ) );

		self::assertSame( array( 'cod', 'cod' ), get_user_meta( self::$users['customer'], self::key(), true ) );
		self::assertSame( array(), $this->updates );
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
		self::assertSame( array(), $this->updates );
	}

	public function test_the_strategy_grants_and_revokes_a_users_methods_and_announces_each_change(): void {
		$user = self::$users['customer'];

		self::assertTrue( self::strategy()->grant( $user, 'cod', 'bacs' ) );
		self::assertTrue( self::strategy()->grant( $user, 'cod' ) );
		self::assertTrue( self::strategy()->revoke( $user, 'bacs', 'cheque' ) );
		self::assertTrue( self::strategy()->revoke( $user, 'bacs' ) );

		self::assertSame( array( 'cod' ), self::strategy()->get_granted_ids( $user ) );
		self::assertSame( array( 'cod' ), get_user_meta( $user, self::key(), true ) );
		self::assertSame(
			array(
				array( 'user_profile', $user, array( 'cod', 'bacs' ), array() ),
				array( 'user_profile', $user, array( 'cod' ), array( 'cod', 'bacs' ) ),
			),
			$this->updates
		);
	}

	public function test_the_strategy_refuses_grants_for_a_user_that_does_not_exist(): void {
		$granted = self::strategy()->grant( 0, 'cod' );
		$revoked = self::strategy()->revoke( self::$users['customer'] + 1000, 'cod' );

		self::assertInstanceOf( \WP_Error::class, $granted );
		self::assertSame( 'dws_lpmwc_subject_not_found', $granted->get_error_code() );
		self::assertInstanceOf( \WP_Error::class, $revoked );
		self::assertSame( 'dws_lpmwc_subject_not_found', $revoked->get_error_code() );
		self::assertSame( array(), $this->updates );
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
		self::assertSame( array( 'username', 'role', 'dws_lpm_unlocked_for', 'posts' ), \array_keys( $columns ) );
		self::assertSame( 'Unlocked for', $columns['dws_lpm_unlocked_for'] );

		self::assertSame( 'Cash on delivery: unlocked by User profiles<br />Direct bank transfer: locked', apply_filters( 'manage_users_custom_column', '', 'dws_lpm_unlocked_for', self::$users['customer'] ) );
		self::assertSame( 'Cash on delivery: unlocked by User roles<br />Direct bank transfer: unlocked by User roles', apply_filters( 'manage_users_custom_column', '', 'dws_lpm_unlocked_for', self::$users['shop_manager'] ) );
		self::assertSame( 'other', apply_filters( 'manage_users_custom_column', 'other', 'posts', self::$users['customer'] ) );
	}

	public function test_the_users_screen_renders_the_cell_after_a_callback_that_echoes_its_own_and_returns_nothing(): void {
		update_user_meta( self::$users['customer'], self::key(), array( 'cod' ) );
		$echoes = static function (): void {};
		add_filter( 'manage_users_custom_column', $echoes, 9 );

		try {
			self::assertSame( 'Cash on delivery: unlocked by User profiles<br />Direct bank transfer: locked', apply_filters( 'manage_users_custom_column', '', 'dws_lpm_unlocked_for', self::$users['customer'] ) );
			self::assertNull( apply_filters( 'manage_users_custom_column', '', 'posts', self::$users['customer'] ) );
		} finally {
			remove_filter( 'manage_users_custom_column', $echoes, 9 );
		}
	}

	public function test_the_users_screen_names_no_rule_for_a_method_a_filter_unlocks(): void {
		$editor = self::$users['editor'];
		$unlock = static fn ( mixed $lock, string $gateway_id, int $user_id ): mixed => $editor === $user_id && 'bacs' === $gateway_id ? false : $lock;
		add_filter( 'locked_payment_methods_for_woocommerce/payment_method/should_lock', $unlock, 20, 3 );

		try {
			self::assertSame( 'Cash on delivery: locked<br />Direct bank transfer: unlocked by another rule', apply_filters( 'manage_users_custom_column', '', 'dws_lpm_unlocked_for', $editor ) );
		} finally {
			remove_filter( 'locked_payment_methods_for_woocommerce/payment_method/should_lock', $unlock, 20 );
		}
	}

	public function test_the_users_screen_filters_by_a_method_granted_on_the_profile_from_either_dropdown(): void {
		update_user_meta( self::$users['customer'], self::key(), array( 'cod' ) );
		update_user_meta( self::$users['editor'], self::key(), array( 'bacs' ) );
		$GLOBALS['pagenow'] = 'users.php';
		set_current_screen( 'users' );

		\ob_start();
		do_action( 'restrict_manage_users', 'top' );
		$select = self::parse( (string) \ob_get_clean() );
		self::assertSame( array( '', 'cod', 'bacs' ), \array_map( static fn ( \Dom\Element $option ): string => (string) $option->getAttribute( 'value' ), \iterator_to_array( $select->querySelectorAll( 'select[name="dws_lpmwc_unlocked_for_top"] option' ) ) ) );
		self::assertNotNull( $select->querySelector( 'span > label[for="dws_lpmwc_unlocked_for_top"] + select + input[type="submit"]' ), 'The label, the select and the button share one span, so core\'s floats never split them.' );

		$_GET['dws_lpmwc_unlocked_for_top'] = 'cod';
		self::assertSame( array( self::$users['customer'] ), self::listed_users() );

		$_GET['dws_lpmwc_unlocked_for_bottom'] = 'bacs';
		$_GET['bottom']                        = 'Filter';
		self::assertSame( array( self::$users['editor'] ), self::listed_users() );
	}

	public function test_the_users_screen_filter_narrows_a_list_another_plugin_widened_with_or(): void {
		update_user_meta( self::$users['customer'], self::key(), array( 'cod' ) );
		$GLOBALS['pagenow']                 = 'users.php';
		$_GET['dws_lpmwc_unlocked_for_top'] = 'cod';
		set_current_screen( 'users' );
		$args = self::query_args() + array(
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Another plugin's list query, which the filter narrows.
				'relation' => 'OR',
				array(
					'key'     => 'acme_vip',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => 'acme_vip',
					'compare' => 'NOT EXISTS',
				),
			),
		);

		self::assertSame( array( self::$users['customer'] ), self::queried_users( apply_filters( 'users_list_table_query_args', $args ) ) );
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
		return self::insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => 'password',
				'user_email' => "$login@example.org",
				'role'       => $role,
			)
		);
	}

	public function test_the_grants_step_moves_v1s_user_grants_into_the_sites_lists_and_deletes_every_v1_grant_row(): void {
		global $wpdb;

		$moved  = self::$users['customer'];
		$merged = self::$users['editor'];
		update_user_meta( $moved, 'dws_wc_lpm_grant_access_cod', 'yes' );
		update_user_meta( $moved, 'dws_wc_lpm_grant_access_bacs', 'no' );
		update_user_meta( $merged, 'dws_wc_lpm_grant_access_cod', 'yes' );
		update_user_meta( $merged, self::key(), array( 'cheque' ) );

		Migrations::get_steps()[2]();
		Migrations::get_steps()[2]();
		wp_cache_flush();

		self::assertSame( array( 'cod' ), get_user_meta( $moved, self::key(), true ) );
		self::assertSame( array( 'cheque', 'cod' ), get_user_meta( $merged, self::key(), true ) );
		self::assertSame( '0', $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", 'dws\_wc\_lpm\_grant\_access\_%' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Counts the rows the step deletes.
	}

	public function test_the_grants_step_throws_when_a_users_grants_cannot_be_stored(): void {
		$user = self::$users['customer'];
		update_user_meta( $user, 'dws_wc_lpm_grant_access_cod', 'yes' );
		$fail = static fn ( mixed $check, int $object_id, string $meta_key ): mixed => \str_ends_with( $meta_key, 'dws_lpmwc_unlocked_payment_methods' ) ? false : $check;
		add_filter( 'update_user_metadata', $fail, 10, 3 );

		try {
			$this->expectExceptionObject( new \RuntimeException( "The payment methods unlocked for user $user could not be stored." ) );
			Migrations::get_steps()[2]();
		} finally {
			remove_filter( 'update_user_metadata', $fail );
			self::assertSame( 'yes', get_user_meta( $user, 'dws_wc_lpm_grant_access_cod', true ) );
			delete_user_meta( $user, 'dws_wc_lpm_grant_access_cod' );
		}
	}

	protected static function strategy(): \DWS_LPMWC_Grant_Strategy {
		$strategy = dws_lpmwc_get_unlock_strategy( UserProfileStrategy::ID );
		self::assertInstanceOf( \DWS_LPMWC_Grant_Strategy::class, $strategy );
		return $strategy;
	}

	protected static function key(): string {
		global $wpdb;
		return $wpdb->get_blog_prefix() . 'dws_lpmwc_unlocked_payment_methods';
	}


	protected static function render( int $user_id ): \Dom\HTMLDocument {
		\ob_start();
		do_action( 'edit_user_profile', get_userdata( $user_id ) );
		return self::parse( (string) \ob_get_clean() );
	}

	protected static function choices( \Dom\HTMLDocument $form ): array {
		$choices = array();
		foreach ( $form->querySelectorAll( 'input[type="checkbox"]' ) as $input ) {
			$choices[ (string) $input->getAttribute( 'value' ) ] = array( \trim( (string) $input->parentElement?->textContent ), $input->hasAttribute( 'checked' ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM properties.
		}

		return $choices;
	}

	protected static function save( int $user_id, array|string $methods ): void {
		$_POST[ self::FORM ]            = array( 'dws_lpmwc_unlocked_payment_methods' => $methods );
		$_POST[ self::FORM . '_nonce' ] = wp_create_nonce( self::FORM . "_nonce_$user_id" );
		do_action( 'edit_user_profile_update', $user_id );
		wp_cache_flush();
	}

	protected static function listed_users(): array {
		return self::queried_users( apply_filters( 'users_list_table_query_args', self::query_args() ) );
	}

	protected static function queried_users( ?array $args = null ): array {
		return \array_map( 'intval', new \WP_User_Query( $args ?? self::query_args() )->get_results() );
	}

	protected static function query_args(): array {
		return array(
			'include' => \array_values( self::$users ),
			'fields'  => 'ID',
			'orderby' => 'ID',
		);
	}
}
