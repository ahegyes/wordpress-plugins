<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Integration;

use DeepWebSolutions\LockedPaymentMethods\Plugin;
use DeepWebSolutions\LockedPaymentMethods\Tests\Support\RunsRequestsTrait;
use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class UninstallTest extends TestCase {
	use RunsRequestsTrait;

	protected const int USER = 1;

	#[AfterClass]
	public static function forget_the_rows_the_uninstall_deleted(): void {
		wp_cache_flush(); // The uninstall ran in a child process, so this process still caches the rows it deleted, such as a setting the tab saved.
	}

	#[RunInSeparateProcess]
	public function test_an_uninstall_checks_the_floors_of_its_own_main_file(): void {
		\define( 'WP_UNINSTALL_PLUGIN', 'another-plugin/another-plugin.php' ); // A bulk uninstall keeps the first plugin's value for every later one.
		$source = (string) \file_get_contents( DWS_LPMWC_FILE );
		\file_put_contents( DWS_LPMWC_FILE, (string) \preg_replace( '/^(\s*\*\s*Requires PHP:\s*).*$/m', '${1}99.0', $source ) );
		$log = (string) \tempnam( \sys_get_temp_dir(), 'dws-tpl-uninstall-' );
		\ini_set( 'error_log', $log ); // phpcs:ignore WordPress.PHP.IniSet.Risky -- The child process's stderr would fail the test.

		try {
			include \dirname( DWS_LPMWC_FILE ) . '/uninstall.php';
		} finally {
			\file_put_contents( DWS_LPMWC_FILE, $source );
		}

		self::assertStringContainsString( 'below its PHP or WordPress floor', (string) \file_get_contents( $log ) );
		\unlink( $log );
	}

	#[RunInSeparateProcess]
	public function test_a_consenting_uninstall_removes_a_row_of_every_kind_the_framework_roots_store(): void {
		$prefix = Plugin::PREFIX . '_';
		update_option( DWS_LPMWC_OPTION_DELETE_DATA, 'yes' );
		update_option( "{$prefix}notices", array( 'seeded' ) );
		update_network_option( null, "{$prefix}network_notices", array( 'seeded' ) );
		set_transient( "{$prefix}boot_failure_seeded", 1, HOUR_IN_SECONDS );
		update_user_option( self::USER, "{$prefix}hidden_notices", array( 'seeded' ) );
		update_user_meta( self::USER, "{$prefix}network_hidden_notices", array( 'seeded' ) );
		wp_schedule_single_event( \time() + HOUR_IN_SECONDS, "{$prefix}revision/migration/run" );
		as_schedule_single_action( \time() + HOUR_IN_SECONDS, "{$prefix}seeded", array(), Plugin::PREFIX );

		self::assertTrue( uninstall_plugin( plugin_basename( DWS_LPMWC_FILE ) ) );
		wp_cache_flush();

		self::assertFalse( get_option( DWS_LPMWC_OPTION_DELETE_DATA ) );
		self::assertFalse( get_option( "{$prefix}notices" ) );
		self::assertFalse( get_network_option( null, "{$prefix}network_notices" ) );
		self::assertFalse( get_transient( "{$prefix}boot_failure_seeded" ) );
		self::assertFalse( get_user_option( "{$prefix}hidden_notices", self::USER ) );
		self::assertSame( '', get_user_meta( self::USER, "{$prefix}network_hidden_notices", true ) );
		self::assertFalse( wp_next_scheduled( "{$prefix}revision/migration/run" ) );
		self::assertSame(
			array(),
			as_get_scheduled_actions(
				array(
					'group'  => Plugin::PREFIX,
					'status' => 'pending',
				),
				'ids'
			)
		);

		// The other tests share the database, so the runner stores again what a new site starts with.
		dws_lpmwc_plugin()->migrations?->run();
		self::assertSame( 'no', get_option( DWS_LPMWC_OPTION_DELETE_DATA ) );
	}

	#[RunInSeparateProcess]
	public function test_an_uninstall_in_a_request_that_never_loaded_the_plugin_removes_its_data(): void {
		update_option( DWS_LPMWC_OPTION_DELETE_DATA, 'yes' );
		update_option( DWS_LPMWC_OPTION_GROUPS_UNLOCKED_METHODS, array( 7 => array( 'cod' ) ) );

		try {
			// WordPress uninstalls a plugin in a request that never included it, as WP-CLI's --skip-plugins does here.
			$request = self::request( 'require_once ABSPATH . "wp-admin/includes/plugin.php"; echo wp_json_encode( array( uninstall_plugin( "' . plugin_basename( DWS_LPMWC_FILE ) . '" ), get_option( "' . DWS_LPMWC_OPTION_GROUPS_UNLOCKED_METHODS . '" ) ) );', '--skip-plugins=' . Plugin::SLUG );

			self::assertSame( '[true,false]', $request['last'], $request['output'] );
		} finally {
			wp_cache_flush();
			wp_roles()->for_site();
			// The other tests share the database, so the runner stores again what a new site starts with, and the grants act again.
			dws_lpmwc_plugin()->migrations?->run();
			do_action( 'wp_loaded' );
		}
	}

	#[RunInSeparateProcess]
	public function test_a_consenting_uninstall_removes_the_grants_v1s_rows_and_the_capabilities(): void {
		global $wpdb;

		update_option( DWS_LPMWC_OPTION_DELETE_DATA, 'yes' );
		update_option( DWS_LPMWC_OPTION_GROUPS_UNLOCKED_METHODS, array( 7 => array( 'cod' ) ) );
		foreach ( array( 'dws-wc-lpm_general_locked-payment-methods', 'locked_payment_methods_for_woocommerce_version', 'locked_payment_methods_for_woocommerce_original_version', '_locked_payment_methods_for_woocommerce_admin_notices' ) as $v1_option ) {
			update_option( $v1_option, 'seeded' );
		}
		update_user_meta( self::USER, $wpdb->get_blog_prefix() . DWS_LPMWC_USER_OPTION_UNLOCKED_METHODS, array( 'cod' ) );
		update_user_meta( self::USER, 'dws_wc_lpm_grant_access_cod', 'yes' );
		update_user_meta( self::USER, '_locked_payment_methods_for_woocommerce_admin_notices', array( 'seeded' ) );
		$order = wc_create_order();
		$order->update_meta_data( DWS_LPMWC_META_UNLOCKED_METHODS, array( 'cod' ) );
		$order->update_meta_data( '_dws_wc_lpm_grant_access_cod', 'yes' );
		$order->save();
		$plan = (int) wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'LPM plan',
				'post_status' => 'draft',
			)
		);
		update_post_meta( $plan, DWS_LPMWC_META_UNLOCKED_METHODS, array( 'cod' ) );
		update_post_meta( $plan, '_dws_wc_lpm_unlocked_payment_methods', array( 'cod' ) );

		try {
			self::assertTrue( get_role( 'administrator' )?->has_cap( DWS_LPMWC_CAP_UNLOCK_VIA_GROUPS ) );
			self::assertTrue( uninstall_plugin( plugin_basename( DWS_LPMWC_FILE ) ) );
			wp_cache_flush();
			wp_roles()->for_site();

			self::assertFalse( get_option( DWS_LPMWC_OPTION_GROUPS_UNLOCKED_METHODS ) );
			foreach ( array( 'dws-wc-lpm_general_locked-payment-methods', 'locked_payment_methods_for_woocommerce_version', 'locked_payment_methods_for_woocommerce_original_version', '_locked_payment_methods_for_woocommerce_admin_notices' ) as $v1_option ) {
				self::assertFalse( get_option( $v1_option ), $v1_option );
			}
			self::assertSame( '', get_user_meta( self::USER, $wpdb->get_blog_prefix() . DWS_LPMWC_USER_OPTION_UNLOCKED_METHODS, true ) );
			self::assertSame( '', get_user_meta( self::USER, 'dws_wc_lpm_grant_access_cod', true ) );
			self::assertSame( '', get_user_meta( self::USER, '_locked_payment_methods_for_woocommerce_admin_notices', true ) );
			self::assertSame( '', wc_get_order( $order->get_id() )?->get_meta( DWS_LPMWC_META_UNLOCKED_METHODS ) );
			self::assertSame( '', wc_get_order( $order->get_id() )?->get_meta( '_dws_wc_lpm_grant_access_cod' ) );
			self::assertSame( '', get_post_meta( $plan, DWS_LPMWC_META_UNLOCKED_METHODS, true ) );
			self::assertSame( '', get_post_meta( $plan, '_dws_wc_lpm_unlocked_payment_methods', true ) );
			self::assertFalse( get_role( 'administrator' )?->has_cap( DWS_LPMWC_CAP_UNLOCK_VIA_GROUPS ) );
		} finally {
			$order->delete( true );
			wp_delete_post( $plan, true );
			// The other tests share the database, so the runner stores again what a new site starts with, and the grants act again.
			dws_lpmwc_plugin()->migrations?->run();
			do_action( 'wp_loaded' );
		}
	}
}
