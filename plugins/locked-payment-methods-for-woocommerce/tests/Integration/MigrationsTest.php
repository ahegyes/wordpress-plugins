<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Integration;

use DeepWebSolutions\LockedPaymentMethods\Plugin;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\Migration\MigrationRunner;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class MigrationsTest extends TestCase {
	// v1.3.5's settings, as its free and premium builds stored them.
	protected const array V1_KEYS = array(
		'dws-wc-lpm_general_locked-payment-methods',
		'dws-wc-lpm_general_unlock-via-user-role',
		'dws-wc-lpm_general_full-access-user-roles',
		'dws-wc-lpm_general_unlock-via-user-meta',
		'dws-wc-lpm_general_unlock-via-order-meta',
		'dws-wc-lpm_integrations_unlock-via-groups',
		'dws-wc-lpm_integrations_unlock-via-wc-memberships',
		'dws-wc-lpm_plugin_remove-data-uninstall',
	);

	protected const array KEYS = array(
		DWS_LPMWC_OPTION_LOCKED_METHODS,
		DWS_LPMWC_OPTION_UNLOCK_VIA_USER_ROLES,
		DWS_LPMWC_OPTION_FULL_ACCESS_ROLES,
		DWS_LPMWC_OPTION_UNLOCK_VIA_USER_PROFILES,
		DWS_LPMWC_OPTION_UNLOCK_VIA_ORDERS,
		DWS_LPMWC_OPTION_UNLOCK_VIA_GROUPS,
		DWS_LPMWC_OPTION_UNLOCK_VIA_WC_MEMBERSHIPS,
		DWS_LPMWC_OPTION_DELETE_DATA,
	);

	protected array $stored = array();

	#[\Override]
	protected function setUp(): void {
		foreach ( array( ...self::KEYS, ...self::V1_KEYS ) as $key ) {
			$this->stored[ $key ] = get_option( $key );
		}
	}

	#[After]
	protected function restore_rows(): void {
		foreach ( $this->stored as $key => $value ) {
			false === $value ? delete_option( $key ) : update_option( $key, $value );
		}
		update_option( Plugin::PREFIX . '_revision', \count( dws_lpmwc_get_migration_steps() ) );
	}

	// The runner's time budget lasts for its request, and this process's bootstrap spends it when it migrates a new site, so the row runs as a request of its own.
	#[RunInSeparateProcess]
	public function test_the_runner_brings_a_new_site_to_the_latest_revision_with_every_default_autoloaded(): void {
		global $wpdb;

		foreach ( array( ...self::KEYS, ...self::V1_KEYS ) as $key ) {
			delete_option( $key );
		}
		update_option( Plugin::PREFIX . '_revision', 0 );

		dws_lpmwc_plugin()->migrations?->run();

		self::assertSame( \count( dws_lpmwc_get_migration_steps() ), MigrationRunner::get_revision( Plugin::PREFIX . '_revision' ) );
		self::assertSame(
			array(
				DWS_LPMWC_OPTION_LOCKED_METHODS            => array(),
				DWS_LPMWC_OPTION_UNLOCK_VIA_USER_ROLES     => 'yes',
				DWS_LPMWC_OPTION_FULL_ACCESS_ROLES         => array( 'administrator', 'shop_manager' ),
				DWS_LPMWC_OPTION_UNLOCK_VIA_USER_PROFILES  => 'yes',
				DWS_LPMWC_OPTION_UNLOCK_VIA_ORDERS         => 'no',
				DWS_LPMWC_OPTION_UNLOCK_VIA_GROUPS         => 'no',
				DWS_LPMWC_OPTION_UNLOCK_VIA_WC_MEMBERSHIPS => 'no',
				DWS_LPMWC_OPTION_DELETE_DATA               => 'no',
			),
			\array_combine( self::KEYS, \array_map( 'get_option', self::KEYS ) )
		);
		foreach ( self::KEYS as $key ) {
			self::assertSame( 'on', $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $key ) ), $key ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reads the stored autoload flag.
		}
	}

	public function test_the_step_copies_v1s_settings_and_leaves_v1s_rows_in_place(): void {
		$v1 = array(
			'dws-wc-lpm_general_locked-payment-methods' => array( 'cod', 'bacs' ),
			'dws-wc-lpm_general_unlock-via-user-role'   => 'no',
			'dws-wc-lpm_general_full-access-user-roles' => array( 'administrator' ),
			'dws-wc-lpm_general_unlock-via-user-meta'   => 'no',
			'dws-wc-lpm_general_unlock-via-order-meta'  => 'yes',
			'dws-wc-lpm_integrations_unlock-via-groups' => 'yes',
			'dws-wc-lpm_integrations_unlock-via-wc-memberships' => 'yes',
			'dws-wc-lpm_plugin_remove-data-uninstall'   => 'yes',
		);
		foreach ( self::KEYS as $key ) {
			delete_option( $key );
		}
		foreach ( $v1 as $key => $value ) {
			update_option( $key, $value );
		}

		dws_lpmwc_add_settings();

		self::assertSame(
			array(
				DWS_LPMWC_OPTION_LOCKED_METHODS            => array( 'cod', 'bacs' ),
				DWS_LPMWC_OPTION_UNLOCK_VIA_USER_ROLES     => 'no',
				DWS_LPMWC_OPTION_FULL_ACCESS_ROLES         => array( 'administrator' ),
				DWS_LPMWC_OPTION_UNLOCK_VIA_USER_PROFILES  => 'no',
				DWS_LPMWC_OPTION_UNLOCK_VIA_ORDERS         => 'yes',
				DWS_LPMWC_OPTION_UNLOCK_VIA_GROUPS         => 'yes',
				DWS_LPMWC_OPTION_UNLOCK_VIA_WC_MEMBERSHIPS => 'yes',
				DWS_LPMWC_OPTION_DELETE_DATA               => 'yes',
			),
			\array_combine( self::KEYS, \array_map( 'get_option', self::KEYS ) )
		);
		self::assertSame( $v1, \array_combine( \array_keys( $v1 ), \array_map( 'get_option', \array_keys( $v1 ) ) ) );
	}

	public function test_the_step_keeps_the_values_a_site_stored(): void {
		update_option( DWS_LPMWC_OPTION_DELETE_DATA, 'yes' );
		update_option( DWS_LPMWC_OPTION_LOCKED_METHODS, array() );
		update_option( 'dws-wc-lpm_plugin_remove-data-uninstall', 'no' );
		update_option( 'dws-wc-lpm_general_locked-payment-methods', array( 'cod' ) );

		dws_lpmwc_add_settings();

		self::assertSame( 'yes', get_option( DWS_LPMWC_OPTION_DELETE_DATA ) );
		self::assertSame( array(), get_option( DWS_LPMWC_OPTION_LOCKED_METHODS ) );
	}

	public function test_the_step_stores_the_default_for_a_v1_value_of_the_wrong_kind(): void {
		foreach ( self::KEYS as $key ) {
			delete_option( $key );
		}
		update_option( 'dws-wc-lpm_general_unlock-via-user-role', 'maybe' );
		update_option( 'dws-wc-lpm_general_locked-payment-methods', 'cod' );
		update_option( 'dws-wc-lpm_general_full-access-user-roles', array( 'editor', 7, array( 'nested' ) ) );

		dws_lpmwc_add_settings();

		self::assertSame( 'yes', get_option( DWS_LPMWC_OPTION_UNLOCK_VIA_USER_ROLES ) );
		self::assertSame( array(), get_option( DWS_LPMWC_OPTION_LOCKED_METHODS ) );
		self::assertSame( array( 'editor' ), get_option( DWS_LPMWC_OPTION_FULL_ACCESS_ROLES ) );
	}

	public function test_v1s_settings_map_onto_the_plugins_settings_one_to_one(): void {
		$v1_keys = array();
		foreach ( DWS_LPMWC_V1_SETTINGS as $group => $fields ) {
			foreach ( \array_keys( $fields ) as $id ) {
				$v1_keys[] = "dws-wc-lpm_{$group}_{$id}";
			}
		}

		self::assertEqualsCanonicalizing( self::V1_KEYS, $v1_keys );
		self::assertEqualsCanonicalizing( self::KEYS, \array_merge( ...\array_values( \array_map( 'array_values', DWS_LPMWC_V1_SETTINGS ) ) ) );
		self::assertEqualsCanonicalizing( self::KEYS, \array_keys( DWS_LPMWC_OPTION_DEFAULTS ) );
	}

	public function test_the_grants_step_moves_v1s_user_grants_into_the_sites_lists_and_deletes_every_v1_grant_row(): void {
		global $wpdb;

		$key   = $wpdb->get_blog_prefix() . DWS_LPMWC_USER_OPTION_UNLOCKED_METHODS;
		$users = self::create_users( 2 );
		update_user_meta( $users[0], 'dws_wc_lpm_grant_access_cod', 'yes' );
		update_user_meta( $users[0], 'dws_wc_lpm_grant_access_bacs', 'no' );
		update_user_meta( $users[1], 'dws_wc_lpm_grant_access_cod', 'yes' );
		update_user_meta( $users[1], $key, array( 'cheque' ) );

		try {
			dws_lpmwc_move_user_grants();
			dws_lpmwc_move_user_grants();
			wp_cache_flush();

			self::assertSame( array( 'cod' ), get_user_meta( $users[0], $key, true ) );
			self::assertSame( array( 'cheque', 'cod' ), get_user_meta( $users[1], $key, true ) );
			self::assertSame( '0', $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", 'dws\_wc\_lpm\_grant\_access\_%' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Counts the rows the step deletes.
		} finally {
			self::delete_users( $users );
		}
	}

	public function test_the_grants_step_throws_when_a_users_grants_cannot_be_stored(): void {
		$users = self::create_users( 1 );
		update_user_meta( $users[0], 'dws_wc_lpm_grant_access_cod', 'yes' );
		$fail = static fn ( mixed $check, int $object_id, string $meta_key ): mixed => \str_ends_with( $meta_key, DWS_LPMWC_USER_OPTION_UNLOCKED_METHODS ) ? false : $check;
		add_filter( 'update_user_metadata', $fail, 10, 3 );

		try {
			$this->expectExceptionObject( new \RuntimeException( "The payment methods unlocked for user {$users[0]} could not be stored." ) );
			dws_lpmwc_move_user_grants();
		} finally {
			remove_filter( 'update_user_metadata', $fail );
			self::assertSame( 'yes', get_user_meta( $users[0], 'dws_wc_lpm_grant_access_cod', true ) );
			self::delete_users( $users );
		}
	}

	public function test_the_step_throws_when_a_setting_cannot_be_stored(): void {
		global $wpdb;

		delete_option( DWS_LPMWC_OPTION_DELETE_DATA );
		$fail       = static fn ( string $query ): string => \str_contains( $query, "'" . DWS_LPMWC_OPTION_DELETE_DATA . "'" ) && \str_starts_with( \ltrim( $query ), 'INSERT' ) ? "SELECT * FROM {$wpdb->prefix}dws_lpmwc_missing" : $query;
		$suppressed = $wpdb->suppress_errors();
		add_filter( 'query', $fail );
		try {
			$this->expectExceptionObject( new \RuntimeException( 'The option ' . DWS_LPMWC_OPTION_DELETE_DATA . ' could not be stored.' ) );
			dws_lpmwc_add_settings();
		} finally {
			remove_filter( 'query', $fail );
			$wpdb->suppress_errors( $suppressed );
		}
	}

	protected static function create_users( int $count ): array {
		$users = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$users[] = (int) wp_insert_user(
				array(
					'user_login' => "lpm-migrations-$i",
					'user_pass'  => 'password',
					'user_email' => "lpm-migrations-$i@example.org",
				)
			);
		}

		return $users;
	}

	protected static function delete_users( array $users ): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( $users as $user ) {
			wp_delete_user( $user );
		}
	}
}
