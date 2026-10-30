<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration;

use DeepWebSolutions\LinkedOrders\Plugin;
use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class UninstallTest extends TestCase {
	protected const int USER = 1;

	#[AfterClass]
	public static function forget_the_rows_the_uninstall_deleted(): void {
		wp_cache_flush(); // The uninstall ran in a child process, so this process still caches the rows it deleted, such as a setting the tab saved.
	}

	#[RunInSeparateProcess]
	public function test_an_uninstall_checks_the_floors_of_its_own_main_file(): void {
		\define( 'WP_UNINSTALL_PLUGIN', 'another-plugin/another-plugin.php' ); // A bulk uninstall keeps the first plugin's value for every later one.
		$source = (string) \file_get_contents( DWS_LOWC_FILE );
		\file_put_contents( DWS_LOWC_FILE, (string) \preg_replace( '/^(\s*\*\s*Requires PHP:\s*).*$/m', '${1}99.0', $source ) );
		$log = (string) \tempnam( \sys_get_temp_dir(), 'dws-tpl-uninstall-' );
		\ini_set( 'error_log', $log ); // phpcs:ignore WordPress.PHP.IniSet.Risky -- The child process's stderr would fail the test.

		try {
			include \dirname( DWS_LOWC_FILE ) . '/uninstall.php';
		} finally {
			\file_put_contents( DWS_LOWC_FILE, $source );
		}

		self::assertStringContainsString( 'below its PHP or WordPress floor', (string) \file_get_contents( $log ) );
		\unlink( $log );
	}

	// WordPress uninstalls an inactive plugin, so the request loads uninstall.php alone, never the entry file or functions.php.
	public function test_an_uninstall_in_a_request_that_skips_the_plugin_removes_its_data(): void {
		update_option( DWS_LOWC_OPTION_DELETE_DATA, 'yes' );
		$php = 'uninstall_plugin( "linked-orders-for-woocommerce/linked-orders-for-woocommerce.php" ); echo wp_json_encode( array( defined( "DWS_LOWC_FILE" ), get_option( "' . DWS_LOWC_OPTION_DELETE_DATA . '" ) ) );';

		\exec( 'wp --path=' . \escapeshellarg( ABSPATH ) . ' --skip-plugins=linked-orders-for-woocommerce eval ' . \escapeshellarg( $php ) . ' 2>&1', $output, $status ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- The uninstall needs a request without the plugin.
		wp_cache_flush();

		// The other tests share the database, so the roles get their capabilities back and the runner stores again what a new site starts with.
		foreach ( DWS_LOWC_CAPABILITY_GRANTS as $role => $capabilities ) {
			foreach ( $capabilities as $capability ) {
				get_role( $role )?->add_cap( $capability );
			}
		}
		dws_lowc_plugin()->migrations?->run();

		self::assertSame( 0, $status, \implode( "\n", $output ) );
		self::assertSame( '[false,false]', (string) \end( $output ) );
	}

	#[RunInSeparateProcess]
	public function test_an_uninstall_after_v1_consent_removes_the_links_on_both_storages_v1s_rows_and_the_capabilities(): void {
		global $wpdb;
		update_option( DWS_LOWC_OPTION_DELETE_DATA, 'yes' );
		update_option( 'linked_orders_for_woocommerce_version', array( 'seeded' ) );
		update_option( 'linked_orders_for_woocommerce_original_version', array( 'seeded' ) );
		update_option( '_linked_orders_for_woocommerce_admin_notices', array( 'seeded' ) );
		update_user_meta( self::USER, '_linked_orders_for_woocommerce_admin_notices', array( 'seeded' ) );
		$linked = array();
		foreach ( array( 'yes', 'no' ) as $hpos ) {
			$storage = static fn (): string => $hpos;
			add_filter( 'pre_option_woocommerce_custom_orders_table_enabled', $storage );
			add_filter( 'pre_option_woocommerce_custom_orders_table_data_sync_enabled', '__return_empty_string' );
			$order = wc_create_order();
			$order->update_meta_data( '_dws_lo_depth', 1 );
			$order->update_meta_data( '_dws_lo_parent', 7 );
			$order->save();
			$linked[ $order->get_id() ] = $hpos;
			remove_filter( 'pre_option_woocommerce_custom_orders_table_enabled', $storage );
			remove_filter( 'pre_option_woocommerce_custom_orders_table_data_sync_enabled', '__return_empty_string' );
		}

		self::assertTrue( uninstall_plugin( plugin_basename( DWS_LOWC_FILE ) ) );
		wp_cache_flush();
		wp_roles()->for_site();

		$ids = \implode( ',', \array_map( 'intval', \array_keys( $linked ) ) );
		self::assertSame( '0', $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id IN ($ids) AND meta_key LIKE '\\_dws\\_lo\\_%'" ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Reads both storages' rows directly; the ids are cast to integers.
		self::assertSame( '0', $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders_meta WHERE order_id IN ($ids) AND meta_key LIKE '\\_dws\\_lo\\_%'" ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Reads both storages' rows directly; the ids are cast to integers.
		foreach ( array( DWS_LOWC_OPTION_DELETE_DATA, 'linked_orders_for_woocommerce_version', 'linked_orders_for_woocommerce_original_version', '_linked_orders_for_woocommerce_admin_notices' ) as $option ) {
			self::assertFalse( get_option( $option ), "The option $option is still stored." );
		}
		self::assertSame( '', get_user_meta( self::USER, '_linked_orders_for_woocommerce_admin_notices', true ) );
		foreach ( DWS_LOWC_CAPABILITY_GRANTS as $role => $capabilities ) {
			foreach ( $capabilities as $capability ) {
				self::assertFalse( get_role( $role )?->has_cap( $capability ), "The $role role still holds $capability." );
			}
		}

		// The other tests share the database, so the roles get their capabilities back and the runner stores again what a new site starts with.
		foreach ( DWS_LOWC_CAPABILITY_GRANTS as $role => $capabilities ) {
			foreach ( $capabilities as $capability ) {
				get_role( $role )?->add_cap( $capability );
			}
		}
		dws_lowc_plugin()->migrations?->run();
		foreach ( $linked as $id => $hpos ) {
			$storage = static fn (): string => $hpos;
			add_filter( 'pre_option_woocommerce_custom_orders_table_enabled', $storage );
			$order   = wc_get_order( $id );
			self::assertInstanceOf( \WC_Order::class, $order );
			$order->delete( true );
			remove_filter( 'pre_option_woocommerce_custom_orders_table_enabled', $storage );
		}
	}

	#[RunInSeparateProcess]
	public function test_a_consenting_uninstall_removes_a_row_of_every_kind_the_framework_roots_store(): void {
		$prefix = Plugin::PREFIX . '_';
		update_option( DWS_LOWC_OPTION_DELETE_DATA, 'yes' );
		update_option( "{$prefix}notices", array( 'seeded' ) );
		update_network_option( null, "{$prefix}network_notices", array( 'seeded' ) );
		set_transient( "{$prefix}boot_failure_seeded", 1, HOUR_IN_SECONDS );
		update_user_option( self::USER, "{$prefix}hidden_notices", array( 'seeded' ) );
		update_user_meta( self::USER, "{$prefix}network_hidden_notices", array( 'seeded' ) );
		wp_schedule_single_event( \time() + HOUR_IN_SECONDS, "{$prefix}revision/migration/run" );
		as_schedule_single_action( \time() + HOUR_IN_SECONDS, "{$prefix}seeded", array(), Plugin::PREFIX );

		self::assertTrue( uninstall_plugin( plugin_basename( DWS_LOWC_FILE ) ) );
		wp_cache_flush();

		self::assertFalse( get_option( DWS_LOWC_OPTION_DELETE_DATA ) );
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
		dws_lowc_plugin()->migrations?->run();
		self::assertSame( 'no', get_option( DWS_LOWC_OPTION_DELETE_DATA ) );
	}
}
