<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods\Tests\Integration;

use DeepWebSolutions\LockedPaymentMethods\Capabilities;
use DeepWebSolutions\LockedPaymentMethods\Options;
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
		wp_cache_flush(); // The uninstall ran in a child process, so this process still caches the rows it deleted, such as a setting the section saved.
	}

	#[RunInSeparateProcess]
	public function test_an_uninstall_checks_the_floors_of_its_own_main_file(): void {
		\define( 'WP_UNINSTALL_PLUGIN', 'another-plugin/another-plugin.php' ); // A bulk uninstall keeps the first plugin's value for every later one.
		$source = (string) \file_get_contents( DWS_LPMWC_FILE );
		\file_put_contents( DWS_LPMWC_FILE, (string) \preg_replace( '/^(\s*\*\s*Requires PHP:\s*).*$/m', '${1}99.0', $source ) );
		$log = (string) \tempnam( \sys_get_temp_dir(), 'dws-lpmwc-uninstall-' );
		\ini_set( 'error_log', $log );

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
		update_option( Options::DELETE_DATA, 'yes' );
		update_option( "{$prefix}notices", array( 'seeded' ) );
		update_network_option( null, "{$prefix}network_notices", array( 'seeded' ) );
		set_transient( "{$prefix}boot_failure_seeded", 1, HOUR_IN_SECONDS );
		update_user_option( self::USER, "{$prefix}hidden_notices", array( 'seeded' ) );
		update_user_meta( self::USER, "{$prefix}network_hidden_notices", array( 'seeded' ) );
		wp_schedule_single_event( \time() + HOUR_IN_SECONDS, 'locked_payment_methods_for_woocommerce/migration/run' );
		as_schedule_single_action( \time() + HOUR_IN_SECONDS, "{$prefix}seeded", array(), Plugin::PREFIX );

		self::assertTrue( uninstall_plugin( plugin_basename( DWS_LPMWC_FILE ) ) );
		wp_cache_flush();

		self::assertFalse( get_option( Options::DELETE_DATA ) );
		self::assertFalse( get_option( "{$prefix}notices" ) );
		self::assertFalse( get_network_option( null, "{$prefix}network_notices" ) );
		self::assertFalse( get_transient( "{$prefix}boot_failure_seeded" ) );
		self::assertFalse( get_user_option( "{$prefix}hidden_notices", self::USER ) );
		self::assertSame( '', get_user_meta( self::USER, "{$prefix}network_hidden_notices", true ) );
		self::assertFalse( wp_next_scheduled( 'locked_payment_methods_for_woocommerce/migration/run' ) );
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
		self::assertSame( 'no', get_option( Options::DELETE_DATA ) );
	}

	#[RunInSeparateProcess]
	public function test_an_uninstall_in_a_request_that_never_loaded_the_plugin_removes_its_data(): void {
		update_option( Options::DELETE_DATA, 'yes' );
		update_option( 'dws_lpmwc_group_7_unlocked_payment_methods', array( 'cod' ) );

		try {
			// WordPress uninstalls a plugin in a request that never included it, as WP-CLI's --skip-plugins does here.
			$request = self::request( 'require_once ABSPATH . "wp-admin/includes/plugin.php"; echo wp_json_encode( array( uninstall_plugin( "' . plugin_basename( DWS_LPMWC_FILE ) . '" ), get_option( "dws_lpmwc_group_7_unlocked_payment_methods" ) ) );', '--skip-plugins=' . Plugin::SLUG );

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
	public function test_a_web_uninstall_whose_engine_fails_ends_with_its_message_and_keeps_the_consent(): void {
		global $wpdb;

		\define( 'WP_UNINSTALL_PLUGIN', plugin_basename( DWS_LPMWC_FILE ) );
		update_option( Options::DELETE_DATA, 'yes' );
		$failing = "DELETE FROM `$wpdb->options` WHERE `option_name` = '" . Plugin::PREFIX . "_revision'";
		add_filter( 'query', static fn ( string $query ): string => \str_contains( $query, $failing ) ? 'SELECT * FROM dws_lpmwc_missing_table' : $query );
		add_filter( 'wp_die_handler', static fn (): \Closure => static fn ( string $message, string $title, array $args ): never => throw new \LogicException( $args['response'] . ' ' . $message ) );
		$wpdb->suppress_errors();

		try {
			include \dirname( DWS_LPMWC_FILE ) . '/uninstall.php';
			self::fail( 'The uninstall finished.' );
		} catch ( \LogicException $died ) {
			self::assertStringStartsWith( '500 Uninstall could not remove everything: option ' . Plugin::PREFIX . '_revision', $died->getMessage() );
			self::assertSame( 'yes', get_option( Options::DELETE_DATA ) );
		} finally {
			remove_all_filters( 'query' );
			delete_option( Options::DELETE_DATA );
			delete_option( Plugin::PREFIX . '_revision' );
			wp_cache_flush();
			wp_roles()->for_site();
			// The other tests share the database, so the runner stores again what a new site starts with, and the grants act again.
			dws_lpmwc_plugin()->migrations?->run();
			do_action( 'wp_loaded' );
		}
	}

	#[RunInSeparateProcess]
	public function test_a_consenting_uninstall_removes_the_grants_on_both_order_storages_v1s_rows_and_the_capabilities(): void {
		global $wpdb;

		update_option( Options::DELETE_DATA, 'yes' );
		update_option( 'dws_lpmwc_group_7_unlocked_payment_methods', array( 'cod' ) );
		foreach ( array( 'dws-wc-lpm_general_locked-payment-methods', 'locked_payment_methods_for_woocommerce_version', 'locked_payment_methods_for_woocommerce_original_version', '_locked_payment_methods_for_woocommerce_admin_notices' ) as $v1_option ) {
			update_option( $v1_option, 'seeded' );
		}
		update_user_meta( self::USER, $wpdb->get_blog_prefix() . 'dws_lpmwc_unlocked_payment_methods', array( 'cod' ) );
		update_user_meta( self::USER, 'dws_wc_lpm_grant_access_cod', 'yes' );
		update_user_meta( self::USER, '_locked_payment_methods_for_woocommerce_admin_notices', array( 'seeded' ) );
		$orders = array();
		foreach ( array( 'yes', 'no' ) as $hpos ) {
			$storage = static fn (): string => $hpos;
			add_filter( 'pre_option_woocommerce_custom_orders_table_enabled', $storage );
			add_filter( 'pre_option_woocommerce_custom_orders_table_data_sync_enabled', '__return_empty_string' );
			$order = wc_create_order();
			$order->update_meta_data( '_dws_lpmwc_unlocked_payment_methods', array( 'cod' ) );
			$order->update_meta_data( '_dws_wc_lpm_grant_access_cod', 'yes' );
			$order->save();
			$orders[ $order->get_id() ] = $hpos;
			remove_filter( 'pre_option_woocommerce_custom_orders_table_enabled', $storage );
			remove_filter( 'pre_option_woocommerce_custom_orders_table_data_sync_enabled', '__return_empty_string' );
		}
		$plan = (int) wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'LPM plan',
				'post_status' => 'draft',
			)
		);
		update_post_meta( $plan, '_dws_lpmwc_unlocked_payment_methods', array( 'cod' ) );
		update_post_meta( $plan, '_dws_wc_lpm_unlocked_payment_methods', array( 'cod' ) );

		try {
			self::assertTrue( get_role( 'administrator' )?->has_cap( Capabilities::UNLOCK_VIA_GROUPS ) );
			self::assertTrue( uninstall_plugin( plugin_basename( DWS_LPMWC_FILE ) ) );
			wp_cache_flush();
			wp_roles()->for_site();

			self::assertFalse( get_option( 'dws_lpmwc_group_7_unlocked_payment_methods' ) );
			foreach ( array( 'dws-wc-lpm_general_locked-payment-methods', 'locked_payment_methods_for_woocommerce_version', 'locked_payment_methods_for_woocommerce_original_version', '_locked_payment_methods_for_woocommerce_admin_notices' ) as $v1_option ) {
				self::assertFalse( get_option( $v1_option ), $v1_option );
			}
			self::assertSame( '', get_user_meta( self::USER, $wpdb->get_blog_prefix() . 'dws_lpmwc_unlocked_payment_methods', true ) );
			self::assertSame( '', get_user_meta( self::USER, 'dws_wc_lpm_grant_access_cod', true ) );
			self::assertSame( '', get_user_meta( self::USER, '_locked_payment_methods_for_woocommerce_admin_notices', true ) );
			$ids = \implode( ',', \array_map( 'intval', \array_keys( $orders ) ) );
			self::assertSame( '0', $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id IN ($ids) AND ( meta_key LIKE '\\_dws\\_lpmwc\\_%' OR meta_key LIKE '\\_dws\\_wc\\_lpm\\_%' )" ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Reads both storages' rows directly; the ids are cast to integers.
			self::assertSame( '0', $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders_meta WHERE order_id IN ($ids) AND ( meta_key LIKE '\\_dws\\_lpmwc\\_%' OR meta_key LIKE '\\_dws\\_wc\\_lpm\\_%' )" ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Reads both storages' rows directly; the ids are cast to integers.
			self::assertSame( '', get_post_meta( $plan, '_dws_lpmwc_unlocked_payment_methods', true ) );
			self::assertSame( '', get_post_meta( $plan, '_dws_wc_lpm_unlocked_payment_methods', true ) );
			self::assertFalse( get_role( 'administrator' )?->has_cap( Capabilities::UNLOCK_VIA_GROUPS ) );
		} finally {
			foreach ( $orders as $id => $hpos ) {
				$storage = static fn (): string => $hpos;
				add_filter( 'pre_option_woocommerce_custom_orders_table_enabled', $storage );
				wc_get_order( $id )?->delete( true );
				remove_filter( 'pre_option_woocommerce_custom_orders_table_enabled', $storage );
			}
			wp_delete_post( $plan, true );
			// The other tests share the database, so the runner stores again what a new site starts with, and the grants act again.
			dws_lpmwc_plugin()->migrations?->run();
			do_action( 'wp_loaded' );
		}
	}

	#[RunInSeparateProcess]
	// The update copies v1's consent with the other settings, so a site that never ran it consents to nothing.
	public function test_an_uninstall_on_a_site_that_never_ran_the_update_keeps_its_data_whatever_v1s_row_holds(): void {
		delete_option( Options::DELETE_DATA );
		update_option( 'dws-wc-lpm_plugin_remove-data-uninstall', 'yes' );
		update_option( 'dws_lpmwc_group_7_unlocked_payment_methods', array( 'cod' ) );

		try {
			self::assertTrue( uninstall_plugin( plugin_basename( DWS_LPMWC_FILE ) ) );
			wp_cache_flush();

			self::assertSame( array( 'cod' ), get_option( 'dws_lpmwc_group_7_unlocked_payment_methods' ) );
			self::assertSame( 'yes', get_option( 'dws-wc-lpm_plugin_remove-data-uninstall' ) );
		} finally {
			// The other tests share the database, so v1's consent and the group's unlocked methods go, the runner stores again what a new site starts with, from the first step, and the capability grants act again where a pass removed them.
			delete_option( 'dws-wc-lpm_plugin_remove-data-uninstall' );
			delete_option( 'dws_lpmwc_group_7_unlocked_payment_methods' );
			delete_option( Plugin::PREFIX . '_revision' );
			wp_roles()->for_site();
			dws_lpmwc_plugin()->migrations?->run();
			do_action( 'wp_loaded' );
		}
	}

	#[RunInSeparateProcess]
	public function test_an_uninstall_keeps_the_grants_where_the_stored_setting_refuses_whatever_v1s_row_holds(): void {
		update_option( Options::DELETE_DATA, 'no' );
		update_option( 'dws-wc-lpm_plugin_remove-data-uninstall', 'yes' );
		update_option( 'dws_lpmwc_group_7_unlocked_payment_methods', array( 'cod' ) );

		try {
			self::assertTrue( uninstall_plugin( plugin_basename( DWS_LPMWC_FILE ) ) );
			wp_cache_flush();

			self::assertSame( array( 'cod' ), get_option( 'dws_lpmwc_group_7_unlocked_payment_methods' ) );
			self::assertSame( 'no', get_option( Options::DELETE_DATA ) );
		} finally {
			delete_option( 'dws-wc-lpm_plugin_remove-data-uninstall' );
			delete_option( 'dws_lpmwc_group_7_unlocked_payment_methods' );
		}
	}
}
